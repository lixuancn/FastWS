<?php
/**
 * MeepoPS核心文件
 * Created by Lane
 * User: lane
 * Date: 16/3/23
 * Time: 下午2:41
 * E-mail: lixuan868686@163.com
 * WebSite: http://www.lanecn.com
 */
namespace MeepoPS\Core;

use MeepoPS\Core\TransportProtocol\TransportProtocolInterface;
use MeepoPS\Core\TransportProtocol\Tcp;
use MeepoPS\Core\Event\EventInterface;

class MeepoPS
{
    /**
     * 实例相关.每个实例都是一个MeepoPS的对象.每个实例至少有一个进程
     */
    //实例名称
    public $instanceName;
    //这个实例有多少子进程
    public $childProcessCount = 1;
    //绑定需要解析的协议
    private $_bindProtocol = '';
    //绑定需要监听的主机IP
    private $_bindHost = '';
    //绑定需要监听的端口
    private $_bindPort = '';
    //所有的实例列表
    private static $_instanceList = array();
    //实例所属所有子进程的Pid列表.一个实例有多个Pid(多子进程).一个MeepoPS有多个实例
    //array('instance1'=>array(1001, 1002, 1003), 'instance2'=>array(1004, 1005, 1006))
    private static $_instancePidList;
    //实例ID
    private $_instanceId;
    //当前状态
    private static $_currentStatus = MEEPO_PS_STATUS_STARTING;

    /**
     * 回调函数
     */
    //MeepoPS启动时触发该回调函数
    public $callbackStartInstance;
    //有新的链接加入时触发该回调函数
    public $callbackConnect;
    //收到新数据时触发该回调函数
    public $callbackNewData;
    //实例停止时触发该回调函数
    public $callbackInstanceStop;
    //链接断开时出发该回调函数
    public $callbackConnectClose;
    //有错误时触发该回调函数
    public $callbackError;
    //待发送缓冲区已经塞满时触发该回调函数
    public $callbackSendBufferFull;
    //待发送缓冲区没有积压时触发该回调函数
    public $callbackSendBufferEmpty;

    /**
     * 协议相关
     */
    //传输层协议
    private $_transportProtocol = 'tcp';
    //应用层协议
    private $_applicationProtocol = '';
    //应用层协议处理类
    private $_applicationProtocolClassName = '';
    //传输层协议
    private static $_transportProtocolList = array('tcp' => 'tcp');

    /**
     * 客户端相关
     */
    //客户端列表.每个链接是一个客户端
    public $clientList = array();

    /**
     * 事件相关
     */
    //全局事件
    public static $globalEvent;
    //当前的事件轮询方式,默认为select.但是不推荐select, 建议使用ngxin所采用的epoll方式.需要安装libevent
    private static $_currentPoll = 'select';

    /**
     * Socket相关
     */
    //主进程PID
    private static $_masterPid;
    //主进程Socket资源.由stream_socket_server()返回
    private $_masterSocket;
    //Socket上下文资源,由stream_context_create()返回
    private $_streamContext;

    /**
     * 其他
     */
    //是否启动守护进程
    private static $isDaemon = false;
    //统计信息
    private static $_statistics = array(
        'start_time' => '',
        'instance_exit_info' => array(),
    );

