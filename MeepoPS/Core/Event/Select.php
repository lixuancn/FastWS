<?php
/**
 * 使用Select方式进行轮询
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

class Select implements EventInterface
{

    //所有事件
    private $_eventList = array();
    //待监听所有读事件的资源列表
    private $_readEventResourceList = array();
    //待监听所有写事件的资源列表
    private $_writeEventResourceList = array();
    //SPL_PRIORITY_QUEUE
    private $_splPriorityQueue = array();
    //计时器任务ID
    private $_timerId = 1;
    //select 超时时间 微妙 默认100秒
    private $_selectTimeout = MEEPO_PS_EVENT_SELECT_POLL_TIMEOUT;

    /**
     * 初始化
     * EventInterface constructor.
     */
    public function __construct()
    {
        //初始化事件列表
        $this->_initEventList();
        //初始化一个队列
        $this->_splPriorityQueue = new \SplPriorityQueue();
        //设置队列为提取数组包含值和优先级
        $this->_splPriorityQueue->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);
    }

    /**
     * 初始化事件列表
     */
    private function _initEventList()
    {
        $this->_eventList = array(
            self::EVENT_TYPE_READ => array(),
            self::EVENT_TYPE_WRITE => array(),
            self::EVENT_TYPE_SIGNAL => array(),
            self::EVENT_TYPE_TIMER => array(),
            self::EVENT_TYPE_TIMER_ONCE => array(),
        );
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
        //如果是读写事件,则两者和不能超过MEEPO_PS_EVENT_SELECT_MAX_SIZE限制.
        if (($type == EventInterface::EVENT_TYPE_READ || $type == EventInterface::EVENT_TYPE_WRITE)) {
            $total = (count($this->_eventList[self::EVENT_TYPE_READ]) + count($this->_eventList[self::EVENT_TYPE_WRITE]));
            if (MEEPO_PS_EVENT_SELECT_MAX_SIZE < $total) {
                Log::write('Select maximum number of listening resources is set to ' . MEEPO_PS_EVENT_SELECT_MAX_SIZE . ', but you have descriptors numbered at least as high as ' . $total . '. If you want to change this value, you must recompile PHP (--enable-fd-setsize=2048)', 'WARNING');
                return false;
            }
        }
        $type = intval($type);
        switch ($type) {
            //读/写/信号事件
            case self::EVENT_TYPE_READ:
            case self::EVENT_TYPE_WRITE:
            case self::EVENT_TYPE_SIGNAL:
                $uniqueId = (int)($resource);
                $this->_eventList[$type][$uniqueId] = array($callback, $resource);
                if ($type === self::EVENT_TYPE_READ) {
                    $this->_readEventResourceList[$uniqueId] = $resource;
                } else if ($type === self::EVENT_TYPE_WRITE) {
                    $this->_writeEventResourceList[$uniqueId] = $resource;
                } else {
                    pcntl_signal($resource, array($this, 'signalCallback'), false);
                }
                return $uniqueId;
            //永久性定时任务/一次性定时任务
            case self::EVENT_TYPE_TIMER:
            case self::EVENT_TYPE_TIMER_ONCE:
                //下次运行时间 = 当前时间 + 时间间隔
                $runTime = microtime(true) + $resource;
                //添加到定时器任务
                $timerId = $this->_timerId++;
                $this->_eventList[$type][$timerId] = array($callback, $args, $resource, $type);
                //入队,优先级大的排在队列的前面, 即,下次运行时间越大,优先级越低. 所以传入下次运行时间的负数
                $this->_splPriorityQueue->insert($timerId, -$runTime);
                //执行
                $this->_runTimerEvent();
                return $timerId;
            default:
                Log::write('MeepoPS: Event library Select adds an unknown type: ' . $type, 'ERROR');
                return false;
        }
    }

    /**
     * 信号回调函数
     * @param $signal int 信号
     */
    public function signalCallback($signal)
    {
        $uniqueId = (int)($signal);
        try {
            call_user_func($this->_eventList[self::EVENT_TYPE_SIGNAL][$uniqueId][0], $uniqueId);
        } catch (\Exception $e) {
            Log::write('MeepoPS: execution callback function run timer signal callback-' . $this->_eventList[self::EVENT_TYPE_SIGNAL][$uniqueId][0] . ' throw exception' . json_encode($e), 'ERROR');
        }
    }

    /**
     * 执行定时器任务
     */
    private function _runTimerEvent()
    {
        //如果队列不为空
        while (!$this->_splPriorityQueue->isEmpty()) {
            //查看队列顶部的最高优先级的数据(只看,不出队)
            $data = $this->_splPriorityQueue->top();
            //优先级 = 下次运行时间的负数
            $runTime = -$data['priority'];
            $nowTime = microtime(true);
            //当前时间还没有到下次运行时间
            if ($nowTime < $runTime) {
                $this->_selectTimeout = ($runTime - $nowTime) * 1000000;
                return;
            }
            //定时器任务的Id
            $timerId = $data['data'];
            //将数据出队
            $this->_splPriorityQueue->extract();
            //从定时器任务列表中获取本次的任务
            if (isset($this->_eventList[self::EVENT_TYPE_TIMER][$timerId])) {
                $type = self::EVENT_TYPE_TIMER;
            } else if (isset($this->_eventList[self::EVENT_TYPE_TIMER_ONCE][$timerId])) {
                $type = self::EVENT_TYPE_TIMER_ONCE;
            } else {
                continue;
            }
            $task = $this->_eventList[$type][$timerId];
            //如果是长期的定时器任务.则计算下次执行时间,并重新根据优先级入队
            if ($type === EventInterface::EVENT_TYPE_TIMER) {
                $nextRunTime = $nowTime + $task[2];
                $this->_splPriorityQueue->insert($timerId, -$nextRunTime);
                //如果是一次性定时任务,则从队列列表中删除
            } else if ($type === EventInterface::EVENT_TYPE_TIMER_ONCE) {
                $this->delOne($timerId, EventInterface::EVENT_TYPE_TIMER_ONCE);
            }
            try {
                call_user_func_array($task[0], $task[1]);
            } catch (\Exception $e) {
                Log::write('MeepoPS: execution callback function run timer event-' . $task[0] . ' throw exception' . json_encode($e), 'ERROR');
            }
            continue;
        }
        $this->_selectTimeout = MEEPO_PS_EVENT_SELECT_POLL_TIMEOUT;
    }

    /**
     * 删除指定的事件
     * @param $resource resource|int 读写事件中表示socket资源,定时器任务中表示时间(int,秒),信号回调中表示信号(int)
     * @param $type int 类型
     */
    public function delOne($resource, $type)
    {
        $uniqueId = (int)($resource);
        if ($type === self::EVENT_TYPE_READ) {
            unset($this->_readEventResourceList[$uniqueId]);
            unset($this->_eventList[$type][$uniqueId]);
        } else if ($type === self::EVENT_TYPE_WRITE) {
            unset($this->_writeEventResourceList[$uniqueId]);
            unset($this->_eventList[$type][$uniqueId]);
        } else if($type === self::EVENT_TYPE_TIMER){
            if(isset($this->_eventList[self::EVENT_TYPE_TIMER_ONCE][$uniqueId])){
                unset($this->_eventList[self::EVENT_TYPE_TIMER_ONCE][$uniqueId]);
            }else{
                unset($this->_eventList[self::EVENT_TYPE_TIMER][$uniqueId]);
            }
        }else{
            //将信号设置为忽略信号
            pcntl_signal($resource, SIG_IGN);
        }
    }

    /**
     * 清除所有的计时器事件
     */
    public function delAllTimer()
    {
        //清空计时器任务列表
        $this->_eventList[self::EVENT_TYPE_TIMER] = array();
        $this->_eventList[self::EVENT_TYPE_TIMER_ONCE] = array();
        //清空队列
        $this->_splPriorityQueue = new \SplPriorityQueue();
        $this->_splPriorityQueue->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);
    }

    /**
     * 循环事件
     */
    public function loop()
    {
        //检测空轮询
        $this->_checkEmptyLoop();
        $e = null;
        while (true) {
            //调用等待信号的处理器.即收到信号后执行通过pcntl_signal安装的信号处理函数.此处不会阻塞一直等待
            pcntl_signal_dispatch();
            //已添加的读事件 - 每个元素都是socket资源
            $readList = $this->_readEventResourceList;
            //已添加的写事件 - 每个元素都是socket资源
            $writeList = $this->_writeEventResourceList;
            //监听读写事件列表,如果哪个有变化则发回变化数量.同时引用传入的两个列表将会变化
            //请注意:stream_select()最多只能接收1024个监听
            $selectNum = @stream_select($readList, $writeList, $e, 0, $this->_selectTimeout);
            //执行定时器队列
            if (!$this->_splPriorityQueue->isEmpty()) {
                $this->_runTimerEvent();
            }
            //如果没有变化的读写事件则开始执行下次等待
            if (!$selectNum) {
                continue;
            }
            //处理接收到的读和写请求
            $selectList = array(
                array('type' => EventInterface::EVENT_TYPE_READ, 'data' => $readList),
                array('type' => EventInterface::EVENT_TYPE_WRITE, 'data' => $writeList),
            );
            foreach ($selectList as $select) {
                foreach ($select['data'] as $item) {
                    $uniqueId = (int)($item);
                    if (isset($this->_eventList[$select['type']][$uniqueId])) {
                        try {
                            call_user_func($this->_eventList[$select['type']][$uniqueId][0], $this->_eventList[$select['type']][$uniqueId][1]);
                        } catch (\Exception $e) {
                            Log::write('MeepoPS: execution callback function select loop-' . $this->_eventList[$select['type']][$uniqueId][0] . ' throw exception' . json_encode($e), 'ERROR');
                        }
                    }
                }
            }
        }
    }

    /**
     * 如果读事件列表为空,则创建空socket链接,以避免造成空轮询.
     * 空轮询会使得CPU瞬间飙升至100%
     */
    private function _checkEmptyLoop()
    {
        if (empty($this->_eventList[self::EVENT_TYPE_READ])) {
            $channel = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            if ($channel) {
                stream_set_blocking($channel[0], 0);
                $this->_eventList[self::EVENT_TYPE_READ][0] = array('', $channel[0]);
                fclose($channel[1]);
            }
        }
    }
}

