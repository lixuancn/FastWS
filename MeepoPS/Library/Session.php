<?php
/**
 * Created by Lane
 * User: lane
 * Date: 16/6/13
 * Time: 下午6:01
 * E-mail: lixuan868686@163.com
 * WebSite: http://www.lanecn.com
 */
namespace MeepoPS\Library;

use MeepoPS\Api\Http;

class Session{
    //Session文件保存路径
    private $_savePath;
    //Session是否开启
    private $_isStart = false;
    //SessionId
    private $_sessionId;

    /**
     * 开启Session
     * @return bool
     */
    public function start(){
        //非命令行调用时
        if(PHP_SAPI !== 'cli'){
            return session_start();
        }
        //Session路径
        $sessionSavePath = session_save_path();
        $this->_savePath = !empty($sessionSavePath) ? $sessionSavePath : sys_get_temp_dir();
        if(strlen($this->_savePath) > 1 && $this->_savePath[strlen($this->_savePath)-1] === '/'){
            $this->_savePath = substr($this->_savePath, 0, -1);
        }
        if(!$this->_savePath){
            //日志
            return false;
        }
        if(!is_dir($this->_savePath)){
            $result = @mkdir($this->_savePath, 0777);
            if($result !== true){
                //日志
                return false;
            }
        }
        //获取SessionId
        $this->_sessionId = isset($_COOKIE[MEEPO_PS_HTTP_SESSION_NAME]) ? $_COOKIE[MEEPO_PS_HTTP_SESSION_NAME] : '';
        if(empty($this->_sessionId) || !is_file($this->_savePath . '/' . $_COOKIE[MEEPO_PS_HTTP_SESSION_NAME])){
            $this->_sessionId = uniqid('sess_', true);
            return Http::setCookie(
                MEEPO_PS_HTTP_SESSION_NAME
                , $this->_sessionId
                , ini_get('session.cookie_lifetime')
                , ini_get('session.cookie_path')
                , ini_get('session.cookie_domain')
                , ini_get('session.cookie_secure')
                , ini_get('session.cookie_httponly')
            );
        }
        //填充$_SESSION
        $_SESSION = $this->_read();
        $_SESSION = $this->_decode($_SESSION);
        //Session状态
        $this->_isStart = true;
        //回收过期SESSION
        $this->_gc();
        return true;
    }

    public function id(){
        return $this->_sessionId;
    }

    /**
     * 读取Session时调用
     * @return string
     */
    private function _read(){
        return file_get_contents($this->_savePath . '/' . $this->_sessionId);
    }

    /**
     * Session保存到文件时先encode
     */
    private function _encode($data){
        return serialize($data);
    }

    /**
     * Session从文件读取后先Decode
     */
    private function _decode($data){
        return unserialize($data);
    }

    /**
     * 保存Session
     * @param $data
     * @return bool or int
     */
    public function write($data){
        return @file_put_contents($this->_savePath . '/' . $this->_sessionId, $this->_encode($data));
    }

    /**
     * 关闭Session
     * @return bool
     */
    public function close(){
        return true;
    }

    /**
     * 销毁Session
     * @return bool
     */
    public function destroy(){
        $file = $this->_savePath . '/' . $this->_sessionId;
        if (file_exists($file)) {
            unlink($file);
        }
        return true;
    }

    /**
     * 资源回收。
     * 本方法涉及到三个外部参数, 来自PHP.ini
     * 调用周期由 session.gc_probability 和 session.gc_divisor 参数控制
     * SESSION有效期由session.gc_maxlifetime 设置
     * @return bool
     */
    private function _gc(){
        $probability = intval(ini_get('session.gc_probability'));
        $divisor = intval(ini_get('session.gc_divisor'));
        $maxLifeTime = intval(ini_get('session.gc_maxlifetime'));
        if(!$probability || !$divisor || !$maxLifeTime){
            return false;
        }
        //概率计算
        if($probability < $divisor){
            //概率
            $rand = mt_rand(0, $divisor);
            if($rand > $probability){
                return false;
            }
        }
        //开始清除
        foreach (glob($this->_savePath . '/sess_*') as $file) {
            if (filemtime($file) + $maxLifeTime < time()) {
                @unlink($file);
            }
        }
        return true;
    }
}
