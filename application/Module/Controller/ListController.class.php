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
// class ListController extends AdminbaseController{
class ListController extends ModuleBaseController{

    /**
     * 模型管理首页
     * @author huajie <banhuajie@163.com>
     */
    public function index(){
    	$this->_lists(CONTROLLER_NAME,intval($_REQUEST['p']));
    	
        $map = array('status'=>array('gt',-1));
        $list = $this->lists('Model',$map);
        int_to_string($list);
        // 记录当前列表页的cookie
        Cookie('__forward__',$_SERVER['REQUEST_URI']);
        $this->assign('_list', $list);
        $this->meta_title = '模型管理';
        $template =  file_exists("Module".sp_get_theme_path().MODULE_NAME.'/'.CONTROLLER_NAME.'/index.html') ? CONTROLLER_NAME.':index' : 'Module@Content:index';
        $this->display($template);
    }

    
    public function index2(){
    	$map = array('status'=>array('gt',-1));
    	$list = $this->lists('Model',$map);
    	int_to_string($list);
    	// 记录当前列表页的cookie
    	Cookie('__forward__',$_SERVER['REQUEST_URI']);
    	
    	$this->assign('_list', $list);
    	$this->meta_title = '模型管理';
    	$this->display();
    }
    
    /**
     * 分类文档列表页
     * @param integer $cate_id 分类id
     * @param integer $model_id 模型id
     * @param integer $position 推荐标志
     * @param integer $group_id 分组id
     */
    private function _index($cate_id = null, $model_id = null, $position = null,$group_id=null){
    	//获取左边菜单
//     	$this->getMenu();
    	
    	if($cate_id===null){
    		$cate_id = $this->cate_id;
    	}
    	if(!empty($cate_id)){
    		$pid = I('pid',0);
    		// 获取列表绑定的模型
    		if ($pid == 0) {
    			$models     =   get_category($cate_id, 'model');
    			// 获取分组定义
    			$groups		=	get_category($cate_id, 'groups');
    			if($groups){
    				$groups	=	parse_field_attr($groups);
    			}
    		}else{ // 子文档列表
    			$models     =   get_category($cate_id, 'model_sub');
    		}
    		if(is_null($model_id) && !is_numeric($models)){
    			// 绑定多个模型 取基础模型的列表定义
    			$model = M('Model')->getByName('document');
    		}else{
    			$model_id   =   $model_id ? : $models;
    			//获取模型信息
    			$model = M('Model')->getById($model_id);
    			if (empty($model['list_grid'])) {
    				$model['list_grid'] = M('Model')->getFieldByName('document','list_grid');
    			}
    		}
    		$this->assign('model', explode(',', $models));
    	}else{
    		// 获取基础模型信息
    		$model = M('Model')->getByName('document');
    		$model_id   =   null;
    		$cate_id    =   0;
    		$this->assign('model', null);
    	}
    	
    	//解析列表规则
    	$fields =	array();
    	$grids  =	preg_split('/[;\r\n]+/s', trim($model['list_grid']));
    	foreach ($grids as &$value) {
    		// 字段:标题:链接
    		$val      = explode(':', $value);
    		// 支持多个字段显示
    		$field   = explode(',', $val[0]);
    		$value    = array('field' => $field, 'title' => $val[1]);
    		if(isset($val[2])){
    			// 链接信息
    			$value['href']  =   $val[2];
    			// 搜索链接信息中的字段信息
    			preg_replace_callback('/\[([a-z_]+)\]/', function($match) use(&$fields){$fields[]=$match[1];}, $value['href']);
    		}
    		if(strpos($val[1],'|')){
    			// 显示格式定义
    			list($value['title'],$value['format'])    =   explode('|',$val[1]);
    		}
    		foreach($field as $val){
    			$array  =   explode('|',$val);
    			$fields[] = $array[0];
    		}
    	}
    	
    	// 文档模型列表始终要获取的数据字段 用于其他用途
    	$fields[] = 'category_id';
    	$fields[] = 'model_id';
    	$fields[] = 'pid';
    	// 过滤重复字段信息
    	$fields =   array_unique($fields);
    	// 列表查询
    	$list   =   $this->getDocumentList($cate_id,$model_id,$position,$fields,$group_id);
    	// 列表显示处理
    	$list   =   $this->parseDocumentList($list,$model_id);
    	
    	$this->assign('model_id',$model_id);
    	$this->assign('group_id',$group_id);
    	$this->assign('position',$position);
    	$this->assign('groups', $groups);
    	$this->assign('list',   $list);
    	$this->assign('list_grids', $grids);
    	$this->assign('model_list', $model);
    	// 记录当前列表页的cookie
//     	Cookie('__forward__',$_SERVER['REQUEST_URI']);
//     	$this->display();
    }
    
