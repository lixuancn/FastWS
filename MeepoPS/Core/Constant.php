<?php
/**
 * Created by Lane
 * User: lane
 * Date: 16/3/24
 * Time: 下午6:18
 * E-mail: lixuan868686@163.com
 * WebSite: http://www.lanecn.com
 */
namespace MeepoPS\Core;

//MeepoPS当前状态 - 启动中
define('MEEPO_PS_STATUS_STARTING', 1);

//MeepoPS当前状态 - 运行中
define('MEEPO_PS_STATUS_RUNING', 2);

//MeepoPS当前状态 - 关闭中
define('MEEPO_PS_STATUS_CLOSING', 4);

//MeepoPS当前状态 - 停止
define('MEEPO_PS_STATUS_SHUTDOWN', 8);

//MeepoPS的Backlog.Backlog来自TCP协议.backlog是一个连接队列,队列总和=未完成三次握手队列+已经完成三次握手队列.Accept时从已经完成三次握手队列的取出一个链接.
define('MEEPO_PS_BACKLOG', 2048);

//UDP协议下所允许的最大包
define('MEEPO_PS_MAX_UDP_PACKET_SIZE', 65535);