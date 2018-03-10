<?php
/**
 * @desc: wangge api接口
 * @author: benjamin
 * @date: 2017年5月5日
 */
namespace Api\Controller;

use Common\Controller\ApibaseController;

class DemoController extends ApibaseController{
	
	
	/**
	* @desc 新闻中心分类
	* @access 
	* @param unknowtype
	* @return 
	* @example http://thinkcmfxapi.tao3w.com/index.php?g=Api&m=Demo&a=demo
	* @date 2017年5月5日
	* @author benjamin
	*/
	public function demo()
	{
		#第一步，数据校验
		$this->__rulesArr =  array(
				#这里写一些无参数据的校验，比如登录等等
// 				'' => array('required' => array(306009),'IsLogin' => array(306010,"请登录后提交编辑信息"),'IsPost'=>array(306011,'请post数据')),
				#下面是有参(post或者get)
// 				'title'=>array('required'=>array(306012)),
// 				'video_url'=>array('required'=>array(306013)),
// 				'id'=>array("VideoById"=>array(306014)),
		);
		
		#第二步，逻辑处理
		$listArr = array('name'=>'birdman','age'=>23);
		$newsArr = M("news")->find();//单条记录
// 		$newsArr = M("news")->select();//多条记录
		$newsObj = D("News");
// 		$newsObj->addJoinObj(new \Common\Model\NewsUsers());
		$newsObj->addJoinObj(new \Common\Model\UsersNews());
// 		$newsObj->addJoinObj(new \Common\Model\NewsUsers(),'filter');//关联同时，对Users表进行数据重构
// 		$newsObj->addFilterObj(new \Common\Model\DemoApiOutFilter());
		$listsArr = $newsObj->setLists($newsArr)->join()->filter()->getLists();
		
		#第三步，输出
		$this->__listArr = $listsArr; 
	}
}

