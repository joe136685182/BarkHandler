<?php
require_once("settings.php");
require_once("tool_func.php");

class Logs {
    static $_settings = array();

    static function log_by_config($config, $msg) {
        if ($config["Mode"] != "1") {
            print("log_by_config: Config not log [".gettype($config)."].\n");
            return;
        }
        log_file($config["LogFile"], $config["LevelTag"], $msg);
    }

    static function init($sectionName) {
        try {
            $settings = new Settings_XML;
            $path = __DIR__."/log.xml";
            if (!$settings->load($path)) {
                throw new Exception("Load XML error!\n");
            }
            Logs::$_settings = $settings->get($sectionName);
            if (!Logs::$_settings) {
                throw new Exception("Find section name failed!\n");
            }
            return true;
        } catch (Exception $e) {
            echo $e->getMessage()."\n";
            return false;
        }
    }

    // 根据开关参数的配置，按格式写入日志文件
    static function info($msg) {
        $config = Logs::$_settings["Info"];
        Logs::log_by_config($config, $msg);
    }

    static function debug($msg) {
        $config = Logs::$_settings["Debug"];
        Logs::log_by_config($config, $msg);
    }

    static function error($msg) {
        $config = Logs::$_settings["Error"];
        Logs::log_by_config($config, $msg);
    }
}

?>