    /**
     * 初始化.
     * MeepoPS constructor.
     * @param string $protocol string 协议,默认为Telnet
     * @param string $host string 需要监听的地址
     * @param string $port string 需要监听的端口
     * @param array $contextOptionList
     */
    public function __construct($protocol = '', $host = '', $port = '', $contextOptionList = array())
    {
        //验证端口
        if($port && $port > 65536){
            Log::write('Port not more than 65536.', 'FATAL');
        }
        //---每一个实例的属性---
        $this->_bindProtocol = $protocol;
        $this->_bindHost = $host;
        $this->_bindPort = $port;
        //传入的协议是应用层协议还是传输层协议
        if($protocol){
            if (isset(self::$_transportProtocolList[$protocol])) {
                $this->_transportProtocol = self::$_transportProtocolList[$this->$protocol];
                //不是传输层协议,则认为是应用层协议.直接new应用层协议类
            } else{
                $this->_applicationProtocol = $protocol;
                $this->_applicationProtocolClassName = '\MeepoPS\Core\ApplicationProtocol\\' . ucfirst($this->_applicationProtocol);
                if (!class_exists($this->_applicationProtocolClassName)) {
                    Log::write('Application layer protocol class not found.', 'FATAL');
                }
            }
        }
        //给实例起名
        $this->instanceName = $this->instanceName ? $this->instanceName : $this->_getBind();
        //创建资源流上下文
        if ($this->_bindProtocol && $this->_bindHost && $this->_bindPort) {
            $contextOptionList['socket']['backlog'] = !isset($contextOptionList['socket']['backlog']) ? MEEPO_PS_BACKLOG : $contextOptionList['socket']['backlog'];
            $this->_streamContext = stream_context_create($contextOptionList);
        }
        //---全局共享信息---
        $this->_instanceId = spl_object_hash($this);
        self::$_instanceList[$this->_instanceId] = $this;
        self::$_instancePidList[$this->_instanceId] = array();
    }

    /**
     * 开始运行MeepoPS
     * @throws \Exception
     * @return void
     */
    public static function runMeepoPS()
    {
        //初始化工作
        self::_init();
        //根据启动命令选择是开始\重启\查看状态\结束等
        self::_command();
        //保存主进程id到文件中
        self::_saveMasterPid();
        //启动实例
        self::_createInstance();
        //给主进程安装信号处理函数
        self::_installSignal();
        //检测每个实例,并启动相应数量的子进程
        self::_checkInstanceListProcess();
        //主进程启动完成
        self::_masterProcessComplete();
    }

    /**
     * 初始化操作
     */
    private static function _init()
    {
        //添加统计数据
        self::$_statistics['start_time'] = date('Y-m-d H:i:s');
        //给主进程起个名字
        Func::setProcessTitle('meepops_master_process');
        //设置ID
        foreach (self::$_instanceList as $instanceId => $instance) {
            self::$_instancePidList[$instanceId] = array_fill(0, $instance->childProcessCount, 0);
        }
        //初始化定时器
        Timer::init();
    }

    /**
     * 解析启动命令,比如start, stop等 执行不同的操作
     */
    private static function _command()
    {
        global $argv;
        $startFilename = trim($argv[0]);
        $operation = trim($argv[1]);
        $isDomain = isset($argv[2]) && trim($argv[2]) === '-d' ? true : false;
        //获取主进程ID - 用来判断当前进程是否在运行
        $masterPid = false;
        if (file_exists(MEEPO_PS_MASTER_PID_PATH)) {
            $masterPid = @file_get_contents(MEEPO_PS_MASTER_PID_PATH);
        }
        //主进程当前是否正在运行
        $masterIsAlive = false;
        //给MeepoPS主进程发送一个信号, 信号为SIG_DFL, 表示采用默认信号处理程序.如果发送信号成功则该进程正常
        if ($masterPid && @posix_kill($masterPid, SIG_DFL)) {
            $masterIsAlive = true;
        }
        //不能重复启动
        if ($masterIsAlive && $operation === 'start') {
            Log::write('MeepoPS already running. file: ' . $startFilename, 'FATAL');
        }
        //未启动不能查看状态
        if (!$masterIsAlive && $operation === 'status') {
            Log::write('MeepoPS no running. file: ' . $startFilename, 'FATAL');
        }
        //未启动不能终止
        if (!$masterIsAlive && $operation === 'stop') {
            Log::write('MeepoPS no running. file: ' . $startFilename, 'FATAL');
        }
        //根据不同的执行参数执行不同的动作
        switch ($operation) {
            //启动
            case 'start':
                self::_commandStart($isDomain);
                break;
            //停止
            case 'stop':
                self::_commandStop($masterPid);
                break;
            //重启
            case 'restart':
                self::_commandRestart($masterPid, $isDomain);
                break;
            //状态
            case 'status':
                self::_commandStatus($masterPid);
                break;
            //停止所有的MeepoPS
            case 'kill':
                self::_commandKill($startFilename);
                break;
            //参数不合法
            default:
                Log::write('Parameter error. Usage: php index.php start|stop|restart|status|kill', 'FATAL');
        }
    }

