<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 麦当苗儿 <zuojiazi@vip.qq.com> <http://zjzit.cn>
// +----------------------------------------------------------------------

namespace Scar;



class Error
{
    /**
     * 注册异常处理
     * @access public
     * @return void
     */
    public static function register()
    {
        error_reporting(E_ALL);
        set_error_handler([__CLASS__, 'appError']);
        set_exception_handler([__CLASS__, 'appException']);
        register_shutdown_function([__CLASS__, 'appShutdown']);
    }

    /**
     * 异常处理
     * @access public
     * @param  \Exception|\Throwable $e 异常
     * @return void
     */
    public static function appException($e)
    {
       echo "异常处理\n";
       print_r($e->getMessage());
       echo "\n";
       print_r( $e->getTraceAsString() );
	    echo "\n";
    }

	/**
	 * 错误处理
	 * @access public
	 * @param  integer $errno      错误编号
	 * @param  integer $errstr     详细错误信息
	 * @param  string  $errfile    出错的文件
	 * @param  integer $errline    出错行号
	 *
	 * @throws \ErrorException
	 */
    public static function appError($errno, $errstr, $errfile = '', $errline = 0)
    {
    	echo "错误处理\n";
		print_r( '错误编号: '.$errno );
		echo "\n";
	    print_r( '详细错误信息: '.$errstr );
	    echo "\n";
	    print_r( '出错的文件: '.$errfile );
	    echo "\n";
	    print_r( '出错行号: '.$errline );
	    echo "\n";
    }

    /**
     * 异常中止处理
     * @access public
     * @return void
     */
    public static function appShutdown()
    {
        // 将错误信息托管至 think\ErrorException
        if (!is_null($error = error_get_last()) && self::isFatal($error['type'])) {
            self::appException(new \ErrorException(
                $error['type'], $error['message'], $error['file'], $error['line']
            ));
        }
    }

    /**
     * 确定错误类型是否致命
     * @access protected
     * @param  int $type 错误类型
     * @return bool
     */
    protected static function isFatal($type)
    {
        return in_array($type, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE]);
    }

	/**
	 * 获取异常处理的实例
	 * @return mixed
	 */
    public static function getExceptionHandler()
    {
        static $handle;


        return $handle;
    }
}
