<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 2019/7/20
 * Time: 14:39
 */

namespace Scar;


use Scar\http\Response;
use Swoole\Coroutine;
use Scar\http\Request;

class Context
{

	/**
	 * @param \Scar\http\Request  $request
	 * @param \Scar\http\Response $response
	 */
	public static function setRequestResponse( Request $request, Response $response  )
	{
		self::setContext( Response::class,$response );
		self::setContext(Request::class,$request);
	}

	/**
	 * @return Request
	 */
	public static function getRequest()
	{
		return self::getContext( Request::class );
	}

	/**
	 * @return Response
	 */
	public static function getResponse()
	{
		return self::getContext( Response::class );
	}



	/**
	 *
	 * @param $key
	 * @param $value
	 */
	protected static function setContext( $key,$value )
	{
		Coroutine::getContext()[$key] = $value;
	}

	/**
	 * @param $key
	 *
	 * @return mixed
	 */
	protected static function getContext( $key )
	{
		return Coroutine::getContext()[$key];
	}
}