<?php
/**
 * 常用函数
 * Created by Lane
 * User: lane
 * Date: 16/3/25
 * Time: 下午2:32
 * E-mail: lixuan868686@163.com
 * WebSite: http://www.lanecn.com
 */
namespace MeepoPS\Core;
class Func
{
    /**
     * @descrpition 数组的KEY变更为项中的ID
     * @param $arr
     * @return array
     */
    public static function  arrayKey($arr, $key = 'id')
    {
        $data = array();
        foreach ($arr as $a) {
            $data[$a[$key]] = $a;
        }
        return $data;
    }

    public static function setProcessTitle($title)
    {
        //mac下cli_set_process_title()有bug。所以就放到try里了。
        try{
            if (function_exists('cli_set_process_title')) {
                @cli_set_process_title($title);
            } elseif (extension_loaded('proctitle') && function_exists('setproctitle')) {
                @setproctitle($title);
            }
        }catch (\Exception $e){
        }
    }

    public static function getCurrentUser()
    {
        $userInfo = posix_getpwuid(posix_getuid());
        return $userInfo['name'];
    }

    /**
     * 当前运行环境是不是MeepoPS
     */
    public static function isMeepoPSRuntimeEvironment(){
        if(isset($_SERVER['runtime_environment']) && $_SERVER['runtime_environment'] === 'meepops'){
            return true;
        }else{
            return false;
        }
    }
}