    /**
     * 保存MeepoPS主进程的Pid
     */
    private static function _saveMasterPid()
    {
        self::$_masterPid = posix_getpid();
        if (false === @file_put_contents(MEEPO_PS_MASTER_PID_PATH, self::$_masterPid)) {
            Log::write('Can\'t write pid to ' . MEEPO_PS_MASTER_PID_PATH, 'FATAL');
        }
    }

    /**
     * 创建实例
     */
    private static function _createInstance()
    {
        foreach (self::$_instanceList as &$instance) {
            //每个实例开始监听
            $instance->listen();
        }
    }

    /**
     * 监听
     */
    public function listen()
    {
        //如果没有监听的IP,端口,或者已经建立了socket链接.则不再继续监听
        if (!$this->_bindProtocol || !$this->_bindHost || !$this->_bindPort || $this->_masterSocket) {
            return;
        }
        $listen = $this->_transportProtocol . '://' . $this->_bindHost . ':' . $this->_bindPort;
        $errno = 0;
        $errmsg = '';
        $flags = $this->_transportProtocol === 'tcp' ? STREAM_SERVER_BIND | STREAM_SERVER_LISTEN : STREAM_SERVER_BIND;
        $this->_masterSocket = stream_socket_server($listen, $errno, $errmsg, $flags, $this->_streamContext);
        if (!$this->_masterSocket) {
            Log::write('stream_socket_server() error: errno=' . $errno . ' errmsg=' . $errmsg, 'FATAL');
        }
        //如果是TCP协议,打开长链接,并且禁用Nagle算法,默认为开启Nagle
        //Nagle是收集多个数据包一起发送.再实时交互场景(比如游戏)中,追求高实时性,要求一个包,哪怕再小,也要立即发送给服务端.因此我们禁用Nagle
        if ($this->_transportProtocol === 'tcp' && function_exists('socket_import_stream')) {
            $socket = socket_import_stream($this->_masterSocket);
            @socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
            @socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
        }
        //使用非阻塞
        stream_set_blocking($this->_masterSocket, 0);
        //创建一个监听事件
        if (self::$globalEvent) {
            self::$globalEvent->add(array($this, 'acceptTcpConnect'), array(), $this->_masterSocket, EventInterface::EVENT_TYPE_READ);
        }
    }

    /**
     * 注册信号,给信号添加回调函数
     */
    private static function _installSignal()
    {
        //SIGINT/SIGTERM为停止MeepoPS的信号
        pcntl_signal(SIGINT, array('\MeepoPS\Core\MeepoPS', 'signalCallback'), false);
        pcntl_signal(SIGTERM, array('\MeepoPS\Core\MeepoPS', 'signalCallback'), false);
        //SIGUSR1 为查看MeepoPS所有状态的信号
        pcntl_signal(SIGUSR1, array('\MeepoPS\Core\MeepoPS', 'signalCallback'), false);
        //SIGPIPE 信号会导致Linux下Socket进程终止.我们忽略他
        pcntl_signal(SIGPIPE, SIG_IGN, false);
    }

