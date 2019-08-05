<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 2019/7/8
 * Time: 10:10
 */

namespace Scar\db\connector\Pool;


class MysqlPool
{
	protected $available = true;
	protected $pool = [];

	public function __construct( array $config  )
	{
		foreach ( $config as $item ){
			$this->pool[ $item ] = new \SplQueue();
		}
	}

	public function put( $key, $mysql)
	{
		if( $this->available ){
			$this->pool[$key]->push($mysql);
		}
	}

	/**
	 * @return bool|mixed|\Swoole\Coroutine\MySQL
	 */
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