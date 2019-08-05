<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 2019/7/20
 * Time: 16:03
 */

namespace Scar;


class Result {
	protected static $instance = null;

	protected $result
		= [
			'code' => 0,
			'data' => null,
			'message'  => '',
		];

	protected function __construct() {
	}


	public function setData(  $value ) {
		$this->result['data'] = $value;
		return $this;
	}

	public function setCode( int $value ) {
		$this->result['code'] = $value;
		return $this;
	}

	public function setMes( string $msg )
	{
		$this->result['message'] = $msg;
		return $this;
	}

	public function setCodeMes( int $code, string $msg )
	{
		$this->result['code'] = $code;
		$this->result['message'] = $msg;
		return $this;
	}

	public function setKey( $key, $value )
	{
		$this->result[$key] = $value;
		return $this;
	}

	public function getResult()
	{
		return $this->result;
	}

	public function __toString() {
		if($this->result['code'] !== 0 ){
			unset( $this->result['data'] );
		}
		return json_encode( $this->result );
	}

	public static function instance()
	{
		if(is_null(self::$instance)){
		    self::$instance = new self();
		}
		$instance = clone self::$instance;
		return $instance;
	}
}