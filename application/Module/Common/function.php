<?php
/**
 * select返回的数组进行整数映射转换
 *
 * @param array $map  映射关系二维数组  array(
 *                                          '字段名1'=>array(映射关系数组),
 *                                          '字段名2'=>array(映射关系数组),
 *                                           ......
 *                                       )
 * @author 朱亚杰 <zhuyajie@topthink.net>
 * @return array
 *
 *  array(
 *      array('id'=>1,'title'=>'标题','status'=>'1','status_text'=>'正常')
 *      ....
 *  )
 *
 */
function int_to_string(&$data,$map=array('status'=>array(1=>'正常',-1=>'删除',0=>'禁用',2=>'未审核',3=>'草稿'))) {
    if($data === false || $data === null ){
        return $data;
    }
    $data = (array)$data;
    foreach ($data as $key => $row){
        foreach ($map as $col=>$pair){
            if(isset($row[$col]) && isset($pair[$row[$col]])){
                $data[$key][$col.'_text'] = $pair[$row[$col]];
            }
        }
    }
    return $data;
}

/**
 * 时间戳格式化
 * @param int $time
 * @return string 完整的时间显示
 * @author huajie <banhuajie@163.com>
 */
function time_format($time = NULL,$format='Y-m-d H:i'){
	$time = $time === NULL ? NOW_TIME : intval($time);
	return date($format, $time);
}

// 获取数据的状态操作
function show_status_op($status) {
	switch ($status){
		case 0  : return    '启用';     break;
		case 1  : return    '禁用';     break;
		case 2  : return    '审核';       break;
		default : return    false;      break;
	}
}

/**
 * 记录行为日志，并执行该行为的规则
 * @param string $action 行为标识
 * @param string $model 触发行为的模型名
 * @param int $record_id 触发行为的记录id
 * @param int $user_id 执行行为的用户id
 * @return boolean
 * @author huajie <banhuajie@163.com>
 */
function action_log($action = null, $model = null, $record_id = null, $user_id = null){
	
	//参数检查
	if(empty($action) || empty($model) || empty($record_id)){
		return '参数不能为空';
	}
	if(empty($user_id)){
		$user_id = is_login();
	}
	
	//查询行为,判断是否执行
	$action_info = M('Action')->getByName($action);
	if($action_info['status'] != 1){
		return '该行为被禁用或删除';
	}
	
	//插入行为日志
	$data['action_id']      =   $action_info['id'];
	$data['user_id']        =   $user_id;
	$data['action_ip']      =   ip2long(get_client_ip());
	$data['model']          =   $model;
	$data['record_id']      =   $record_id;
	$data['create_time']    =   NOW_TIME;
	
	//解析日志规则,生成日志备注
	if(!empty($action_info['log'])){
		if(preg_match_all('/\[(\S+?)\]/', $action_info['log'], $match)){
			$log['user']    =   $user_id;
			$log['record']  =   $record_id;
			$log['model']   =   $model;
			$log['time']    =   NOW_TIME;
			$log['data']    =   array('user'=>$user_id,'model'=>$model,'record'=>$record_id,'time'=>NOW_TIME);
			foreach ($match[1] as $value){
				$param = explode('|', $value);
				if(isset($param[1])){
					$replace[] = call_user_func($param[1],$log[$param[0]]);
				}else{
					$replace[] = $log[$param[0]];
				}
			}
			$data['remark'] =   str_replace($match[0], $replace, $action_info['log']);
		}else{
			$data['remark'] =   $action_info['log'];
		}
	}else{
		//未定义日志规则，记录操作url
		$data['remark']     =   '操作url：'.$_SERVER['REQUEST_URI'];
	}
	
	M('ActionLog')->add($data);
	
	if(!empty($action_info['rule'])){
		//解析行为
		$rules = parse_action($action, $user_id);
		
		//执行行为
		$res = execute_action($rules, $action_info['id'], $user_id);
	}
}

/**
 * 执行行为
 * @param array $rules 解析后的规则数组
 * @param int $action_id 行为id
 * @param array $user_id 执行的用户id
 * @return boolean false 失败 ， true 成功
 * @author huajie <banhuajie@163.com>
 */
function execute_action($rules = false, $action_id = null, $user_id = null){
	if(!$rules || empty($action_id) || empty($user_id)){
		return false;
	}
	
	$return = true;
	foreach ($rules as $rule){
		
		//检查执行周期
		$map = array('action_id'=>$action_id, 'user_id'=>$user_id);
		$map['create_time'] = array('gt', NOW_TIME - intval($rule['cycle']) * 3600);
		$exec_count = M('ActionLog')->where($map)->count();
		if($exec_count > $rule['max']){
			continue;
		}
		
		//执行数据库操作
		$Model = M(ucfirst($rule['table']));
		$field = $rule['field'];
		$res = $Model->where($rule['condition'])->setField($field, array('exp', $rule['rule']));
		
		if(!$res){
			$return = false;
		}
	}
	return $return;
}

