<?php
// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: huajie <banhuajie@163.com>
// +----------------------------------------------------------------------


namespace Module\Controller;

use Common\Controller\AdminbaseController;


/**
 * 模型管理控制器
 * @author huajie <banhuajie@163.com>
 */
class ModuleBaseController extends AdminbaseController{
	function __construct(){
		parent::__construct();
		
// 		echo M(CONTROLLER_NAME);die();
		
		$modelArr = M("model")->where("name='".CONTROLLER_NAME."'")->find();
		if($modelArr){
			$attributeArr = M("attribute")->where("model_id=".$modelArr['id'])->select();
			foreach ($attributeArr as $k => $v){
				if($v['name'] == 'listorder'){
					$this->assign("listorder",true);
				}
				if($v['name'] == 'status'){
					$this->assign("status",true);
				}
				if($v['name'] == 'istop'){
					$this->assign("istop",true);
				}
				if($v['name'] == 'recommended'){
					$this->assign("recommended",true);
				}
				if($v['name'] == 'status'){
					$this->assign("status",true);
				}
				
			}
		}
// 		$this->assign('listorder',true);
// 		dump($modelArr);die();
	}
// namespace Admin\Controller;

/**
 * 属性控制器
 * @author huajie <banhuajie@163.com>
 */
// class AttributeController extends AdminController {

/**
 * 通用分页列表数据集获取方法
 *
 *  可以通过url参数传递where条件,例如:  index.html?name=asdfasdfasdfddds
 *  可以通过url空值排序字段和方式,例如: index.html?_field=id&_order=asc
 *  可以通过url参数r指定每页数据条数,例如: index.html?r=5
 *
 * @param sting|Model  $model   模型名或模型实例
 * @param array        $where   where查询条件(优先级: $where>$_REQUEST>模型设定)
 * @param array|string $order   排序条件,传入null时使用sql默认排序或模型属性(优先级最高);
 *                              请求参数中如果指定了_order和_field则据此排序(优先级第二);
 *                              否则使用$order参数(如果$order参数,且模型也没有设定过order,则取主键降序);
 *
 * @param boolean      $field   单表模型用不到该参数,要用在多表join时为field()方法指定参数
 * @author 朱亚杰 <xcoolcc@gmail.com>
 *
 * @return array|false
 * 返回数据集
 */
	protected function lists ($model,$where=array(),$order='',$field=true){
		$options    =   array();
		$REQUEST    =   (array)I('request.');
		if(is_string($model)){
			$model  =   M($model);
		}
		
		$OPT        =   new \ReflectionProperty($model,'options');
		$OPT->setAccessible(true);
		
		$pk         =   $model->getPk();
		if($order===null){
			//order置空
		}else if ( isset($REQUEST['_order']) && isset($REQUEST['_field']) && in_array(strtolower($REQUEST['_order']),array('desc','asc')) ) {
			$options['order'] = '`'.$REQUEST['_field'].'` '.$REQUEST['_order'];
		}elseif( $order==='' && empty($options['order']) && !empty($pk) ){
			$options['order'] = $pk.' desc';
		}elseif($order){
			$options['order'] = $order;
		}
		unset($REQUEST['_order'],$REQUEST['_field']);
		
		if(empty($where)){
			$where  =   array('status'=>array('egt',0));
		}
		if( !empty($where)){
			$options['where']   =   $where;
		}
		$options      =   array_merge( (array)$OPT->getValue($model), $options );
		$total        =   $model->where($options['where'])->count();
		
		if( isset($REQUEST['r']) ){
			$listRows = (int)$REQUEST['r'];
		}else{
			$listRows = C('LIST_ROWS') > 0 ? C('LIST_ROWS') : 10;
		}
		$page = new \Think\Page($total, $listRows, $REQUEST);
		if($total>$listRows){
			$page->setConfig('theme','%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END% %HEADER%');
		}
		$p =$page->show();
		$this->assign('_page', $p? $p: '');
		$this->assign('_total',$total);
		$options['limit'] = $page->firstRow.','.$page->listRows;
		
		$model->setProperty('options',$options);
		
		return $model->field($field)->select();
	}
	
	/**
	 * 排序 排序字段为listorders数组 POST 排序字段为：listorder或者自定义字段
	 * @param mixed $model 需要排序的模型类
	 * @param string $custom_field 自定义排序字段 默认为listorder,可以改为自己的排序字段
	 */
	protected function _listorders($model,$custom_field='') {
		if (!is_object($model)) {
			return false;
		}
		$field=empty($custom_field)&&is_string($custom_field)?'listorder':$custom_field;
		$pk = $model->getPk(); //获取主键名称
		$ids = $_POST['listorders'];
		foreach ($ids as $key => $r) {
			$data[$field] = $r;
			$model->where(array($pk => $key))->save($data);
		}
		return true;
	}
	protected function _listordersbak($model,$custom_field='') {
		if (!is_object($model)) {
			return false;
		}
		$field=empty($custom_field)&&is_string($custom_field)?'listorder':$custom_field;
// 		$pk = $model->getPk(); //获取主键名称
		$pk = "id"; //获取主键名称
		$ids = $_POST['listorders'];
		foreach ($ids as $key => $r) {
			$data[$field] = $r;
			$model->where(array($pk => $key))->save($data);
		}
		return true;
	}
	
