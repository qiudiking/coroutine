<?php
namespace Scar\cache\swoole;
use Scar\App;
use Scar\cache\SwooleTable;
use Scar\Config;

/**
 * Created by PhpStorm.
 * User: 秋狄
 * Date: 2019/8/31
 * Time: 14:16
 */

class WebSocketTable extends SwooleTable
{
	public  $field = [
		[
			'name'=>'uri',
			'type' =>\swoole_table::TYPE_STRING,
			'len'  => 30,
		],
	];

	protected static $instances = null;

	public function __construct( int $size = 50000 ) {
		$config = Config::get('http.set',App::$swoole);
		$size = isset( $config['max_connection'] )?$config['max_connection']:50000;
		parent::__construct( $size );
	}

	/**
	 * @return \Scar\cache\SwooleTable
	 * @throws \Exception
	 */
	public static function instance()
	{
		if( is_null(self::$instances) ){
			self::$instances = new self();
		}
		return self::$instances;
	}


}