<?php
/**
 * API - HTTP协议
 * Created by Lane
 * User: lane
 * Date: 16/3/23
 * Time: 下午2:12
 * E-mail: lixuan868686@163.com
 * WebSite: http://www.lanecn.com
 */
namespace MeepoPS\Api;

use MeepoPS\Core\MeepoPS;
use MeepoPS\Core\Log;
use MeepoPS\Core\TransportProtocol\Tcp;
use MeepoPS\Library\Session;

class Http extends MeepoPS
{
    //默认页文件名
    private $_defaultIndexList = array();
    //代码文件根目录 array('www.lanecn.com' => '/var/www/')
    private $_documentRoot = array();
    //错误页 array('404' => '页面不在', '503' => 'tpl/err_503.html')
    private $_errorPage = array();
    //用户自定义的callbackNewData
    private $_userCallbackNewData;
    //Session
    private static $_sessionInstance;

    /**
     * WebServer constructor.
     * @param string $host string 需要监听的地址
     * @param string $port string 需要监听的端口
     * @param array $contextOptionList
     */
    public function __construct($host, $port, $contextOptionList = array())
    {
        if (!$host || !$port) {
            return;
        }
        parent::__construct('http', $host, $port, $contextOptionList);
        //域名和目录
        $domainDocumentList = explode('|', MEEPO_PS_HTTP_DOMAIN_DOCUMENT_LIST);
        foreach($domainDocumentList as $domainDocument){
            if(!$domainDocument){
                continue;
            }
            $domainDocument = explode('&', $domainDocument);
            if(empty($domainDocument[0]) || empty($domainDocument[1])){
                continue;
            }
            $this->_documentRoot[strtolower(trim($domainDocument[0]))] = trim($domainDocument[1]);
        }
        //默认页
        $this->_defaultIndexList = explode(',', MEEPO_PS_HTTP_DEFAULT_PAGE);
    }

    /**
     * 运行一个WebService实例
     */
    public function run()
    {
        if (empty($this->_documentRoot)) {
            Log::write('not set document root.', 'ERROR');
        }
        //设置MeepoPS的回调.
        $this->_userCallbackNewData = $this->callbackNewData;
        $this->callbackNewData = array($this, 'callbackNewData');
        //运行MeepoPS
        parent::run();
    }

    /**
     * 设置域名和路径
     * @param $domain
     * @param $path
     * @return bool
     */
    public function setDocument($domain, $path){
        if(!$domain || !$path){
            return false;
        }
        if(!file_exists($path) || !is_dir($path)){
            return false;
        }
        $this->_documentRoot[$domain] = $path;
        return true;
    }

    /**
     * 设置http头
     * @param string $string 头字符串
     * @param bool $replace 是否用后面的头替换前面相同类型的头.即相同的多个头存在时,后来的会覆盖先来的.
     * @param int $httpResponseCode 头字符串
     * @return bool
     */
    public static function setHeader($string, $replace = true, $httpResponseCode = 0)
    {
        return \MeepoPS\Core\ApplicationProtocol\Http::setHeader($string, $replace, $httpResponseCode);
    }

    /**
     * 删除header()设置的HTTP头信息
     * @param string $name 删除指定的头信息
     */
    public static function delHttpHeader($name)
    {
        \MeepoPS\Core\ApplicationProtocol\Http::delHttpHeader($name);
    }

    /**
     * 设置Cookie
     * 参数意义请参考setcookie()
     * @param string $name
     * @param string $value
     * @param integer $maxage
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httpOnly
     * @return bool
     */
    public static function setCookie($name, $value = '', $maxage = 0, $path = '', $domain = '', $secure = false, $httpOnly = false)
    {
        return \MeepoPS\Core\ApplicationProtocol\Http::setCookie($name, $value, $maxage, $path, $domain, $secure, $httpOnly);
    }

    /**
     * 开启SESSION
     * 功能类似session_start();
     * @return bool
     */
    public static function sessionStart()
    {
        self::$_sessionInstance->start();
    }