    /**
     * 检测每个实例的子进程是否都已启动
     * @return void
     */
    private static function _checkInstanceListProcess()
    {
        foreach (self::$_instanceList as $instance) {
            foreach (self::$_instancePidList[$instance->_instanceId] as $pid) {
                if ($pid <= 0) {
                    self::_forkInstance($instance);
                }
            }
        }
        //子进程启动完毕后,设置主进程状态为运行中
        self::$_currentStatus = MEEPO_PS_STATUS_RUNING;
    }

    /**
     * 创建子进程
     * @param $instance object 一个MeepoPS的实例
     */
    private static function _forkInstance($instance)
    {
        //创建子进程
        $pid = pcntl_fork();
        //初始化的时候$_instancePidList是用0来填充的.这次就是查找到0的第一次出现的位置的索引,并且替换它.0表示尚未启动的子进程
        $id = array_search(0, self::$_instancePidList[$instance->_instanceId]);
        //如果是主进程
        if ($pid > 0) {
            unset(self::$_instancePidList[$instance->_instanceId][$id]);
            self::$_instancePidList[$instance->_instanceId][$pid] = $pid;
            //如果是子进程
        } elseif ($pid === 0) {
            self::$_instancePidList = array();
            self::$_instanceList = array($instance->_instanceId => $instance);
            Timer::delAll();
            Func::setProcessTitle('meepops: instance process  ' . $instance->instanceName . ' ' . $instance->_getBind());
            $instance->id = $id;
            $instance->run();
            exit(250);
            //创建进程失败
        } else {
            Log::write('fork child process failed', 'ERROR');
        }
    }

    /**
     * 运行一个实例进程的一个子进程
     */
    protected function run()
    {
        //设置状态
        self::$_currentStatus = MEEPO_PS_STATUS_RUNING;
        //注册一个退出函数.在任何退出的情况下检测是否由于错误引发的.包括die,exit等都会触发
        register_shutdown_function(array('\MeepoPS\Core\MeepoPS', 'checkShutdownErrors'));
        //创建一个全局的循环事件
        if (!self::$globalEvent) {
            $eventPollClass = '\MeepoPS\Core\Event\\' . ucfirst(self::_chooseEventPoll());
            if (!class_exists($eventPollClass)) {
                Log::write('Event class not exists: ' . $eventPollClass, 'FATAL');
            }
            self::$globalEvent = new $eventPollClass();
            //注册一个读事件的监听.当服务器端的Socket准备读取的时候触发这个事件.
            if ($this->_bindProtocol && $this->_bindHost && $this->_bindPort) {
                self::$globalEvent->add(array($this, 'acceptTcpConnect'), array(), $this->_masterSocket, EventInterface::EVENT_TYPE_READ);
            }
            //重新安装信号处理函数
            self::_reinstallSignalCallback();
            //重置输入输出
            self::_redirectStdinAndStdout();
            //初始化计时器任务,用事件轮询的方式
            Timer::init(self::$globalEvent);
            //执行系统开始启动工作时的回调函数
            if ($this->callbackStartInstance) {
                try {
                    call_user_func($this->callbackStartInstance, $this);
                } catch (\Exception $e) {
                    Log::write('MeepoPS: execution callback function callbackStartInstance-' . json_encode($this->callbackStartInstance) . ' throw exception' . json_encode($e), 'ERROR');
                }
            }
            //开启事件轮询
            self::$globalEvent->loop();
        }
    }

    /**
     * 重新安装信号处理函数 - 子进程重新安装
     */
    private static function _reinstallSignalCallback()
    {
        //设置之前设置的信号处理方式为忽略信号.并且系统调用被打断时不可重启系统调用
        pcntl_signal(SIGINT, SIG_IGN, false);
        pcntl_signal(SIGTERM, SIG_IGN, false);
        pcntl_signal(SIGUSR1, SIG_IGN, false);
        //安装新的信号的处理函数,采用事件轮询的方式
        self::$globalEvent->add(array('\MeepoPS\Core\MeepoPS', 'signalCallback'), array(), SIGINT, EventInterface::EVENT_TYPE_SIGNAL);
        self::$globalEvent->add(array('\MeepoPS\Core\MeepoPS', 'signalCallback'), array(), SIGTERM, EventInterface::EVENT_TYPE_SIGNAL);
        self::$globalEvent->add(array('\MeepoPS\Core\MeepoPS', 'signalCallback'), array(), SIGUSR1, EventInterface::EVENT_TYPE_SIGNAL);
    }

