<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 2019/7/15
 * Time: 17:25
 */

namespace Scar\server;


use Cake\Cache\Cache;
use Scar\App;
use Scar\cache\CachePool;
use Scar\cache\swoole\WebSocketTable;
use Scar\cache\SwooleTable;
use Scar\Config;
use Scar\Container;
use Scar\db\connector\Pool\MysqlPool;
use Scar\exception\OperationException;
use Scar\http\Response;
use Scar\Result;
use Scar\Router;
use Scar\http\Request as PsrRequest;
use Scar\http\Response as PsrResponse;

class WebSocket
{
	/**
	 * @var \swoole_websocket_server
	 */
	protected $server = null;

	protected  $callback = [
		'connect',
		'workerStart',
		'shutdown',
		'workerStop',
		'start',
		'workerError',
		'managerStart',
		'task',
		'finish',
		'close',
		'request',
		'pipeMessage',
		'packet',
		'workerExit',
		'managerStart',
		'managerStop',
		'open',
		'handshake',
		'message',
	];

	/**
	 * 默认事件
	 * @return array
	 */
	public  function defaultEvents()
	{
		return [
			'start'=>[$this,'onStart'],
			'workerStart'=>[$this,'onWorkerStart'],
			'workerExit'=>[$this,'onWorkerExit'],
			'task'=>[$this,'onTask'],
			'finish'=>[$this,'onFinish'],
			'message'=>[$this,'onMessage'],
			'open'=>[$this,'onOpen'],
			'handshake'=>[$this,'onHandshake'],
			'request'=>[$this,'onRequest'],
			'connect'=>[$this,'onConnect'],
			'close'=>[$this,'onClose'],
			'managerStart'=>[$this,'onManagerStart']
		];
	}



	public function onStart($server)
	{
		\SeasLog::info( 'master启动成功' );
		cli_set_process_title('Scar_Main');
		$class = App::$listenerSwoole . '\\StartEvent';
		App::triggerEvent( $class,$server );
	}

	public function onWorkerStart($server,$worker_id)
	{
		\SeasLog::info( 'worker启动成功' );
		if(!$server->taskworker){
			$process_name = 'Scar_Worker';
		}else{
			$process_name = 'Scar_Task';
		}
		cli_set_process_title($process_name);
		$class = App::$listenerSwoole . '\\WorkerStartEvent';
		App::triggerEvent( $class,$server,$worker_id );
	}

	public function onManagerStart( $server )
	{
		\SeasLog::info( 'manager启动成功' );
		cli_set_process_title('Scar_Message');
		$class = App::$listenerSwoole . '\\ManagerStartEvent';
		App::triggerEvent( $class,$server );
	}


	public function onWorkerExit( $server,  $worker_id)
	{
		$mysqlPool = Container::getInstance()->getMysqlPool();
		$mysqlPool->destruct();
		$cachePool = Container::getInstance()->getCachePool();
		$cachePool->destruct();
		$class = App::$listenerSwoole . '\\WorkerExitEvent';
		App::triggerEvent( $class,$server,$worker_id );
	}


	public function onTask( $server,  $task)
	{
		try{

			$data = unserialize( $task->data );
			if( isset($data['action']) === false ) throw new \Exception( 'onTask params not Action',4001 );
			$action = $data['action'];

			if( strpos($action,'::') === false ) throw new \Exception( 'onTask Action ont :: tag',4002 );
			list( $class,$method ) = explode('::',$action);

			$class = App::$task.'\\'.$class;
			if(  !class_exists($class) ) throw new \Exception( "onTask ont $class class",4003 );
			$instance = new $class($data, $server,$task );

			if( !method_exists( $instance,$method ) ) throw new \Exception( "onTask $class ont $method method",4004);
			$instance->$method($data, $server,$task );

		}catch(Exception $e){

			$result = 'errorCode:'.$e->getCode().PHP_EOL.(string) $e;
			\SeasLog::error( $result );
		}

	}

	public function onFinish( $server,  $task_id,  $data)
	{

	}


	public function onMessage( \swoole_websocket_server $server, \Swoole\WebSocket\Frame $frame )
	{
		$class = App::$listenerSwoole . '\\MessageEvent';
		App::triggerEvent( $class,$server,$frame );

		$uri = WebSocketTable::instance()->get($frame->fd,'uri');
		$class = App::$webSocketController.$uri;

		$instance = new $class;
		if( method_exists($instance,'message') ){
			$instance->message( $server, $frame );
		}
	}

	/**
	 * @param \swoole_websocket_server $server
	 * @param \swoole_http_request     $request
	 *
	 * @throws \Exception
	 */
	public function onOpen( \swoole_websocket_server $server,  \swoole_http_request $request  )
	{
		$class = App::$listenerSwoole . '\\OpenEvent';
		App::triggerEvent( $class,$server,$request );

		$uri = WebSocketTable::instance()->get($request->fd,'uri');

		$class = App::$webSocketController.$uri;

		$instance = new $class;
		if( method_exists($instance,'open') ){
		    $instance->open( $server, $request );
		}
	}

