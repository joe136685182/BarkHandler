<?php

require_once("tool_func.php");
// 加载配置文件
$MainConfig = parse_ini_file("config.ini", true);

$ServerConfig = $MainConfig["BarkServerConfig"];  // 控制服务端的某些配置
$SqlConfig = $MainConfig["BarkSqlConfig"];  // 控制数据库相关的配置
$LogConfig = $MainConfig["BarkLogConfig"];  // 控制日志相关的配置

$CaptchaKeyword = explode(",", $ServerConfig["CaptchaKeyWord"]);
$TestMsgSender = explode(",", $ServerConfig["TestMsgSender"]);
$SendNotification = $ServerConfig["SendNotification"];

// 根据开关参数的配置，按格式写入日志文件
function log_info($msg) {
    global $LogConfig;
    if ($LogConfig["InfoMode"] != "1") {
        return;
    }
    log_file($LogConfig["InfoLogFile"], "INFO", $msg);
}

function log_debug($msg) {
    global $LogConfig;
    if ($LogConfig["DebugMode"] != "1") {
        return;
    }
    log_file($LogConfig["DebugLogFile"], "DEBUG", $msg);
}

function log_error($msg) {
    global $LogConfig;
    if ($LogConfig["ErrorMode"] != "1") {
        return;
    }
    log_file($LogConfig["ErrorLogFile"], "Error", $msg);
}

// =========================================

// 处理微信消息，转换为短信格式
function pre_process_json(&$jsonObj) {
    log_debug("pre_process_json");
    
    if ($jsonObj->smsrn == "" && $jsonObj->smsrf == "" && starts_with($jsonObj->smsrb, "【微信】")) {
        // 来自微信的消息
        $totalMsg = mb_substr($jsonObj->smsrb, mb_strlen("【微信】", 'utf8'));  // 已经去掉了"【微信】"
        $pos_1 = mb_strpos($totalMsg, ":");  // 找到第一个":"，定位发件人和消息的分界线
        $sender = mb_substr($totalMsg, 0, $pos_1);
        $msg = mb_substr($totalMsg, ($pos_1 + 2), (mb_strlen($totalMsg) - $pos_1 - 2));
        
        $jsonObj->smsrn = "微信消息";
        $jsonObj->smsrf = $sender;
        $jsonObj->smsrb = $msg;
    }
}

// 保存到数据库
function add_to_database($jsonObj) {
    global $TestMsgSender, $SqlConfig;
    log_debug("add_to_database() => ".array_to_string($TestMsgSender));
    $conn = get_mysql_conn($SqlConfig["Host"], $SqlConfig["Username"], $SqlConfig["Password"], $SqlConfig["DBname"]);
    if ($conn) {
        $tableName = "";
        if (in_array($jsonObj->smsrf, $TestMsgSender)) {  // 测试消息写入测试库
            log_debug("收到测试消息，写入测试库！[".$jsonObj->smsrf."]");
            $tableName = $SqlConfig["TBNameT"];
        } else {

            $tableName = $SqlConfig["TBName"];
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

// 判断是否有验证码
function has_captcha($msg) {
    log_debug("has_captcha(".$msg.")");
    global $CaptchaKeyword;
    for ($wordIdx = 0; $wordIdx < count($CaptchaKeyword); $wordIdx++) {
        $pattern = "/".$CaptchaKeyword[$wordIdx]."/";
        log_debug("[".$wordIdx."]CaptchaKeyword[".$wordIdx."] = ".$pattern);
        if (preg_match($pattern, $msg) > 0) {
            log_debug("Find keyword![".$wordIdx."][".$CaptchaKeyword[$wordIdx]."]");
            return true;
        } else {
            log_debug("Didn't find in round ".($wordIdx + 1));
        }
    }
    log_debug("Didn't find keyword.");
    return false;
}

// 解析验证码，支持4-8位数字
function get_captcha($msg) {
    log_debug("get_captcha(".$msg.")");

    $patternBegin = "/(?<!\d)\d{";
    $patternEnd = "}(?!\d)/";
    $matches = array();
    
    for ($captchaIdx = 4; $captchaIdx <= 8; $captchaIdx++) {
        $pattern = $patternBegin . $captchaIdx . $patternEnd;
        if (preg_match($pattern, $msg, $matches) > 0) {
            log_debug("Find captcha![".$captchaIdx."][".$matches[0]."]");
            return $matches[0];
        }
    }
    log_error("Could not find captcha![".$msg."]");
    return "";
}

// 获取 Bark 推送需要的参数字符串
function get_msg_json($reqObj, $hasCaptcha=false, $captcha="") {
    log_debug("[get_msg_json()] hasCaptcha: ".$hasCaptcha." captcha: ".$captcha);
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

    if ($hasCaptcha && $captcha != "") {
        $retObj->automaticallyCopy = 1;
        $retObj->copy = $captcha;
        $retObj->title = "验证码 ".$captcha;
    }

    return base64_encode(json_encode($retObj));
}

$clientJson = file_get_contents('php://input');
log_debug("Request json = [".$clientJson."]");
$clientObj = json_decode($clientJson);

pre_process_json($clientObj);
add_to_database($clientObj);

// 用 base64 编码发送，避免引号传参问题
$data_base64 = "";
if ($clientObj->smsrn != "微信消息" && has_captcha($clientObj->smsrb) == true) {
    $data_base64 = get_msg_json($clientObj, true, get_captcha($clientObj->smsrb));
} else {
    $data_base64 = get_msg_json($clientObj);
}

$command = "python bark_push_json.py ".$data_base64." >> ".$LogConfig["PyDebugLogFile"];
log_info("Py command: ".$command);

if ($SendNotification == "1") {
    exec($command);
}

// 测试 config.ini 文件
// $config = array_to_string($MainConfig);
// log_debug("加载了如下配置文件:\n".$config);

log_debug("====================");
log_info("====================");
?>