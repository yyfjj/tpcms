<?php
/**
 * @desc: api参数校验类
 * @author: benjamin by 2015-05-15
 * @example import("@.ORG.ApiParamsValidate");
 *          使用默认错误提示(一般推荐使用这个参数)
 *          $rulesArr = array('userid'=>array(
                                	         'userbyid'=>array(100681),
                                	         'required'=>array(100681)
                                	     ),
            	         'id'=>array(
                            	             'quanzibyid'=>array(100681),
                            	             'required'=>array(100681)
            	                       ),
            	         'type'=>array(
                            	             'favoritesfavtype'=>array(100681),
                            	             'required'=>array(100681)
            	         ));
                                使用自定义错误提示
            $rulesArr = array('userid'=>array(
                                	         'userbyid'=>array(100681,'不存在该用户'),
                                	         'required'=>array(100681,'该参数是必填参数')
                                	     ),
            	         'id'=>array(
                            	             'quanzibyid'=>array(100681,'不存在该圈子'),
                            	             'required'=>array(100681,'该参数是必填参数')
            	                       ),
            	         'type'=>array(
                            	             'favoritesfavtype'=>array(100681,'请填入正确关注类型'),
                            	             'required'=>array(100681,'该参数是必填参数')
            	         )); 	 
                                组合参数，即验证2个以上参数   
            $rulesArr = array('userid,questionid' => array('Cust' => array(170501)),
                              'questionid'=>array('IsExistQuestionid'=>array(170503),'Required'=>array(170504))
                            );                    
                                
            $apvObj  = new ApiParamsValidate($rulesArr,$_REQUEST);
            $tipsArr = $apvObj->doCheck();
                               如果参数校验完全正确$tipsArr的值是：
            array();
                               如果有错误信息返回如下：
            Array
            (
                [userid] => Array
                    (
                        [code] => 100681
                        [message] => userid参数的值【17106000】是非法数据,根据UserById规则验证
                    )
            
                [id] => Array
                    (
                        [code] => 100681
                        [message] => id参数的值【128】是非法数据,根据QuanziById规则验证
                    )
            )
            
 * @date: 2015年05月15日
 */
// namespace app\org\controller;

// use think\Config;
// use think\Controller;
// use think\Db;
// use think\Request;
// use think\Session;
class ApiParamsValidate
{
    private $_status = 0;//只有0和1两种值，当为0时，仅仅输出参数不符合规定的一种状态，当为1时，输出该参数所有的不符合规定

    function __construct($rulesArr,$dataArr)
    {
        $this->_rulesArr = $this->lowercase($rulesArr);
        $this->_dataArr  = $this->lowercase($dataArr);
    }
    
    private function lowercase($var)
    {
    	return $var;
    	if(empty($var) || !is_array($var)){
    		return $var;
    	}
    	
    	$tmpArr = array();
    	foreach ($var as $k => $v){
    		$lowercaseK = strtolower($k);
    		$tmpArr[$lowercaseK] = $v;
    	}
    	
    	return $tmpArr;
    }
    