    /**
     * 检测退出的错误
     */
    public static function checkShutdownErrors()
    {
        Log::write('MeepoPS check shutdown reason');
        if (self::$_currentStatus != MEEPO_PS_STATUS_SHUTDOWN) {
            $errno = error_get_last();
            if (is_null($errno)) {
                Log::write('MeepoPS normal exit');
                return;
            }
            Log::write('MeepoPS unexpectedly quits. last error: ' . json_encode($errno), 'ERROR');
        }
    }

    /**
     * 信号处理函数
     * @param $signal
     */
    public static function signalCallback($signal)
    {
        switch ($signal) {
            case SIGINT:
            case SIGTERM:
                self::_stopAll();
                break;
            case SIGUSR1:
                self::_statisticsToFile();
                break;
        }
    }

    /**
     * 显示启动界面
     */
    private static function _startScreen()
    {
        echo "-------------------------- MeepoPS Start Success ------------------------\n";
        echo 'MeepoPS Version: ' . MEEPO_PS_VERSION . ' | PHP Version: ' . PHP_VERSION . ' | Master Pid: ' . self::$_masterPid . ' | Event: ' . ucfirst(self::_chooseEventPoll()) . "\n";
        echo "-------------------------- Instances List -------------------------\n";
        foreach (self::$_instanceList as $instance) {
            echo $instance->instanceName . '  ' . $instance->_getBind() . '  Child Process: ' . $instance->childProcessCount . "\n";
        }
        echo "\n";
    }

    /**
     * 重设标准输入输出
     */
    private static function _redirectStdinAndStdout()
    {
        if (self::$isDaemon !== true) {
            return false;
        }
        global $STDOUT, $STDERR;
        $handle = fopen(MEEPO_PS_STDOUT_PATH, 'a');
        if ($handle) {
            unset($handle);
            @fclose(STDOUT);
            @fclose(STDERR);
            $STDOUT = fopen(MEEPO_PS_STDOUT_PATH, 'a');
            $STDERR = fopen(MEEPO_PS_STDOUT_PATH, 'a');
        } else {
            Log::write('fopen STDIN AND STDOUT file failed. ' . MEEPO_PS_STDOUT_PATH, 'WARNING');
        }
        return true;
    }

    /**
     * 主进程启动完成
     */
    private static function _masterProcessComplete(){
        //输出启动成功字样
        echo "MeepoPS Start: \033[40G[\033[49;32;5mOK\033[0m]\n";
        //重置输入输出
        self::_redirectStdinAndStdout();
        //启动画面
        self::_startScreen();
        //管理子进程
        self::_monitorChildProcess();
    }

