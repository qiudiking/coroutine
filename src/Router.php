<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 2019/7/26
 * Time: 16:59
 */

namespace Scar;



use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;
use Scar\exception\OperationException;
use Scar\http\Request as PsrRequest;
use Scar\http\Response as PsrResponse;
use Scar\Doc\ClassDocInfo;
use Scar\http\Response;

class Router
{
	/**
	 * @var \FastRoute\Dispatcher
	 */
	protected static $dispatcher = null;

	protected static $controllerPool = [];

	protected static $methods = [
		'GET',
		'POST',
		'PUT',
		'PATCH',
		'DELETE ',
		'HEAD '
	];

	protected static $paramsType = [
		'Boolean',
		'Object[]',
		'Number[]',
		'String[]',
		'Object',
		'Number',
		'String'
	];

	protected static $paramsLength = [
		'..',
		'-',
		'>=',
		'<=',
		'=',
		'>',
		'<',
	];


	/**
	 * 路由初始化
	 * @throws \Exception
	 */
	public function run()
	{
		$this->controllerPath( App::$httpControllerPath );
		self::$dispatcher = simpleDispatcher(function(RouteCollector $r) {
			foreach ( self::$controllerPool as $key =>$value ){
				foreach ( $value as $k2 =>$v2 ){
					$r->addRoute($v2['method'], $v2['uri'],['class'=>$key,'method'=>$k2] );
				}
			}
		});
	}

	/**
	 *
	 * @param $path
	 *
	 * @throws \Exception
	 */
	public function controllerPath( $path )
	{
		$file = scandir( $path );
		foreach ( $file as $item ){
			if($item === '.' || $item === '..'){
				continue;
			}
			if( is_file($path.DS.$item) ){
				list($name,$ex) = explode('.',$item);
				if( $path === App::$httpControllerPath ){
				    $class = App::$httpController.'\\'.$name;
				}else{
					$string = str_replace(App::$httpControllerPath,'',$path);
					if( strpos( $string,'/') !== false ){
						$class = App::$httpController.str_replace('/','\\',$string).'\\'.$name;
					}else{
						$class = App::$httpController.'\\'.$string.'\\'.$name;
					}
				}
				$this->registerRouter( $class );
			}elseif (is_dir($path.DS.$item)){
				$this->controllerPath( $path.DS.$item );
			}
		}
	}

	/**
	 * 解析注释
	 * @param $class
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function registerRouter( $class )
	{
		$classInfo = ClassDocInfo::getClassInfo( $class );
		if( isset($classInfo['methods']) === false ) return false;
		foreach ( $classInfo['methods'] as $key =>$value){
			if( array_key_exists('api',$value) === false) continue;
			$this->uriMethodAnalysis( $value['api'],$uri,$method );
			self::$controllerPool[$class][$key] = [
				'uri'=>$uri,
				'method'=>$method,
			];
			if( array_key_exists('apiParam',$value) ){
				if( is_array($value['apiParam'] ) ){
					$params = $value['apiParam'];
				}else{
					$params[] = $value['apiParam'];
				}
				$res = $this->paramsAnalysis( $params,$class.' '.$key );

				if($res  !== false )self::$controllerPool[$class][$key]['params'] = $res;
			}
		}
	}

	/**
	 * 解析路由
	 * @param $string
	 * @param $uri
	 * @param $method
	 *
	 * @throws \Exception
	 */
	public function uriMethodAnalysis( $string,&$uri,&$method )
	{
		$braceBeforeLength = strpos($string,'{');
		$braceLaterLength = strpos($string,'}');
		$slashLength =  strpos($string,'/');
		if( $braceBeforeLength === false || $braceLaterLength === false || $slashLength === false  )
			throw new \Exception($string.': Annotation syntax error');
		$method = substr($string,$braceBeforeLength+1,$braceLaterLength-1 );
		if( strpos($method,'|') !== false ){
			$method = explode('|',$method);
			foreach ( $method as &$item ){
				$item = strtoupper( trim($item) );
				if( in_array( $item,self::$methods ) === false ) throw new \Exception($string.': Method Not Allowed');
			}
		}else{
			$method =  strtoupper( trim($method) );
			if( in_array( $method,self::$methods ) === false ) throw new \Exception($string.': Method Not Allowed');
		}
		$uri = trim(substr($string,$slashLength));
		$strEmptyLength = strpos( $uri,' ' );
		if( $strEmptyLength !== false) $uri = substr($uri,0, $strEmptyLength );
		$uri = strtolower($uri);
	}