/**
 * 解析行为规则
 * 规则定义  table:$table|field:$field|condition:$condition|rule:$rule[|cycle:$cycle|max:$max][;......]
 * 规则字段解释：table->要操作的数据表，不需要加表前缀；
 *              field->要操作的字段；
 *              condition->操作的条件，目前支持字符串，默认变量{$self}为执行行为的用户
 *              rule->对字段进行的具体操作，目前支持四则混合运算，如：1+score*2/2-3
 *              cycle->执行周期，单位（小时），表示$cycle小时内最多执行$max次
 *              max->单个周期内的最大执行次数（$cycle和$max必须同时定义，否则无效）
 * 单个行为后可加 ； 连接其他规则
 * @param string $action 行为id或者name
 * @param int $self 替换规则里的变量为执行用户的id
 * @return boolean|array: false解析出错 ， 成功返回规则数组
 * @author huajie <banhuajie@163.com>
 */
function parse_action($action = null, $self){
	if(empty($action)){
		return false;
	}
	
	//参数支持id或者name
	if(is_numeric($action)){
		$map = array('id'=>$action);
	}else{
		$map = array('name'=>$action);
	}
	
	//查询行为信息
	$info = M('Action')->where($map)->find();
	if(!$info || $info['status'] != 1){
		return false;
	}
	
	//解析规则:table:$table|field:$field|condition:$condition|rule:$rule[|cycle:$cycle|max:$max][;......]
	$rules = $info['rule'];
	$rules = str_replace('{$self}', $self, $rules);
	$rules = explode(';', $rules);
	$return = array();
	foreach ($rules as $key=>&$rule){
		$rule = explode('|', $rule);
		foreach ($rule as $k=>$fields){
			$field = empty($fields) ? array() : explode(':', $fields);
			if(!empty($field)){
				$return[$key][$field[0]] = $field[1];
			}
		}
		//cycle(检查周期)和max(周期内最大执行次数)必须同时存在，否则去掉这两个条件
		if(!array_key_exists('cycle', $return[$key]) || !array_key_exists('max', $return[$key])){
			unset($return[$key]['cycle'],$return[$key]['max']);
		}
	}
	
	return $return;
}

// 分析枚举类型字段值 格式 a:名称1,b:名称2
// 暂时和 parse_config_attr功能相同
// 但请不要互相使用，后期会调整
function parse_field_attr($string) {
	if(0 === strpos($string,':')){
		// 采用函数定义
		return   eval('return '.substr($string,1).';');
	}elseif(0 === strpos($string,'[')){
		// 支持读取配置参数（必须是数组类型）
		return C(substr($string,1,-1));
	}
	
	$array = preg_split('/[,;\r\n]+/', trim($string, ",;\r\n"));
	if(strpos($string,':')){
		$value  =   array();
		foreach ($array as $val) {
			list($k, $v) = explode(':', $val);
			$value[$k]   = $v;
		}
	}else{
		$value  =   $array;
	}
	return $value;
}

// 获取模型名称
function get_model_by_id($id){
	return $model = M('Model')->getFieldById($id,'title');
}


// 获取属性类型信息
function get_attribute_type($type=''){
	// TODO 可以加入系统配置
	static $_type = array(
			'num'         =>  array('数字','int(10) UNSIGNED NOT NULL'),
			'string'      =>  array('字符串','varchar(255) NOT NULL'),
			'textarea'    =>  array('文本框','text NOT NULL'),
			'date'        =>  array('日期','int(10) NOT NULL'),
			'datetime'    =>  array('时间','int(10) NOT NULL'),
			'bool'        =>  array('布尔','tinyint(2) NOT NULL'),
			'select'      =>  array('枚举','char(50) NOT NULL'),
			'radio'       =>  array('单选','char(10) NOT NULL'),
			'checkbox'    =>  array('多选','varchar(100) NOT NULL'),
			'editor'      =>  array('编辑器','text NOT NULL'),
			'picture'     =>  array('上传图片','int(10) UNSIGNED NOT NULL'),
			'file'        =>  array('上传附件','int(10) UNSIGNED NOT NULL'),
			'create_time' =>  array('发布时间(右边)','int(10) NOT NULL','发布时间','create_time'),
			'status'      =>  array('发布状态(右边)','tinyint(2) NOT NULL','发布状态','status',"-1:删除\r\n0:禁用\r\n1:正常"),
			'istop'       =>  array('置顶(右边)','tinyint(2) NOT NULL','置顶','istop'),
			'recommended' =>  array('推荐(右边)','tinyint(2) NOT NULL','推荐','recommended'),
			'listorder'   =>  array('排序(右边)','tinyint(2) NOT NULL','排序','listorder'),
	);
	return $type?$_type[$type][0]:$_type;
}

