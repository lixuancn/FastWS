<?php
/**
 * 使用Libevent方式进行事件驱动
 * Libevent是封装了Linux的epoll，BSD的kqueue，Windows的IOCP
 * 时间复杂度O(n)
 * Created by Lane
 * User: lane
 * Date: 16/3/29
 * Time: 下午5:38
 * E-mail: lixuan868686@163.com
 * WebSite: http://www.lanecn.com
 */
namespace MeepoPS\Core\Event;

use MeepoPS\Core\Log;

class Libevent implements EventInterface
{

    //所有事件 - 读事件和写事件和信号事件
    private $_eventList = array();
    //创建并且初始的事件.由event_base_new()返回
    private $_eventBase;

    /**
     * 初始化
     * EventInterface constructor.
     */
    public function __construct()
    {
        $this->_eventBase = event_base_new();
    }

    /**
     * 添加事件
     * @param $callback string|array 回调函数
     * @param $args array 回调函数的参数
     * @param $resource resource|int 读写事件中表示socket资源,定时器任务中表示时间(int,秒),信号回调中表示信号(int)
     * @param $type int 类型
     * @return int|false
     */
    public function add($callback, array $args, $resource, $type)
    {
        $type = intval($type);
        //1.创建一个新的事件
        $event = event_new();
        switch ($type) {
            //读/写/信号事件
            //必须指定EV_PERSIST.不指定这个属性的话，回调函数被触发后事件会被删除
            case self::EVENT_TYPE_READ:
            case self::EVENT_TYPE_WRITE:
            case self::EVENT_TYPE_SIGNAL:
                $libeventType = $type === self::EVENT_TYPE_READ ? (EV_READ | EV_PERSIST) :
                    ($type === self::EVENT_TYPE_WRITE ? (EV_WRITE | EV_PERSIST) : (EV_SIGNAL | EV_PERSIST));
                $uniqueId = (int)($resource);
                //2.准备想要在event_add中添加事件
                if (event_set($event, $resource, $libeventType, $callback, $args)
                    //3.关联事件到事件base
                    && event_base_set($event, $this->_eventBase)
                    //4.向指定的设置中添加一个执行事件
                    && event_add($event)
                ) {
                    $this->_eventList[$type][$uniqueId] = $event;
                    return $uniqueId;
                }
                return false;
            //永久性定时任务/一次性定时任务
            case self::EVENT_TYPE_TIMER:
            case self::EVENT_TYPE_TIMER_ONCE:
                $timerId = (int)$event;
                $intervalMicrosecond = $resource * 1000000;
                if (event_set($event, 0, EV_TIMEOUT, array($this, 'timerCallback'), $timerId)
                    && event_base_set($event, $this->_eventBase)
                    && event_add($event, $intervalMicrosecond)
                ) {
                    $this->_eventList[$type][$timerId] = array($callback, (array)$args, $event, $type, $intervalMicrosecond);
                    return $timerId;
                }
                return false;
            default :
                Log::write('Libevent: add failed. ' . $type . ' is unrecognized type', 'WARNING');
                return false;
        }
    }

    /**
     * 删除指定的事件
     * @param $resource resource|int 读写事件中表示socket资源,定时器任务中表示时间(int,秒),信号回调中表示信号(int)
     * @param $type int 类型
     */
    public function delOne($resource, $type)
    {
        $type = intval($type);
        $uniqueId = (int)($resource);
        switch ($type) {
            case self::EVENT_TYPE_READ:
            case self::EVENT_TYPE_WRITE:
            case self::EVENT_TYPE_SIGNAL:
                $event = !empty($this->_eventList[$type][$uniqueId]) ? $this->_eventList[$type][$uniqueId] : '';
                break;
            case self::EVENT_TYPE_TIMER:
            case self::EVENT_TYPE_TIMER_ONCE:
                $event = '';
                if(!empty($this->_eventList[self::EVENT_TYPE_TIMER][$uniqueId][2])){
                    $event = $this->_eventList[self::EVENT_TYPE_TIMER][$uniqueId][2];
                }else if(!empty($this->_eventList[self::EVENT_TYPE_TIMER_ONCE][$uniqueId][2])){
                    $event = $this->_eventList[self::EVENT_TYPE_TIMER_ONCE][$uniqueId][2];
                }
                break;
            default:
                Log::write('Libevent: del one failed. ' . $type . ' is unrecognized type', 'WARNING');
                return;
        }
        if (!empty($event)) {
            event_del($event);
            unset($this->_eventList[$type][$uniqueId]);
        }
    }

    /**
     * 清除所有的计时器事件
     * @return mixed
     */
    public function delAllTimer()
    {
        //从永久性定时任务中移除
        foreach ($this->_eventList[self::EVENT_TYPE_TIMER] as $timerEvent) {
            //从设置的事件中移除事件
            event_del($timerEvent[2]);
        }
        //从一次性定时任务中移除
        foreach ($this->_eventList[self::EVENT_TYPE_TIMER_ONCE] as $timerEvent) {
            //从设置的事件中移除事件
            event_del($timerEvent[2]);
        }
    }

    /**
     * 循环事件
     */
    public function loop()
    {
        //处理事件，根据指定的base来处理事件循环
        event_base_loop($this->_eventBase);
    }

    /**
     * 定时器的回调函数
     * Libevent会先回调本方法.本方法再回调使用方设置的回调函数
     * @param int $timerId 定时器ID
     */
    public function timerCallback($timerId)
    {
        if (isset($this->_eventList[self::EVENT_TYPE_TIMER][$timerId])) {
            $timer = $this->_eventList[self::EVENT_TYPE_TIMER][$timerId];
        } else if (isset($this->_eventList[self::EVENT_TYPE_TIMER_ONCE][$timerId])) {
            $timer = $this->_eventList[self::EVENT_TYPE_TIMER_ONCE][$timerId];
        } else {
            Log::write('Libevent: timer event handle failed. timer id is ' . $timerId, 'WARNING');
            return;
        }
        //如果是永久定时器任务.则再次加入事件
        if ($timer[3] === self::EVENT_TYPE_TIMER) {
            event_add($timer[2], $timer[4]);
        }
        //如果是一次性定时器任务.则从任务列表中删除
        if ($timer[3] === self::EVENT_TYPE_TIMER_ONCE) {
            $this->delOne($timerId, self::EVENT_TYPE_TIMER_ONCE);
        }
        //触发回调函数
        try {
            call_user_func_array($timer[0], $timer[1]);
        } catch (\Exception $e) {
            Log::write('MeepoPS: execution callback function timer callback-' . $timer[0] . ' throw exception' . json_encode($e), 'ERROR');
        }
    }
}