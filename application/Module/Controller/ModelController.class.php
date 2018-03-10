<?php
// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: huajie <banhuajie@163.com>
// +----------------------------------------------------------------------

namespace Module\Controller;

// use Common\Controller\AdminbaseController;


/**
 * 模型管理控制器
 * @author huajie <banhuajie@163.com>
 */
// class ModelController extends AdminbaseController{
class ModelController extends ModuleBaseController{

    /**
     * 模型管理首页
     * @author huajie <banhuajie@163.com>
     */
    public function index(){
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
     * 新增页面初始化
     * @author huajie <banhuajie@163.com>
     */
    public function add(){
        //获取所有的模型
        $models = M('Model')->where(array('extend'=>0))->field('id,title')->select();

        $this->assign('models', $models);
        $this->meta_title = '新增模型';
        $this->display();
    }

    /**
     * 编辑页面初始化
     * @author huajie <banhuajie@163.com>
     */
    public function edit(){
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
        if($data['extend'] !== "0"){
        	if($data['extend'] === '1'){
        		$extend_fields  = M('Attribute')->where(array('model_id'=>$data['extend']))->getField('id,name,title,is_show',true);
        		$fields        += $extend_fields;
        	}else{//benjamin by 2017-06-21 增加自定义模型
//         		$fieldGroupArr = parse_config_attr($data['field_group']);
        		$extendArr = explode(",", $data['extend']);
        		foreach ($extendArr as $model_id){
        			$extend_fields  = M('Attribute')->where(array('model_id'=>$model_id))->getField('id,name,title,is_show',true);
        			$fields        += $extend_fields;
        		}
        	}
            
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
                	if(isset($fields[$value])){//benjamin by 2017-06-21 增加有效性判断
                    	$fields[$value]['group']  =  $group;
                    	$fields[$value]['sort']   =  $key;
                	}
                }
            }
            
            #删除脏数据
            foreach($field_sort as $group => $ids){
            	foreach($ids as $key => $value){
            		if(!isset($fields[$value])){//benjamin by 2017-06-21 增加有效性判断
            			unset($fields[$value]);
            		}
            	}
            }
        }
        
        // 模型字段列表排序
        $fields = list_sort_by($fields,"sort");
        
        $this->assign('fields', $fields);
        $this->assign('info', $data);
        $this->meta_title = '编辑模型';
        $this->display();
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
            
            if($this->_getPk($table) === null){
            	$this->error('表缺少主键，无法生成模型,如果是视图,请将其中一列定义为id即可！');
            }
            
            $res = D('Model')->generate($table,I('post.name'),I('post.title'));
            if($res){
            	if(stripos($table, C("DB_PREFIX")) !== 0){
            		$tableNameNoPreFix = $table;
            	}else{
            		$tableNameNoPreFix = substr($table, strlen(C("DB_PREFIX")));
            	}
            	$this->addMenu($tableNameNoPreFix);
            	
                $this->success('生成模型成功！', U('index'));
            }else{
                $this->error(D('Model')->getError());
            }
        }
    }
    
    /**
    * @desc 获取表的PK
    * @access 
    * @param string $tableName 不带前缀的表名
    * @return 
    * @example 
    * @date 2017年6月22日
    * @author benjamin
    */
    final private function _getPk($tableName){
    	$sql="desc `$tableName`";
    	$info=M()->query($sql);
//     	dump($info);
    	foreach ($info as $k => $v){
    		if($v['extra'] === 'auto_increment'){
    			return $v['field'];
    		}
    	}
    	
    	foreach ($info as $k => $v){
    		if($v['field'] === 'id'){
    			return $v['field'];
    		}
    	}
    	return null;
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
//     	$REQUEST    =   (array)I('request.');
    	$REQUEST    =   (array)I('param.');
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
}