/**
 * 获取表名（不含表前缀）
 * @param string $model_id
 * @return string 表名
 * @author huajie <banhuajie@163.com>
 */
function get_table_name($model_id = null){
	if(empty($model_id)){
		return false;
	}
	$Model = M('Model');
	$name = '';
	$info = $Model->getById($model_id);
	if($info['extend'] != 0){
		$name = $Model->getFieldById($info['extend'], 'name').'_';
	}
	$name .= $info['name'];
	return $name;
}


/**
 * 获取属性信息并缓存
 * @param  integer $id    属性ID
 * @param  string  $field 要获取的字段名
 * @return string         属性信息
 */
function get_model_attribute($model_id, $group = true,$fields=true){
	static $list;
	
	/* 非法ID */
	if(empty($model_id) || !is_numeric($model_id)){
		return '';
	}
	
	/* 获取属性 */
	if(!isset($list[$model_id])){
		$map = array('model_id'=>$model_id);
		$extend = M('Model')->getFieldById($model_id,'extend');
		
		if($extend){
			$map = array('model_id'=> array("in", array($model_id, $extend)));
		}
		$info = M('Attribute')->where($map)->field($fields)->select();
		$list[$model_id] = $info;
	}
	
	$attr = array();
	if($group){
		foreach ($list[$model_id] as $value) {
			$attr[$value['id']] = $value;
		}
		$model     = M("Model")->field("field_sort,attribute_list,attribute_alias")->find($model_id);
		$attribute = explode(",", $model['attribute_list']);
		if (empty($model['field_sort'])) { //未排序
			$group = array(1 => array_merge($attr));
		} else {
			$group = json_decode($model['field_sort'], true);
			
			$keys = array_keys($group);
			foreach ($group as &$value) {
				foreach ($value as $key => $val) {
					$value[$key] = $attr[$val];
					unset($attr[$val]);
				}
			}
			
			if (!empty($attr)) {
				foreach ($attr as $key => $val) {
					if (!in_array($val['id'], $attribute)) {
						unset($attr[$key]);
					}
				}
				$group[$keys[0]] = array_merge($group[$keys[0]], $attr);
			}
		}
		if (!empty($model['attribute_alias'])) {
			$alias  = preg_split('/[;\r\n]+/s', $model['attribute_alias']);
			$fields = array();
			foreach ($alias as &$value) {
				$val             = explode(':', $value);
				$fields[$val[0]] = $val[1];
			}
			foreach ($group as &$value) {
				foreach ($value as $key => $val) {
					if (!empty($fields[$val['name']])) {
						$value[$key]['title'] = $fields[$val['name']];
					}
				}
			}
		}
		$attr = $group;
	}else{
		foreach ($list[$model_id] as $value) {
			$attr[$value['name']] = $value;
		}
	}
	return $attr;
}

/* 解析列表定义规则*/

function get_list_field($data, $grid){
	// 获取当前字段数据
	foreach($grid['field'] as $field){
		$array  =   explode('|',$field);
		$temp  =    $data[$array[0]];
		// 函数支持
		if(isset($array[1])){
			$temp = call_user_func($array[1], $temp);
		}
		$data2[$array[0]]    =   $temp;
	}
	if(!empty($grid['format'])){
		$value  =   preg_replace_callback('/\[([a-z_]+)\]/', function($match) use($data2){return $data2[$match[1]];}, $grid['format']);
	}else{
		$value  =   implode(' ',$data2);
	}
	// 链接支持
	if('title' == $grid['field'][0] && '目录' == $data['type'] ){
		// 目录类型自动设置子文档列表链接
		$grid['href']   =   '[LIST]';
	}
	if(!empty($grid['href'])){
		$links  =   explode(',',$grid['href']);
		foreach($links as $link){
			$array  =   explode('|',$link);
			$href   =   $array[0];
			if(preg_match('/^\[([a-z_]+)\]$/',$href,$matches)){
				$val[]  =   $data2[$matches[1]];
			}else{
				
				$show   =   isset($array[1])?$array[1]:$value;
				// 替换系统特殊字符串
				$href   =   str_replace(
						array('[REALDEL]','[DELETE]','[EDIT]','[LIST]'),
						array(
								'realdel?ids=['.strtolower($grid['pk']).']',
								'setstatus?status=-1&ids=['.strtolower($grid['pk']).']',
								'edit?id=['.strtolower($grid['pk']).']&model=[model_id]&cate_id=[category_id]',
								'index?pid=['.strtolower($grid['pk']).']&model=[model_id]&cate_id=[category_id]'),
						$href);
				
				// 替换数据变量
				$href   =   preg_replace_callback('/\[([a-z_]+)\]/', function($match) use($data){return $data[$match[1]];}, $href);
				
				$val[]  =   '<a href="'.U($href).'">'.$show.'</a>';
			}
		}
		
		$value  =   implode(' ',$val);
	}
	return $value;
}

