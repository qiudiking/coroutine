<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 2019/7/8
 * Time: 19:37
 */

namespace Scar\db\exception;



use Swoole\Coroutine\MySQL\Exception;

class MysqlException extends Exception
{
	public function __construct( string $message = null, $code = null, $previous = null ) {
		parent::__construct( $message, $code, $previous );
	}
}