<?php
/**
 * @desc: message
 * @author: benjamin
 * @date: 2015年6月24日
 * @example import("Logic.Message.Message",APP_PATH,".class.php");
	$messageQueueObj = new MessageQueue(1, "accountinfo");
	$messageQueueObj->addMessageClassName('JPushAccountinfo');
	$messageQueueObj->all();
 */
class MessageQueue
{
	private $_foreign_unique;
	private $_foreitn_type;
	
	private $_messageClassName;
	
	public function __construct($foreign_unique,$foreign_type)
	{
		$this->_foreign_unique = $foreign_unique;
		$this->_foreitn_type   = $foreign_type;
	}
	
	public function addMessageClassName($messageClassName)
	{
		$this->_messageClassName[] = $messageClassName;
		return $this;
	}
	
	public function single()
	{
		foreach($this->_messageClassName as $key => $message)
		{
			$messageObj = new $message($this->_foreign_unique,$this->_foreitn_type);
			if(method_exists ( $messageObj , 'single' )){
				$messageObj->single();
			}
		}
		return $this;
	}
	
	public function all()
	{
		foreach($this->_messageClassName as $key => $message)
		{
			$messageObj = new $message($this->_foreign_unique,$this->_foreitn_type);
			if(method_exists ( $messageObj , 'all' )){
				$messageObj->all();
			}
		}
		return $this;
	}
}

abstract class AMessage
{
	protected $jpushObj;
	protected $foreignArr;
	
	/**
	* @desc 
	* @access 
	* @param string $foreign_type   表名
	* @param mix    $foreign_unique 表的唯一索引
	* @return 
	* @example 
	* @date 2017年8月1日
	* @author benjamin
	*/
	function __construct($foreign_unique,$foreign_type)
	{
		import("Logic.Message.MessageQueue",APP_PATH,".class.php");
		$this->jpushObj = new \MessageJPush();
		$pk = M($foreign_type)->getPk();
		$this->foreignArr = M($foreign_type)->where("{$pk}=".$foreign_unique)->find();
	}
	
	abstract function single();
	abstract function all();
}


//=====================================极光推送============================================
//https://www.jpush.cn/push/apps/ca500a88420121101fb2ecd0/push/notification/
//https://github.com/jpush/jpush-api-php-client/blob/master/doc/api.md
//http://docs.jpush.io/server/php_sdk/
//========================================================================================

// use JPush\Model as M;
// use JPush\JPushClient;
// use JPush\Exception\APIConnectionException;
// use JPush\Exception\APIRequestException;
use JPush\Client;

abstract class AMessageJPush
{
    protected $_push        = null;
    protected $_messageObj  = null;
    
    public function __construct($projectStr = 'aiyou')
    {
//         import("@.ORG.Message.vendor.Autoload");
        vendor("jpush.autoload");
        if($projectStr == 'aiyou')
        {
        	$this->_push = new Client(JPUSH_APPKEY, JPUSH_MASTERSECRET);
        }
        elseif ($projectStr == 'maojie')
        {
        	$this->_push = new Client(JPUSH_APPKEY_MAOJIE, JPUSH_MASTERSECRET_MAOJIE);
        }
        elseif($projectStr == 'malihong')
        {
        	$this->_push = new Client(JPUSH_APPKEY_MALIHONG, JPUSH_MASTERSECRET_MALIHONG);
        }
        else 
        {
            throw new Exception("amessagejpush异常");
        }
        $this->_push = $this->_push->push()->options(array(
        		// sendno: 表示推送序号，纯粹用来作为 API 调用标识，
        		// API 返回时被原样返回，以方便 API 调用方匹配请求与返回
        		// 这里设置为 100 仅作为示例
        		
        		// 'sendno' => 100,
        		
        		// time_to_live: 表示离线消息保留时长(秒)，
        		// 推送当前用户不在线时，为该用户保留多长时间的离线消息，以便其上线时再次推送。
        		// 默认 86400 （1 天），最长 10 天。设置为 0 表示不保留离线消息，只有推送当前在线的用户可以收到
        		// 这里设置为 1 仅作为示例
        		
        		// 'time_to_live' => 1,
        		
        		// apns_production: 表示APNs是否生产环境，
        		// True 表示推送生产环境，False 表示要推送开发环境；如果不指定则默认为推送生产环境
        		
        		'apns_production' => PRODUCTION,
        		
        		// big_push_duration: 表示定速推送时长(分钟)，又名缓慢推送，把原本尽可能快的推送速度，降低下来，
        		// 给定的 n 分钟内，均匀地向这次推送的目标用户推送。最大值为1400.未设置则不是定速推送
        		// 这里设置为 1 仅作为示例
        		
        		// 'big_push_duration' => 1
        ));
    }

