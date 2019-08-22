<?php
// +----------------------------------------------------------------------
// | ScarPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://Scarphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace Scar;

use Scar\cache\CachePool;
use Scar\cache\Driver;
use Swoole\Coroutine;

class Cache
{
    /**
     * @var array 缓存的实例
     */
    public static $instance = self::class.'instance';

    /**
     * @var int 缓存读取次数
     */
    public static $readTimes = self::class.'readTimes';

    /**
     * @var int 缓存写入次数
     */
    public static $writeTimes = self::class.'writeTimes';

    /**
     * @var object 操作句柄
     */
    public static $handler = self::class.'handler';

    /**
     * 连接缓存驱动
     * @access public
     * @param  array       $options 配置数组
     * @param  bool|string $name    缓存连接标识 true 强制重新连接
     * @return Driver
     */
    public static function connect(array $options = [], $name = false)
    {
        $type = !empty($options['type']) ? $options['type'] : 'File';

        if (false === $name) {
            $name = md5(serialize($options));
        }

        if (true === $name || !isset(Coroutine::getContext()[self::$instance][$name])) {
            $class = false === strpos($type, '\\') ?
            '\\Scar\\cache\\driver\\' . ucwords($type) :
            $type;

            // 记录初始化信息
            //App::$debug && Log::record('[ CACHE ] INIT ' . $type, 'info');

            if (true === $name) {
                return new $class($options);
            }
			Coroutine::getContext()[self::$instance][$name] = new $class($options);
        }

        return Coroutine::getContext()[self::$instance][$name];
    }

	/**
	 * 回收连接实例
	 */
	public static function recycle()
	{

		if(isset(Coroutine::getContext()[self::$instance]) === true ){
			$arr  = Coroutine::getContext()[self::$instance];
			foreach ($arr as $item){
				if(  $item->handler() instanceof \Swoole\Coroutine\Redis ){
					$CachePool = Container::getInstance()->getCachePool();
					if( $item->handler()->connected ){
						$CachePool->put($item->handler()->host.$item->handler()->port,$item->handler());
					}
				}
			}
		}
	}


    /**
     * 自动初始化缓存
     * @access public
     * @param  array $options 配置数组
     * @return Driver
     */
    public static function init(array $options = [])
    {
        if (isset(Coroutine::getContext()[self::$handler]) === false ) {
            if (empty($options) && 'complex' == Config::get('cache.type')) {
                $default = Config::get('cache.default');
                // 获取默认缓存配置，并连接
                $options = Config::get('cache.' . $default['type']) ?: $default;
            } elseif (empty($options)) {
                $options = Config::get('cache');
            }

	        Coroutine::getContext()[self::$handler] = self::connect($options);
        }

        return Coroutine::getContext()[self::$handler];
    }

    /**
     * 切换缓存类型 需要配置 cache.type 为 complex
     * @access public
     * @param  string $name 缓存标识
     * @return Driver
     */
    public static function store($name = '')
    {
        if ('' !== $name && 'complex' == Config::get('cache.type')) {
            return self::connect(Config::get('cache.' . $name), strtolower($name));
        }

        return self::init();
    }

    /**
     * 判断缓存是否存在
     * @access public
     * @param  string $name 缓存变量名
     * @return bool
     */
    public static function has($name)
    {
        isset( Coroutine::getContext()[self::$readTimes])?Coroutine::getContext()[self::$readTimes]++:Coroutine::getContext()[self::$readTimes] = 1;

        return self::init()->has($name);
    }

    /**
     * 读取缓存
     * @access public
     * @param  string $name    缓存标识
     * @param  mixed  $default 默认值
     * @return mixed
     */
    public static function get($name, $default = false)
    {
	    isset( Coroutine::getContext()[self::$readTimes])?Coroutine::getContext()[self::$readTimes]++:Coroutine::getContext()[self::$readTimes] = 1;

        return self::init()->get($name, $default);
    }

    /**
     * 写入缓存
     * @access public
     * @param  string   $name   缓存标识
     * @param  mixed    $value  存储数据
     * @param  int|null $expire 有效时间 0为永久
     * @return boolean
     */
    public static function set($name, $value, $expire = null)
    {

        isset(Coroutine::getContext()[self::$writeTimes])?Coroutine::getContext()[self::$writeTimes]++:Coroutine::getContext()[self::$writeTimes] = 1;

        return self::init()->set($name, $value, $expire);
    }

    /**
     * 自增缓存（针对数值缓存）
     * @access public
     * @param  string $name 缓存变量名
     * @param  int    $step 步长
     * @return false|int
     */
    public static function inc($name, $step = 1)
    {
	    isset(Coroutine::getContext()[self::$writeTimes])?Coroutine::getContext()[self::$writeTimes]++:Coroutine::getContext()[self::$writeTimes] = 1;


	    return self::init()->inc($name, $step);
    }

    /**
     * 自减缓存（针对数值缓存）
     * @access public
     * @param  string $name 缓存变量名
     * @param  int    $step 步长
     * @return false|int
     */
    public static function dec($name, $step = 1)
    {
	    isset(Coroutine::getContext()[self::$writeTimes])?Coroutine::getContext()[self::$writeTimes]++:Coroutine::getContext()[self::$writeTimes] = 1;


	    return self::init()->dec($name, $step);
    }

    /**
     * 删除缓存
     * @access public
     * @param  string $name 缓存标识
     * @return boolean
     */
    public static function rm($name)
    {
	    isset(Coroutine::getContext()[self::$writeTimes])?Coroutine::getContext()[self::$writeTimes]++:Coroutine::getContext()[self::$writeTimes] = 1;


	    return self::init()->rm($name);
    }

    /**
     * 清除缓存
     * @access public
     * @param  string $tag 标签名
     * @return boolean
     */
    public static function clear($tag = null)
    {
	    isset(Coroutine::getContext()[self::$writeTimes])?Coroutine::getContext()[self::$writeTimes]++:Coroutine::getContext()[self::$writeTimes] = 1;


	    return self::init()->clear($tag);
    }

    /**
     * 读取缓存并删除
     * @access public
     * @param  string $name 缓存变量名
     * @return mixed
     */
    public static function pull($name)
    {
	    isset( Coroutine::getContext()[self::$readTimes])?Coroutine::getContext()[self::$readTimes]++:Coroutine::getContext()[self::$readTimes] = 1;
	    isset(Coroutine::getContext()[self::$writeTimes])?Coroutine::getContext()[self::$writeTimes]++:Coroutine::getContext()[self::$writeTimes] = 1;

	    return self::init()->pull($name);
    }

    /**
     * 如果不存在则写入缓存
     * @access public
     * @param  string $name   缓存变量名
     * @param  mixed  $value  存储数据
     * @param  int    $expire 有效时间 0为永久
     * @return mixed
     */
    public static function remember($name, $value, $expire = null)
    {
	    isset( Coroutine::getContext()[self::$readTimes])?Coroutine::getContext()[self::$readTimes]++:Coroutine::getContext()[self::$readTimes] = 1;

        return self::init()->remember($name, $value, $expire);
    }

    /**
     * 缓存标签
     * @access public
     * @param  string       $name    标签名
     * @param  string|array $keys    缓存标识
     * @param  bool         $overlay 是否覆盖
     * @return Driver
     */
    public static function tag($name, $keys = null, $overlay = false)
    {
        return self::init()->tag($name, $keys, $overlay);
    }

}
