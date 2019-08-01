<?php

require_once("ToolFunc/tool_func.php");
require_once("ToolFunc/logs.php");

// 初始化日志服务
if (!Logs::init("BarkServer")) {
    throw new Exception("Init log module failed!\n");
}

// 加载配置文件
$path = __DIR__ . "/config.ini";
if (!ServerConf::init($path)) {
    throw new Exception("Init config module failed!\n");
}

// 加载部分配置
$TestMsgSender = explode(",", ServerConf::$Conf->get("BarkServerConfig.TestMsgSender"));
$WechatIgnore = explode(",", ServerConf::$Conf->get("BarkServerConfig.WechatIgnore"));
for ($idx = 0; $idx < count($WechatIgnore); $idx++) {
    $keyWord_1 = str_replace("·", ",", $WechatIgnore[$idx]);  // 替换回","
    $keyWord_1 = str_replace("【", "(", $keyWord_1);  // 替换回"("
    $keyWord_1 = str_replace("】", ")", $keyWord_1);  // 替换回")"
    $WechatIgnore[$idx] = $keyWord_1;
}

/**
 * 处理微信消息，过滤掉一些无用的信息，如微信系统提示，并转换为短信格式
 * @param object &$jsonObj 未处理的客户端消息对象
 * @return 无返回值
 */
function pre_process_json(&$jsonObj)
{
    if ($jsonObj->smsrn == "" && $jsonObj->smsrf == "" && starts_with($jsonObj->smsrb, "【微信】")) {  // 来自微信的消息
        $totalMsg = mb_substr($jsonObj->smsrb, mb_strlen("【微信】", 'utf8'));  // 截掉"【微信】"

        // 如果找不到目标字符(串)，则mb_strpos()会返回空字符串
        $pos_1 = mb_strpos($totalMsg, "：");  // 微信系统消息的发件人和消息内容分隔符是全角冒号
        $pos_2 = mb_strpos($totalMsg, ":");  // 普通消息的消息分隔符是半角冒号

        $cord_1 = $pos_1 != "" && $pos_2 != "" && $pos_1 < $pos_2;  // 两种冒号都有且全角在半角前
        $cord_2 = $pos_1 != "" && $pos_2 == "";  // 只有全角冒号

        if ($cord_1 || $cord_2) {  // 系统消息
            $sender = mb_substr($totalMsg, 0, $pos_1);
            $msg = mb_substr($totalMsg, ($pos_1 + 1), (mb_strlen($totalMsg) - $pos_1 - 1));
        } else {
            $sender = mb_substr($totalMsg, 0, $pos_2);
            $msg = mb_substr($totalMsg, ($pos_2 + 2), (mb_strlen($totalMsg) - $pos_2 - 2));
        }
        $jsonObj->smsrn = "微信消息";
        $jsonObj->smsrf = $sender;
        $jsonObj->smsrb = $msg;
    }
}

/**
 * 判断是否为屏蔽的微信系统信息
 * @param object $jsonObj 经过 pre_process_json() 函数预处理的客户端消息对象
 * @return 无返回值
 */
function is_wechat_ignore($jsonObj)
{
    global $WechatIgnore;
    Logs::debug("<wechat_ignore> name[" . $jsonObj->smsrn . "] sender[" . $jsonObj->smsrf . "] message[" . $jsonObj->smsrb . "]");

    if ($jsonObj->smsrn == "微信消息" && $jsonObj->smsrf == "微信") {  // 经过 pre_process_json() 处理后的微信系统消息特征
        if (in_array($jsonObj->smsrb, $WechatIgnore)) {
            return true;
        }
    }
    return false;
}

// 保存到数据库
function add_to_database($jsonObj)
{
    $sqlConfig = ServerConf::$Conf->get("BarkSqlConfig");
    global $TestMsgSender;

    $conn = get_mysql_conn($sqlConfig["Host"], $sqlConfig["Username"], $sqlConfig["Password"], $sqlConfig["DBname"]);
    if ($conn) {
        $tableName = "";
        if (in_array($jsonObj->smsrf, $TestMsgSender)) {  // 测试消息写入测试库
            Logs::debug("收到测试消息，写入测试库！[" . $jsonObj->smsrf . "]");
            $tableName = $sqlConfig["TBNameT"];
        } else {

            $tableName = $sqlConfig["TBName"];
        }

        $sql = "INSERT INTO " . $tableName . " (smsrn, smsrf, smsrc, smsrk, smsrb, smsrt) VALUES (\"" . $jsonObj->smsrn . "\", \"" . $jsonObj->smsrf . "\", \"" . $jsonObj->smsrc . "\", \"" . $jsonObj->smsrk . "\", \"" . $jsonObj->smsrb . "\", \"" . $jsonObj->smsrt . "\")";
        Logs::debug("<add_to_database> sql[" . $sql . "]");
        if ($conn->query($sql) === TRUE) {
            Logs::debug("<add_to_database> 新记录插入成功!");
        } else {
            Logs::error("<add_to_database> Query failed! sql[" . $sql . "] ErrorMsg[" . $conn->error . "]");
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

    for ($captchaIdx = 8; $captchaIdx >= 4; $captchaIdx--) {
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
    Logs::debug("<get_post_data> hasCaptcha[" . $hasCaptcha . "] captcha[" . $captcha . "]");
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

function main()
{
    $clientJson = file_get_contents('php://input');
    Logs::info("Request json = [" . $clientJson . "]");
    if ($clientJson == "") {
        Logs::debug("<main()> Empty request.");
        return;
    }
    $clientObj = json_decode($clientJson);

    pre_process_json($clientObj);
    if (is_wechat_ignore($clientObj)) {
        Logs::debug("<main> Receive wechat system msg. msg[" . $clientObj->smsrb . "]");
    } else {
        Logs::debug("<main> Receive normal msg. msg[" . $clientObj->smsrb . "]");
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
            Logs::info("Sent. Ret: [" . $ret . "]");
        } else {
            Logs::debug("Not send as config.");
        }
    }
}

main();
Logs::debug("====================");
Logs::info("====================");
?>
