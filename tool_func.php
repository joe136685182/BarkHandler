<?php

// 判断字符串参数1是否以字符串参数2开头
function starts_with($haystack, $needle) {
     $length = strlen($needle);
     return (substr($haystack, 0, $length) === $needle);
}

// 判断字符串参数1是否以字符串参数2结尾
function ends_with($haystack, $needle) {
    $length = strlen($needle);
    if ($length == 0) {
        return false;
    }

    return (substr($haystack, -$length) === $needle);
}

// 根据传入的参数，获取到数据库的连接  // 使用后需手动关闭连接
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
    

// 将时间戳转换为格式化日期  // 精确到毫秒，小数点后三位代表毫秒
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

// 将格式化日期转换为时间戳，精确到毫秒
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

// 将多维数组转化为字符串以便打印
function array_to_string($array, $layout=0) {
    $strArray = "";
    foreach ($array as $key => $value) {
        // echo "Key=".$key." Value=".$value."\n";
        $strArray = $strArray.str_repeat("  ", $layout)."[".$key."] => ";
        if (is_array($value)) {
            $strArray = $strArray."[\n".array_to_string($value, $layout+1)."]\n";
        } else {
            $strArray = $strArray.$value."\n";
        }
    }	
    return $strArray;
}

?>