    /**
     * 管理子进程
     */
    private static function _monitorChildProcess()
    {
        //管理子进程
        while (true) {
            //调用等待信号的处理器.即收到信号后执行通过pcntl_signal安装的信号处理函数
            pcntl_signal_dispatch();
            //函数刮起当前进程的执行直到一个子进程退出或接收到一个信号要求中断当前进程或调用一个信号处理函数
            $status = 0;
            $pid = pcntl_wait($status, WUNTRACED);
            //再次调用等待信号的处理器.即收到信号后执行通过pcntl_signal安装的信号处理函数
            pcntl_signal_dispatch();
            //如果发生错误或者不是子进程
            if (!$pid || $pid <= 0) {
                //如果是关闭状态 并且 已经没有子进程了 则主进程退出
                if (self::$_currentStatus === MEEPO_PS_STATUS_SHUTDOWN && !self::_getAllEnablePidList()) {
                    self::_exitAndClearAll();
                }
                continue;
            }
            //查找是那个子进程退出
            foreach (self::$_instancePidList as $instanceId => $pidList) {
                if (isset($pidList[$pid])) {
                    $instance = self::$_instanceList[$instanceId];
                    Log::write('MeepoPS instance(' . $instance->instanceName . ':' . $pid . ') exit. Status: ' . $status, $status !== 0 ? 'ERROR' : 'INFO');
                    //记录统计信息.
                    self::$_statistics['instance_exit_info'][$instanceId]['info'] = $instance;
                    self::$_statistics['instance_exit_info'][$instanceId]['status'][$status] = !isset(self::$_statistics['instance_exit_info'][$instanceId]['status'][$status]) ? 0 : (self::$_statistics['instance_exit_info'][$instanceId]['status'][$status]++);
                    //清除数据
                    self::$_instancePidList[$instanceId][$pid] = 0;
                    break;
                }
            }

            //如果是停止状态, 并且所有的instance的所有进程都没有pid了.那么就退出所有.即所有的子进程都结束了,就退出主进程
            if (self::$_currentStatus === MEEPO_PS_STATUS_SHUTDOWN && !self::_getAllEnablePidList()) {
                self::_exitAndClearAll();
                //如果不是停止状态,则检测是否需要创建一个新的子进程
            } else if (self::$_currentStatus !== MEEPO_PS_STATUS_SHUTDOWN) {
                self::_checkInstanceListProcess();
            }
        }
    }

    /**
     * 退出当前进程
     */
    private static function _exitAndClearAll()
    {
        @unlink(MEEPO_PS_MASTER_PID_PATH);
        Log::write('MeepoPS has been pulled out', 'INFO');
        exit();
    }

    /**
     * 获取实例的协议://HOST:端口
     * @return string
     */
    private function _getBind()
    {
        if ($this->_bindProtocol && $this->_bindHost && $this->_bindPort) {
            $bind = lcfirst($this->_bindProtocol . '://' . $this->_bindHost . ':' . $this->_bindPort);
        }
        return isset($bind) ? $bind : '';
    }

    /**
     * 获取事件轮询机制
     * @return string 可用的事件轮询机制
     */
    private static function _chooseEventPoll()
    {
        if (extension_loaded('libevent')) {
            self::$_currentPoll = 'libevent';
        } else {
            self::$_currentPoll = 'select';
        }
        return self::$_currentPoll;
    }

    /**
     * 获取所有实例的所有进程的pid
     * @return array
     */
    private static function _getAllEnablePidList()
    {
        $ret = array();
        foreach (self::$_instancePidList as $pidList) {
            foreach ($pidList as $pid) {
                if ($pid > 0) {
                    $ret[$pid] = $pid;
                }
            }
        }
        return $ret;
    }

    /**
     * 终止MeepoPS所有进程
     */
    private static function _stopAll()
    {
        self::$_currentStatus = MEEPO_PS_STATUS_SHUTDOWN;
        //如果是主进程
        if (self::$_masterPid === posix_getpid()) {
            Log::write('MeepoPS is stopping...', 'INFO');
            $pidList = self::_getAllEnablePidList();
            foreach ($pidList as $pid) {
                posix_kill($pid, SIGINT);
                Timer::add('posix_kill', array($pid, SIGKILL), MEEPO_PS_KILL_INSTANCE_TIME_INTERVAL, false);
            }
            //如果是子进程
        } else {
            foreach (self::$_instanceList as $instance) {
                $instance->stop();
            }
            exit();
        }
    }