	/**
	 * 参数分析
	 * @param array $params
	 *
	 * @return array|bool
	 * @throws \Exception
	 */
	public function paramsAnalysis( array $params,string $classMethod)
	{
		$data = [];

		foreach ( $params as $key =>$value ){
			$braceBeforeLength = strpos( $value,'{');
			$braceLaterLength = strrpos($value,'}');
			if($braceBeforeLength === false || $braceLaterLength === false ) throw new \Exception($classMethod." [$key]".' params error');
			$typeStr = substr( $value,$braceBeforeLength+1,$braceLaterLength-1 );

			$type = false;
			$required = false;
			$default = '';
			$rules = [];
			foreach ( self::$paramsType as $item1 ){
				if( stripos( $typeStr,$item1 ) !== false ){
					if(stripos( $typeStr,$item1 ) !== 0 ) throw new \Exception($classMethod." [$key]".' params type format error');
					$type = $item1;
					break;
				}
			}
			if( $type === false ) throw new \Exception($classMethod." [$key]".' params not type ');

			$braceBeforeLength2 = strpos($typeStr,'{');
			$braceLaterLength2 = strpos($typeStr,'}');
			if( $braceBeforeLength2 !== false && $braceLaterLength2 !== false ){
				$paramsSize = substr($typeStr,$braceBeforeLength2+1,$braceLaterLength2-$braceBeforeLength2-1);
				if( !$paramsSize ) throw new \Exception($classMethod." [$key]".' size not empty');
				$tag = '';
				foreach (self::$paramsLength as $item2){
					if(strpos($paramsSize,$item2) !==false ){
						$tag = $item2;
						break;
					}
				}
				switch ($tag){
					case '..':
						if( strpos($paramsSize,'..') === 0 ){
							$rule = [
								'type' =>'<=',
								'value'=>str_replace('..','',$paramsSize),
							];
						}elseif( strpos($paramsSize,'..') > 0){
							$arr = explode('..',$paramsSize);
							if($arr[0] && $arr[1] ){
								$rule = [
									'type'=>'-',
									'min' =>$arr[0],
									'max' =>$arr[1],
								];
							}else{
								$rule = [
									'type'=>'>=',
									'value'=>$arr[0],
								];
							}
						}else{
							$rule = [
								'type'=>'=',
								'value'=>$paramsSize,
							];
						}
						break;
					case '-':
						$arr = explode('-',$paramsSize);
						$rule = [
							'type'=>'-',
							'min' =>$arr[0],
							'max' =>$arr[1],
						];
						break;
					case '>=':
						$rule = [
							'type'=>'>=',
							'value'=>str_replace('>=','',$paramsSize),
						];
						break;
					case '<=':
						$rule = [
							'type'=>'<=',
							'value'=>str_replace('<=','',$paramsSize),
						];
						break;
					case '>':
						$rule = [
							'type'=>'>',
							'value'=>str_replace('>','',$paramsSize),
						];
						break;
					case '<':
						$rule = [
							'type'=>'<',
							'value' =>str_replace('>','',$paramsSize),
						];
						break;
					case '=':
						$rule = [
							'type'=>'=',
							'value' =>str_replace('=','',$paramsSize),
						];
						break;
					default:
						$rule = [
							'type'=>'=',
							'value' =>$paramsSize,
						];
				}
				if( isset($rule['type']) && $rule['type'] === '-'){
					if(is_numeric( $rule['min'] ) === false) throw new \Exception($classMethod." [$key]".' size not number  ');
					if(is_numeric( $rule['max'] ) === false) throw new \Exception($classMethod." [$key]". ' size not number  ');
				}elseif( isset($rule['type']) ){
					if( is_numeric( $rule['value']) === false )  throw new \Exception($classMethod." [$key]".' size not number  ');
				}
				isset($rule) && $rules['size'] = $rule;
			}




			$equal = strpos($typeStr,'=');
			if( $equal  !== false  ){
				if($type ==='Number'||$type === 'String' ){
					$allowedValues = substr($typeStr,$equal+1);
					if( strpos($allowedValues,',') ){
						$allowedArr = explode(',',$allowedValues);
						if( $type === 'String'){
							foreach ($allowedArr as  &$item){
								if( strpos($item,"'") !== false ){
								    $item = trim($item,"'");
								}elseif( strpos($item,'"') !== false ){
									$item = trim($item,'"');
								}
							}
						}
						$allowed = $allowedArr;
					}else{
						$allowed[] = $allowedValues;
					}
					$rules['allowed'] = $allowed;
				}else{
					throw  new \Exception($classMethod." [$key]".' only Number String set allowedValues');
				}
			}


			$fieldStr = substr($value,$braceLaterLength+1);
			if( $fieldStr ){
				$fieldStr =  ltrim($fieldStr);
				if( strpos($fieldStr,' ') !== false ){
					$fieldStr = substr( $fieldStr,0,strpos($fieldStr,' ') );
				}
				$bracketBeforeLength = strpos($fieldStr,'[');
				$bracketLaterLength = strpos($fieldStr,']');
				if( $bracketBeforeLength !== false && $bracketLaterLength !== false ){
					$fieldStr = substr( $fieldStr,$bracketBeforeLength+1,$bracketLaterLength-1 );
					$required = false;
				}else{
					$required = true;
				}
				if( strpos($fieldStr,'=') !== false  ){
					$default = substr($fieldStr,strpos($fieldStr,'=')+1);
					if( strpos($default,'"') !==  false)$default = trim( $default,'"' );
					if( strpos($default,"'") !==  false)$default = trim( $default,"'" );
					$fieldStr = substr( $fieldStr,0,strpos($fieldStr,'=') );
				}
				if( !$fieldStr )throw new \Exception($classMethod." [$key]".' field name not empty ');
				$data[ $fieldStr ] = [
					'type'=> $type,
					'required'=> $required,
				];
				isset($rules) && $data[ $fieldStr ]['rules'] =$rules;
				isset($default) && $data[ $fieldStr ]['default'] =$default;
			}else{
				if( $type === false ) throw new \Exception($classMethod." [$key]".' params field not empty ');
			}
		}
		return $data;
	}


