<?php
namespace MeepoPS;

global $meepopsRunningParam;
$meepopsRunningParam = array(
    'listenIp' => '0.0.0.0',
    'listenPort' => isset($argv[3]) && $argv[3] ? $argv[3] : '19910',
    'protocol' => 'CBNSQ',
    'childProcessCount' => isset($argv[2]) && intval($argv[2]) ? intval($argv[2]) : 1,
);

//MeepoPS根目录
define('MEEPO_PS_ROOT_PATH', dirname(__FILE__) . '/');

//载入MeepoPS配置文件
require MEEPO_PS_ROOT_PATH . '/Core/Config.php';

//环境检测
require MEEPO_PS_ROOT_PATH . '/Core/CheckEnv.php';

//载入MeepoPS初始化文件
require MEEPO_PS_ROOT_PATH . '/Core/Init.php';

//给主进程起个名字
\MeepoPS\Core\Func::setProcessTitle('meepops_master_process');
//设置ID
$meepopsRunningParam['pidList'] = array_fill(0, $meepopsRunningParam['childProcessCount'], 0);
//初始化定时器
\MeepoPS\Core\Timer::init();

//载入MeepoPS核心文件
require MEEPO_PS_ROOT_PATH . '/Core/MeepoPS.php';
