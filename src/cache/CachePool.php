<?php
namespace Scar\cache;
/**
 * Created by PhpStorm.
 * User: 秋狄
 * Date: 2019/8/9
 * Time: 11:10
 */

class CachePool
{
	protected $available = true;
	protected $pool = [];

	public function __construct( array $config  )
	{
		foreach ( $config as $item ){
			$this->pool[ $item ] = new \SplQueue();
		}
	}

	public function put( $key, $redis)
	{
		if( $this->available ){
			isset($this->pool[$key]) && $this->pool[$key]->push($redis);
		}
	}



	public function get( $key )
	{
		//有空闲连接且连接池处于可用状态
		if ($this->available && count($this->pool[$key]) > 0) {
			return $this->pool[$key]->pop();
		}
		return false;
	}

	public function destruct()
	{
		// 连接池销毁, 置不可用状态, 防止新的客户端进入常驻连接池, 导致服务器无法平滑退出
		$this->available = false;
		foreach ( $this->pool as $item ){
			while (!$item->isEmpty()) {
				$item->pop();
			}
		}

	}
}