    #参数校验客户端  benjamin by 2015-05-15
    function doCheck_bak()
    {
        $messages = array();
        foreach($this->_rulesArr as $param => $rulesDetailArr){
            #必须先检查在$rulesDetailArr里是否有required这个key
        	if(isset($rulesDetailArr['required'])){
                $ruleObj = new Required($param,$this);
                $checkArr = $ruleObj->rule();
                #记录不成功的参数
                if($checkArr['code'] !== 0){
                    $messages[$param] = $checkArr;
                    continue;
                }
            }
            #如果是非必须参数，并且未传递该参数，可忽略校验
            if(strpos($param,",") === false && strpos($param,":") === false)
            {
                if( array_key_exists($param, $this->_dataArr) === false)
                    continue;
            }
            else 
            {

                $tmpParamArr = explode(",", $param);
                $tmpErrNum      = 0;
                foreach ($tmpParamArr as $key => $value)
                {
                		$value = strpos($value, ':') > -1 ? substr($value,strpos($value, ':')+1) : $value;
                		$value = strpos($value, '->') > -1 ? substr($value,0,strpos($value, '->')) : $value;
                		
                    if( array_key_exists($value, $this->_dataArr) === false)
                    {
                        $tmpErrNum ++;
                    }
                }
                //都不存在
                if($tmpErrNum == count($tmpParamArr))
                {
                    continue;
                }
                //都存在
                elseif ($tmpErrNum == 0)
                {
                    
                }
                else 
                {
//                     //组合参数缺失
//                     $ruleObj = new Required($param,$this);
//                     $checkArr = $ruleObj->rule();
//                     #记录不成功的参数
//                     if($checkArr['code'] !== 0)
//                     {
//                         $messages[$param] = $checkArr;
//                         continue;
//                     }
                    
                    
                }
            }
            #校验
            foreach ($rulesDetailArr as $ruleNameStr => $ruleTipsArr)
            {
                $ruleObj = new $ruleNameStr($param,$this);
                
                $checkArr = $ruleObj->rule();
                #记录不成功的参数
                if($checkArr['code'] !== 0)
                {
                    #会形成多维数组，对输入参数进行一次完全校验
                    #$messages[$param][$ruleTipsArr[0]] = $checkArr;
                    if(isset($messages[$param]))
                    {
                        if($ruleObj->getClassName() == 'required')
                        {
                            $messages[$param] = $checkArr;
                        }
                    }
                    else
                    {
                        $messages[$param] = $checkArr;
                    }
                }
            }
        }
        return $messages;
    }

    
    function doCheck()
    {
    	$message = array();
    	
    	foreach($this->_rulesArr as $param => $rulesDetailArr){
	    	$require = new RequireValidate($param,$rulesDetailArr,$this->_dataArr,$this);
	    	$empty   = new EmptyValidate($param,$rulesDetailArr,$this->_dataArr,$this);
	    	$mparams = new MParamsValidate($param,$rulesDetailArr,$this->_dataArr,$this);
// 	    	$require = new RequireValidate($this->_rulesArr,$this->_dataArr,$this);
// 	    	$empty   = new EmptyValidate($this->_rulesArr,$this->_dataArr,$this);
// 	    	$mparams = new MParamsValidate($this->_rulesArr,$this->_dataArr,$this);
	    	$message = array_merge($message,$require->validate());
	    	$message = array_merge($message,$empty->validate());
	    	$message = array_merge($message,$mparams->validate());
    	}
    	
    	return $message;
    }

}

abstract class ACheck
{
	protected $param;
	protected $rulesDetailArr;
// 	protected $rulesArr;
	protected $dataArr;
	protected $apiParamsValidate;
	
	function __construct($param,$rulesDetailArr,$dataArr,ApiParamsValidate $apiParamsValidateObj)
	{
		$this->param = $param;	
		$this->rulesDetailArr  = $rulesDetailArr;
		$this->dataArr = $dataArr;
		$this->apiParamsValidate = $apiParamsValidateObj;
	}
// 	protected $rulesArr;
// 	protected $dataArr;
// 	protected $apiParamsValidate;
	
// 	function __construct($rulesArr,$dataArr,ApiParamsValidate $apiParamsValidateObj)
// 	{
// 		$this->rulesArr = $rulesArr;	
// 		$this->dataArr  = $dataArr;
// 		$this->apiParamsValidate = $apiParamsValidateObj;
// 	}
	
	abstract function validate();
}

class RequireValidate extends ACheck
{
	function validate()
	{
		$messages = array();
		
		if(!isset($this->rulesDetailArr['required'])){
			return $messages;
		}
		
// 		foreach($this->rulesArr as $param => $rulesDetailArr){
			#必须先检查在$rulesDetailArr里是否有required这个key
// 			if(isset($this->rulesDetailArr['required'])){
				$ruleObj = new Required($this->param,$this->apiParamsValidate);
				$checkArr = $ruleObj->rule();
				#记录不成功的参数
				if($checkArr['code'] !== 0){
					$messages[$this->param] = $checkArr;
// 					continue;
				}
// 			}
// 		}
		return $messages;
	}
}

