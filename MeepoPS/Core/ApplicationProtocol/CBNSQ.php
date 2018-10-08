<?php
/**
 * 自定义协议: 从TCP数据流中解析PHP序列化数据。格式为：8位的数据正文的长度 + messageID + 数据正文
 * Created by Lane.
 * User: lane
 * Date: 2018/9/29
 * Time: 下午5:50
 * E-mail: lixuan868686@163.com
 * WebSite: http://www.lanecn.com
 */
namespace MeepoPS\Core\ApplicationProtocol;

use MeepoPS\Core\TransportProtocol\TransportProtocolInterface;

class CBNSQ implements ApplicationProtocolInterface
{

    //基础头长: 8为正文长度
    //单位：位
    const BASE_HEADER_LENGTH = 8;
    //消息id的长度, NSQ生成的
    const MESSAGE_ID_LENGTH = 16;

    /**
     * 检测数据, 返回数据包的长度.
     * 没有数据包或者数据包未结束,则返回0
     * 返回 < 0时会销毁连接
     * @param string $data 数据包
     * @param TransportProtocolInterface $connect 基于传输层协议的链接
     * @return int
     */
    public static function input($data, TransportProtocolInterface $connect)
    {
        //数据长度
        $dataLength = strlen($data);
        //头部未完 (当前帧未完)
        if($dataLength < self::BASE_HEADER_LENGTH){
            return 0;
        }
        //数据正文长度转int
        $contentLength = substr($data, 0, self::BASE_HEADER_LENGTH);
        $contentLength = intval($contentLength);
        //加上头和消息id
        $len = self::BASE_HEADER_LENGTH + self::MESSAGE_ID_LENGTH + $contentLength;
        return $len;
    }

    /**
     * 数据编码. 默认在发送数据前自动调用此方法. 不用您手动调用.
     * @param string $data 给数据流中发送的数据
     * @param TransportProtocolInterface $connect 基于传输层协议的链接
     * @return string
     */
    public static function encode($data, TransportProtocolInterface $connect)
    {
        if(is_string($data)){
            return $data;
        }
        return "";
    }

    /**
     * 数据解码. 默认在接收数据时自动调用此方法. 不用您手动调用.
     * @param string $data 从数据流中接收到的数据
     * @param TransportProtocolInterface $connect 基于传输层协议的链接
     * @return string
     */
    public static function decode($data, TransportProtocolInterface $connect)
    {
        $data = substr($data, self::BASE_HEADER_LENGTH + 1);
        return $data;
    }
}