    /**
     * 写入SESSION
     * 默认情况下自动执行
     * 功能类似session_write_close();
     * @return bool
     */
    public static function sessionWrite()
    {
        self::$_sessionInstance->write();
    }

    /**
     * 获取SESSION ID
     * 功能类似session_id();
     * @return bool
     */
    public static function sessionId()
    {
        self::$_sessionInstance->id();
    }

    /**
     * SESSION
     * 功能类似session_destroy();
     * @return bool
     */
    public static function sessionDestroy()
    {
        self::$_sessionInstance->destroy();
    }

    /**
     * 结束,程序不在向下执行。退出业务逻辑返回到MeepoPS
     * 功能和普通Web开发的Exit一样
     * 就像平时exit()后退出PHP,交接给Nginx继续执行。
     * 可是在MeepoPS中, 不能使用exit()
     * @return bool
     */
    public static function end($msg='')
    {
        echo $msg;
        throw new \Exception('meepops_http_end');
    }
    
    /**
     * 收到新消息的回调
     * @param $connect
     * @param $data array
     */
    public function callbackNewData($connect, $data)
    {
        if (!empty($this->_userCallbackNewData)) {
            try {
                call_user_func_array($this->_userCallbackNewData, array($connect, $data));
            } catch (\Exception $e) {
                Tcp::$statistics['exception_count']++;
                Log::write('MeepoPS: execution callback function callbackNewData-' . json_encode($this->_userCallbackNewData) . ' throw exception' . json_encode($e), 'ERROR');
            }
        }
        self::$_sessionInstance = new Session();
        //解析来访的URL
        $requestUri = parse_url($_SERVER['REQUEST_URI']);
        if (!$requestUri) {
            $this->setHeader('HTTP/1.1 400 Bad Request');
            $this->_close($connect, $this->_getErrorPage(400, 'Bad Request'));
            return;
        }
        $urlPath = !empty($requestUri['path']) ? $requestUri['path'] : '';
        $_SERVER['HTTP_HOST'] = !empty($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
        $urlPath = $urlPath[strlen($urlPath) - 1] === '/' ? substr($urlPath, 0, -1) : $urlPath;
        $documentRoot = isset($this->_documentRoot[$_SERVER['HTTP_HOST']]) ? $this->_documentRoot[$_SERVER['HTTP_HOST']] : current($this->_documentRoot);
        $filename = $documentRoot . $urlPath;
        //清除文件状态缓存
        clearstatcache();
        //如果是目录
        if (is_dir($filename)) {
            //如果缺省首页存在
            if ($this->_defaultIndexList) {
                foreach ($this->_defaultIndexList as $index) {
                    $file = $filename . '/' . trim($index);
                    if (is_file($file)) {
                        $filename = $file;
                        break;
                    }
                }
            } else {
                $this->setHeader("HTTP/1.1 403 Forbidden");
                $this->_close($connect, $this->_getErrorPage(403, 'Forbidden'));
                return;
            }
        }
        //文件是否有效
        if (!is_file($filename)) {
            // 博客特殊处理 start
            $_SERVER['REQUEST_URI'] = $urlPath;
            $filename = $documentRoot . '/index.php';
            if (!is_file($filename)) {
            // 博客特殊处理 end
                $this->setHeader("HTTP/1.1 404 Not Found");
                $this->_close($connect, $this->_getErrorPage(404, 'File not found'));
                return;
            }
        }
        //文件是否可读
        if (!is_readable($filename)) {
            $this->setHeader("HTTP/1.1 403 Forbidden");
            $this->_close($connect, $this->_getErrorPage(403, 'Forbidden'));
            return;
        }
        //获取文件后缀
        $urlPathInfo = pathinfo($filename);
        $fileExt = isset($urlPathInfo['extension']) ? $urlPathInfo['extension'] : '';
        //访问的路径是否是指定根目录的子目录
        $realFilename = realpath($filename);
        $documentRootRealPath = realpath($documentRoot) . '/';
        if (!$realFilename || !$documentRootRealPath || strpos($realFilename, $documentRootRealPath) !== 0) {
            $this->setHeader("HTTP/1.1 403 Forbidden");
            $this->_close($connect, $this->_getErrorPage(403, 'Forbidden'));
            return;
        }
        //如果请求的是PHP文件
        if ($fileExt === 'php') {
            ob_start();
            try{
                include $realFilename;
            }catch (\Exception $e){
                if ($e->getMessage() != 'meepops_http_end') {
                    Log::write('Exception was introduced to the PHP file.', 'WARNING');
                }
            }
            $content = ob_get_clean();
            $this->_close($connect, $content);
            return;
        }
        //静态文件
        $mimeType = $this->_getMimeTypeByExt($fileExt);
        $fileExt && isset($mimeType) ? $this->setHeader('Content-Type: ' . $mimeType) : $this->setHeader('Content-Type: text/html; charset=utf-8');
        //获取文件更新时间
        $fileMtime = filemtime($filename);
        if($fileMtime){
            $fileMtime = date('D, d M Y H:i:s', $fileMtime) . ' GMT';
            $this->setHeader('Last-Modified: ' . $fileMtime);
            //静态文件未改变.则返回304
            if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $fileMtime === $_SERVER['HTTP_IF_MODIFIED_SINCE']) {
                $this->setHeader('HTTP/1.1 304 Not Modified');
                $this->_close($connect, '');
                return;
            }
        }
        //给客户端发送消息,并且断开连接.
        $this->_close($connect, file_get_contents($realFilename));
        return;
    }