    /**
     * 显示指定模型列表数据
     * @param  String $model 模型标识
     * @author 麦当苗儿 <zuojiazi@vip.qq.com>
     */
    private function _lists($modelName = null, $p = 0){
    	$modelName || $this->error('模型名标识必须！');
    	$page = intval($p);
    	$page = $page ? $page : 1; //默认显示第一页数据
    	
    	//获取模型信息
    	$model = M('Model')->getByName($modelName);
    	$model || $this->error('模型不存在！');
    	
    	
    	
    	//解析列表规则
    	$fields = array();
    	$grids  = preg_split('/[;\r\n]+/s', trim($model['list_grid']));
    	foreach ($grids as &$value) {
    		if(trim($value) === ''){
    			continue;
    		}
    		// 字段:标题:链接
    		$val      = explode(':', $value);
    		// 支持多个字段显示
    		$field   = explode(',', $val[0]);
    		$value    = array('field' => $field, 'title' => $val[1]);
    		if(isset($val[2])){
    			// 链接信息
    			$value['href']	=	$val[2];
    			// 搜索链接信息中的字段信息
    			preg_replace_callback('/\[([a-z_]+)\]/', function($match) use(&$fields){$fields[]=$match[1];}, $value['href']);
    		}
    		if(strpos($val[1],'|')){
    			// 显示格式定义
    			list($value['title'],$value['format'])    =   explode('|',$val[1]);
    		}
    		foreach($field as $val){
    			$array	=	explode('|',$val);
    			$fields[] = $array[0];
    		}
    		
    		$value['pk'] = M($modelName)->getPk();
    	}
    	// 过滤重复字段信息
    	$fields =   array_unique($fields);
    	// 关键字搜索
    	$map	=	array();
    	$key	=	$model['search_key']?$model['search_key']:'title';
    	if(isset($_REQUEST[$key])){
    		$map[$key]	=	array('like','%'.$_GET[$key].'%');
    		unset($_REQUEST[$key]);
    	}
    	// 条件搜索
    	foreach($_REQUEST as $name=>$val){
    		if(in_array($name,$fields)){
    			$map[$name]	=	$val;
    		}
    	}
    	$row    = empty($model['list_row']) ? 10 : $model['list_row'];
    	
    	//读取模型数据列表
    	if($model['extend']){
    		$name   = get_table_name($model['id']);
    		$parent = get_table_name($model['extend']);
    		$fix    = C("DB_PREFIX");
    		
    		$key = array_search('id', $fields);
    		if(false === $key){
    			array_push($fields, "{$fix}{$parent}.id as id");
    		} else {
    			$fields[$key] = "{$fix}{$parent}.id as id";
    		}
    		
    		/* 查询记录数 */
    		$count = M($parent)->join("INNER JOIN {$fix}{$name} ON {$fix}{$parent}.id = {$fix}{$name}.id")->where($map)->count();
    		
    		// 查询数据
    		$data   = M($parent)
    		->join("INNER JOIN {$fix}{$name} ON {$fix}{$parent}.id = {$fix}{$name}.id")
    		/* 查询指定字段，不指定则查询所有字段 */
    		->field(empty($fields) ? true : $fields)
    		// 查询条件
    		->where($map)
    		/* 默认通过id逆序排列 */
    		->order("{$fix}{$parent}.id DESC")
    		/* 数据分页 */
    		->page($page, $row)
    		/* 执行查询 */
    		->select();
    		
    	} else {
    		//获取pk
    		$pk = M($modelName)->getPk();
    		if($model['need_pk']){
    			in_array('id', $fields) || array_push($fields, $pk);
    		}
    		$name = parse_name(get_table_name($model['id']), true);
    		$data = M($name)
    		/* 查询指定字段，不指定则查询所有字段 */
    		->field(empty($fields) ? true : $fields)
    		// 查询条件
    		->where($map)
    		/* 默认通过id逆序排列 */
    		->order($model['need_pk']?"{$pk} DESC":'')
    		/* 数据分页 */
    		->page($page, $row)
    		/* 执行查询 */
    		->select();
    		/* 查询记录总数 */
    		$count = M($name)->where($map)->count();
    	}
    	
    	//分页
    	if($count > $row){
    		$page = new \Think\Page($count, $row);
    		$page->setConfig('theme','%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END% %HEADER%');
    		$this->assign('page', $page->show());
    	}
    	$data   =   $this->parseDocumentList($data,$model['id']);
    	$this->assign('model', $model);
    	$this->assign('list_grids', $grids);
    	$this->assign('list_data', $data);
    	$this->meta_title = $model['title'].'列表';
//     	$this->display($model['template_list']);
    }
    