    /**
    * @desc 推送消息给单个app的所有设备
    * @access 
    * @param unknowtype
    * @return 
    * @example 
    * @date 2015年7月2日
    * @author benjamin
    */
    abstract function all($title,$content);
    abstract function single($title,$content,$registration_id);
}

class MessageJPush extends AMessageJPush
{
	#ios
	private $_badge = "+1";
	private $_content_availabel = true;
	private $_mutable_content = true;
	private $_category = 'jiguan';
	
	#android
	private $_build_id = 2;
	
	#ios和android共有的参数
	private $_extras = array('key'=>'value','jiquang');
	
	#消息模板对象
	private $_messageTemplate;
	
	public function setBadge($badge){
		$this->_badge = $badge;
		return $this;
	}
	
	public function setContent_available($content_available){
		$this->_content_availabel = $content_available;
		return $this;
	}
	
	public function setMutable_content($mutable_content){
		$this->_mutable_content = $mutable_content;
		return $this;
	}
	
	public function setExtras(array $extras){
		$this->_extras = $extras;
		return $this;
	}
	
	final protected function template($title,$content)
	{
		return $this->_push
		->setPlatform(array('ios', 'android'))
    		->iosNotification($title, array(
		    				'sound'             => 'sound.caf',
		    				'badge'             => $this->_badge,//非必须
		    				'content-available' => $this->_content_availabel,//非必须
		    				'mutable-content'   => $this->_mutable_content,//非必须
		    				'category'          => $this->_category,
		    				'extras'            => $this->_extras,
		    		))
    		->androidNotification($content, array(
		    				'title'    => $title,
		    				'build_id' => $this->_build_id,//非必须
		    				'extras'   => $this->_extras,
		    		))
		->options(array(
				// sendno: 表示推送序号，纯粹用来作为 API 调用标识，
				// API 返回时被原样返回，以方便 API 调用方匹配请求与返回
				// 这里设置为 100 仅作为示例
				
				// 'sendno' => 100,
				
				// time_to_live: 表示离线消息保留时长(秒)，
				// 推送当前用户不在线时，为该用户保留多长时间的离线消息，以便其上线时再次推送。
				// 默认 86400 （1 天），最长 10 天。设置为 0 表示不保留离线消息，只有推送当前在线的用户可以收到
				// 这里设置为 1 仅作为示例
				
				// 'time_to_live' => 1,
				
				// apns_production: 表示APNs是否生产环境，
				// True 表示推送生产环境，False 表示要推送开发环境；如果不指定则默认为推送生产环境
				
				'apns_production' => PRODUCTION,
				
				// big_push_duration: 表示定速推送时长(分钟)，又名缓慢推送，把原本尽可能快的推送速度，降低下来，
				// 给定的 n 分钟内，均匀地向这次推送的目标用户推送。最大值为1400.未设置则不是定速推送
				// 这里设置为 1 仅作为示例
				
				// 'big_push_duration' => 1
		));
// 		return $this;
// 		->send();
	}
	
	public function all($title,$content){
		return $this->template($title,$content)->addAllAudience()->send();
	}
	
	public function single($title,$content,$registration_id)
	{
		return $this->template($title,$content)->addRegistrationId($registration_id)->send();
	}
	
}


?>