    private function _close($connect, $data){
        $connect->close($data);
        self::$_sessionInstance->write($_SESSION);
        self::$_sessionInstance = null;
    }

    /**
     * 根据文件后缀获取MIME TYPE
     * @param 文件后缀
     * @return string
     */
    private function _getMimeTypeByExt($ext)
    {
        //从nginx1.10.0的mime.types中复制的, 然后转换成数组
        $mimeTypeList = array('html' => 'text/html', 'htm' => 'text/html', 'shtml' => 'text/html', 'css' => 'text/css', 'xml' => 'text/xml', 'gif' => 'image/gif', 'jpeg' => 'image/jpeg', 'jpg' => 'image/jpeg', 'js' => 'application/javascript', 'atom' => 'application/atom+xml', 'rss' => 'application/rss+xml', 'mml' => 'text/mathml', 'txt' => 'text/plain', 'jad' => 'text/vnd.sun.j2me.app-descriptor', 'wml' => 'text/vnd.wap.wml', 'htc' => 'text/x-component', 'png' => 'image/png', 'tif' => 'image/tiff', 'tiff' => 'image/tiff', 'wbmp' => 'image/vnd.wap.wbmp', 'ico' => 'image/x-icon', 'jng' => 'image/x-jng', 'bmp' => 'image/x-ms-bmp', 'svg' => 'image/svg+xml', 'svgz' => 'image/svg+xml', 'webp' => 'image/webp', 'woff' => 'application/font-woff', 'jar' => 'application/java-archive', 'war' => 'application/java-archive', 'ear' => 'application/java-archive', 'json' => 'application/json', 'hqx' => 'application/mac-binhex40', 'doc' => 'application/msword', 'pdf' => 'application/pdf', 'ps' => 'application/postscript', 'eps' => 'application/postscript', 'ai' => 'application/postscript', 'rtf' => 'application/rtf', 'm3u8' => 'application/vnd.apple.mpegurl', 'xls' => 'application/vnd.ms-excel', 'eot' => 'application/vnd.ms-fontobject', 'ppt' => 'application/vnd.ms-powerpoint', 'wmlc' => 'application/vnd.wap.wmlc', 'kml' => 'application/vnd.google-earth.kml+xml', 'kmz' => 'application/vnd.google-earth.kmz', '7z' => 'application/x-7z-compressed', 'cco' => 'application/x-cocoa', 'jardiff' => 'application/x-java-archive-diff', 'jnlp' => 'application/x-java-jnlp-file', 'run' => 'application/x-makeself', 'pl' => 'application/x-perl', 'pm' => 'application/x-perl', 'prc' => 'application/x-pilot', 'pdb' => 'application/x-pilot', 'rar' => 'application/x-rar-compressed', 'rpm' => 'application/x-redhat-package-manager', 'sea' => 'application/x-sea', 'swf' => 'application/x-shockwave-flash', 'sit' => 'application/x-stuffit', 'tcl' => 'application/x-tcl', 'tk' => 'application/x-tcl', 'der' => 'application/x-x509-ca-cert', 'pem' => 'application/x-x509-ca-cert', 'crt' => 'application/x-x509-ca-cert', 'xpi' => 'application/x-xpinstall', 'xhtml' => 'application/xhtml+xml', 'xspf' => 'application/xspf+xml', 'zip' => 'application/zip', 'bin' => 'application/octet-stream', 'exe' => 'application/octet-stream', 'dll' => 'application/octet-stream', 'deb' => 'application/octet-stream', 'dmg' => 'application/octet-stream', 'iso' => 'application/octet-stream', 'img' => 'application/octet-stream', 'msi' => 'application/octet-stream', 'msp' => 'application/octet-stream', 'msm' => 'application/octet-stream', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'mid' => 'audio/midi', 'midi' => 'audio/midi', 'kar' => 'audio/midi', 'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'm4a' => 'audio/x-m4a', 'ra' => 'audio/x-realaudio', '3gpp' => 'video/3gpp', '3gp' => 'video/3gpp', 'ts' => 'video/mp2t', 'mp4' => 'video/mp4', 'mpeg' => 'video/mpeg', 'mpg' => 'video/mpeg', 'mov' => 'video/quicktime', 'webm' => 'video/webm', 'flv' => 'video/x-flv', 'm4v' => 'video/x-m4v', 'mng' => 'video/x-mng', 'asx' => 'video/x-ms-asf', 'asf' => 'video/x-ms-asf', 'wmv' => 'video/x-ms-wmv', 'avi' => 'video/x-msvideo');
        return isset($mimeTypeList[$ext]) ? $mimeTypeList[$ext] : '';
    }

    /**
     * 设置HTTP错误页
     * @param $httpCode int HTTP状态码
     * @param $description string 该状态码时, 错误页的描述或自定义的错误页面路径
     */
    public function setErrorPage($httpCode, $description)
    {
        $this->_errorPage[$httpCode] = $description;
    }

    /**
     * 获取错误页面
     * @param $httpCode
     * @param string $message
     * @param string $description
     * @return bool|string
     */
    private function _getErrorPage($httpCode, $message = '', $description = '')
    {
        if (!$httpCode) {
            return false;
        }
        if (!isset($this->_errorPage[$httpCode])) {
            $httpCodeArray = \MeepoPS\Core\ApplicationProtocol\Http::getHttpCode();
            $message = $message ? $message : '';
            $description = $description ? $description : (isset($httpCodeArray[$httpCode]) ? $httpCodeArray[$httpCode] : '');
            $display = '<html><head><title>%s %s</title></head><body><center><h3>%s %s</h3><br>%s</center></body></html>';
            $display = sprintf($display, $httpCode, $message, $httpCode, $message, $description);
        } else {
            //如果是文件
            if (file_exists($this->_errorPage[$httpCode])) {
                ob_start();
                include $this->_errorPage[$httpCode];
                $display = ob_get_clean();
            } else {
                $display = '<html><head><title>%s</title></head><body><center><h3>%s</h3><br>%s</center></body></html>';
                $display = sprintf($display, $httpCode, $httpCode, $this->_errorPage[$httpCode]);
            }
        }
        return $display;
    }
}