<?php
/**
 * DEMO文件. 展示基于Telnet协议的数据传输
 *
 * 收集部署在服务器上的客户端发送来的数据,整理入库
 * Created by Lane
 * User: lane
 * Date: 16/4/16
 * Time: 下午10:05
 * E-mail: lixuan868686@163.com
 * WebSite: http://www.lanecn.com
 */

//引入MeepoPS
require_once '../../MeepoPS/index.php';

//使用文本传输的Api类
$telnet = new \MeepoPS\Api\Telnet('0.0.0.0', '19910');

//启动的子进程数量. 通常为CPU核心数
$telnet->childProcessCount = 1;

//设置MeepoPS实例名称
$telnet->instanceName = 'MonitorServerStatus-Telnet';

//设置回调函数 - 这是所有应用的业务代码入口
$telnet->callbackStartInstance = 'callbackStartInstance';
$telnet->callbackNewData = 'callbackNewData';


//启动MeepoPS
\MeepoPS\runMeepoPS();

//global $mysql;
$mysql = null;


//以下为回调函数, 业务相关.
function callbackStartInstance($instance)
{
    global $mysql;
    $mysql = new \MeepoPS\Library\Db\Mysql('127.0.0.1', 'root', '123456', 'meepops');
}

function callbackNewData($connect, $data)
{
    global $mysql;
    $data = json_decode($data, true);
    $ip = $connect->getClientAddress()[0];
    $cpuSys = $data['sy'];
    $cpuUser = $data['us'];
    $memory = $data['buff'];
    $createTime = date('Y-m-d H:i:s');
    $sql = 'INSERT INTO `moni_2016-05-25` (ip, cpu_sys, cpu_user, memory, create_time) VALUES ("'.$ip.'", "'.$cpuSys.'", "'.$cpuUser.'", "'.$memory.'", "'.$createTime.'")';
    $result = $mysql->query($sql);
    $connect->send($result);
}