    /**
     * 默认文档列表方法
     * @param integer $cate_id 分类id
     * @param integer $model_id 模型id
     * @param integer $position 推荐标志
     * @param mixed $field 字段列表
     * @param integer $group_id 分组id
     */
    protected function getDocumentList($cate_id=0,$model_id=null,$position=null,$field=true,$group_id=null){
    	/* 查询条件初始化 */
    	$map = array();
    	if(isset($_GET['title'])){
    		$map['title']  = array('like', '%'.(string)I('title').'%');
    	}
    	if(isset($_GET['status'])){
    		$map['status'] = I('status');
    		$status = $map['status'];
    	}else{
    		$status = null;
    		$map['status'] = array('in', '0,1,2');
    	}
    	if ( isset($_GET['time-start']) ) {
    		$map['update_time'][] = array('egt',strtotime(I('time-start')));
    	}
    	if ( isset($_GET['time-end']) ) {
    		$map['update_time'][] = array('elt',24*60*60 + strtotime(I('time-end')));
    	}
    	if ( isset($_GET['nickname']) ) {
    		$map['uid'] = M('Member')->where(array('nickname'=>I('nickname')))->getField('uid');
    	}
    	
    	// 构建列表数据
    	$Document = M('Document');
    	
    	if($cate_id){
    		$map['category_id'] =   $cate_id;
    	}
    	$map['pid']         =   I('pid',0);
    	if($map['pid']){ // 子文档列表忽略分类
    		unset($map['category_id']);
    	}
    	$Document->alias('DOCUMENT');
    	if(!is_null($model_id)){
    		$map['model_id']    =   $model_id;
    		if(is_array($field) && array_diff($Document->getDbFields(),$field)){
    			$modelName  =   M('Model')->getFieldById($model_id,'name');
    			$Document->join('__DOCUMENT_'.strtoupper($modelName).'__ '.$modelName.' ON DOCUMENT.id='.$modelName.'.id');
    			$key = array_search('id',$field);
    			if(false  !== $key){
    				unset($field[$key]);
    				$field[] = 'DOCUMENT.id';
    			}
    		}
    	}
    	if(!is_null($position)){
    		$map[] = "position & {$position} = {$position}";
    	}
    	if(!is_null($group_id)){
    		$map['group_id']	=	$group_id;
    	}
    	$list = $this->lists($Document,$map,'level DESC,DOCUMENT.id DESC',$field);
    	
    	if($map['pid']){
    		// 获取上级文档
    		$article    =   $Document->field('id,title,type')->find($map['pid']);
    		$this->assign('article',$article);
    	}
    	//检查该分类是否允许发布内容
    	$allow_publish  =   get_category($cate_id, 'allow_publish');
    	
    	$this->assign('status', $status);
    	$this->assign('allow',  $allow_publish);
    	$this->assign('pid',    $map['pid']);
    	
    	$this->meta_title = '文档列表';
    	return $list;
    }
    
