<?php

require_once("tool_func.php");
$LogPath = "./logs/php_bark_handler.log";
$InfoMode = 1;
$DebugMode = 1;

function log_info($msg) {
    if ($GLOBALS["InfoMode"] != "1") {
        return;
    }
    log_file($GLOBALS["LogPath"], "INFO", $msg);
}

function log_debug($msg) {
    if ($GLOBALS["DebugMode"] != "1") {
        return;
    }
    log_file($GLOBALS["LogPath"], "DEBUG", $msg);
}

function log_error($msg) {
    log_file($GLOBALS["LogPath"], "ERROR", $msg);
}

// =========================================

// TODO // 处理微信消息，转换为短信格式
function pre_process_json(&$jsonObj) {
    print("pre_process_json\n");
    // for($index = 1; $index < strlen($json); $index++) {
    //     if 
    // }
    
    if ($jsonObj->smsrn == "" && $jsonObj->smsrf == "" && starts_with($jsonObj->smsrb, "【微信】")) {
        // 来自微信的消息
        $totalMsg = mb_substr($jsonObj->smsrb, mb_strlen("【微信】", 'utf8'));  // 已经去掉了"【微信】"
        $pos_1 = mb_strpos($totalMsg, ":");  // 找到第一个":"，定位发件人和消息的分界线
        $sender = mb_substr($totalMsg, 0, $pos_1);
        $msg = mb_substr($totalMsg, ($pos_1 + 2), (mb_strlen($totalMsg) - $pos_1 - 2));
        // print("[".$sender."][".$msg."]\n");
        $jsonObj->smsrn = "微信消息";
        $jsonObj->smsrf = $sender;
        $jsonObj->smsrb = $msg;
    }
    // print($jsonObj->smsrn." ".$jsonObj->smsrf." ".$jsonObj->smsrb."\n");
}

// 保存到数据库
function add_to_database($jsonObj) {
    $conn = get_mysql_conn("localhost", "barkadmin", "k0f8bic", "BarkMessage");
    if ($conn) {
        $tableName = "MsmMsg";
        if ($jsonObj->smsrf == "9999" || $jsonObj->smsrf == "双卡助手") {  // 测试消息写入测试库
            log_debug("收到测试消息，写入测试库！[".$jsonObj->smsrf."]");
            $tableName = "MsmMsg_1";
        }
        
        $sql = "INSERT INTO ".$tableName." (smsrn, smsrf, smsrc, smsrk, smsrb, smsrt) VALUES (\"".$jsonObj->smsrn."\", \"".$jsonObj->smsrf."\", \"".$jsonObj->smsrc."\", \"".$jsonObj->smsrk."\", \"".$jsonObj->smsrb."\", \"".$jsonObj->smsrt."\")";
        log_debug("Insert SQL: ".$sql);
        if ($conn->query($sql) === TRUE) {
            log_debug("新记录插入成功!");
        } else {
            log_error("Query failed! SQL: ".$sql." ErrorMsg: ".$conn->error);
        }

        $conn->close();
    }
}

// TODO // 判断是否有验证码
function has_captcha($msg) {
    $has_captcha = false;


    return $has_captcha;
}

// TODO // 解析验证码，支持4-8位数字(todo)
function get_captcha($msg) {
    $captcha = "012345";
    
    return $captcha;
}


function get_msg_json($reqObj, $hasCaptcha=false, $captcha="") {
    $retObj = new StdClass();
    $msg = "收件人: ".$reqObj->smsrk."\n消息内容: ".$reqObj->smsrb."\n时间: ".$reqObj->smsrt;

    if ($reqObj->smsrn == "") {
        if ($reqObj->smsrf != "") {
            $retObj->title = $reqObj->smsrf;  // 若无发件人名称且有发件人号码，则标题为发件人号码
        }  // 若无发件人名称及号码，则无标题
    } else {
        $retObj->title = $reqObj->smsrn;  // 若有发件人名称，则标题为发件人号码
        if ($reqObj->smsrf != "") {  // 若有发件人号码，则在正文中插入该项内容
            $msg = "发件人号码: ".$reqObj->smsrf."\n".$msg;
        }
    }
    $retObj->body = $msg;

    if ($hasCaptcha && $code != "") {
        $retObj->automaticallyCopy = 1;
        $retObj->copy = $captcha;
    }

    // var_dump($retObj);
    return base64_encode(json_encode($retObj));
}

$clientJson = file_get_contents('php://input');
$clientObj = json_decode($clientJson);

pre_process_json($clientObj);
add_to_database($clientObj);

$data_base64 = "";
if (has_captcha($clientObj->smsrb) == true) {
    $data_base64 = get_msg_json($clientObj, true, get_captcha($clientObj->smsrb));
} else {
    $data_base64 = get_msg_json($clientObj);
}

$command = "python bark_push_json.py ".$data_base64." >> ./logs/python_log.log";
log_info("Py command: ".$command);
exec($command);

  
?>