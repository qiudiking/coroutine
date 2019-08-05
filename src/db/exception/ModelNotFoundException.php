<?php
// +----------------------------------------------------------------------
// | ScarPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://Scarphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 麦当苗儿 <zuojiazi@vip.qq.com> <http://zjzit.cn>
// +----------------------------------------------------------------------

namespace Scar\db\exception;


class ModelNotFoundException extends DbException
{
    protected $model;

    /**
     * 构造方法
     * @param string $message
     * @param string $model
     */
    public function __construct($message, $model = '', array $config = [])
    {
        $this->message = $message;
        $this->model   = $model;

        $this->setData('Database Config', $config);
    }

    /**
     * 获取模型类名
     * @access public
     * @return string
     */
    public function getModel()
    {
        return $this->model;
    }

}
