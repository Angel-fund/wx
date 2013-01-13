<?php
include("wx_lib.php");
define("TOKEN", "angel");
$wechatObj = new wechatCallbackapiTest(TOKEN,8);

/*$fromUsername = 'baby';
echo $wechatObj->contentStr($fromUsername,'+0');

$last_id    = $wechatObj->get_a_value('username', 'name', array('id'=>1));
echo $last_id;exit;*/

$wechatObj->valid();
?>