	/**
	 * 设置一条或者多条数据的状态
	 */
	public function setStatus($Model=CONTROLLER_NAME){
		
		$ids    =   I('request.ids');
		$status =   I('request.status');
		if(empty($ids)){
			$this->error('请选择要操作的数据');
		}
		
		$map['id'] = array('in',$ids);
		switch ($status){
			case -1 :
				$this->delete($Model, $map, array('success'=>'删除成功','error'=>'删除失败'));
				break;
			case 0  :
				$this->forbid("Model", $map, array('success'=>'禁用成功','error'=>'禁用失败'));
				break;
			case 1  :
				$this->resume($Model, $map, array('success'=>'启用成功','error'=>'启用失败'));
				break;
			default :
				$this->error('参数错误');
				break;
		}
	}
	
	/**
	 * 条目假删除
	 * @param string $model 模型名称,供D函数使用的参数
	 * @param array  $where 查询时的where()方法的参数
	 * @param array  $msg   执行正确和错误的消息 array('success'=>'','error'=>'', 'url'=>'','ajax'=>false)
	 *                     url为跳转页面,ajax是否ajax方式(数字则为倒数计时秒数)
	 *
	 * @author 朱亚杰  <zhuyajie@topthink.net>
	 */
	protected function delete ( $model , $where = array() , $msg = array( 'success'=>'删除成功！', 'error'=>'删除失败！')) {
		$data['status']         =   -1;
		$this->editRow(   $model , $data, $where, $msg);
	}
	
	
	
	// 文章删除
	public function deletebak(){
		if(isset($_GET['id'])){
			$id = I("get.id",0,'intval');
			if (M(CONTROLLER_NAME)->where(array('id'=>$id))->save(array('status'=>-1)) !==false) {
				$this->success("删除成功！");
			} else {
				$this->error("删除失败！");
			}
		}
		
		if(isset($_POST['ids'])){
			$ids = I('post.ids/a');
			
			if (M(CONTROLLER_NAME)->where(array('id'=>array('in',$ids)))->save(array('status'=>-1))!==false) {
				$this->success("删除成功！");
			} else {
				$this->error("删除失败！");
			}
		}
	}
	
	/**
	 * 禁用条目
	 * @param string $model 模型名称,供D函数使用的参数
	 * @param array  $where 查询时的 where()方法的参数
	 * @param array  $msg   执行正确和错误的消息,可以设置四个元素 array('success'=>'','error'=>'', 'url'=>'','ajax'=>false)
	 *                     url为跳转页面,ajax是否ajax方式(数字则为倒数计时秒数)
	 *
	 * @author 朱亚杰  <zhuyajie@topthink.net>
	 */
	protected function forbid ( $model , $where = array() , $msg = array( 'success'=>'状态禁用成功！', 'error'=>'状态禁用失败！')){
		//     	dump($model);
		//     	dump($where);
		//     	dump($msg);
		$data    =  array('status' => 0);
		$this->editRow( $model , $data, $where, $msg);
	}
	
	/**
	 * 对数据表中的单行或多行记录执行修改 GET参数id为数字或逗号分隔的数字
	 *
	 * @param string $model 模型名称,供M函数使用的参数
	 * @param array  $data  修改的数据
	 * @param array  $where 查询时的where()方法的参数
	 * @param array  $msg   执行正确和错误的消息 array('success'=>'','error'=>'', 'url'=>'','ajax'=>false)
	 *                     url为跳转页面,ajax是否ajax方式(数字则为倒数计时秒数)
	 *
	 * @author 朱亚杰  <zhuyajie@topthink.net>
	 */
	final protected function editRow ( $model ,$data, $where , $msg ){
		$id    = array_unique((array)I('id',0));
		$id    = is_array($id) ? implode(',',$id) : $id;
		//如存在id字段，则加入该条件
		$fields = M($model)->getDbFields();
		if(in_array('id',$fields) && !empty($id)){
			$where = array_merge( array('id' => array('in', $id )) ,(array)$where );
		}
		
		$msg   = array_merge( array( 'success'=>'操作成功！', 'error'=>'操作失败！', 'url'=>'' ,'ajax'=>IS_AJAX) , (array)$msg );
		if( M($model)->where($where)->save($data)!==false ) {
			$this->success($msg['success'],$msg['url'],$msg['ajax']);
		}else{
			$this->error($msg['error'],$msg['url'],$msg['ajax']);
		}
	}
	
	/**
	 * 恢复条目
	 * @param string $model 模型名称,供D函数使用的参数
	 * @param array  $where 查询时的where()方法的参数
	 * @param array  $msg   执行正确和错误的消息 array('success'=>'','error'=>'', 'url'=>'','ajax'=>false)
	 *                     url为跳转页面,ajax是否ajax方式(数字则为倒数计时秒数)
	 *
	 * @author 朱亚杰  <zhuyajie@topthink.net>
	 */
	protected function resume (  $model , $where = array() , $msg = array( 'success'=>'状态恢复成功！', 'error'=>'状态恢复失败！')){
		$data    =  array('status' => 1);
		$this->editRow(   $model , $data, $where, $msg);
	}
	
	/**
	 * @desc 将模型链接增加到menu表
	 * @access
	 * @param unknowtype
	 * @return
	 * @example
	 * @date 2017年5月26日
	 * @author benjamin
	 */
	protected function addMenu($model)
	{
		$data['parentid']  = 0;
		$data['app']       = 'Module';
		$data['model']     = $model;
		$data['action']    = 'index';
		$data['type']      = 1;
		$data['status']    = 1;
		$data['name']      = $model;
		$data['icon']      = '';
		$data['remark']    = '';
		$data['listorder'] = 0;
		$id = M("menu")->add($data);
		return $id;
	}
}
