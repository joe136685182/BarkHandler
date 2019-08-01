<?php
require_once("settings.php");
require_once("tool_func.php");

/**
 * 日志模块，根据 log.xml 配置文件进行初始化，并根据配置将日志写入对应文件中
 */
class Logs
{
    static $_settings = array();

    /**
     * 根据参数配置，将日志写入对应文件
     * @param string $config 配置内容
     * @param string $msg 要写入的文本
     * @return 无返回值
     */
    static function log_by_config($config, $msg)
    {
        if (!$config || $config["Mode"] != "1") {
            print("log_by_config: Config not log [" . gettype($config) . "].\n");
            return;
        }
        log_file($config["LogFile"], $config["LevelTag"], $msg);
    }

    /**
     * 初始化日志模块
     * @param string $sectionName 调用该函数的项目名，与 log.xml 中的第二层名称对应
     * @return 无返回值
     */
    static function init($sectionName)
    {
        try {
            $settings = new Settings_XML;
            $path = __DIR__ . "/log.xml";
            if (!$settings->load($path)) {
                throw new Exception("Load XML error!\n");
            }
            Logs::$_settings = $settings->get($sectionName);
            if (!Logs::$_settings) {
                throw new Exception("Find section name failed!\n");
            }
            return true;
        } catch (Exception $e) {
            echo $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * 根据开关参数的配置，将日志按 Info 级别写入对应日志文件
     * @param string $msg 要写入日志文件的日志文本
     * @return 无返回值
     */
    static function info($msg)
    {
        $config = Logs::$_settings["Info"];
        Logs::log_by_config($config, $msg);
    }

    /**
     * 根据开关参数的配置，将日志按 Debug 级别写入对应日志文件
     * @param string $msg 要写入日志文件的日志文本
     * @return 无返回值
     */
    static function debug($msg)
    {
        $config = Logs::$_settings["Debug"];
        Logs::log_by_config($config, $msg);
    }

    /**
     * 根据开关参数的配置，将日志按 Error 级别写入对应日志文件
     * @param string $msg 要写入日志文件的日志文本
     * @return 无返回值
     */
    static function error($msg)
    {
        $config = Logs::$_settings["Error"];
        Logs::log_by_config($config, $msg);
    }
}

?>