/**
 * @desc: 多参数校验
 * @author: benjamin
 * @date: 2017年6月6日
 */
class MParamsValidate extends ACheck
{
	function validate()
	{
		$messages = array();
// 		foreach($this->rulesArr as $param => $rulesDetailArr){
			if(strpos($this->param,",") === false){
				if( array_key_exists($this->param, $this->dataArr) === false){
// 					continue;
					return $messages;
				}
			}else{
				$tmpParamArr = explode(",", $this->param);
				$tmpErrNum   = 0;
				foreach ($tmpParamArr as $key => $value){
					if( array_key_exists($value, $this->dataArr) === false){
						$tmpErrNum ++;
					}
				}
				//都不存在
				if($tmpErrNum == count($tmpParamArr)){
					return $messages;
				}
			}
			
			foreach ($this->rulesDetailArr as $ruleNameStr => $ruleTipsArr)
			{
				$ruleObj = new $ruleNameStr($this->param,$this->apiParamsValidate);
				
				$checkArr = $ruleObj->rule();
				#记录不成功的参数
				if($checkArr['code'] !== 0)
				{
					#会形成多维数组，对输入参数进行一次完全校验
					#$messages[$param][$ruleTipsArr[0]] = $checkArr;
					if(isset($messages[$this->param]))
					{
						if($ruleObj->getClassName() == 'required')
						{
							$messages[$this->param] = $checkArr;
						}
					}
					else
					{
						$messages[$this->param] = $checkArr;
					}
				}
			}
// 		}
		return $messages;
	}
}

class EmptyValidate extends ACheck
{
	function validate()
	{
		$messages = array();
		if($this->param !== ''){
			return $messages;
		}
// 		foreach($this->rulesArr as $param => $rulesDetailArr){
			#必须先检查在$rulesDetailArr里是否有required这个key
// 			if($this->param === ""){
				foreach ($this->rulesDetailArr as $ruleNameStr => $ruleTipsArr){
					$ruleObj = new $ruleNameStr($this->param,$this->apiParamsValidate);
					
					$checkArr = $ruleObj->rule();
					#记录不成功的参数
					if($checkArr['code'] !== 0)
					{
						#会形成多维数组，对输入参数进行一次完全校验
						#$messages[$param][$ruleTipsArr[0]] = $checkArr;
						if(isset($messages[$this->param]))
						{
							if($ruleObj->getClassName() == 'required')
							{
								$messages[$this->param] = $checkArr;
							}
						}
						else
						{
							$messages[$this->param] = $checkArr;
						}
					}
				}
// 			}
// 			if(isset($rulesDetailArr['required'])){
// 				$ruleObj = new Required($param,$this);
// 				$checkArr = $ruleObj->rule();
// 				#记录不成功的参数
// 				if($checkArr['code'] !== 0){
// 					$messages[$param] = $checkArr;
// 					continue;
// 				}
// 			}
// 		}
		return $messages;
	}
}

/**
 * @desc: api参数校验抽象类
 * @author: benjamin
 * @date: 2015年5月15日
 */
