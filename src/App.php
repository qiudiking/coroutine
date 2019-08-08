<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 2019/7/16
 * Time: 11:12
 */

namespace Scar;


use Scar\db\connector\Pool\MysqlPool;
use Scar\server\WebSocket;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Process;

class App
{


	public static $httpControllerPath = APP_PATH.'/application/Http/Controller';
	/**
	 * HTTP请求控制器
	 * @var string
	 */
	public static $httpController = '\\application\\Http\\Controller';

	/**
	 * webSocket 连接
	 * @var string
	 */
	public static $webSocketController = '\\application\\WebSocket';
	/**
	 * Task任务投递
	 * @var string
	 */
	public static $task = '\\application\\Task';
	/**
	 * 监听swoole事件
	 * @var string
	 */
	public static $listenerSwoole = '\\application\\Listener\\Swoole';
	/**
	 * 监听框架事件
	 * @var string
	 */
	public static $listenerScar = '\\application\\Listener\\Scar';
	/**
	 * 监听事件执行的方法名
	 * @var string
	 */
	public static $listenerMethod = 'handle';
	/**
	 * 配置文件目录
	 * @var string
	 */
	public $configPath = APP_PATH.'/config';
	/**
	 * swoole的配置文件
	 * @var string
	 */
	public static $swoole = 'swoole';
	/**
	 * 数据库的配置文件
	 * @var string
	 */
	public static $databases = 'databases';
	/**
	 * log日志目录
	 * @var string
	 */
	public static $logPath = APP_PATH.'/runtime/logs';
	/**
	 * 启动框架进程PID文件
	 * @var string
	 */
	public static $webSocket_pid_file = APP_PATH.'/runtime/webSocket.pid';

	/**
	 * 事件对象
	 * @var array
	 */
	public static $event = [];


	public static $daemonize = false;

	/**
	 * 运行
	 */
	public function run()
	{

		if( isset( $_SERVER['argv'][1] ) === false){
			echo "\033[31m请入参数\033[0m\n";
			echo "php scar.php start 或者 php scar.php stop\n";
			return false;
		}
		switch ( $_SERVER['argv'][1] ){
			case 'start':
				if(isset($_SERVER['argv'][2]) && $_SERVER['argv'][2] === '-d') self::$daemonize = true;
				$this->init();
				$this->start();
				break;
			case 'stop':
				$this->stop();
				break;
			default:
				echo "\033[31m参数输入错误\033[0m\n";
				echo "php scar.php start 或者 php scar.php stop\n";
				break;
		}
	}

	public function start()
	{
		$webSocket = new WebSocket();
		$webSocket->start();
	}

	public function stop()
	{
		if(is_file( self::$webSocket_pid_file )){
			$pid = file_get_contents(self::$webSocket_pid_file);
			$res = Process::kill($pid);
			if( $res ){
				echo  "\033[01;40;32m停止成功\033[0m\n";
			}else{
				echo "\033[31m停止失败\033[0m\n";
			}
		}else{
			echo "\033[31m主进程pid不存在\033[0m\n";
		}
	}

	/**
	 * 初始话
	 * @throws \Scar\Exception
	 */
	public function init()
	{
		date_default_timezone_set( 'Asia/Shanghai' );
		Config::getConfigPath( $this->configPath );
		(new Router())->run();
		$this->mysqlPool();
		$this->setLogPath();
	}

	/**
	 * 初始化MySQL连接池
	 * @throws \Scar\Exception
	 */
	public function mysqlPool()
	{
		$hostname = Config::get('hostname',App::$databases);
		$database = Config::get('database',App::$databases);
		if( strpos($hostname,',') !== false ){
			$hostname = explode(',',$hostname);
			if( strpos($database,',') ){
				$database = explode(',',$database);
			}
		}
		$data = [];
		if( is_array( $hostname ) ){
			foreach ( $hostname as $key => $item ){
				$host = $item;
				is_string($database) && $host = $host.$database;
				if(is_array($database) && isset($database[$key])){
					$host = $host.$database[$key];
				}else{
					throw new Exception('分布式数据库配置错误',32113);
				}
				$data[] = $host;
			}
		}else{
			$data[] = $hostname.$database;
		}
		$mysqlPool = new MysqlPool( $data );
		Container::getInstance()->set( $mysqlPool );
	}


	/**
	 * 日志初始化
	 */
	public function setLogPath()
	{
		if( extension_loaded('SeasLog') ){
			$logPath = Config::get('log.path');
			$logPath || $logPath = self::$logPath;
			\SeasLog::setBasePath( $logPath );
		}
	}

	/**
	 * 请求时初始化
	 */
	public static function  onRequestInit( Request $request, Response $response )
	{
		self::triggerEvent( self::$listenerSwoole.'\\Request',$request,$response );
		Db::recycle();
	}

	/**
	 * 注册事件
	 * @param \swoole_websocket_server $server
	 * @param                          $eventAll
	 * @param                          $defaultEvent
	 */
	public static function registerSwooleEvent( \swoole_websocket_server $server, $eventAll,$defaultEvent )
	{
		foreach ( $eventAll as $key =>$value ){
			if( array_key_exists($value,$defaultEvent) ){
				$server->on( $value,array($defaultEvent[$value][0],$defaultEvent[$value][1]) );
			}else{
				$class = self::$listenerSwoole.'\\'.ucfirst( $value ).'Event';
				if( class_exists( $class ) ){
					$instance = new $class;
					method_exists($instance,App::$listenerMethod) && $server->on( $value,array($instance,self::$listenerMethod) );
				}
			}
		}
	}

	/**
	 * 触发事件
	 * @param mixed ...$params
	 */
	public static function triggerEvent( ...$params )
	{
		$class = $params[0];
		unset( $params[0] );
		if( class_exists( $class ) ){
			if( isset( self::$event[$class]) ){
				$instance = self::$event[$class];
			}else{
				$instance = new $class;
				self::$event[$class] = $instance;
			}
			method_exists($instance,App::$listenerMethod) &&
			call_user_func_array(array($instance,App::$listenerMethod),$params);
			unset( $instance );
		}
	}
}