    /**
     * 新增页面初始化
     * @author huajie <banhuajie@163.com>
     */
    public function add_bak(){
        //获取所有的模型
        $models = M('Model')->where(array('extend'=>0))->field('id,title')->select();
        $this->assign('models', $models);
        
        $model = I('request.model',null);
        //获取模型信息
        $model = M('Model')->find($model);
        $model || $this->error('模型不存在！');
        $this->assign("model",$model);
        
        $fields     = get_model_attribute($model['id']);
        $this->assign('fields', $fields);
        
        
        $this->meta_title = '新增数据';
        
        $template =  file_exists("Module".sp_get_theme_path().MODULE_NAME.'/'.CONTROLLER_NAME.'/index.html') ? CONTROLLER_NAME.':index' : 'Module@Content:add';
        $this->display($template);
    }
    
    
//     public function add($model = null){
    public function add(){
    	$model = intval($_GET['model']);
    	//获取模型信息
    	$model = M('Model')->where(array('status' => 1))->find($model);
    	
    	$model || $this->error('模型不存在！');
    	if(IS_POST){
    		$Model  =   D(parse_name(get_table_name($model['id']),1));
    		
//     		$post=I("post.");
//     		$attributeArr = M("attribute")->where("model_id=".$model['id'])->select();
//     		foreach ($attributeArr as $k => $v){
//     			if($v['type'] == 'editor' && isset($post[$v['name']])){
//     				$post[$v['name']] = htmlspecialchars_decode($post[$v['name']]);
//     			}
//     		}

//     		$post = $this->_rebuildPost($model['id']);
    		
    		// 获取模型的字段信息
    		$Model  =   $this->checkAttr($Model,$model['id']);
    		if($Model->create() && $Model->add()){
//     			$this->success('添加'.$model['title'].'成功！', U('lists?model='.$model['name']));
//     			die(U('index?model='.$model['name']));
    			$this->success('保存'.$model['title'].'成功！', U('index?model='.$model['name']));
    		} else {
    			$this->error($Model->getError());
    		}
    	} else {
    		
    		$fields = get_model_attribute($model['id']);
    		
    		$this->assign('model', $model);
    		$this->assign('fields', $fields);
    		$this->meta_title = '新增'.$model['title'];
    		
    		$template =  file_exists("Module".sp_get_theme_path().MODULE_NAME.'/'.CONTROLLER_NAME.'/index.html') ? CONTROLLER_NAME.':index' : 'Module@Content:add';
    		$this->display($template);
//     		$this->display($model['template_add']?$model['template_add']:'');
    	}
    }
    
    // 參考 - 文章编辑
    public function edit參考(){
    	$id=  I("get.id",0,'intval');
    	
    	$term_relationship = M('TermRelationships')->where(array("object_id"=>$id,"status"=>1))->getField("term_id",true);
    	$this->_getTermTree($term_relationship);
    	$terms=$this->terms_model->select();
    	$post=$this->posts_model->where("id=$id")->find();
    	$this->assign("post",$post);
    	$this->assign("smeta",json_decode($post['smeta'],true));
    	$this->assign("terms",$terms);
    	$this->assign("term",$term_relationship);
    	$this->display();
    }