	/**
	 * 分发请求
	 * @param $httpMethod
	 * @param $uri
	 *
	 * @throws \Exception
	 */
	public static function dispatch( PsrRequest $psrRequest, PsrResponse $psrResponse )
	{

		$request_method = $psrRequest->server('request_method');
		$request_uri = strtolower($psrRequest->server('request_uri'));
		$routeInfo = self::$dispatcher->dispatch( $request_method, $request_uri );
		switch ($routeInfo[0]) {
			case \FastRoute\Dispatcher::NOT_FOUND:
				// ... 404 Not Found
				$psrResponse = $psrResponse->withStatus(404)->withContent('Not Found');
				break;
			case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
				$allowedMethods = $routeInfo[1];
				// ... 405 Method Not Allowed
				$psrResponse = $psrResponse->withStatus(405)->withContent('Method Not Allowed');
				break;
			case \FastRoute\Dispatcher::FOUND:
				$handler = $routeInfo[1];
				$vars = $routeInfo[2];
				if(empty($vars) === false  ){
					if( empty($psrRequest->get()) === false )$vars = array_merge( $vars,$psrRequest->get() );
					$psrRequest = $psrRequest->withQueryParams( $vars );
				}
				$headers = Config::get('response');
				if(is_array( $headers ) && empty($headers) === false ) $psrResponse = $psrResponse->withHeaders( $headers );
				Context::setRequestResponse( $psrRequest,$psrResponse );
				self::verifyParams($psrRequest,$handler['class'],$handler['method']);
				if( class_exists( $handler['class'] ) ){
					$class = new $handler['class']( $psrRequest,$psrResponse );
					$action = $handler['method'];
					if( method_exists( $class, $action ) ){
						$psrRequest =  Context::getRequest();
						$psrResponse =  Context::getResponse();
						 $result =  $class->$action( $psrRequest,$psrResponse );
						 if($result instanceof Response) $psrResponse = $result;
					}else{
						throw new \Exception($handler['class'].' not Method');
					}
				}else{
					throw new \Exception($handler['class'].' not Controller');
				}
				break;
		}
		$psrResponse->send();
	}

