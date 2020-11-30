<?php
/*
 * MeepoPS核心文件
 */
$meepopsRunningParam['pidList'] = array_fill(0, $meepopsRunningParam['childProcessCount'], 0);
//给主进程起个名字
\MeepoPS\Core\Func::setProcessTitle('meepops_master_process');
//初始化定时器
\MeepoPS\Core\Timer::init();

meepopsCommand();
meepopsInit();
meepopsSaveMasterPid();
meepopsListen();
meepopsInstallSignal();

//检测每个实例的子进程是否都已启动
foreach($meepopsRunningParam['pidList'] as $i => $pid) {
    if ($pid > 0) {
        continue;
    }
    //创建子进程
    $pid = pcntl_fork();
    //如果是主进程
    if($pid > 0){
        $meepopsRunningParam['pidList'][$i] = $pid;
    //如果是子进程
    }else if ($pid === 0){
        require MEEPO_PS_ROOT_PATH . '/Core/ChildProcess.php';
    //创建进程失败
    }else{
        \MeepoPS\Core\Log::write('fork child process failed', 'ERROR');
    }
}

//子进程启动完毕后,设置主进程状态为运行中
$meepopsRunningParam['currentStatus'] = MEEPO_PS_STATUS_RUNING;
//主进程启动完成
meepopsMasterProcessComplete();
//管理子进程
while (true){
    //调用等待信号的处理器.即收到信号后执行通过pcntl_signal安装的信号处理函数
    pcntl_signal_dispatch();
    //函数刮起当前进程的执行直到一个子进程退出或接收到一个信号要求中断当前进程或调用一个信号处理函数
    $status = 0;
    $pid = pcntl_wait($status, WUNTRACED);
    //再次调用等待信号的处理器.即收到信号后执行通过pcntl_signal安装的信号处理函数
    pcntl_signal_dispatch();
    //如果发生错误或者不是子进程
    if(!$pid || $pid <= 0){
        //如果是关闭状态 并且 已经没有子进程了 则主进程退出
        if ($meepopsRunningParam['currentStatus'] === MEEPO_PS_STATUS_SHUTDOWN && !meepopsGetAllEnablePidList()) {
            meepopsExitAndClearAll();
        }
        continue;
    }
    //查找是那个子进程退出
    foreach ($meepopsRunningParam['pidList'] as $i => $p) {
        if($pid != $p){
            continue;
        }
        \MeepoPS\Core\Log::write('MeepoPS Child Process :' . $pid . ' exit. Status: ' . $status, $status !== 0 ? 'ERROR' : 'INFO');
        //清除数据
        $meepopsRunningParam['pidList'][$i] = 0;
        break;
    }
    //如果是停止状态, 并且所有的instance的所有进程都没有pid了.那么就退出所有.即所有的子进程都结束了,就退出主进程
    if ($meepopsRunningParam['currentStatus'] === MEEPO_PS_STATUS_SHUTDOWN && !meepopsGetAllEnablePidList()) {
        meepopsExitAndClearAll();
    //如果不是停止状态,则检测是否需要创建一个新的子进程
    }else if($meepopsRunningParam['currentStatus'] !== MEEPO_PS_STATUS_SHUTDOWN){
        //检测每个实例的子进程是否都已启动
        foreach($meepopsRunningParam['pidList'] as $i => $pid) {
            if ($pid > 0) {
                continue;
            }
            //创建子进程
            $pid = pcntl_fork();
            //如果是主进程
            if($pid > 0){
                $meepopsRunningParam['pidList'][$i] = $pid;
            //如果是子进程
            }else if ($pid === 0){
                require MEEPO_PS_ROOT_PATH . 'Core/ChildProcess.php';
            //创建进程失败
            }else{
                \MeepoPS\Core\Log::write('fork child process failed', 'ERROR');
            }
        }
        //子进程启动完毕后,设置主进程状态为运行中
        $meepopsRunningParam['currentStatus'] = MEEPO_PS_STATUS_RUNING;
    }
}

