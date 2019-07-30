<?php

class ServerConf
{
    static $Conf = array();

    static function init()
    {
        try {
            $settings = new Settings_INI;
            $path = __DIR__ . "/config.ini";
            if (!$settings->load($path)) {
                throw new Exception("Load INI error!\n");
            }
            ServerConf::$Conf = $settings;
            return true;
        } catch (Exception $e) {
            echo $e->getMessage() . "\n";
            return false;
        }
    }
}

require_once("ToolFunc/tool_func.php");
require_once("ToolFunc/logs.php");

// 初始化日志服务
if (!Logs::init("BarkServer")) {
    throw new Exception("Init log module failed!\n");
}

// 加载配置文件
if (!ServerConf::init()) {
    throw new Exception("Init config module failed!\n");
}

// 处理微信消息，转换为短信格式  // TODO: 过滤掉一些无用的信息，如微信提示
function pre_process_json(&$jsonObj)
{
    if ($jsonObj->smsrn == "" && $jsonObj->smsrf == "" && starts_with($jsonObj->smsrb, "【微信】")) {  // 来自微信的消息
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
function add_to_database($jsonObj)
{
    $sqlConfig = ServerConf::$Conf->get("BarkSqlConfig");
    $testMsgSender = explode(",", ServerConf::$Conf->get("BarkServerConfig.TestMsgSender"));

    $conn = get_mysql_conn($sqlConfig["Host"], $sqlConfig["Username"], $sqlConfig["Password"], $sqlConfig["DBname"]);
    if ($conn) {
        $tableName = "";
        if (in_array($jsonObj->smsrf, $testMsgSender)) {  // 测试消息写入测试库
            Logs::debug("收到测试消息，写入测试库！[" . $jsonObj->smsrf . "]");
            $tableName = $sqlConfig["TBNameT"];
        } else {

            $tableName = $sqlConfig["TBName"];
        }

        $sql = "INSERT INTO " . $tableName . " (smsrn, smsrf, smsrc, smsrk, smsrb, smsrt) VALUES (\"" . $jsonObj->smsrn . "\", \"" . $jsonObj->smsrf . "\", \"" . $jsonObj->smsrc . "\", \"" . $jsonObj->smsrk . "\", \"" . $jsonObj->smsrb . "\", \"" . $jsonObj->smsrt . "\")";
        Logs::debug("Insert SQL: " . $sql);
        if ($conn->query($sql) === TRUE) {
            Logs::debug("新记录插入成功!");
        } else {
            Logs::error("Query failed! SQL: " . $sql . " ErrorMsg: " . $conn->error);
        }

        $conn->close();
    }
}

// 判断是否有验证码
function has_captcha($msg)
{
    $captchaKeyword = explode(",", ServerConf::$Conf->get("BarkServerConfig.CaptchaKeyWord"));

    for ($wordIdx = 0; $wordIdx < count($captchaKeyword); $wordIdx++) {
        $pattern = "/" . $captchaKeyword[$wordIdx] . "/";
        Logs::debug("CaptchaKeyword[" . $wordIdx . "] = " . $pattern);
        if (preg_match($pattern, $msg) > 0) {
            Logs::debug("Find keyword![" . $wordIdx . "][" . $captchaKeyword[$wordIdx] . "]");
            return true;
        } else {
            Logs::debug("Didn't find in round " . ($wordIdx + 1));
        }
    }
    Logs::debug("Didn't find keyword.");
    return false;
}

// 解析验证码，支持4-8位数字
function get_captcha($msg)
{
    Logs::debug("get_captcha(" . $msg . ")");

    $patternBegin = "/(?<!\d)\d{";
    $patternEnd = "}(?!\d)/";
    $matches = array();

    for ($captchaIdx = 4; $captchaIdx <= 8; $captchaIdx++) {
        $pattern = $patternBegin . $captchaIdx . $patternEnd;
        if (preg_match($pattern, $msg, $matches) > 0) {
            Logs::debug("Find captcha![" . $captchaIdx . "][" . $matches[0] . "]");
            return $matches[0];
        }
    }
    Logs::error("Could not find captcha![" . $msg . "]");
    return "";
}

// 获取 Bark 推送需要的参数
function get_post_data($reqObj, &$reqData, $hasCaptcha = false, $captcha = "")
{
    Logs::debug("[get_msg_json()] hasCaptcha: " . $hasCaptcha . " captcha: " . $captcha);
    $msg = "收件人: " . $reqObj->smsrk . "\n消息内容: " . $reqObj->smsrb . "\n时间: " . $reqObj->smsrt;

    if ($reqObj->smsrn == "") {
        if ($reqObj->smsrf != "") {
            $reqData["title"] = $reqObj->smsrf;  // 若无发件人名称且有发件人号码，则标题为发件人号码
        }  // 若无发件人名称及号码，则无标题
    } else {
        $reqData["title"] = $reqObj->smsrn;  // 若有发件人名称，则标题为发件人号码
        if ($reqObj->smsrf != "") {  // 若有发件人号码，则在正文中插入该项内容
            $msg = "发件人号码: " . $reqObj->smsrf . "\n" . $msg;
        }
    }
    $reqData["body"] = $msg;

    if ($hasCaptcha && $captcha != "") {
        $reqData["automaticallyCopy"] = 1;
        $reqData["copy"] = $captcha;
        $reqData["title"] = "验证码 " . $captcha;
    }
}

// 发送 POST 请求
function send_post($url, $postData)
{
    $postData = http_build_query($postData);
    $options = array(
        'http' => array(
            'method' => 'POST',
            'header' => 'Content-type:application/x-www-form-urlencoded',
            'content' => $postData,
            'timeout' => 60 // 超时时间（单位:s）
        )
    );
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    return $result;
}

$clientJson = file_get_contents('php://input');
Logs::info("Request json = [" . $clientJson . "]");
$clientObj = json_decode($clientJson);

pre_process_json($clientObj);
add_to_database($clientObj);

$req_data = array();
if ($clientObj->smsrn != "微信消息" && has_captcha($clientObj->smsrb) == true) {
    get_post_data($clientObj, $req_data, true, get_captcha($clientObj->smsrb));
} else {
    get_post_data($clientObj, $req_data);
}

if (ServerConf::$Conf->get("BarkServerConfig.SendNotification") == "1") {
    $url = ServerConf::$Conf->get("BarkServerConfig.ServerUrl") . "/" . ServerConf::$Conf->get("BarkServerConfig.DeviceKey") . "/";
    $ret = send_post($url, $req_data);
    Logs::info("Sent. Ret: [".$ret."]");
}
Logs::debug("====================");
Logs::info("====================");

?>