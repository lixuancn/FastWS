<?php
/**
 * Created by Lane
 * User: lane
 * Date: 16/3/17
 * Time: 下午2:54
 * E-mail: lixuan868686@163.com
 * WebSite: http://www.lanecn.com
 */

namespace MeepoPS\Core;

class SplAutoload
{

    const NAMESPACE_PREFIX = 'MeepoPS\\';

    /**
     * 向PHP注册在自动载入函数
     */
    public static function register()
    {
        spl_autoload_register(array(new self, 'autoload'));
    }

    /**
     * 根据类名载入所在文件
     */
    public static function autoload($className)
    {
        $namespacePrefixStrlen = strlen(self::NAMESPACE_PREFIX);
        if (strncmp(self::NAMESPACE_PREFIX, $className, $namespacePrefixStrlen) === 0) {
            $filePath = str_replace('\\', DIRECTORY_SEPARATOR, substr($className, $namespacePrefixStrlen));
            $realpath = realpath(MEEPO_PS_ROOT_PATH . (empty($filePath) ? '' : DIRECTORY_SEPARATOR) . $filePath . '.php');
            if (file_exists($realpath)) {
                require_once $realpath;
            } else {
                die('File Not Exists. filePath: ' . $filePath . ', realPath: ' . $realpath . ' ,class:' . $className . "\n");
            }
        }
    }
}

SplAutoload::register();
