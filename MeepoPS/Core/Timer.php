<?php
/**
 * 定时器
 * 监听alarm信号,并通过alarm信号来控制每次的执行.即收到alarm信号后开始执行定时器任务
 * 比如每十秒发送一次Alarm信号,那么每十秒就执行一次定时器任务
 * 如有事件支持时,比如Select或者Libevent等,则不再使用alarm信号来辅助
 * Created by Lane
 * User: lane
 * Date: 16/3/25
 * Time: 下午2:52
 * E-mail: lixuan868686@163.com
 * WebSite: http://www.lanecn.com
 */
namespace MeepoPS\Core;

use MeepoPS\Core\Event\EventInterface;

class Timer
{
    //事件机制.为空时则使用alarm信号来完成定时器机制.不为空时依赖Select/Libevent等
    private static $_event = null;
    //任务列表
    private static $_taskList = array();
    //待删除的任务列表
    private static $_waitDelList = array();
    //任务ID
    private static $_id = 1;

    /**
     * 初始化定时器
     * @param object $event
     */
    public static function init($event = null)
    {
        //如果没有事件,则安装一个alarm信号处理器
        is_object($event) ? (self::$_event = $event) : (pcntl_signal(SIGALRM, array('\MeepoPS\Core\Timer', 'signalCallback'), false));
    }

    /**
     * 添加任务
     * @param $callback string|array 回调函数
     * @param $args array 参数
     * @param $intervalSecond int 每次执行的间隔时间,单位秒,必须大于0
     * @param $isAlways bool|true 是否一直执行,默认为true. 一次性任务请传入false
     * @return int|false
     */
    public static function add($callback, array $args, $intervalSecond, $isAlways = true, $timerId = null)
    {
        if ($intervalSecond <= 0 || !is_callable($callback)) {
            return false;
        }
        if (!is_null(self::$_event)) {
            return self::$_event->add($callback, $args, $intervalSecond, $isAlways ? EventInterface::EVENT_TYPE_TIMER : EventInterface::EVENT_TYPE_TIMER_ONCE);
        } else {
            pcntl_alarm(1);
            $startTime = time() + $intervalSecond;
            if(is_null($timerId)) {
                $timerId = self::$_id++;
            }
            self::$_taskList[$timerId] = array($callback, $args, $startTime, $intervalSecond, $isAlways);
            return $timerId;
        }
    }

    /**
     * 删除一个定时器
     * @param $timerId
     */
    public static function delOne($timerId)
    {
        if (!is_null(self::$_event)) {
            self::$_event->delOne($timerId, EventInterface::EVENT_TYPE_TIMER);
        } else {
            self::$_waitDelList[$timerId] = null;
            unset(self::$_taskList[$timerId]);
        }
    }

    /**
     * 删除所有的定时器任务
     */
    public static function delAll()
    {
        self::$_taskList = array();
        self::$_waitDelList = array();
        pcntl_alarm(0);
        if (!is_null(self::$_event)) {
            self::$_event->delAllTimer();
        }
    }

    /**
     * 信号处理函数
     */
    public static function signalCallback()
    {
        //没有事件机制,并且队列不为空,则使用alarm信号
        if (is_null(self::$_event) && !empty(self::$_taskList)) {
            //创建一个计时器，每秒向进程发送一个alarm信号。
            pcntl_alarm(1);
            self::_execute();
        }
    }

    /**
     * 执行任务
     */
    private static function _execute()
    {
        $nowTime = time();
        foreach (self::$_taskList as $timerId => $task) {
            //检测是否即将删除
            if(isset(self::$_waitDelList[$timerId])){
                unset(self::$_taskList[$timerId]);
                unset(self::$_waitDelList[$timerId]);
                continue;
            }
            //当前时间小于启动时间,则不启动该时间段任务
            if ($nowTime < $task[2]) {
                continue;
            }
            //如果是持续性定时器任务,则覆盖任务队列中本次任务xinxi
            if ($task[4]) {
                self::add($task[0], $task[1], $task[3], $task[4], $timerId);
            }
            //执行回调函数
            try {
                call_user_func_array($task[0], $task[1]);
            } catch (\Exception $e) {
                Log::write('MeepoPS: execution callback function timer execute-' . $task[0] . ' throw exception' . json_encode($e), 'ERROR');
            }
            //一次性定时器任务，删除本次已经执行的任务
            if (!$task[4]) {
                unset(self::$_taskList[$timerId]);
            }
        }
    }
}