    public function stop()
    {
        //执行关闭实例的时候的回调
        if ($this->callbackInstanceStop) {
            try {
                call_user_func($this->callbackInstanceStop, $this);
            } catch (\Exception $e) {
                Log::write('MeepoPS: execution callback function callbackInstanceStop-' . json_encode($this->callbackInstanceStop) . ' throw exception' . json_encode($e), 'ERROR');
            }
        }
        //删除这个实例相关的所有事件监听
        self::$globalEvent->delOne($this->_masterSocket, EventInterface::EVENT_TYPE_READ);
        //关闭资源
        @fclose($this->_masterSocket);
        unset($this->_masterSocket);
    }

    /**
     * 接收Tcp链接
     * @param resource $socket Socket资源
     */
    public function acceptTcpConnect($socket)
    {
        //接收一个链接
        $connect = @stream_socket_accept($socket, 0, $peerName);
        //false可能是惊群问题.但是在较新(13年下半年开始)的Linux(2.6)内核已经解决了此问题.
        if ($connect === false) {
            return;
        }
        //TCP协议链接
        $tcpConnect = new Tcp($connect, $peerName, $this->_applicationProtocolClassName);
        //给Tcp链接对象的属性赋值
        $this->clientList[$tcpConnect->id] = $tcpConnect;
        $tcpConnect->instance = $this;
        //触发有新链接时的回调函数
        if ($this->callbackConnect) {
            try {
                call_user_func($this->callbackConnect, $tcpConnect);
            } catch (\Exception $e) {
                Log::write('MeepoPS: execution callback function callbackConnect-' . json_encode($this->callbackConnect) . ' throw exception' . json_encode($e), 'ERROR');
            }
        }
    }

    /**
     * 解析命令 - 启动
     */
    private static function _commandStart($isDomain)
    {
        if ($isDomain) {
            self::$isDaemon = true;
            self::_daemon();
        }
    }

    /**
     * 解析命令 - 停止
     */
    private static function _commandStop($masterPid)
    {
        Log::write('MeepoPS receives the "stop" instruction, MeepoPS will graceful stop');
        //给当前正在运行的主进程发送终止信号SIGINT(ctrl+c)
        if ($masterPid) {
            posix_kill($masterPid, SIGINT);
        }
        $nowTime = time();
        $timeout = 5;
        while (true) {
            //主进程是否在运行
            $masterIsAlive = $masterPid && posix_kill($masterPid, SIG_DFL);
            if ($masterIsAlive) {
                //如果超时
                if ((time() - $nowTime) > $timeout) {
                    Log::write('MeepoPS stop master process failed: timeout ' . $timeout . 's', 'FATAL');
                }
                //等待10毫秒,再次判断是否终止.
                usleep(10000);
                continue;
            }
            break;
        }
        echo "MeepoPS Stop: \033[40G[\033[49;32;5mOK\033[0m]\n";
        exit();
    }

    /**
     * 解析命令 - 重启
     */
    private static function _commandRestart($masterPid, $isDomain)
    {
        Log::write('MeepoPS receives the "restart" instruction, MeepoPS will graceful restart');
        //给当前正在运行的主进程发送终止信号SIGINT(ctrl+c)
        if ($masterPid) {
            posix_kill($masterPid, SIGINT);
        }
        $nowTime = time();
        $timeout = 5;
        while (true) {
            //主进程是否在运行
            $masterIsAlive = $masterPid && posix_kill($masterPid, SIG_DFL);
            if ($masterIsAlive) {
                //如果超时
                if ((time() - $nowTime) > $timeout) {
                    Log::write('MeepoPS stop master process failed: timeout ' . $timeout . 's', 'FATAL');
                }
                //等待10毫秒,再次判断是否终止.
                usleep(10000);
                continue;
            }
            break;
        }
        echo "MeepoPS Stop: \033[40G[\033[49;32;5mOK\033[0m]\n";
        if ($isDomain === true) {
            self::_commandStart($isDomain);
        }
    }