    /**
    * @desc post数据重构 1、editor进行htmlspecialchars_decode处理
    *                2、date、datetime进行格林威治时间时间处理 即将2017-06-12 16:55变为1497257700
    * @access 
    * @param int   $model_id model表自增id
    *        array $_POST    隐形参数
    * @return array $post 重构后的post数据
    * @example 
    * @date 2017年6月15日
    * @author benjamin
    */
    private final function _rebuildPost废弃($model_id)
    {
    	$post=I("post.");
    	$attributeArr = M("attribute")->where("model_id=".$model_id)->select();
    	
    	foreach ($attributeArr as $k => $v){
    		if($v['type'] == 'editor' && isset($post[$v['name']])){
    			$post[$v['name']] = htmlspecialchars_decode($post[$v['name']]);
    		}
    		
    		if($v['type'] == 'date' && isset($post[$v['name']])){
    			$post[$v['name']] = strtotime($post[$v['name']]);
    		}
    		
    		if($v['type'] == 'datetime' && isset($post[$v['name']])){
    			$post[$v['name']] = strtotime($post[$v['name']]);
    		}
    	}
//     	dump($post);
    	return $post;
    }
    
//     public function edit( $model = null, $id = 0){
    public function edit(){
    	$model = I('request.model',null);
    	$id    = I('request.id',0);
    	//获取模型信息
    	$model = M('Model')->find($model);
    	$model || $this->error('模型不存在！');
    	
    	if(IS_POST){
    		$Model  =   D(parse_name(get_table_name($model['id']),1));
			
// 			$post=I("post.");
// 			$attributeArr = M("attribute")->where("model_id=".$model['id'])->select();
// 			foreach ($attributeArr as $k => $v){
// 				if($v['type'] == 'editor' && isset($post[$v['name']])){
// 					$post[$v['name']] = htmlspecialchars_decode($post[$v['name']]);
// 				}
// 			}

//     		$post = $this->_rebuildPost($model['id']);
    		// 获取模型的字段信息
    		$Model  =   $this->checkAttr($Model,$model['id']);
    		if($Model->create() && $savaResult = $Model->save()){
    			$this->success('保存'.$model['title'].'成功！', U('index?model='.$model['name']));
    		} else {
    			if($savaResult === 0){
    				$this->error('没有更新');
    			}
    			$this->error($Model->getError());
    		}
    	} else {
    		$fields     = get_model_attribute($model['id']);
    		//获取数据
    		$tableNameStr = get_table_name($model['id']);
    		$data       = M($tableNameStr)->find($id);
    		$data || $this->error('数据不存在！');
			$this->assign('modelName',ucfirst($model['name']));
    		$this->assign('model', $model);
    		$this->assign('fields', $fields);
    		$this->assign('data', $data);
    		$this->meta_title = '编辑'.$model['title'];
    		
    		$template =  file_exists("Module".sp_get_theme_path().MODULE_NAME.'/'.CONTROLLER_NAME.'/index.html') ? CONTROLLER_NAME.':index' : 'Module@Content:edit';
    	
    		if($model['template_edit']){
    			$template = $model['template_edit'];
    		}else{
    			if(file_exists("Module".sp_get_theme_path().MODULE_NAME.'/'.CONTROLLER_NAME.'/edit.html')){
    				$template = CONTROLLER_NAME.':edit';//等同于下面代码
//     				$template = '';//等同于上面代码
    			}else{
    				$template = 'Module@Content:edit';
    			}
    		}
    		
    		$this->display($template);
//     		$this->display($model['template_edit']?$model['template_edit']:'');
    	}
    }
    