// 分析枚举类型配置值 格式 a:名称1,b:名称2
function parse_config_attr($string) {
	$array = preg_split('/[,;\r\n]+/', trim($string, ",;\r\n"));
	if(strpos($string,':')){
		$value  =   array();
		foreach ($array as $val) {
			list($k, $v) = explode(':', $val);
			$value[$k]   = $v;
		}
	}else{
		$value  =   $array;
	}
	return $value;
}

/**
 * 获取分类信息并缓存分类
 * @param  integer $id    分类ID
 * @param  string  $field 要获取的字段名
 * @return string         分类信息
 */
function get_category($id, $field = null){
	static $list;
	
	/* 非法分类ID */
	if(empty($id) || !is_numeric($id)){
		return '';
	}
	
	/* 读取缓存数据 */
	if(empty($list)){
		$list = S('sys_category_list');
	}
	
	/* 获取分类名称 */
	if(!isset($list[$id])){
		$cate = M('Category')->find($id);
		if(!$cate || 1 != $cate['status']){ //不存在分类，或分类被禁用
			return '';
		}
		$list[$id] = $cate;
		S('sys_category_list', $list); //更新缓存
	}
	return is_null($field) ? $list[$id] : $list[$id][$field];
}

/**
 * 获取Portal应用当前模板下的模板列表
 * @return array
 */
function sp_admin_get_tpl_file_list(){
	$template_path=C("SP_TMPL_PATH").C("SP_DEFAULT_THEME")."/Portal/";
	$files=sp_scan_dir($template_path."*");
	$tpl_files=array();
	foreach ($files as $f){
		if($f!="." || $f!=".."){
			if(is_file($template_path.$f)){
				$suffix=C("TMPL_TEMPLATE_SUFFIX");
				$result=preg_match("/$suffix$/", $f);
				if($result){
					$tpl=str_replace($suffix, "", $f);
					$tpl_files[$tpl]=$tpl;
				}else if(preg_match("/\.php$/", $f)){
					$tpl=str_replace($suffix, "", $f);
					$tpl_files[$tpl]=$tpl;
				}
			}
		}
	}
	return $tpl_files;
}

/**
 * 字符串转换为数组，主要用于把分隔符调整到第二个参数
 * @param  string $str  要分割的字符串
 * @param  string $glue 分割符
 * @return array
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
function str2arr($str = '', $glue = ','){
	return explode($glue, $str);
}

/**
 * 数组转换为字符串，主要用于把分隔符调整到第二个参数
 * @param  array  $arr  要连接的数组
 * @param  string $glue 分割符
 * @return string
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
function arr2str($arr = array(), $glue = ','){
	return implode($glue, $arr);
}


/**
* @desc 
* @access 
* @param array $arr 多维数组
* @return 
* @example 
* @date 2017年6月23日
* @author benjamin
* @example $arr = Array
(
	//回调函数参数，auto里面自定义
    [auto] => Array
        (
            [0] => pic
            [1] => arr2str_pic
            [2] => 3
            [3] => function_call_user_func
            [4] => Array
                (
                    [sex] => 0
                    [pic] => Array
                        (
                            [0] => module/20170623/594c6cb01ca98.jpg
                            [1] => module/20170623/594c6cb0485d0.png
                        )

                    [pic_alt] => Array
                        (
                            [0] => timg1.jpg
                            [1] => 7PZk47.png
                        )

                    [love] => Array
                        (
                            [0] => 1
                            [1] => 2
                            [2] => 3
                        )

                    [smeta] => Array
                        (
                            [template] => facilitate
                        )

                    [desc] => <p>3333<br/></p>
                )

        )
	//表单post数据
    [data] => Array
        (
            [sex] => 0
            [pic] => Array
                (
                    [0] => module/20170623/594c6cb01ca98.jpg
                    [1] => module/20170623/594c6cb0485d0.png
                )

            [love] => Array
                (
                    [0] => 1
                    [1] => 2
                    [2] => 3
                )

            [desc] => <p>3333<br/></p>
        )

)

*/
function arr2str_pic($arr = array(),$gule=","){
	error_log(print_r($arr,true),3,'args.txt');
	#表单图片名
	$pic     = $arr['auto'][0];
	#表单图片alt名
	$pic_alt = $arr['auto'][0].'_alt';
	
	$auto = $arr['auto'];
	$data = $arr['auto'][4];
	
	$img = array();
	foreach ($data[$pic] as $k => $v){
		$img[$k]['url'] = $v;
		$img[$k]['alt'] = $data[$pic_alt][$k];
 	}
	
 	return json_encode($img);
}

