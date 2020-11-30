<?php
/**
 * MeepoPS子进程逻辑
 */

//引入ktvserver所需文件
if(isset($meepopsChildProcessIncludeFile) && $meepopsChildProcessIncludeFile){
    require $meepopsChildProcessIncludeFile;
    NsqService::includeVendor();
}

//运行子进程
\MeepoPS\Core\Timer::delAll();
\MeepoPS\Core\Func::setProcessTitle('meepops_child_process_' . $i);
//设置状态
$meepopsRunningParam['currentStatus'] = MEEPO_PS_STATUS_RUNING;
//注册一个退出函数.在任何退出的情况下检测是否由于错误引发的.包括die,exit等都会触发
register_shutdown_function('meepopsCheckShutdownErrors');
//创建一个全局的循环事件
$eventPollClass = '\MeepoPS\Core\Event\\' . ucfirst(meepopsChooseEventPoll());
if(!class_exists($eventPollClass)){
    \MeepoPS\Core\Log::write('Event class not exists: ' . $eventPollClass, 'FATAL');
}
$meepopsRunningParam['globalEvent'] = new $eventPollClass();
//注册一个读事件的监听.当服务器端的Socket准备读取的时候触发这个事件.
$meepopsRunningParam['globalEvent']->add('acceptTcpConnect', array(), $meepopsRunningParam['masterSocket'], \MeepoPS\Core\Event\EventInterface::EVENT_TYPE_READ);
//重新安装信号处理函数
reinstallSignalCallback();
//初始化计时器任务,用事件轮询的方式
\MeepoPS\Core\Timer::init($meepopsRunningParam['globalEvent']);
//执行系统开始启动工作时的回调函数
if(function_exists('callbackStartInstance')){
    try {
        call_user_func('callbackStartInstance');
    } catch (\Exception $e) {
        \MeepoPS\Core\Log::write('MeepoPS: execution callback function callbackStartInstance' . ' throw exception' . json_encode($e), 'ERROR');
    }
}
//开启事件轮询
$meepopsRunningParam['globalEvent']->loop();

exit(250);

 /**
 * 重新安装信号处理函数 - 子进程重新安装
 */
function reinstallSignalCallback(){
    global $meepopsRunningParam;
    //设置之前设置的信号处理方式为忽略信号.并且系统调用被打断时不可重启系统调用
    pcntl_signal(SIGINT, SIG_IGN, false);
    pcntl_signal(SIGTERM, SIG_IGN, false);
    pcntl_signal(SIGUSR1, SIG_IGN, false);
    //安装新的信号的处理函数,采用事件轮询的方式
    $meepopsRunningParam['globalEvent']->add('meepopsSignalCallback', array(), SIGINT, \MeepoPS\Core\Event\EventInterface::EVENT_TYPE_SIGNAL);
    $meepopsRunningParam['globalEvent']->add('meepopsSignalCallback', array(), SIGTERM, \MeepoPS\Core\Event\EventInterface::EVENT_TYPE_SIGNAL);
    $meepopsRunningParam['globalEvent']->add('meepopsSignalCallback', array(), SIGUSR1, \MeepoPS\Core\Event\EventInterface::EVENT_TYPE_SIGNAL);
}

/**
 * 接收Tcp链接
 * @param resource $socket Socket资源
 */
function acceptTcpConnect($socket){
    global $meepopsRunningParam;
    //接收一个链接
    $peerName = null;
    $connect = @stream_socket_accept($socket, 0, $peerName);
    //false可能是惊群问题
    if($connect === false){
        return;
    }
    //TCP协议链接
    $tcpConnect = new \MeepoPS\Core\TransportProtocol\Tcp($connect, $peerName, $meepopsRunningParam['applicationProtocolClassName']);
}

/**
* 检测退出的错误
*/
function meepopsCheckShutdownErrors(){
    global $cbnsqWorker;
    $log = 'MeepoPS check shutdown reason: ' . $cbnsqWorker['stopProcessMsg'] . '.';
    if(strtolower($cbnsqWorker['stopProcessLevel']) !== 'info'){
        global $meepopsRunningParam;
        if($meepopsRunningParam['currentStatus'] != MEEPO_PS_STATUS_SHUTDOWN){
            $errno = error_get_last();
            if(!is_null($errno)){
                $log .= ' ' . json_encode($errno);
            }else{
                $log .= ' MeepoPS normal exit.';
            }
        }
    }
    \MeepoPS\Core\Log::write($log, $cbnsqWorker['stopProcessLevel']);
    $cbnsqWorker['stopProcessLevel'] = '';
    $cbnsqWorker['stopProcessMsg'] = '';
}
