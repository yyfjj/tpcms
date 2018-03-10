<?php
/**
 * 
 * Empty (空模块)
 *
 * @package      	YOURPHP
 * @author          liuxun QQ:147613338 <admin@yourphp.cn>
 * @copyright     	Copyright (c) 2008-2011  (http://www.yourphp.cn)
 * @license         http://www.yourphp.cn/license.txt
 * @version        	YourPHP企业网站管理系统 v2.1 2012-10-08 yourphp.cn $
 */
// if(!defined("Yourphp")) exit("Access Denied");
// class EmptyAction extends Action
// {	
// 	public function _empty()
// 	{
// 		R('Admin/Content/'.ACTION_NAME);
// 	}
// }


namespace Module\Controller;

use Common\Controller\AdminbaseController;

class EmptyController extends AdminbaseController {
	
	public function _empty()
	{
// 		$r =get_defined_constants();
// 		dump($r);
// 		die("=====");
		$r = 'Admin/Model/'.ACTION_NAME;
		$r = 'Module/List/'.ACTION_NAME;
// 		echo $r;
// 		die();
// 		R($r,array($_GET));
// 		echo http_build_query ( $_GET );die();
		R($r);
	}
	
	/* public function index(){
		
		$mysql= M()->query("select VERSION() as version");
		$mysql=$mysql[0]['version'];
		$mysql=empty($mysql)?L('UNKNOWN'):$mysql;
		
		//server infomaions
		$info = array(
				L('OPERATING_SYSTEM') => PHP_OS,
				L('OPERATING_ENVIRONMENT') => $_SERVER["SERVER_SOFTWARE"],
				L('PHP_VERSION') => PHP_VERSION,
				L('PHP_RUN_MODE') => php_sapi_name(),
				L('PHP_VERSION') => phpversion(),
				L('MYSQL_VERSION') =>$mysql,
				L('PROGRAM_VERSION') => THINKCMF_VERSION . "&nbsp;&nbsp;&nbsp; [<a href='http://www.thinkcmf.com' target='_blank'>ThinkCMF</a>]",
				L('UPLOAD_MAX_FILESIZE') => ini_get('upload_max_filesize'),
				L('MAX_EXECUTION_TIME') => ini_get('max_execution_time') . "s",
				L('DISK_FREE_SPACE') => round((@disk_free_space(".") / (1024 * 1024)), 2) . 'M',
		);
		$this->assign('server_info', $info);
		$this->display();
	} */
}
?>