/**
 * 初始化
 */
function meepopsInit(){
    global $meepopsRunningParam;
    //应用层协议类是否存在
    $meepopsRunningParam['applicationProtocolClassName'] = '\MeepoPS\Core\ApplicationProtocol\\' . ucfirst($meepopsRunningParam['protocol']);
    if (!class_exists($meepopsRunningParam['applicationProtocolClassName'])) {
        \MeepoPS\Core\Log::write('Application layer protocol class not found.', 'FATAL');
    }
}

function meepopsCommand(){
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
    global $argv;
    $startFilename = trim($argv[0]);
    $operation = trim($argv[1]);
    //不能重复启动
    if ($masterIsAlive && $operation === 'start') {
        \MeepoPS\Core\Log::write('MeepoPS already running.', 'FATAL');
    }
    //未启动不能终止
    if (!$masterIsAlive && $operation === 'stop') {
        \MeepoPS\Core\Log::write('MeepoPS no running.', 'FATAL');
    }
    if($operation === 'stop'){
        meepopsStop($masterPid);
    }else if($operation === 'kill'){
        meepopsKill($startFilename);
    }else if($operation === 'start'){
         \MeepoPS\Core\Log::write('Meepops is running...', 'INFO');
    }else{
         \MeepoPS\Core\Log::write('Parameter error. Usage: php index.php start|stop|restart|status|kill', 'FATAL');
    }
}

/**
 * 保存MeepoPS主进程的Pid
 */
function meepopsSaveMasterPid(){
    global $meepopsRunningParam;
    $meepopsRunningParam['masterPid'] = posix_getpid();
    if (false === @file_put_contents(MEEPO_PS_MASTER_PID_PATH, $meepopsRunningParam['masterPid'])){
        \MeepoPS\Core\Log::write('Can\'t write pid to ' . MEEPO_PS_MASTER_PID_PATH, 'FATAL');
    }
}

/**
 * 监听
 */
function meepopsListen(){
    global $meepopsRunningParam;
    $errno = 0;
    $errmsg = '';
    $meepopsRunningParam['masterSocket'] = stream_socket_server("tcp://{$meepopsRunningParam['listenIp']}:{$meepopsRunningParam['listenPort']}", $errno, $errmsg, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
    if (!$meepopsRunningParam['masterSocket']){
        \MeepoPS\Core\Log::write('stream_socket_server() error: errno=' . $errno . ' errmsg=' . $errmsg, 'FATAL');
    }
    //如果是TCP协议,打开长链接,并且禁用Nagle算法,默认为开启Nagle
    //Nagle是收集多个数据包一起发送.再实时交互场景(比如游戏)中,追求高实时性,要求一个包,哪怕再小,也要立即发送给服务端.因此我们禁用Nagle
    $socket = socket_import_stream($meepopsRunningParam['masterSocket']);
    @socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
    @socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
    //使用非阻塞
    stream_set_blocking($meepopsRunningParam['masterSocket'], 0);
}

/**
 * 注册信号,给信号添加回调函数
 */
function meepopsInstallSignal(){
    //SIGINT/SIGTERM为停止MeepoPS的信号
    pcntl_signal(SIGINT,  'meepopsSignalCallback', false);
    pcntl_signal(SIGTERM, 'meepopsSignalCallback', false);
    //SIGUSR1 为查看MeepoPS所有状态的信号
    pcntl_signal(SIGUSR1, 'meepopsSignalCallback', false);
    //SIGPIPE 信号会导致Linux下Socket进程终止.我们忽略他
    pcntl_signal(SIGPIPE, SIG_IGN, false);
}

/**
 * 退出MeepoPS
 */
function meepopsStop($masterPid){
    \MeepoPS\Core\Log::write('MeepoPS receives the "stop" instruction, MeepoPS will graceful stop');
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
                \MeepoPS\Core\Log::write('MeepoPS stop master process failed: timeout ' . $timeout . 's', 'FATAL');
                break;
            }
            //等待10毫秒,再次判断是否终止.
            sleep(1);
            continue;
        }
        break;
    }
    echo "MeepoPS Stop: \033[40G[\033[49;32;5mOK\033[0m]\n";
    exit();
}