    /**
     * 解析命令 - 强行结束
     */
    private static function _commandKill($startFilename)
    {
        Log::write('MeepoPS receives the "kill" instruction, MeepoPS will end the violence');
        exec("ps aux | grep $startFilename | grep -v grep | awk '{print $2}' |xargs kill -SIGINT");
        exec("ps aux | grep $startFilename | grep -v grep | awk '{print $2}' |xargs kill -SIGKILL");
        exit();
    }

    /**
     * 解析命令 - 查看状态
     */
    private static function _commandStatus($masterPid)
    {
        echo "Success! Information is being collected about three seconds....\n\n";
        //删除之前的统计文件.忽略可能发生的warning(文件不存在的时候)
        @array_map('unlink', glob(MEEPO_PS_STATISTICS_PATH . '*'));
        //给正在运行的MeepoPS的主进程发送SIGUSR1信号,此时主进程收到SIGUSR1信号后会通知子进程将当前状态写入文件当中
        posix_kill($masterPid, SIGUSR1);
        //本进程sleep.目的是等待正在运行的MeepoPS的子进程完成写入状态文件的操作
        sleep(3);
        //输出状态
        Statistic::display();
        exit();
    }

    /**
     * 已守护进程的方式启动MeepoPS
     */
    private static function _daemon()
    {
        //文件掩码清0
        umask(0);
        //创建一个子进程
        $pid = pcntl_fork();
        //fork失败
        if ($pid === -1) {
            Log::write('MeepoPS _daemon: fork failed', 'FATAL');
        //父进程
        } else if ($pid > 0) {
            exit();
        }
        //设置子进程为Session leader, 可以脱离终端工作.这是实现daemon的基础
        if (posix_setsid() === -1) {
            Log::write('MeepoPS _daemon: set sid failed', 'FATAL');
        }
        //再次在开启一个子进程
        //这不是必须的,但通常都这么做,防止获得控制终端.
        $pid = pcntl_fork();
        if ($pid === -1) {
            Log::write('MeepoPS _daemon: fork2 failed', 'FATAL');
        //将父进程退出
        } else if ($pid !== 0) {
            exit();
        }
    }

    /**
     * 将当前信息统计后写入文件中
     * 1. 必然是主进程先进来执行, 写全局统计信息到统计文件.
     * 2. 子进程进来后, 追加各个进程信息到统计文件
     */
    private static function _statisticsToFile()
    {
        $statistics = array();
        //如果是主进程, 写入全局类的信息, 如果是子进程来执行本方法. 将统计信息以追加的方式写入文件.
        if (self::$_masterPid === posix_getpid()) {
            //-----以下为主进程的统计信息---
            $statistics['master_pid'] = self::$_masterPid;
            $statistics['event'] = self::_chooseEventPoll();
            $statistics['start_time'] = self::$_statistics['start_time'];
            $statistics['total_instance_count'] = count(self::$_instancePidList);
            $statistics['total_child_process_count'] = count(self::_getAllEnablePidList());
            $statistics['instance_exit_info'] = self::$_statistics['instance_exit_info'];
            file_put_contents(MEEPO_PS_STATISTICS_PATH . '_master', json_encode($statistics));
            //主进程做完统计后告诉所有子进程进行统计
            foreach (self::_getAllEnablePidList() as $pid) {
                posix_kill($pid, SIGUSR1);
            }
        }else{
            //-----以下为子进程的统计信息---
            //获取系统分配给PHP的内存,四舍五入到两位小数,单位M
            $instance = current(self::$_instanceList);
            $statistics['pid'] = posix_getpid();
            $statistics['memory'] = round(memory_get_usage(true) / (1024 * 1024), 2);
            $statistics['instance_name'] = $instance->instanceName;
            $statistics['bind'] = $instance->_getBind();
            $statistics['transport_protocol_statistics'] = TransportProtocolInterface::$statistics;
            file_put_contents(MEEPO_PS_STATISTICS_PATH . '_child_' . $statistics['pid'], json_encode($statistics));
        }
        unset($statistics);
    }
}