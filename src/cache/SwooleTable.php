<?php
namespace Scar\cache;
/**
 * Created by PhpStorm.
 * User: 秋狄
 * Date: 2019/8/31
 * Time: 14:05
 */

use Scar\Config;
class SwooleTable {
	protected  $field =[];
	/**
	 * @var \swoole_table
	 */
	protected  $table;

	/**
	 * 创建swoole内存表
	 */
	public  function __construct(  $size = 50000 )
	{
		$table = new \swoole_table( $size );

		foreach ($this->field as $item){
			$table->column($item['name'],$item['type'] , $item['len']);       //1,2,4,8
		}
		$res = $table->create();
		if($res){
			$this->table = $table;
		}else{
			throw new \Exception('swooleTable内存表失败',44432111);
		}
	}





	/**
	 * 设置行的数据，swoole_table使用key-value的方式来访问数据
	 * @param       $key
	 * @param array $value
	 */
	public  function set( $key,  array $value )
	{
		$table = $this->table;
		if($table instanceof \swoole_table){
			return $table->set($key,$value);
		}else{
			throw new \Exception('内存表不存在',42220222);
		}
	}

	/**
	 * 获取一行数据
	 * @param      $key
	 * @param null $default
	 *
	 * @return array|mixed
	 */
	public  function get( $key , $field  = null )
	{
		$table = $this->table;
		if($table instanceof \swoole_table){
			return $table->get( $key, $field );
		}else{
			throw new \Exception('内存表不存在',42220222);
		}
	}

	/**
	 * 删除数据
	 * @param $key
	 *
	 * @return bool|mixed
	 */
	public  function del( $key )
	{
		$table = $this->table;
		if($table instanceof \swoole_table){
			return $table->del( $key );
		}else{
			throw new \Exception('内存表不存在',42220222);
		}
	}

	/**
	 * 原子自增操作。
	 * @param string $key
	 * @param string $column
	 * @param int    $incrby
	 *
	 * @return mixed
	 */
	public  function incr(string $key, string $column, $incrby = 1)
	{
		$table = $this->table;
		if($table instanceof \swoole_table){
			return $table->incr( $key, $column, $incrby );
		}else{
			throw new \Exception('内存表不存在',42220222);
		}
	}

	/**
	 * 原子自减操作
	 * @param string $key
	 * @param string $column
	 * @param int    $decrby
	 *
	 * @return mixed
	 */
	public  function decr(string $key, string $column,  $decrby = 1)
	{
		$table = $this->table;
		if($table instanceof \swoole_table){
			return $table->decr( $key, $column, $decrby );
		}else{
			throw new \Exception('内存表不存在',42220222);
		}
	}

	/**
	 * 检查table中是否存在某一个key。
	 * @param string $key
	 *
	 * @return mixed
	 */
	public  function exist(string $key)
	{
		$table = $this->table;
		if($table instanceof \swoole_table){
			return $table->exist( $key );
		}else{
			throw new \Exception('内存表不存在',42220222);
		}
	}

	/**
	 * 返回table中存在的条目数
	 * @return mixed
	 */
	public  function count()
	{
		$table = $this->table;
		if($table instanceof \swoole_table){
			return $table->count();
		}else{
			throw new \Exception('内存表不存在',42220222);
		}
	}

	/**
	 * 遍历所有table的行并删除
	 * @return mixed
	 */
	public  function delAll()
	{
		$table = $this->table;
		foreach ( $table as $key =>$val ){
			self::del($key);
		}
	}
}