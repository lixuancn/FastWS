<?php
/**
 * API - 自定义协议 - CBNSQ
 * Created by Lane
 * User: lane
 * Date: 2018/9/29
 * Time: 下午6:24
 * E-mail: lixuan868686@163.com
 * WebSite: http://www.lanecn.com
 */
namespace MeepoPS\Api;

use MeepoPS\Core\MeepoPS;

class CBNSQ extends MeepoPS
{

    /**
     * @param string $host string 需要监听的地址
     * @param string $port string 需要监听的端口
     * @param array $contextOptionList
     */
    public function __construct($host, $port, $contextOptionList = array())
    {
        if (!$host || !$port) {
            return;
        }
        parent::__construct('CBNSQ', $host, $port, $contextOptionList);
    }

    /**
     * 运行一个指定协议的实例
     */
    public function run()
    {
        parent::run();
    }
}