    protected function checkAttr($Model,$model_id){
    	$fields     =   get_model_attribute($model_id,false);
    	$validate   =   $auto   =   array();
    	foreach($fields as $key=>$attr){
    		if($attr['is_must']){// 必填字段
    			$validate[]  =  array($attr['name'],'require',$attr['title'].'必须!');
    		}
    		// 自动验证规则
    		if(!empty($attr['validate_rule'])) {
    			$validate[]  =  array($attr['name'],$attr['validate_rule'],$attr['error_info']?$attr['error_info']:$attr['title'].'验证错误',0,$attr['validate_type'],$attr['validate_time']);
    		}
    		// 自动完成规则
    		if(!empty($attr['auto_rule'])) {
    			$auto[]  =  array($attr['name'],$attr['auto_rule'],$attr['auto_time'],$attr['auto_type']);
    		}elseif('checkbox'==$attr['type']){ // 多选型
    			$auto[] =   array($attr['name'],'arr2str',3,'function');
    		}elseif('picture' == $attr['type']){//多图
    			$auto[] =   array($attr['name'],'arr2str_pic',3,'function_call_user_func',$_POST);
    		}elseif('file' == $attr['type']){//多文件
//     			$auto[] =   array($attr['name'],'arr2str_file',3,'function');
    			$auto[] =   array($attr['name'],'arr2str_pic',3,'function_call_user_func',$_POST);
    		}elseif('date' == $attr['type']){ // 日期型
    			$auto[] =   array($attr['name'],'strtotime',3,'function');
    		}elseif('datetime' == $attr['type']){ // 时间型
    			$auto[] =   array($attr['name'],'strtotime',3,'function');
    		}
    	}
    	return $Model->validate($validate)->auto($auto);
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
    protected function delete_bak ( $model , $where = array() , $msg = array( 'success'=>'删除成功！', 'error'=>'删除失败！')) {
    	$data['status']         =   -1;
    	$this->editRow(   $model , $data, $where, $msg);
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
    final protected function editRow_废弃 ( $model ,$data, $where , $msg ){
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
     * 编辑页面初始化
     * @author huajie <banhuajie@163.com>
     */
    public function edit——错误(){
        $id = I('get.id','');
        if(empty($id)){
            $this->error('参数不能为空！');
        }

        /*获取一条记录的详细数据*/
        $Model = M('Model');
        $data = $Model->field(true)->find($id);
        if(!$data){
            $this->error($Model->getError());
        }
        $data['attribute_list'] = empty($data['attribute_list']) ? '' : explode(",", $data['attribute_list']);
        $fields = M('Attribute')->where(array('model_id'=>$data['id']))->getField('id,name,title,is_show',true);
        $fields = empty($fields) ? array() : $fields;
        // 是否继承了其他模型
        if($data['extend'] != 0){
            $extend_fields  = M('Attribute')->where(array('model_id'=>$data['extend']))->getField('id,name,title,is_show',true);
            $fields        += $extend_fields;
        }
        
        // 梳理属性的可见性
        foreach ($fields as $key=>$field){
            if (!empty($data['attribute_list']) && !in_array($field['id'], $data['attribute_list'])) {
                $fields[$key]['is_show'] = 0;
            }
        }
        
        // 获取模型排序字段
        $field_sort = json_decode($data['field_sort'], true);
        if(!empty($field_sort)){
            foreach($field_sort as $group => $ids){
                foreach($ids as $key => $value){
                    $fields[$value]['group']  =  $group;
                    $fields[$value]['sort']   =  $key;
                }
            }
        }
        
        // 模型字段列表排序
        $fields = list_sort_by($fields,"sort");
        
        $this->assign('fields', $fields);
        $this->assign('info', $data);
        $this->meta_title = '编辑模型';
        $template =  file_exists("Module".sp_get_theme_path().MODULE_NAME.'/'.CONTROLLER_NAME.'/index.html') ? CONTROLLER_NAME.':index' : 'Module@Content:edit';
        $this->display($template);
    }

    /**
     * 删除一条数据
     * @author huajie <banhuajie@163.com>
     */
    public function del(){
        $ids = I('get.ids');
        empty($ids) && $this->error('参数不能为空！');
        $ids = explode(',', $ids);
        foreach ($ids as $value){
            $res = D('Model')->del($value);
            if(!$res){
                break;
            }
        }
        if(!$res){
            $this->error(D('Model')->getError());
        }else{
            $this->success('删除模型成功！');
        }
    }
    /**
     * 删除一条数据
     * @author huajie <banhuajie@163.com>
     */
    public function realdel(){
        $ids = I('get.ids');
        empty($ids) && $this->error('参数不能为空！');
        $ids = explode(',', $ids);
        foreach ($ids as $value){
            $res = M(CONTROLLER_NAME)->where("id=".$value)->delete();
            if(!$res){
                break;
            }
        }
        if(!$res){
        	$this->error(M(CONTROLLER_NAME)->getError());
        }else{
            $this->success('删除数据成功！');
        }
    }

    /**
     * 更新一条数据
     * @author huajie <banhuajie@163.com>
     */
    public function update(){
        $res = D('Model')->update();

        if(!$res){
            $this->error(D('Model')->getError());
        }else{
            $this->success($res['id']?'更新成功':'新增成功', Cookie('__forward__'));
        }
    }

    /**
     * 生成一个模型
     * @author huajie <banhuajie@163.com>
     */
    public function generate(){
        if(!IS_POST){
            //获取所有的数据表
            $tables = D('Model')->getTables();

            $this->assign('tables', $tables);
            $this->meta_title = '生成模型';
            $this->display();
        }else{
            $table = I('post.table');
            empty($table) && $this->error('请选择要生成的数据表！');
            $res = D('Model')->generate($table,I('post.name'),I('post.title'));
            if($res){
                $this->success('生成模型成功！', U('index'));
            }else{
                $this->error(D('Model')->getError());
            }
        }
    }
    
    /**
     * 处理文档列表显示
     * @param array $list 列表数据
     * @param integer $model_id 模型id
     */
    protected function parseDocumentList($list,$model_id=null){
    	$model_id = $model_id ? $model_id : 1;
    	$attrList = get_model_attribute($model_id,false,'id,name,type,extra');
//     	dump($list);die();
    	// 对列表数据进行显示处理
    	if(is_array($list)){
    		foreach ($list as $k=>$data){
    			foreach($data as $key=>$val){
    				if(isset($attrList[$key])){
    					$extra      =   $attrList[$key]['extra'];
    					$type       =   $attrList[$key]['type'];
    					if('select'== $type || 'checkbox' == $type || 'radio' == $type || 'bool' == $type) {
    						// 枚举/多选/单选/布尔型
    						$options    =   parse_field_attr($extra);
    						if($options && array_key_exists($val,$options)) {
    							$data[$key]    =   $options[$val];
    						}
    					}elseif('date'==$type){ // 日期型
    						$data[$key]    =   date('Y-m-d',$val);
    					}elseif('datetime' == $type){ // 时间型
    						$data[$key]    =   date('Y-m-d H:i',$val);
    					}
    				}
    			}
    			$data['model_id'] = $model_id;
    			$list[$k]   =   $data;
    		}
    	}
    	return $list;
    }
    
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
    
    // 文章审核
    public function check(){
    	if(isset($_POST['ids']) && $_GET["check"]){
    		$ids = I('post.ids/a');
//     		dump(MODULE_NAME);
//     		dump(CONTROLLER_NAME);
//     		dump(ACTION_NAME);
//     		dump(__ACTION__);
//     		dump(get_defined_vars());
    		if ( M(CONTROLLER_NAME)->where(array('id'=>array('in',$ids)))->save(array('status'=>1)) !== false ) {
    			$this->success("审核成功！");
    		} else {
    			$this->error("审核失败！");
    		}
    	}
    	if(isset($_POST['ids']) && $_GET["uncheck"]){
    		$ids = I('post.ids/a');
    		
    		if ( M(CONTROLLER_NAME)->where(array('id'=>array('in',$ids)))->save(array('status'=>0)) !== false) {
    			$this->success("取消审核成功！");
    		} else {
    			$this->error("取消审核失败！");
    		}
    	}
    }
    
    // 文章置顶
    public function top(){
    	if(isset($_POST['ids']) && $_GET["top"]){
    		$ids = I('post.ids/a');
    		
    		if ( M(CONTROLLER_NAME)->where(array('id'=>array('in',$ids)))->save(array('istop'=>1))!==false) {
    			$this->success("置顶成功！");
    		} else {
    			$this->error("置顶失败！");
    		}
    	}
    	if(isset($_POST['ids']) && $_GET["untop"]){
    		$ids = I('post.ids/a');
    		
    		if ( M(CONTROLLER_NAME)->where(array('id'=>array('in',$ids)))->save(array('istop'=>0))!==false) {
    			$this->success("取消置顶成功！");
    		} else {
    			$this->error("取消置顶失败！");
    		}
    	}
    }
    
    
    
    // 文章推荐
    public function recommend(){
    	if(isset($_POST['ids']) && $_GET["recommend"]){
    		$ids = I('post.ids/a');
    		
    		if ( M(CONTROLLER_NAME)->where(array('id'=>array('in',$ids)))->save(array('recommended'=>1))!==false) {
    			$this->success("推荐成功！");
    		} else {
    			$this->error("推荐失败！");
    		}
    	}
    	if(isset($_POST['ids']) && $_GET["unrecommend"]){
    		$ids = I('post.ids/a');
    		
    		if ( M(CONTROLLER_NAME)->where(array('id'=>array('in',$ids)))->save(array('recommended'=>0))!==false) {
    			$this->success("取消推荐成功！");
    		} else {
    			$this->error("取消推荐失败！");
    		}
    	}
    }
    
    
    
    
    
    // 文章排序
    public function listorders() {
//     	$status = parent::_listorders($this->term_relationships_model);
//     	$status = parent::_listorders(D("Portal/TermRelationships"));
    	$status = parent::_listorders(M(CONTROLLER_NAME));
    	if ($status) {
    		$this->success("排序更新成功！");
    	} else {
    		$this->error("排序更新失败！");
    	}
    }
    
    
}
