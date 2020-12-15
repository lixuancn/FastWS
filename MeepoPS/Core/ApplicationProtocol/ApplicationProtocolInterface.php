<?php
/**
 * 应用层协议接口(application layer protocol)
 * Created by Lane
 * User: lane
 * Date: 16/3/26
 * Time: 下午10:28
 * E-mail: lixuan868686@163.com
 * WebSite: http://www.lanecn.com
 */
namespace MeepoPS\Core\ApplicationProtocol;
use MeepoPS\Core\TransportProtocol\TransportProtocolInterface;

interface ApplicationProtocolInterface
{

    /**
     * 检测数据, 返回数据包的长度.
     * 没有数据包或者数据包未结束,则返回0
     * @param string $data 数据包
     * @param TransportProtocolInterface $connect 基于传输层协议的链接
     * @return mixed
     */
    public static function input($data, TransportProtocolInterface $connect);

    /**
     * 对发送的数据进行encode. 例如将数据整理为符合Http/WebSocket/stream(json/text等)等协议的规定
     * @param $data
     * @param TransportProtocolInterface $connect 基于传输层协议的链接
     * @return mixed
     */
    public static function encode($data, TransportProtocolInterface $connect);

    /**
     * 对接收到的数据进行decode. 例如将数据按照客户端约定的协议如Http/WebSocket/stream(json/text等)等进行解析
     * 本方法将会触发MeepoPS::$callbackNewData的回调函数
     * @param string $data 待解码的数据
     * @param TransportProtocolInterface $connect 基于传输层协议的链接
     * @return mixed
     */
    public static function decode($data, TransportProtocolInterface $connect);
}