<?php
/**
 * MeepoPS框架核心入口文件
 * Created by Lane
 * User: lane
 * Date: 16/3/17
 * Time: 下午2:50
 * E-mail: lixuan868686@163.com
 * WebSite: http://www.lanecn.com
 */
namespace MeepoPS\Core;

/**
 * -------从这里开始,以下流程的代码请勿修改,除非你有把握-------------------------------
 */

//自动载入函数
require 'Autoload.php';

//载入常量定义
require 'Constant.php';

//定义版本号
define('MEEPO_PS_VERSION', '0.1.0');

//定义变量
$_SERVER['runtime_environment'] = 'meepops';

//错误报告是否开启
if (MEEPO_PS_DEBUG) {
    error_reporting(E_ALL);
} else {
    //error_reporting(0);
    error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
}

//开启立即刷新输出
if (MEEPO_PS_IMPLICIT_FLUSH) {
    ob_implicit_flush();
} else {
    ob_implicit_flush(false);
}

//设置脚本执行时间为永不超时
set_time_limit(0);
