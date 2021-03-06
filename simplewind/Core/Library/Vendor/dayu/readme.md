# 阿里大于接口使用说明
------------
|版本|作者|描述|时间|
|:--|:--|:--|:--|
|1.0|benjamin|1.初稿|2017.07.19|

## SDK获取方式
```
方式一：https://api.alidayu.com/docs/doc.htm?articleId=101618&docType=1&treeId=1
方式二：vendor下载
方式三：直接把本目录所在文件夹的所有文件复制到新站点即可
```


## SDK使用说明
```
https://api.alidayu.com/docs/api.htm?spm=a3142.7395905.4.6.wRDIqB&apiId=25450
```

## SDK使用例子

### SDK包的放置
```
将SDK包放在thinkphp3.2或者5版本的vendor的文件夹下即可，其中的config.php就是配置文件。
```
![阿里大于文件夹目录](http://i.imgur.com/yIkX9bp.png)

### 配置阿里大于用户名和密码
```
在SDK包放置位置有个config.php文件，内容如下
if (!defined("APPKEY"))
{
	define("APPKEY", "替换为公司的APPKEY");
}

if (!defined("SECRETKEY"))
{
	define("SECRETKEY", "替换为公司的SECRETKEY");
}
```
### 数据库的设置
```
CREATE TABLE `前缀_sms_code` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '流水号',
  `mobile` varchar(255) NOT NULL COMMENT '手机号码',
  `type` varchar(20) NOT NULL DEFAULT '' COMMENT '阿里大鱼模版名称',
  `code` varchar(10) NOT NULL DEFAULT '0' COMMENT '验证码',
  `status` tinyint(1) DEFAULT '0' COMMENT '状态：0=可用; 1=已使用',
  `content` varchar(256) DEFAULT NULL COMMENT '说明',
  `create_time` int(10) DEFAULT NULL COMMENT '创建时间',
  `update_time` int(10) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='手机验证码记录';
```

### 逻辑层的封装
```
一般在application目录下的Logic文件夹下，增加一个sms.class.php类，当然你可以将该类放在其他文件夹下，sms.class.php内容如下
/**
 * @desc: 阿里大鱼短信平台
 * @author: benjamin
 * @date: 2017年6月5日
 * @example
 * ==========================tp5.0、3.2============================
 * 
   import("Logic.sms",APP_PATH,".class.php");
   $sms66805325 = new \SMS_66805325();
   $sms66805325->setCode("890");
   $sms = new \Sms();
   $sms->setMobile("13047664596")->doIt($sms66805325);
 */
use think\Db;
class Sms
{
	protected $topClientObj;
	protected $alibabaAliqinFcSmsNumSendRequestObj;
	protected $mobile;
	protected $arguments;//模版参数
	protected $smsTemplateCode;//大鱼模版code
	
	function __construct()
	{
		vendor("dayu.TopSdk");
		new \Autoloader('TopClient');
		$this->topClientObj = new \TopClient();
		new \Autoloader('AlibabaAliqinFcSmsNumSendRequest');
		$this->alibabaAliqinFcSmsNumSendRequestObj = new \AlibabaAliqinFcSmsNumSendRequest();
		$this->topClientObj->appkey = APPKEY;
		$this->topClientObj->secretKey = SECRETKEY;
		
		$this->alibabaAliqinFcSmsNumSendRequestObj->setSmsType("normal");
		$this->alibabaAliqinFcSmsNumSendRequestObj->setSmsFreeSignName("魔文新媒体系统");
		
	}
	
	function setMobile($mobile)
	{
		$this->mobile = $mobile;
		$this->alibabaAliqinFcSmsNumSendRequestObj->setRecNum($mobile);
		return $this;
	}
	
	/**
	* @desc https://api.alidayu.com/doc2/apiDetail?spm=a3142.7395905.1999205496.19.cZzfM5&apiId=25450
	* @access 
	* @param obj $templateObj 短信模版对象
	* @return array array('status'=>1,'msg'=>bizId);   //发送成功
	*               array('status'=>0,'msg'=>'失败原因'); //发送失败
	* @example 
	* @date 2017年6月6日
	* @author benjamin
	*/
	function doIt($templateObj)
	{
		$templateObj->setMobile($this->mobile);
		$mix = $templateObj->get();
		$this->arguments= $mix['arguments'];
		$this->smsTemplateCode= $mix['smsTemplateCode'];
		$param = json_encode($mix['arguments']);
		
		$this->alibabaAliqinFcSmsNumSendRequestObj->setSmsParam($param);
		
		$this->alibabaAliqinFcSmsNumSendRequestObj->setSmsTemplateCode($mix['smsTemplateCode']);
		$resp = $this->topClientObj->execute($this->alibabaAliqinFcSmsNumSendRequestObj);
		$respArr = simplexml_to_array($resp);
		
		try {
			if($respArr['result']['success']){
				$this->_doOk($respArr['result']['model']);
				return (['status'=>1,'msg'=>'发送成功']);
			}else{
				$this->_doFail($respArr['sub_msg']);
				return (['status'=>0,'msg'=>$respArr['sub_msg']]);
			}
		} catch (Exception $e) {
			$this->_doFail("短信发送失败");
			return (['status'=>0,'msg'=>"短信发送失败"]);
		}
	}
	
	private function _doFail($fontStr)
	{
		#记录日志
		$data['mobile']      = $this->mobile;
		$data['type']        = $this->smsTemplateCode;
		$data['code']        = isset($this->arguments['code']) ? $this->arguments['code'] : 0;
		$data['status']      = 1;
		$data['create_time'] = time();
		$data['content']     = $fontStr;
		if(substr(THINK_VERSION,0,2) === "3."){
			$flag=M('sms_code')->add($data);
		}else{
			$flag=Db::name('sms_code')->insert($data);
		}
	}
	
	private function _doOk($bizId)
	{
		new \Autoloader('AlibabaAliqinFcSmsNumQueryRequest');
		$alibabaAliqinFcSmsNumQueryRequestObj = new \AlibabaAliqinFcSmsNumQueryRequest();
		$alibabaAliqinFcSmsNumQueryRequestObj->setBizId($bizId);
		$alibabaAliqinFcSmsNumQueryRequestObj->setRecNum($this->mobile);
		$alibabaAliqinFcSmsNumQueryRequestObj->setQueryDate(date("Ymd"));
		$alibabaAliqinFcSmsNumQueryRequestObj->setCurrentPage("1");
		$alibabaAliqinFcSmsNumQueryRequestObj->setPageSize("1");
		
		$resultObj = $this->topClientObj->execute($alibabaAliqinFcSmsNumQueryRequestObj);
		$resultArr = simplexml_to_array($resultObj);
		try {
			if($resultArr['values']['fc_partner_sms_detail_dto']['sms_content']){
				$fontStr = $resultArr['values']['fc_partner_sms_detail_dto']['sms_content'];
			}else{
				$fontStr = "短信发送失败";
			}
		} catch (Exception $e) {
			$fontStr = "短信发送失败";
		}
		
		#记录日志
		$data['mobile']  = $this->mobile;
		$data['type']    = $this->smsTemplateCode;
		$data['code']    = isset($this->arguments['code']) ? $this->arguments['code'] : 0;
		$data['status']  = 1;
		$data['create_time'] = time();
		$data['content'] = $fontStr;
		if(substr(THINK_VERSION,0,2) === "3."){
			$flag=M('sms_code')->add($data);
		}else{
			$flag=Db::name('sms_code')->insert($data);
		}
		return $flag;
	}
}



// abstract class ATemplate
class Template
{
	protected $paramsArr =array();
	protected $mobile ;
	
	function __set($key,$value)
	{
		$this->paramsArr['arguments'][$key] = $value;
	}
	
	function __construct()
	{
		$this->paramsArr['smsTemplateCode'] = get_class($this);
	}
	
	function setMobile($mobile)
	{
		$this->mobile = $mobile;
		return $this;
	}
	
	function get()
	{
		return $this->paramsArr;
	}
	
}

#发送验证码
class SMS_66805325 extends Template
{
	function setCode($code)
	{
		$this->code = $code;
		return $this;
	}
}

//平台有新任务时：
//【channelname】有新的任务发布，任务赏金【money】元，截止日期【endtime】有意请至魔文写手平台申请。
class SMS_69400021 extends Template
{
	function setChannelname($channelname)
	{
		$this->channelname= $channelname;
		return $this;
	}
	function setMoney($money)
	{
		$this->money = $money;
		return $this;
	}
	function setEndtime($endtime)
	{
		$this->endtime= $endtime;
		return $this;
	}
}

// 申请的任务选中接手时：
// 您申请的【title】已经被选中接手，截止日期【endtime】，请及时交稿完成任务。
class SMS_69365026 extends Template
{
	function setTitle($title)
	{
		$this->title = $title;
		return $this;
	}
	
	function setEndtime($endtime)
	{
		$this->endtime= $endtime;
		return $this;
	}
}

// 申请的任务未被选中接手时：
// 您申请的【title】未被选中接手，请申请其他任务，感谢您的参与。
class SMS_69395036 extends Template
{
	function setTitle($title)
	{
		$this->title = $title;
		return $this;
	}
}

// 任务截止时间还有24小时时：
// 您申请的【title】将于24小时后结束，请尽快完成任务，以免影响后续写作任务。
class SMS_69330132 extends Template
{
	function setTitle($title)
	{
		$this->title = $title;
		return $this;
	}
}

// 任务完成后：
// 您的任务【title】稿件已审核通过，任务完成，获得稿费【money】元。感谢参与。
class SMS_69465054 extends Template
{
	function setTitle($title)
	{
		$this->title = $title;
		return $this;
	}
	function setMoney($money)
	{
		$this->money = $money;
		return $this;
	}
}

// 任务已超时未完成：
// 您的任务【title】已超时，任务未完成，请以后及时完成稿件，以免影响后续任务接手。
class SMS_69320069 extends Template
{
	function setTitle($title)
	{
		$this->title = $title;
		return $this;
	}
}
```

### 业务层的使用
```
经过测试tp3.2以上版本均可完美使用
import("Logic.sms",APP_PATH,".class.php");
$sms66805325 = new \SMS_66805325();
$sms66805325->setCode("890");
$sms = new \Sms();
$sms->setMobile("13047664596")->doIt($sms66805325);
```