/**
 * 强退MeepoPS
 */
function meepopsKill($startFilename){
    \MeepoPS\Core\Log::write('MeepoPS receives the "kill" instruction, MeepoPS will end the violence');
    exec("ps aux | grep $startFilename | grep -v grep | awk '{print $2}' |xargs kill -SIGINT");
    exec("ps aux | grep $startFilename | grep -v grep | awk '{print $2}' |xargs kill -SIGKILL");
    exit();
}

 /**
 * 信号处理函数
 * @param $signal
 */
function meepopsSignalCallback($signal){
    switch ($signal) {
        case SIGINT:
        case SIGTERM:
            meepopsStopAll();
            break;
        case SIGUSR1:
            break;
    }
}

/**
 * 终止MeepoPS所有进程
 */
function meepopsStopAll(){
    global $meepopsRunningParam;
    $meepopsRunningParam['currentStatus'] = MEEPO_PS_STATUS_SHUTDOWN;
    //如果是主进程
    if($meepopsRunningParam['masterPid'] === posix_getpid()) {
        \MeepoPS\Core\Log::write('MeepoPS is stopping...', 'INFO');
        foreach($meepopsRunningParam['pidList'] as $pid){
            if($pid <= 0){
                continue;
            }
            posix_kill($pid, SIGINT);
            \MeepoPS\Core\Timer::add('posix_kill', array($pid, SIGKILL), MEEPO_PS_KILL_INSTANCE_TIME_INTERVAL, false);
        }
    //如果是子进程
    }else{
        meepopsStopOne();
        exit();
    }
}

/**
 * 某进程停止
 */
function meepopsStopOne(){
    global $meepopsRunningParam;
    //删除这个实例相关的所有事件监听
    $meepopsRunningParam['globalEvent']->delOne($meepopsRunningParam['masterSocket'], MeepoPS\Core\Event\EventInterface::EVENT_TYPE_READ);
    //关闭资源
    @fclose($meepopsRunningParam['masterSocket']);
    unset($meepopsRunningParam['masterSocket']);
}

/**
 * 获取事件轮询机制
 * @return string 可用的事件轮询机制
 */
function meepopsChooseEventPoll(){
    if (extension_loaded('libevent')) {
        return 'libevent';
    }else{
        return 'select';
    }
}

/**
 * 获取所有实例的所有进程的pid
 * @return array
 */
function meepopsGetAllEnablePidList(){
    global $meepopsRunningParam;
    $ret = array();
    foreach ($meepopsRunningParam['pidList'] as $pid) {
        if ($pid > 0) {
            $ret[$pid] = $pid;
        }
    }
    return $ret;
}

/**
 * 主进程启动完成
 */
function meepopsMasterProcessComplete(){
    //输出启动成功字样
    echo "MeepoPS Start: \033[40G[\033[49;32;5mOK\033[0m]\n";
    //启动画面
    meepopsStartScreen();
}

/**
 * 显示启动界面
 */
function meepopsStartScreen(){
	global $meepopsRunningParam;
    echo "-------------------------- MeepoPS Start Success ------------------------\n";
    echo 'MeepoPS Version: ' . MEEPO_PS_VERSION . ' | PHP Version: ' . PHP_VERSION . ' | Master Pid: ' . $meepopsRunningParam['masterPid'] . ' | Event: ' . ucfirst(meepopsChooseEventPoll()) . ' | Child Process: ' . $meepopsRunningParam['childProcessCount'] . "\n";
    echo "\n";
}

/**
 * 退出当前进程
 */
function meepopsExitAndClearAll(){
    @unlink(MEEPO_PS_MASTER_PID_PATH);
    \MeepoPS\Core\Log::write('MeepoPS has been pulled out', 'INFO');
    exit();
}
