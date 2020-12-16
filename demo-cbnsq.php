<?php
/**
 * DEMO文件. 展示基于CBNSQ协议的数据传输
 * Created by Lane
 * User: lane
 * Date: 20/12/15
 * Time: 下午11:49
 * E-mail: lixuan868686@163.com
 * WebSite: http://www.lanecn.com
 */

//引入MeepoPS
require_once 'MeepoPS/index.php';

//使用文本协议传输的Api类
$cbNsq = new \MeepoPS\Api\Cbnsq('0.0.0.0', '19910');

//启动的子进程数量. 通常为CPU核心数
$cbNsq->childProcessCount = 10;

//设置MeepoPS实例名称
$cbNsq->instanceName = 'MeepoPS-CBSNQ';

//设置回调函数 - 这是所有应用的业务代码入口
$cbNsq->callbackStartInstance = 'callbackStartInstance';
$cbNsq->callbackConnect = 'callbackConnect';
$cbNsq->callbackNewData = 'callbackNewData';
$cbNsq->callbackSendBufferEmpty = 'callbackSendBufferEmpty';
$cbNsq->callbackInstanceStop = 'callbackInstanceStop';
$cbNsq->callbackConnectClose = 'callbackConnectClose';

//启动MeepoPS
\MeepoPS\runMeepoPS();


//以下为回调函数, 业务相关.
//回调 - 示例启动时
function callbackStartInstance($instance)
{
    echo "实例{$instance->instanceName}成功启动\n";
}

//回调 - 收到新链接
function callbackConnect($connect)
{
    echo "收到新链接. 链接ID={$connect->id}\n";
}

//回调 - 收到新消息
function callbackNewData($connect, $data)
{
    echo "收到新消息, ID:{$_SERVER['MESSAGE_ID']} 内容: {$data}\n";
    $connect->send("200 ok");
}

function callbackSendBufferEmpty($connect)
{
    echo "用户{$connect->id}的待发送队列已经为空\n";
}

function callbackInstanceStop($instance)
{
    foreach ($instance->clientList as $client) {
        $client->send('服务即将停止.');
    }
}

function callbackConnectClose($connect)
{
    echo "链接断开了. 链接ID={$connect->id}\n";
}
