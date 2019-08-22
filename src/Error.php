<?php

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
       $message =  'errorCode:'.$e->getCode().PHP_EOL.(string) $e;;
       self::saveLog( $message,SEASLOG_ERROR );
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
	    $message ='错误处理'. PHP_EOL.'错误类型: '.$errno;
	    $message .= PHP_EOL.'详细错误信息: '.$errstr;
	    $message .= PHP_EOL.'出错的文件: '.$errfile;
	    $message .= PHP_EOL.'出错行号: '.$errline;
	    self::saveLog( $message );
    }

	/**
	 * 保存日志
	 * @param        $message
	 * @param string $level
	 */
    public static function saveLog( $message, $level = SEASLOG_WARNING  )
    {
	    if( extension_loaded('SeasLog') ){
		    $logPath = Config::get('log.path');
		    $logPath || $logPath = APP::$logPath;
		    if( $logPath != \SeasLog::getBasePath() ){
			    \SeasLog::setBasePath( $logPath );
		    }
		    \SeasLog::log( $level, $message );
	    }
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
	        $message ='异常中止处理'. PHP_EOL.'错误类型: '.$error['type'];
	        $message .= PHP_EOL.'详细错误信息: '.$error['message'];
	        $message .= PHP_EOL.'出错的文件: '.$error['file'];
	        $message .= PHP_EOL.'出错行号: '.$error['line'];
	        self::saveLog( $message,SEASLOG_CRITICAL );
            self::appException(new \ErrorException(
	            $error['message'],$error['type'], $error['line'],  $error['file']
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