	/**
	 * @param \swoole_http_request  $request
	 * @param \swoole_http_response $response
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function onHandshake( \swoole_http_request $request, \swoole_http_response $response )
	{
		$class = App::$listenerSwoole . '\\HandshakeEvent';
		App::triggerEvent( $class,$request,$response );

		$uri = $request->server['request_uri'];
		if($uri === '/'){
			$uri = '\Index';
		}else{
			$uri = str_replace("/","\\",$uri);
		}
		$class = App::$webSocketController.$uri;

		if( class_exists($class) ){
		    $instance = new $class;
		    if(method_exists($instance,'handshake')){
			    $res = $instance->handshake( $request, $response);
			    if( $res === false ){
				    $response->end();
			        return false;
			    }
		    }
		}else{
			\SeasLog::warning($class.' not class ');
			$response->end();
			return false;
		}
		WebSocketTable::instance()->set( $request->fd,['uri'=>$uri] );

		$this->server->defer(function ()use ($request){
			$this->onOpen( $this->server, $request );
		});

		// websocket握手连接算法验证
		$secWebSocketKey = $request->header['sec-websocket-key'];
		$patten = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';
		if (0 === preg_match($patten, $secWebSocketKey) || 16 !== strlen(base64_decode($secWebSocketKey))) {
			$response->end();
			return false;
		}
		//echo $request->header['sec-websocket-key'];
		$key = base64_encode(sha1(
			$request->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11',
			true
		));

		$headers = [
			'Upgrade' => 'websocket',
			'Connection' => 'Upgrade',
			'Sec-WebSocket-Accept' => $key,
			'Sec-WebSocket-Version' => '13',
		];

		// WebSocket connection to 'ws://127.0.0.1:9502/'
		// failed: Error during WebSocket handshake:
		// Response must not include 'Sec-WebSocket-Protocol' header if not present in request: websocket
		if (isset($request->header['sec-websocket-protocol'])) {
			$headers['Sec-WebSocket-Protocol'] = $request->header['sec-websocket-protocol'];
		}

		foreach ($headers as $key => $val) {
			$response->header($key, $val);
		}

		$response->status(101);
		$response->end();
	}

	/**
	 * @param \swoole_websocket_server $server
	 * @param int                      $fd
	 * @param int                      $reactorId
	 */
	public function onConnect( \swoole_websocket_server $server, int $fd, int $reactorId )
	{
		$class = App::$listenerSwoole . '\\ConnectEvent';
		App::triggerEvent( $class,$server, $fd,  $reactorId );
	}

	/**
	 * @param \swoole_websocket_server $server
	 * @param int                      $fd
	 * @param int                      $reactorId
	 *
	 * @throws \Exception
	 */
	public function onClose( \swoole_websocket_server $server,int $fd, int $reactorId )
	{
		$class = App::$listenerSwoole . '\\CloseEvent';
		App::triggerEvent( $class,$server, $fd,  $reactorId );


		$connection_info = $server->connection_info( $fd );
		if( isset($connection_info['websocket_status']) ){

			$uri = WebSocketTable::instance()->get( $fd,'uri' );
			$webClass = App::$webSocketController.$uri;

			if( class_exists( $webClass ) ){
				$instance = new $webClass;

				if( method_exists($instance,'close') ){
					$instance->close( $server, $fd,$reactorId );
				}
			}
			WebSocketTable::instance()->del( $fd );
		}

	}

	/**
	 * @param \Swoole\Http\Request  $request
	 * @param \swoole_http_response $response
	 *
	 * @throws \ReflectionException
	 */
	public function onRequest(\Swoole\Http\Request $request ,\swoole_http_response $response)
	{
		try{
			App::onRequestInit( $request,$response );
			$psrRequest  = PsrRequest::new( $request );
			$psrResponse = PsrResponse::new( $response );
			Router::dispatch( $psrRequest, $psrResponse );
		}catch ( OperationException $o ){
			$result = Result::instance();
			$result->setCodeMes($o->getCode(),$o->getMessage());
			if($psrResponse instanceof Response){
				$psrResponse = $psrResponse->withContent( (string) $result );
				$psrResponse->send();
			}else{
				$response->header( 'Content-Type', 'text/html; charset=utf-8' );
				$response->end( (string) $result );
			}
		}catch(\Throwable $t){
			$result = 'errorCode:'.$t->getCode().PHP_EOL.(string) $t;
			$response->header( 'Content-Type', 'text/html; charset=utf-8' );
			\SeasLog::error( $result );
			if($psrResponse instanceof Response){
				if($psrResponse->getStatusCode() === 200){
					$psrResponse = $psrResponse->withStatus(500);
				}
				$psrResponse = $psrResponse->withContent(  $result );
				$psrResponse->send();
			}else{
				$response->status(500);
				$response->end(  $result );
			}
		}
	}




	public function start()
    {
	    $config = Config::get('',App::$swoole);
		$setData = $config['http']['set'];
		$setData['pid_file'] = App::$webSocket_pid_file;
		App::$daemonize && $setData['daemonize'] = 1;
		$setData['reload_async'] = true;
		$setData['task_enable_coroutine'] = true;

		App::loadSwooleMemory();

	    $this->server = new  \swoole_websocket_server($config['http']['bind']['host'],$config['http']['bind']['port']);
	    Container::getInstance()->set( $this->server );
	    $this->server->set( $setData );

	    App::registerSwooleEvent( $this->server,$this->callback,$this->defaultEvents() );
	    echo  "\033[01;40;32m启动成功\033[0m\n";
	    $this->server->start();
    }
}