abstract class ARule
{
    protected $_currentClassName;
    protected $_currentClassNameLower;
    protected $_errorNum;
    protected $_messageStr;
    protected $_apiParamsValidateObj;
    protected $_paramNameStr;
    protected $_paramValueStr;
    #benjamin
    function __construct($paramNameStr,ApiParamsValidate $ApiParamsValidateObj)
    {
    	if($paramNameStr === ""){
    		$this->_paramNameStr     = "";
    		$this->_paramValueStr    = "";
    		
    	}elseif(strpos($paramNameStr,",") === false){
            $this->_paramNameStr     = $paramNameStr;
            $this->_paramValueStr    = isset($ApiParamsValidateObj->_dataArr[$paramNameStr]) ? $ApiParamsValidateObj->_dataArr[$paramNameStr] : null;
        }
        else 
        {
            $this->_paramNameStr     = explode(",",$paramNameStr);

            foreach ($this->_paramNameStr as $k => $v)
            {
                if(isset($ApiParamsValidateObj->_dataArr[$v]))
                    $this->_paramValueStr[]    = $ApiParamsValidateObj->_dataArr[$v];
                else 
                    $this->_paramValueStr[]    = null;
            }

        }
        $this->_currentClassName      = get_class($this);
        $this->_currentClassNameLastStrip = strrpos($this->_currentClassName,'\\');
        $this->_currentClassName = substr($this->_currentClassName, $this->_currentClassNameLastStrip);
//         die();
        
        
       $this->_currentClassNameLower = strtolower($this->_currentClassName);
       $this->_currentClassNameLowerLastStrip = strrpos($this->_currentClassNameLower,'\\');
       $this->_currentClassNameLower = substr($this->_currentClassNameLower,$this->_currentClassNameLowerLastStrip);
//         die();
        $this->_errorNum              = isset($ApiParamsValidateObj->_rulesArr[$paramNameStr][$this->_currentClassName][0]) ? 
                                        $ApiParamsValidateObj->_rulesArr[$paramNameStr][$this->_currentClassName][0] : 
                                        $ApiParamsValidateObj->_rulesArr[$paramNameStr][$this->_currentClassNameLower][0];
        $this->_messageStr            = isset($ApiParamsValidateObj->_rulesArr[$paramNameStr][$this->_currentClassName][1]) ? 
                                        $ApiParamsValidateObj->_rulesArr[$paramNameStr][$this->_currentClassName][1] : 
                                        @$ApiParamsValidateObj->_rulesArr[$paramNameStr][$this->_currentClassNameLower][1] ; 
        $this->_apiParamsValidateObj  = $ApiParamsValidateObj;
    }
    
    /**
     * @desc: 返回失败信息
     * @author: benjamin
     * @date: 2015年5月15日
     */
    function messageFail()
    {
        if(strtolower($this->_currentClassName) == 'required')
        {
            if(is_array($this->_paramValueStr))
            {		
            		if(!empty($this->_messageStr)) return array('code'=>$this->_errorNum,'message'=>$this->_messageStr);
                $this->_messageStr .= implode(",", $this->_paramNameStr).'是组合参数,'; 
                foreach ($this->_paramValueStr as $k => $v)
                {
                    if($v === null)
                    {
                        $this->_messageStr .= $this->_paramNameStr[$k].'参数缺失'; 
                    }
                    else 
                    {
                        $this->_messageStr .= $this->_paramNameStr[$k].'参数值是【'.$this->_paramValueStr[$k].'】';
                    }
                }
                return array('code'=>$this->_errorNum,'message'=>$this->_messageStr);
            }
            else 
            {
                return array('code'=>$this->_errorNum,'message'=>$this->_messageStr ? $this->_messageStr : $this->_paramNameStr.'参数缺失');
            }
        }
        $paramValueStr = is_array($this->_paramValueStr) ? implode(',',$this->_paramValueStr) : $this->_paramValueStr;
        $paramNameStr  = is_array($this->_paramNameStr) ? implode(',',$this->_paramNameStr) : $this->_paramNameStr;
        return array('code'=>$this->_errorNum,'message'=>$this->_messageStr ? $this->_messageStr : $paramNameStr.'参数的值【'.$paramValueStr.'】是非法数据,根据'.get_class($this).'规则验证');
    }
    
    /**
     * @desc: 返回成功信息
     * @author: benjamin
     * @date: 2015年5月15日
     */
    function messageOk()
    {
        return array('code'=>0);
    }
    
    /**
     * @desc: 取得当前运行的类名
     * @author: benjamin
     * @date: 2015年5月15日
     */
    function getClassName()
    {
        return $this->_currentClassName;
    }
    
