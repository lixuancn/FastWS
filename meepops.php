<?php
/**
 * 子进程启动时调用
 */
function callbackStartInstance(){
    global $cbnsqWorker;
    //获取当前代码的版本
    $cbnsqWorker['codeVersion'] = \NsqWorker::getCodeVersion();
    //进程退出原因
    $cbnsqWorker['stopProcessMsg'] = '';
    $cbnsqWorker['stopProcessLevel'] = '';
}

/**
 * 收到新消息时调用
 */
function callbackNewData($connect, $data){
    $work = new NsqWorker($data);
    //获取请求
    if(!$work->messageid){
        $work->output($connect, NsqWorker::RESPONSE_CODE_BAD_REQUEST, 'messageid is empty');
        unset($work);
        return;
    }
    if(!$work->msgOriginal){
        $work->output($connect, NsqWorker::RESPONSE_CODE_BAD_REQUEST, 'message is empty');
        unset($work);
        return;
    }
    if(!$work->dataOriginal){
        $work->output($connect, NsqWorker::RESPONSE_CODE_BAD_REQUEST, 'data is empty');
        unset($work);
        return;
    }

    try{
        //执行
        echo "receive: " . $work->dataOriginal;
        $work->output($connect, NsqWorker::RESPONSE_CODE_SUCCESS, 'ok');
    }catch(Exception $e){
        $work->output($connect, NsqWorker::RESPONSE_CODE_ERROR, $e->getMessage() . ' ' . $e->getTraceAsString());
    }
    unset($work);

    //该进程处理完本次请求后是否需要退出
    global $cbnsqWorker;
    $isStopProcess = false;
    //模拟PHP-FPM的方式，达到一定处理数量后，子进程退出，由主进程重新拉起
    $executeRequestNum = $connect->getStatisticsTotalReadPackageCount();
    if($executeRequestNum > 50000){
        $isStopProcess = true;
        $cbnsqWorker['stopProcessMsg'] = 'total read package > pm_max_request';  
    }

    //检测代码版本是否发生变化, 发生变化时重启MeepoPS
    //代码更新，代码更新依赖于代码发布脚本，每次发布代码时会更新ktvserver下version文件时间，直接svn up时则需要手动重启
    //这里是直接退出，退出后由主进程拉起
    $codeVersion = \NsqWorker::getCodeVersion();
    if($codeVersion != $cbnsqWorker['codeVersion']){
        $isStopProcess = true;
        $cbnsqWorker['stopProcessMsg'] = 'code version modify time';
    }
    if($isStopProcess === true){
        $cbnsqWorker['stopProcessLevel'] = 'INFO';
        sleep(1);
        $connect->close();
        meepopsStopOne();
        exit(0);
    }
}

class NsqWorker{
    //消息ID长度
    const MESSAGE_ID_LENGTH = 16;

    //定义返回值
    //成功
    const RESPONSE_CODE_SUCCESS = 200;
    //参数错误
    const RESPONSE_CODE_BAD_REQUEST = 400;
    //处理失败
    const RESPONSE_CODE_ERROR = 500;

    //请求原始数据
    public $msgOriginal = '';
    //原始数据
    public $dataOriginal = '';
    //nsq消息id
    public $messageid = '-1';

    public function __construct($data){
        $this->getRequest($data);
    }

    /*
     * 获取请求数据
     * @return array
     */
    private function getRequest($data){
        $this->msgOriginal = $data;
        if($this->msgOriginal && strlen($this->msgOriginal) > self::MESSAGE_ID_LENGTH){
            //获取messageid
            $this->messageid = substr($this->msgOriginal, 0, self::MESSAGE_ID_LENGTH);
            $this->dataOriginal = substr($this->msgOriginal, self::MESSAGE_ID_LENGTH);
        }
    }

    /**
     * 输出响应
     */
    public function output($connect, $retCode, $retMsg){
        //校验失败，执行失败，抛出异常，记录日志
//        'messageid' => $this->messageid,
//        'retcode' => $retCode,
//        'retmsg' => $retMsg,
//        'msgOriginal' => $this->msgOriginal,
//        'dataoriginal' => $this->dataOriginal,
        $ret = "{$retCode} {$retMsg}";
        $connect->send($ret);
    }

    /**
     * 获取ktvserver的代码的版本号
     */
    public static function getCodeVersion(){
        clearstatcache();
        $filename = '/home/wwwroot/code/version';
        if(!file_exists($filename)){
            return 0;
        }
        return filemtime($filename);
    }
}

//子进程在执行任务之前，会require这个变量所指的路径
//$meepopsChildProcessIncludeFile = __DIR__ . '/../common/common.inc.php';
//引入MeepoPS核心文件
require __DIR__ . '/MeepoPS/index.php';
