<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2013 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 麦当苗儿 <zuojiazi@vip.qq.com>
// +----------------------------------------------------------------------

namespace Common\Model;
use Think\Model;

/**
 * 配置模型
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */

class ConfigModel extends Model {
	protected $_validate = array(
		array('name', 'require', '标识不能为空', self::EXISTS_VALIDATE, 'regex', self::MODEL_BOTH),
		array('name', '', '标识已经存在', self::VALUE_VALIDATE, 'unique', self::MODEL_BOTH),
		array('title', 'require', '名称不能为空', self::MUST_VALIDATE , 'regex', self::MODEL_BOTH),
	);

	protected $_auto = array(
		array('name', 'strtoupper', self::MODEL_BOTH, 'function'),
		array('create_time', NOW_TIME, self::MODEL_INSERT),
		array('update_time', NOW_TIME, self::MODEL_BOTH),
		array('status', '1', self::MODEL_BOTH),
	);

	/**
	 * 获取配置列表
	 * @return array 配置数组
	 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
	 */
	public function lists(){
		$map    = array('status' => 1);
		$data   = $this->where($map)->select();
		
		$config = array();
		if($data && is_array($data)){
			foreach ($data as $value) {
				$config[$value['name']] = $this->parse($value['type'], $value['value']);
			}
		}
		return $config;
	}

	/**
	 * 根据配置类型解析配置
	 * @param  integer $type  配置类型
	 * @param  string  $value 配置值
	 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
	 */
	private function parse($type, $value){
		switch ($type) {
			case 3: //解析数组
				$value = preg_split('/[,;\r\n]+/', trim($value, ",;\r\n"));
				break;
			case 4: //解析枚举
				$values = preg_split('/[,;\r\n]+/', trim($value, ",;\r\n"));
				$value  = array();
				foreach ($values as $val) {
					list($k, $v) = explode(':', $val);
					$value[$k]   = $v;
				}
				break;
		}
		return $value;
	}

}