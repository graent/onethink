<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2012 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 朱亚杰 <zhuyajie@topthink.net>
// +----------------------------------------------------------------------

/**
 * 权限管理控制器
 * Class AuthManagerController
 * @author 朱亚杰 <zhuyajie@topthink.net>
 */
class AuthManagerController extends AdminController{

    static $i =1;

    static protected $deny  = array('updateRules');

    /* 保存允许所有管理员访问的公共方法 */
    static protected $allow = array();

    static protected $nodes= array(

        array('title'=>'权限管理','url'=>'AuthManager/index','group'=>'用户管理',
              'operator'=>array(
                  array('title'=>'编辑','url'=>'AuthManager/editGroup'),
                  array('title'=>'删除','url'=>'AuthManager/changeStatus?method=deleteGroup'),
                  array('title'=>'禁用','url'=>'AuthManager/changeStatus?method=forbidGroup'),
                  array('title'=>'恢复','url'=>'AuthManager/changeStatus?method=resumeGroup'),
              ),
        ),
    );

    /**
     * 后台节点配置的url作为规则存入auth_rule
     * 执行新节点的插入,已有节点的更新,无效规则的删除三项任务
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function updateRules()
    {
        //需要新增的节点必然位于$nodes
        $nodes    = $this->returnNodes(false);

        $AuthRule = D('AuthRule');
        $map      = array('module'=>'admin','type'=>AuthRuleModel::RULE_URL);//status全部取出,以进行更新
        //需要更新和删除的节点必然位于$rules
        $rules    = $AuthRule->where($map)->order('name')->select();

        //构建insert数据
        $data     = array();//保存需要插入和更新的新节点
        foreach ($nodes as $value){
            $temp['name']   = $value['url'];
            $temp['title']  = $value['title'];
            $temp['module'] = 'admin';
            $temp['type']   = AuthRuleModel::RULE_URL;
            $temp['status'] = 1;
            $data[strtolower($temp['name'].$temp['module'].$temp['type'])] = $temp;//去除重复项
        }

        $update = array();//保存需要更新的节点
        $ids    = array();//保存需要删除的节点的id
        foreach ($rules as $index=>$rule){
            $key = strtolower($rule['name'].$rule['module'].$rule['type']);
            if ( isset($data[$key]) ) {//如果数据库中的规则与配置的节点匹配
                $data[$key]['id'] = $rule['id'];//为配置的节点补充数据库中对应的id值
                $update[] = $data[$key];//保存
                unset($data[$key]); //去除需要更新的节点,只留下需要插入的节点
                unset($rules[$index]);//去除需要更新的节点,只留下需要删除的节点
                unset($rule['condition']);
                $diff[$rule['id']]=$rule;//用户更新规则时的比较判断
            }elseif($rule['status']==1){
                $ids[] = $rule['id'];
            }
        }
        // $AuthRule->startTrans();
        //更新
        if ( count($update) ) {
            foreach ($update as $k=>$row){
                if ( $row!=$diff[$row['id']] ) {
                    $AuthRule->where(array('id'=>$row['id']))->save($row);
                }
            }
        }
        //删除
        if ( count($ids) ) {
            $AuthRule->where( array( 'id'=>array('IN',implode(',',$ids)) ) )->save(array('status'=>-1));
        }
        //新增
        if( count($data) ){
            $AuthRule->addAll(array_values($data));
        }
        if ( $AuthRule->getDbError() ) {
            // $AuthRule->rollback();
            trace('['.__METHOD__.']:'.$AuthRule->getDbError());
            return false;
        }else{
            // $AuthRule->commit();
            return true;
        }
    }
    

    /**
     * 权限管理首页
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function index()
    {
        $this->updateRules();
        $AuthGroup = D('AuthGroup');
        $groups    = $AuthGroup->where(array('status'=>array('egt',0),'module'=>'admin'))->select();
        $groups    = intToString($groups);
        $this->assign('list',$groups);
        $this->display();
    }

    /**
     * 创建管理员用户组
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function createGroup()
    {
        $node_list = $this->returnNodes();
        $map       = array('module'=>'admin','type'=>AuthRuleModel::RULE_URL,'status'=>1);
        $rules     = D('AuthRule')->where($map)->getField('name,id');

        $this->assign('auth_rules',$rules);
        $this->assign('node_list',$node_list);
        if ( empty($this->auth_group) ) {
            $this->assign('auth_group',array('title'=>null,'id'=>null,'description'=>null,'rules'=>null,));//排除notice信息
        }
        $this->display('managergroup');
    }

    /**
     * 编辑管理员用户组
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function editGroup()
    {
        $auth_group = D('AuthGroup')->where( array('module'=>'admin','type'=>AuthGroupModel::TYPE_ADMIN) )
                                    ->find( (int)$_GET['id'] );
        $this->assign('auth_group',$auth_group);
        $this->createGroup();
    }
    
    /**
     * 管理员用户组数据写入/更新
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function writeGroup()
    {
        $_POST['rules']  = implode(',',$_POST['rules']);
        $_POST['module'] = 'admin';
        $_POST['type']   = AuthGroupModel::TYPE_ADMIN;
        $AuthGroup       = D('AuthGroup');
        $data = $AuthGroup->create();
        if ( $data ) {
            if ( empty($data['id']) ) {
                $r = $AuthGroup->add();
            }else{
                $r = $AuthGroup->save();
            }
        }
        if($r===false){
            $this->error('操作失败'.$AuthGroup->getError());
        } else{
            $this->success('操作成功!');
        }
    }
    
    /**
     * 状态修改
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function changeStatus($method=null)
    {
        switch ( $method ){
            case 'forbidGroup':
                $this->forbid('AuthGroup');    
                break;
            case 'resumeGroup':
                $this->resume('AuthGroup');    
                break;
            case 'deleteGroup':
                $this->delete('AuthGroup');    
                break;
            default:
                $this->error('参数非法',__APP__);
        }
    }
    
}