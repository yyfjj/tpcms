<?php
#推送环境
if (!defined("PRODUCTION"))
{
	define('PRODUCTION',false);//false开发环境true生产环境
}

#jpush aiyou
if (!defined("JPUSH_APPKEY"))
{
	define("JPUSH_APPKEY",'f42260d6ed3ef520e2350da7');
}

if (!defined("JPUSH_MASTERSECRET"))
{
	define("JPUSH_MASTERSECRET",'d2ffeb24142b55874217d3ba');
}