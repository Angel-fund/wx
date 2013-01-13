<?php
session_start();
include("wx_lib.php");

$wechatObj = new wechatCallbackapiTest("angel");
$wechatObj->valid();
?>