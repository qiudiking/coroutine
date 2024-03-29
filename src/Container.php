<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 2019/7/8
 * Time: 17:25
 */

namespace Scar;



use Psr\Container\ContainerInterface;
use Scar\cache\CachePool;
use Scar\db\connector\Pool\MysqlPool;
use Scar\exception\NotFoundException;

class Container implements ContainerInterface
{
	/**
	 * current Container
	 *
	 * @var Object
	 */
	protected static $instance = null;

	protected static $objectArr = [];

	/**
	 * @return Object|\Scar\Container
	 */
	public static function getInstance()
	{
		if ( is_null(static::$instance) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function has(  $id ) {
		if( isset(self::$objectArr[$id]) ){
			return true;
		}
		return false;
	}

	public function get( $id ) {
		if( is_string($id) === false ){
			throw new NotFoundException('标识不合法', 13302);
		}
		if( $this->has($id) === false ){
		    throw new NotFoundException('容器ID不存在',13304);
		}
		return self::$objectArr[$id];
	}

	public function set(  $instance)
	{
		$key = get_class( $instance );
		self::$objectArr[$key] = $instance;
	}

	/**
	 * @return MysqlPool
	 */
	public function getMysqlPool()
	{
		return $this->get(MysqlPool::class);
	}

	/**
	 * @return CachePool
	 */
	public function getCachePool()
	{
		return $this->get( CachePool::class );
	}

	/**
	 * @return \Swoole\WebSocket\Server
	 */
	public function getWebServer()
	{
		return $this->get( \Swoole\WebSocket\Server::class );
	}
}