/**
 * 系统加密方法
 * @param string $data 要加密的字符串
 * @param string $key  加密密钥
 * @param int $expire  过期时间 单位 秒
 * @return string
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
function think_encrypt($data, $key = '', $expire = 0) {
	$key  = md5(empty($key) ? C('DATA_AUTH_KEY') : $key);
	$data = base64_encode($data);
	$x    = 0;
	$len  = strlen($data);
	$l    = strlen($key);
	$char = '';
	
	for ($i = 0; $i < $len; $i++) {
		if ($x == $l) $x = 0;
		$char .= substr($key, $x, 1);
		$x++;
	}
	
	$str = sprintf('%010d', $expire ? $expire + time():0);
	
	for ($i = 0; $i < $len; $i++) {
		$str .= chr(ord(substr($data, $i, 1)) + (ord(substr($char, $i, 1)))%256);
	}
	return str_replace(array('+','/','='),array('-','_',''),base64_encode($str));
}

/**
 * 系统解密方法
 * @param  string $data 要解密的字符串 （必须是think_encrypt方法加密的字符串）
 * @param  string $key  加密密钥
 * @return string
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
function think_decrypt($data, $key = ''){
	$key    = md5(empty($key) ? C('DATA_AUTH_KEY') : $key);
	$data   = str_replace(array('-','_'),array('+','/'),$data);
	$mod4   = strlen($data) % 4;
	if ($mod4) {
		$data .= substr('====', $mod4);
	}
	$data   = base64_decode($data);
	$expire = substr($data,0,10);
	$data   = substr($data,10);
	
	if($expire > 0 && $expire < time()) {
		return '';
	}
	$x      = 0;
	$len    = strlen($data);
	$l      = strlen($key);
	$char   = $str = '';
	
	for ($i = 0; $i < $len; $i++) {
		if ($x == $l) $x = 0;
		$char .= substr($key, $x, 1);
		$x++;
	}
	
	for ($i = 0; $i < $len; $i++) {
		if (ord(substr($data, $i, 1))<ord(substr($char, $i, 1))) {
			$str .= chr((ord(substr($data, $i, 1)) + 256) - ord(substr($char, $i, 1)));
		}else{
			$str .= chr(ord(substr($data, $i, 1)) - ord(substr($char, $i, 1)));
		}
	}
	return base64_decode($str);
}

/**
 * 数据签名认证
 * @param  array  $data 被认证的数据
 * @return string       签名
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
function data_auth_sign($data) {
	//数据类型检测
	if(!is_array($data)){
		$data = (array)$data;
	}
	ksort($data); //排序
	$code = http_build_query($data); //url编码并生成query字符串
	$sign = sha1($code); //生成签名
	return $sign;
}

/**
 * 将list_to_tree的树还原成列表
 * @param  array $tree  原来的树
 * @param  string $child 孩子节点的键
 * @param  string $order 排序显示的键，一般是主键 升序排列
 * @param  array  $list  过渡用的中间数组，
 * @return array        返回排过序的列表数组
 * @author yangweijie <yangweijiester@gmail.com>
 */
function tree_to_list($tree, $child = '_child', $order='id', &$list = array()){
	if(is_array($tree)) {
		foreach ($tree as $key => $value) {
			$reffer = $value;
			if(isset($reffer[$child])){
				unset($reffer[$child]);
				tree_to_list($value[$child], $child, $order, $list);
			}
			$list[] = $reffer;
		}
		$list = list_sort_by($list, $order, $sortby='asc');
	}
	return $list;
}

/**
 * 格式化字节大小
 * @param  number $size      字节数
 * @param  string $delimiter 数字和单位分隔符
 * @return string            格式化后的带单位的大小
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
function format_bytes($size, $delimiter = '') {
	$units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
	for ($i = 0; $size >= 1024 && $i < 5; $i++) $size /= 1024;
	return round($size, 2) . $delimiter . $units[$i];
}
