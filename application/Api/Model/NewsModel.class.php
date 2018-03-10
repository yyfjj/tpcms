<?php
namespace Common\Model;
use Common\Model\CommonModel;
use Think\Model\AJoin;
use Think\Model\AFilter;
class NewsModel extends CommonModel{
	
}

/**
 * @desc:news和Users表关联
 * @author: benjamin
 * @date: 2017年6月19日
 */
class NewsUsers extends AJoin
{
	#主表,包含表名table、主鍵pk、外鍵fk
	protected $master = array('table'=>'news' ,'pk'=>'id','fk'=>'post_author');
	#从表,包含表名table、主键pk
	protected $slaver = array('table'=>'users','pk'=>'id');
	
	#该方法用于重构从表数据，无需考虑入参为空的情况
	function filter($arr)
	{
		return $arr;
	}
}

/**
 * @desc:Users和News表关联
 * @author: benjamin
 * @date: 2017年6月19日
 */
class UsersNews extends AJoin
{
	#主表,包含表名table、主鍵pk
	protected $master = array('table'=>'news' ,'pk'=>'id');
	#从表,包含表名table、主键pk、外鍵fk
	protected $slaver = array('table'=>'users','pk'=>'id','fk'=>'id');
	
	#该方法用于重构从表数据，无需考虑入参为空的情况
	function filter($arr)
	{
		return $arr;
	}
}

/**
 * @desc:输出数据重构，无需判断$listsArr为空情况
 * @author: benjamin
 * @date: 2017年6月19日
 * @memo：类命名规则  接口名称.ApiOutFilter
 */
class DemoApiOutFilter extends AFilter
{
	/**
	* @desc api接口输出重构
	* @access public
	* @param array $listsArr 输出数据
	* @return array
	* @example 
	* @date 2017年6月23日
	* @author benjamin
	* @memo:1、如果入参$listsArr为空，无需特别判断，框架已经做了处理
	*       2、方法名字必须是filterData
	*       3、无需如下所示对$listsArr做数组维度判断，接口输出是固定的，不可能一个接口输出多种格式数据，简而言之，要么是array_level($listsArr) == 1，要么就是array_level($listsArr) == 2
	*/
	function filterData($listsArr)
    {
		#该版本无需判断
        /* if(empty($listsArr))
        {
            return $listsArr;
        } */
        
        $return = array();
        #请注意，一般输出结果只有一种情况，要么是if部分，要么是else部分，无需条件判断
        if(array_level($listsArr) == 1)
        {
        	$return['id'] = (int)$listsArr['id'];
        	$return['users']['user_email'] = $listsArr['users']['user_email'];
        }
        else
        {
            foreach ($listsArr as $k => $v)
            {
                $return[$k]['id'] = (int)$v['id'];
                $return[$k]['users']['user_email'] = $v['users']['user_email'];
            }
        }
        return $return;
    }
}