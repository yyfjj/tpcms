<?php
/* 
 *该类主要是存放api接口数据返回和验证等api公共接口
 * guan
 */
namespace Common\Controller;
use Common\Controller\AppframeController;

class ApibaseController extends AppframeController
{
	protected $page=1;
	protected $page_size=PAGE_SIZE;
	
    public function __construct()
    {
    	parent::__construct();
    	
        import("ORG.ApiParamsValidate",APP_PATH,".class.php");
        
        if(isset($_REQUEST['page'])){
        	$rulesArr = array(
        			'page' => array('IsInt' => array(217001)),
        	);
        	$this->_doRules($rulesArr);
        	$this->page = (int)$_REQUEST['page'];
        }
        
        if(isset($_REQUEST['page_size'])){
        	$rulesArr = array(
        			'page_size' => array('IsInt' => array(217002),'MaxPageSize'=>array(217003)),
        	);
        	$this->_doRules($rulesArr);
        	$this->page_size = (int)$_REQUEST['page_size'];
        }
    }
    
    function __set($name, $value)
    {
    	if ($name == '__rulesArr'){
    		$this->_doRules($value);
    	}
    	elseif ($name == '__listArr'){
    		$this->returnType($value);
    	}
    }
    
    #benjamin by 2015-05-15
    public function _doRules($rulesArr)
    {
            $messagesArr = array('error_code'=>null,'msg'=>null,'data'=>null);
            $apvObj = new \ApiParamsValidate($rulesArr, $_REQUEST);
            $tipsArr = $apvObj->doCheck();
            if (!empty($tipsArr)) {
                    foreach ($tipsArr as $k => $v) {
                            $messagesArr['error_code'] = $v['code'];
                            $messagesArr['msg'] .=$v['message'];
                    }
                    trim($messagesArr['msg'], ';');
                    $messagesArr['data']='';
                    $this->returnType($messagesArr);
            }
    }
    public function returnType($messageArr){

            header("Access-Control-Allow-Origin: *");
            header("Access-Control-Allow-Headers: Content-Type,Accept");
            $callback = isset($_REQUEST['callback']) ? $_REQUEST['callback'] : "";

            header('Content-Type: application/json');
            if($callback != '') {
                    echo '/**/' . $callback . '(' . json_encode($messageArr).')';
                    exit;
            }

            echo json_encode($messageArr);
            exit;
    }

    /**
     * 返回api的json
     *
     * @param $error_code   错误码:0为正确
     * @param $msg          消息
     * @param array $data         数据
     */
    public function returnApiJson($error_code, $msg, $data = array())
    {
        $this->returnType(array('error_code' => $error_code, 'msg' => $msg, 'data' => $data));
    }
}
