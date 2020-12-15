<?php
/**
 * Created by Lane
 * User: lane
 * Date: 16/3/23
 * Time: 下午2:19
 * E-mail: lixuan868686@163.com
 * WebSite: http://www.lanecn.com
 */
namespace MeepoPS\Core;

$fatalErrorList = array();
$warningErrorList = array();

//MeepoPS要求PHP环境必须大于PHP5.3
if (!substr(PHP_VERSION, 0, 3) >= '5.3') {
    $fatalErrorList[] = "Fatal error: MeepoPS requires PHP version must be greater than 5.3(contain 5.3). Because MeepoPS used php-namespace";
}

//MeepoPS不支持在Windows下运行
if (strpos(strtolower(PHP_OS), 'win') === 0) {
    $fatalErrorList[] = "Fatal error: MeepoPS not support Windows. Because the required extension is supported only by Linux, such as php-pcntl, php-posix";
}

//MeepoPS必须运行在命令行下
if (php_sapi_name() != 'cli') {
    $fatalErrorList[] = "Fatal error: MeepoPS must run in command line!";
}

//是否已经安装PHP-pcntl 扩展
if (!extension_loaded('pcntl')) {
    $fatalErrorList[] = "Fatal error: MeepoPS must require php-pcntl extension. Because the signal monitor, multi process needs php-pcntl\nPHP manual: http://php.net/manual/zh/intro.pcntl.php";
}

//是否已经安装PHP-posix 扩展
if (!extension_loaded('posix')) {
    $fatalErrorList[] = "Fatal error: MeepoPS must require php-posix extension. Because send a signal to a process, get the real user ID of the current process needs php-posix\nPHP manual: http://php.net/manual/zh/intro.posix.php";
}

//启动参数是否正确
global $argv;
if (!isset($argv[1]) || !in_array($argv[1], array('start', 'stop', 'restart', 'status', 'kill'))) {
    $fatalErrorList[] = "Fatal error: MeepoPS needs to receive the execution of the operation.\nUsage: php index.php start|stop|restart|status|kill\n\"";
}

//日志路径是否已经配置
if (!defined('MEEPO_PS_LOG_PATH_PREFIX')) {
    $fatalErrorList[] = "Fatal error: Log file path prefix is not defined. Please define MEEPO_PS_LOG_PATH_PREFIX in Config.php";
} else {
    //日志目录是否存在
    if (!file_exists(dirname(MEEPO_PS_LOG_PATH_PREFIX))) {
        if (@!mkdir(dirname(MEEPO_PS_LOG_PATH_PREFIX), 0777, true)) {
            $fatalErrorList[] = "Fatal error: Log file directory creation failed: " . dirname(MEEPO_PS_LOG_PATH_PREFIX);
        }
    }
    //日志目录是否可写
    if (!is_writable(dirname(MEEPO_PS_LOG_PATH_PREFIX))) {
        $fatalErrorList[] = "Fatal error: Log file path not to be written: " . dirname(MEEPO_PS_LOG_PATH_PREFIX);
    }
}

//MeepoPS主进程Pid文件路径是否已经配置
if (!defined('MEEPO_PS_MASTER_PID_PATH')) {
    $fatalErrorList[] = "Fatal error: master pid file path is not defined. Please define MEEPO_PS_MASTER_PID_PATH in Config.php";
} else {
    //MeepoPS主进程Pid文件目录是否存在
    if (!file_exists(dirname(MEEPO_PS_MASTER_PID_PATH))) {
        if (@!mkdir(dirname(MEEPO_PS_MASTER_PID_PATH), 0777, true)) {
            $fatalErrorList[] = "Fatal error: master pid file directory creation failed: " . dirname(MEEPO_PS_MASTER_PID_PATH);
        }
    }
    //MeepoPS主进程Pid文件目录是否可写
    if (!is_writable(dirname(MEEPO_PS_MASTER_PID_PATH))) {
        $fatalErrorList[] = "Fatal error: master pid file path not to be written: " . dirname(MEEPO_PS_MASTER_PID_PATH);
    }
}

//标准输出路径是否已经配置
if (!defined('MEEPO_PS_STDOUT_PATH')) {
    $warningErrorList[] = "Warning error: standard output file path is not defined. Please define MEEPO_PS_STDOUT_PATH in Config.php";
} else if (MEEPO_PS_STDOUT_PATH !== '/dev/null') {
    //标准输出目录是否存在
    if (!file_exists(dirname(MEEPO_PS_STDOUT_PATH))) {
        if (@!mkdir(dirname(MEEPO_PS_STDOUT_PATH), 0777, true)) {
            $warningErrorList[] = "Warning error: standard output file directory creation failed: " . dirname(MEEPO_PS_STDOUT_PATH);
        }
    }
    //标准输出目录是否可写
    if (!is_writable(dirname(MEEPO_PS_STDOUT_PATH))) {
        $warningErrorList[] = "Warning error: standard output file path not to be written: " . dirname(MEEPO_PS_STDOUT_PATH);
    }
}

//统计信息存储文件路径是否已经配置
if (!defined('MEEPO_PS_STATISTICS_PATH')) {
    $warningErrorList[] = "Warning error: statistics file path is not defined. Please define MEEPO_PS_STATISTICS_PATH in Config.php";
} else {
    //统计信息存储文件目录是否存在
    if (!file_exists(dirname(MEEPO_PS_STATISTICS_PATH))) {
        if (@!mkdir(dirname(MEEPO_PS_STATISTICS_PATH), 0777, true)) {
            $warningErrorList[] = "Warning error: statistics file directory creation failed: " . dirname(MEEPO_PS_STATISTICS_PATH);
        }
    }
    //统计信息存储文件目录是否可写
    if (!is_writable(dirname(MEEPO_PS_STATISTICS_PATH))) {
        $warningErrorList[] = "Warning error: statistics file path not to be written: " . dirname(MEEPO_PS_STATISTICS_PATH);
    }
}

if ($fatalErrorList) {
    $fatalErrorList = implode("\n\n", $fatalErrorList);
    exit($fatalErrorList);
}

if ($warningErrorList) {
    $warningErrorList = implode("\n\n", $warningErrorList);
    echo $warningErrorList . "\n\n";
}

unset($fatalErrorList);
unset($warningErrorList);