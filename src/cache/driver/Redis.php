<?php


namespace Scar\cache\driver;

use Cake\Log\Log;
use Scar\Cache;
use Scar\cache\CachePool;
use Scar\cache\Driver;
use Scar\Container;
use Swoole\Coroutine;

/**
 * Redis缓存驱动，适合单机部署、有前端代理实现高可用的场景，性能最好
 * 有需要在业务层实现读写分离、或者使用RedisCluster的需求，请使用Redisd驱动
 *
 * 要求安装phpredis扩展：https://github.com/nicolasff/phpredis
 * @author    尘缘 <130775@qq.com>
 */
class Redis extends Driver
{
	protected $options = [
		'host'       => '127.0.0.1',
		'port'       => 6379,
		'password'   => '',
		'select'     => 0,//库序号
		'expire'     => 0,//有效期
		'persistent' => false,
		'prefix'     => '',//key前缀
		'connect_timeout'=>1,//连接超时时间
		'timeout'=>-1,//获取数据超时时间
		'serialize'=>false,//是否序列化
		'reconnect'=>1,//重连次数
	];

	public static $defer = self::class.'defer';

	/**
	 * 构造函数
	 * @param array $options 缓存参数
	 * @access public
	 */
	public function __construct($options = [])
	{
		if (!empty($options)) {
			$this->options = array_merge($this->options, $options);
		}
		$CachePool = Container::getInstance()->getCachePool();
		if( isset( Coroutine::getContext()[self::$defer] ) === false ){
			Cache::recycle();
			Coroutine::getContext()[self::$defer] = true;
		}
		$this->handler = $CachePool->get( $options['host'].$options['port'] );
		if( $this->handler === false ){
			$this->handler = new \Swoole\Coroutine\Redis();
			$this->handler->setOptions([
				'connect_timeout'=>$this->options['connect_timeout'],
				'timeout'=>$this->options['timeout'],
				'serialize'=>$this->options['serialize'],
				'reconnect'=>$this->options['reconnect'],
			]);

			$res = $this->handler->connect($this->options['host'], $this->options['port']);
			if( $res === false ){
				throw new \RedisException( $this->handler->errMsg,$this->handler->errCode );
			}
			if ('' != $this->options['password']) {
				$authRes = $this->handler->auth($this->options['password']);
				if( $authRes === false )throw new \RedisException( $this->handler->errMsg,$this->handler->errCode );
			}

			if (0 != $this->options['select']) {
				$this->handler->select($this->options['select']);
			}
		}
	}

	/**
	 * 判断缓存
	 * @access public
	 * @param string $name 缓存变量名
	 * @return bool
	 */
	public function has($name)
	{
		$result = $this->handler->get($this->getCacheKey($name));
		if( $this->handler->getDefer() ){
			$result = $this->handler->recv();
		}
		return  $result ? true : false;
	}

	/**
	 * 读取缓存
	 * @access public
	 * @param string $name 缓存变量名
	 * @param mixed  $default 默认值
	 * @return mixed
	 */
	public function get($name, $default = false)
	{
		$value = $this->handler->get($this->getCacheKey($name));
		if (is_null($value) || false === $value) {
			return $default;
		}

		try {
			$result = 0 === strpos($value, 'Scar_serialize:') ? unserialize(substr($value, 15)) : $value;
		} catch (\Exception $e) {
			$result = $default;
		}

		return $result;
	}

	/**
	 * 写入缓存
	 * @access public
	 * @param string            $name 缓存变量名
	 * @param mixed             $value  存储数据
	 * @param integer|\DateTime $expire  有效时间（秒）
	 * @return boolean
	 */
	public function set($name, $value, $expire = null)
	{
		if (is_null($expire)) {
			$expire = $this->options['expire'];
		}
		if ($expire instanceof \DateTime) {
			$expire = $expire->getTimestamp() - time();
		}
		if ($this->tag && !$this->has($name)) {
			$first = true;
		}
		$key   = $this->getCacheKey($name);
		$value = is_scalar($value) ? $value : 'Scar_serialize:' . serialize($value);
		if ($expire) {
			$result = $this->handler->setex($key, $expire, $value);
		} else {
			$result = $this->handler->set($key, $value);
		}
		isset($first) && $this->setTagItem($key);
		return $result;
	}

	/**
	 * 自增缓存（针对数值缓存）
	 * @access public
	 * @param  string    $name 缓存变量名
	 * @param  int       $step 步长
	 * @return false|int
	 */
	public function inc($name, $step = 1)
	{
		$key = $this->getCacheKey($name);

		return $this->handler->incrby($key, $step);
	}

	/**
	 * 自减缓存（针对数值缓存）
	 * @access public
	 * @param  string    $name 缓存变量名
	 * @param  int       $step 步长
	 * @return false|int
	 */
	public function dec($name, $step = 1)
	{
		$key = $this->getCacheKey($name);

		return $this->handler->decrby($key, $step);
	}

	/**
	 * 删除缓存
	 * @access public
	 * @param string $name 缓存变量名
	 * @return boolean
	 */
	public function rm($name)
	{
		return $this->handler->delete($this->getCacheKey($name));
	}

	/**
	 * 清除缓存
	 * @access public
	 * @param string $tag 标签名
	 * @return boolean
	 */
	public function clear($tag = null)
	{
		if ($tag) {
			// 指定标签清除
			$keys = $this->getTagItem($tag);
			foreach ($keys as $key) {
				$this->handler->delete($key);
			}
			$this->rm('tag_' . md5($tag));
			return true;
		}
		return $this->handler->flushDB();
	}




}