	/**
	 * 校验参数
	 * @param \Scar\http\Request $request
	 * @param                    $class
	 * @param                    $method
	 *
	 * @throws \Scar\exception\OperationException
	 */
	public static function verifyParams( PsrRequest $request,$class ,$method )
	{
		$arr = self::$controllerPool[$class][$method];
		if( isset( $arr['params'] )  ){
			foreach( $arr['params'] as $key =>$value ){
				$param = self::getParamValue( $request,$key );
				if( $value['required'] ){
					if( !$param  ) throw new OperationException($key.'字段不能为空', 33002);
					if( $value['default'] ){
						if( $value['default'] != $param ) throw new OperationException($key.' 字段的值必须是 "'
						                                                               .$value['default'].'"', 33004);
					}
				}
				if ( $param ){
					switch ($arr['params'][$key]['type']){
						case 'Boolean':
							if( $param != 1 || $param != 0  ) throw new OperationException( "$key 字段必须是Boolean数据类型,0或1" ,33003);
							break;
						case 'String':
							if( empty( $value['rules']) === false ){
								if( isset( $value['rules']['size'])){
									$size = mb_strlen( $param );
									self::verifySize($value['rules']['size'],$size,$key.'字段值String长度必须是');
								}
								if(isset( $value['rules']['allowed'])){
									if( in_array($param, $value['rules']['allowed']) === false ){
										throw new OperationException( $key.'字段的值必须是'
										                              .print_r($value['rules']['allowed'],true),33001 );
									}
								}
							}
							break;
						case 'Number':
							if( is_numeric($param) ){
								if( empty( $value['rules']) === false ){
									if( isset( $value['rules']['size'])){
										self::verifySize($value['rules']['size'],$param,$key.'字段值Number大小必须是');
									}
									if(isset($value['rules']['allowed']) ){
										if( in_array($param, $value['rules']['allowed']) === false ){
											throw new OperationException( $key.'字段的值必须是'
											                              .print_r($value['rules']['allowed'],true),33001 );
										}
									}
								}
							}else{
								throw new OperationException( "$key  字段必须是Number数据类型" );
							}
							break;
						case 'Object':
							$Object = json_decode($param);
							if ($Object && is_object($Object) ) {
								$size = count($Object );
								self::verifySize($value['rules']['size'],$size,$key.'字段值Object的个数必须是');
							}else{
								throw new OperationException( "$key 字段必须是Object转json字符串数据类型",33003 );
							}
							break;
						case 'String[]':
							$StringArr = json_decode($param);
							if ($StringArr && is_object($StringArr) ) {
								$size = count($StringArr );
								self::verifySize($value['rules']['size'],$size,$key.'字段值String数组的个数必须是');
							}else{
								throw new OperationException( "$key 字段必须是String数组转json字符串数据类型" ,33003);
							}
							break;
						case 'Number[]':
							$NumberArr = json_decode($param);
							if ($NumberArr && is_object($NumberArr) ) {
								$size = count($NumberArr );
								self::verifySize($value['rules']['size'],$size,$key.'字段值Number数组的个数必须是');
							}else{
								throw new OperationException( "$key 字段必须是Number数组转json字符串数据类型",33003 );
							}
							break;
						case 'Object[]':
							$ObjectArr = json_decode($param);
							if ($ObjectArr && is_object($ObjectArr) ) {
								$size = count($ObjectArr );
								self::verifySize($value['rules']['size'],$size,$key.'字段值Object数组的个数必须是');
							}else{
								throw new OperationException( "$key 字段必须是Object数组转json字符串数据类型",33003 );
							}
							break;
					}
				}
			}
		}
	}

	/**
	 * 校验字段的value size
	 * @param        $value
	 * @param        $size
	 * @param string $msg
	 *
	 * @throws \Scar\exception\OperationException
	 */
	public static function verifySize( $value, $size ,$msg = '')
	{
		switch ( $value['type'] ){
			case '>=':
				if( !($size >= $value['value']) ) throw new OperationException($msg.'大于等于'.$value['value'],33005);
				break;
			case '<=':
				if( !($size <= $value['value']) ) throw new OperationException($msg.'小于等于'.$value['value'],33005);
				break;
			case '>':
				if( !($size > $value['value']) ) throw new OperationException($msg.'大于'.$value['value'],33005);
				break;
			case '<':
				if( !($size < $value['value']) ) throw new OperationException($msg.'小于'.$value['value'],33005);
				break;
			case '=':
				if( !($size == $value['value']) ) throw new OperationException($msg.'等于'.$value['value'],33005);
				break;
			case '-':
				if( !($size >= $value['min'] && $size <= $value['max'] ) )
					throw new OperationException($msg.'大于等于'.$value['min'].'小于等于'.$value['max'],33005);
				break;
		}
	}



	/**
	 * 获取参数
	 * @param \Scar\http\Request $request
	 * @param                    $key
	 * @param null               $default
	 *
	 * @return array|mixed|null|string
	 */
	public static function getParamValue( PsrRequest $request,$key,$default = null )
	{
		if( $request->isGet() ){
			return $request->get( $key );
		}elseif($request->isPost()){
			return $request->post( $key );
		}
		return $default;
	}

}