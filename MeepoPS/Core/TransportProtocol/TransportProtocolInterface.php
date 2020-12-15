<?php
/**
 * 传输曾协议接口(transport layer protocol)
 * 链接的抽象类.如TCP或UDP等
 * Created by Lane
 * User: lane
 * Date: 16/3/27
 * Time: 上午1:13
 * E-mail: lixuan868686@163.com
 * WebSite: http://www.lanecn.com
 */
namespace MeepoPS\Core\TransportProtocol;

abstract class TransportProtocolInterface
{

    //统计信息
    public static $statistics = array(
        //总链接数
        'total_connect_count' => 0,
        //总读取数
        'total_read_count' => 0,
        //总读取失败数
        'total_read_failed_count' => 0,
        //总读取包数
        'total_read_package_count' => 0,
        //总读取包失败数
        'total_read_package_failed_count' => 0,
        //总发送数
        'total_send_count' => 0,
        //总发送失败数
        'total_send_failed_count' => 0,
        //异常数
        'exception_count' => 0,
        //当前链接数
        'current_connect_count' => 0,
    );

    /**
     * 构造函数
     * @param $socket resource 由stream_socket_accept()返回
     * @param $clientAddress string 由stream_socket_accept()的第三个参数$peerName
     * @param $applicationProtocol string 应用层协议, 默认为空
     */
//    abstract public function __construct($socket, $clientAddress, $applicationProtocol = '');

    /**
     * 读取数据
     * @param $connect resource 由stream_socket_accept()返回
     * @param $isDestroy bool 如果fread读取到的是空数据或者false的话,是否销毁链接.默认为true
     */
    abstract public function read($connect, $isDestroy = true);

    /**
     * 发送数据
     * @param $data mixed 待发送的数据
     * @param $isEncode bool 发送前是否根据应用层协议转码
     */
    abstract public function send($data, $isEncode = true);

    /**
     * 关闭客户端链接
     * @param $data string 关闭链接前发送的消息
     */
    abstract public function close($data = '');

    /**
     * 获取客户端地址
     * @return array|int 成功返回array[0]是ip,array[1]是端口. 失败返回false
     */
    abstract public function getClientAddress();
}