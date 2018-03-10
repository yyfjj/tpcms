<?php
/**
 * @desc: 逻辑实现部分
 * @author: benjamin
 * @date: 2017年8月1日
 */
class JPushAccountinfo extends AMessage
{
	public function single()
	{
		
	}
	
	public function all()
	{
		$result = $this->jpushObj->all($this->foreignArr['accountname'], $this->foreignArr['nickname']);
	}
}

class WebAccountinfo extends AMessage
{
	public function single()
	{
		$data['foreign_unique']  = $this->foreignArr['id'];
		$data['foreign_type']    = 'account';
		$data['content']         = '这是一个消息测试';
		$data['reply']           = '';
		$data['sender_nicename'] = '系统';
		$data['sender_id']       = 0;
		$data['users_nicename']  = $this->foreignArr['accountname'];
		$data['users_id']        = $this->foreignArr['id'];
		$data['message_type']    = 0;//0、系统通知 1、回复 2、点赞
		$data['message_status']  = 0;
		$data['createtime']      = time();
		$data['updatetime']      = time();
		$data['listorder']       = 0;
		$data['status']          = 0;
		M('message')->add($data);
	}
	
	public function all()
	{
		
	}
}