    /**
     * @desc: 规则处理
     * @author: benjamin
     * @date: 2015年5月15日
     */
    function rule()
    {
        if($this->doRule($this->_paramValueStr) === false)
            return $this->messageFail();
        return $this->messageOk();       
    }
    
    /**
     * @desc: 规则处理的抽象类
     * @author: benjamin
     * @date: 2015年5月15日
     */
    abstract function doRule($paramValueStr);
    
}

/**
 * @desc: 必须参数的校验
 * @author: benjamin
 * @date: 2015年5月15日
 */
class Required extends ARule
{
    function doRule($paramValueStr)
    {
        if(is_array($paramValueStr))
        {
            foreach ($paramValueStr as $k => $v)
            {
                if($v === null)
                    return false;
            }
        }
        else 
        {
            if($paramValueStr === NULL)
                return false;
        }
        return true;
    }
}

/**
 * @desc: 验证格林威治时间
 * @author: benjamin
 * @date: 2015年5月15日
 */
class Time extends ARule
{
    function doRule($paramValueStr)
    {
        if(strtotime($paramValueStr))
            return true;
        return false;
    }
}

/**
 * @desc: 验证date函数生成的数据格式
 * @author: benjamin
 * @date: 2015年5月15日
 */
class Date extends ARule
{
    function doRule($paramValueStr)
    {
        if(strtotime($paramValueStr) > strtotime("2010-01-01"))
            return true;
        return false;
    }
}

/**
 * @desc: 根据用户id判断是否存在该用户
 * @author: benjamin
 * @date: 2015年5月15日
 */
class UserById extends ARule
{
    function doRule($paramValueStr)
    {
        if(model('User')->where("id=".intval($paramValueStr))->count() == 1){
        	return true;
        }
        return false;
    }
}

/**
 * @desc: 验证是否是整数
 * @author: benjamin
 * @date: 2017年6月5日
 */
class IsInt extends ARule
{
	function doRule($paramValueStr)
	{
		if(preg_match("/^[1-9][0-9]*$/",$paramValueStr)){
			return true;
		}
		return false;
	}
}

/**
 * @desc: 
 * @author: benjamin
 * @date: 2017年6月5日
 */
class MaxPageSize extends ARule
{
	function doRule($paramValueStr)
	{
		if($paramValueStr > 99){
			return false;
		}
		return true;
	}
}

/**
 * 根据视频id判断是否存在
 *
 * @date 2017-06-05
 * Class videoById
 */
class VideoById extends ARule
{
    function doRule($paramValueStr)
    {
    	$paramValueStr = intval($paramValueStr);
        if (model('Video')->where(array('id' => $paramValueStr))->count() == 1) {
            return true;
        }
        return false;
    }
}

class editVideo extends ARule
{
    function doRule($paramValueStr)
    {

    }
}

/**
 * @desc: 延迟时间限定时间
 * @author: benjamin
 * @date: 2017年6月5日
 */
class IsDelayScope extends ARule
{
	function doRule($paramValueStr)
	{
		$scope = array(1,3,5);
		if(in_array($paramValueStr, $scope)){
			return true;
		}
		
		return false;
	}
}

/**
 * @desc: 根据id判断该值是否存在
 * @author: benjamin
 * @date: 2017年6月5日
 */
class ArticletaskById extends ARule
{
	function doRule($paramValueStr)
	{
		if(Db::name("article_task")->where("id=".$paramValueStr)->count()){
			return true;
		}
		return false;
	}
}

/**
 * @desc: 判断是否登录
 * @author: benjamin
 * @date: 2017年6月6日
 */
class IsLogin extends  ARule
{
	function doRule($paramValueStr)
	{
		if (Session::has('userinfo')) {
			return true;
		}
		return false;
	}
}

/**
 * @desc: 判断是否是post提交
 * @author: benjamin
 * @date: 2017年6月6日
 */
class IsPost extends ARule
{
	function doRule($paramValueStr)
	{
		if(Request::instance()->isPOST()){
			return true;
		}
		return false;
	}
}

