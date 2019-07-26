<?php

function starts_with($haystack, $needle) {
     $length = strlen($needle);
     return (substr($haystack, 0, $length) === $needle);
}

function ends_with($haystack, $needle) {
    $length = strlen($needle);
    if ($length == 0) {
        return false;
    }

    return (substr($haystack, -$length) === $needle);
}

function get_mysql_conn($host, $uname, $pwd, $dbname) {
    try{
        //对mysqli类进行实例化
        $mysqli = new mysqli($host, $uname, $pwd, $dbname);
        if(mysqli_connect_errno()){    //判断是否成功连接上MySQL数据库
            throw new Exception("数据库连接错误！\n");  //如果连接错误，则抛出异常
        }else{
            echo "数据库连接成功！\n";   //打印连接成功的提示
            return $mysqli;
        }
    }catch (Exception $e){        //捕获异常
        echo $e->getMessage()."\n";    //打印异常信息
        return null;
    }
}



//返回当前的毫秒时间戳
function get_msectime() {
    list($msec, $sec) = explode(" ", microtime());
    $msectime =  (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
    return $msectime;
}
    
/** 
 *时间戳 转   日期格式 ： 精确到毫秒，x代表毫秒
*/
function get_microtime_format($time)
{
    if(strstr($time,'.')) {
        sprintf("%01.3f", $time); //小数点。不足三位补0
        list($usec, $sec) = explode(".", $time);
        $sec = str_pad($sec,3, "0", STR_PAD_RIGHT); //不足3位。右边补0
    } else {
        $usec = $time;
        $sec = "000"; 
    }
    $date = date("Y-m-d H:i:s.x", $usec);
    return str_replace('x', $sec, $date);
}

/** 时间日期转时间戳格式，精确到毫秒，
 *     
*/
function get_data_format($time)
{
    list($usec, $sec) = explode(".", $time);
    $date = strtotime($usec);
    $return_data = str_pad($date.$sec, 13, "0", STR_PAD_RIGHT); //不足13位。右边补0
    return $return_data;
}

// 按格式记录日志到文件
function log_file($path, $level, $msg) {
    $file = fopen($path, "a") or exit("Unable to open file! [".$path."]\n");
    $msecTime = get_msectime();
    $formatTime = get_microtime_format($msecTime * 0.001);
    $logMsg = "[".$formatTime."][".$level."] ".$msg."\n";
    fwrite($file, $logMsg);
    fclose($file);
    return;
}

?>