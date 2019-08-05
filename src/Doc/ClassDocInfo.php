<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/10/14 0014
 * Time: 9:47
 */

namespace Scar\Doc;



/**
 * 类的注释信息
 * Class EntityInfo
 *
 * @package DB\Mongodb
 */
class ClassDocInfo
{

	private static $fieldCache = [];

	/**
	 * 获取一个实体表的字段信息
	 * @param $className
	 *
	 * @return bool|mixed|null
	 */
	public static function getFieldInfo($className)
	{
		if(class_exists($className)){
			$info = self::$fieldCache[$className];
			if ( !$info ) {
				$info = self::getClassInfo( $className );
				self::$fieldCache[$className] = $info;
			}

			return $info['property'];

		}
	}

	/**
	 * 获取类的方法注解数据
	 * @param $className
	 *
	 * @return bool|null
	 */
	public static function getMethodsInfo( $className )
	{
		$info = self::$fieldCache[$className];
		if ( !$info ) {
			$info = self::getClassInfo( $className );
			self::$fieldCache[$className] = $info;
		}

		return $info['methods'];
	}

	/**
	 * 获取一个实体类的相关联其它实体的字段
	 *
	 * @param $className
	 *
	 * @return bool|null
	 */
	public static function getEntityFieldInfo( $className )
	{
		$info = self::$fieldCache[$className];
		if ( !$info ) {
			$info = self::getClassInfo( $className );
			self::$fieldCache[$className] = $info;
		}

		return $info['entity'];
	}

	/**
	 * 按参数过滤类的方法信息
	 * @param $className
	 * @param $filter
	 *
	 * @return bool|null
	 */
	public static function getMethodInfoByFilter($className,$filter=null){
		$info = self::getMethodsInfo($className);
		foreach ( $info as $methodName => &$value ) {
			if(!is_null($filter) && !isset($value[$filter])){
				unset( $info[$methodName] );
			}
		}
		return $info;
	}

	/**
	 * 按参数过滤类的属性信息
	 * @param $className
	 * @param $filter
	 *
	 * @return array|bool
	 */
	public static function getPropertyInfoByFilter($className,$filter){
		if(!$filter)return false;
		$info = self::getPropertiesInfo($className);
		foreach ( $info as $property => &$value ) {
			if(!isset($value[$filter])){
				unset( $info[$property] );
			}
		}

		return $info;
	}

	/**
	 * 获取类的方法注解信息
	 * @param $className
	 * @param $method
	 *
	 * @return null
	 */
	public static function getMethodInfo($className,$method){
		$info = self::getMethodsInfo($className);
		return isset($info[$method])?$info[$method]:null;
	}

	/**
	 * 获取一个类的注解信息
	 * @param $className
	 *
	 * @return mixed
	 */
	public static function getClassInfo($className)
	{
		$reflection = new \ReflectionClass ( $className );
		//通过反射获取类的注释
		$doc = $reflection->getDocComment ();
		$data['class']=self::docParserInstance()->parse( $doc );
		$field = [];
		$entity = [];
		$properties=$reflection->getProperties();
		if($properties){
			foreach ( $properties as $property ) {
				if($property->isPublic()){
					$p_doc = $property->getDocComment();
					$name=$property->getName();

					$field[$name]= self::docParserInstance()->parse( $p_doc);
					$hasEntity = array_key_exists('entity',$field[$name])?$field[$name]['entity']:null;
					if($hasEntity){
						$entity[$name] = $field[$name];
					}
				}
				unset($property);
			}
			$data['property'] = $field;
			$data['entity'] =$entity;

		}
		unset($properties,$entity);
		$methodData = [];
		$methods = $reflection->getMethods();
		if ( $methods ) {
			foreach ( $methods as $method ) {
				$m_doc = $method->getDocComment();
				$name = $method->getName();
				$methodData[$name] = self::docParserInstance()->parse( $m_doc );
			}
			$data['methods'] = $methodData;
			unset($method);
		}
		//常量属性
		$data['const']=$reflection->getConstants();
		unset($methodData , $methods);
		return $data;
	}

	/**
	 * 获取属性注解信息
	 * @param $className
	 *
	 * @return array
	 */
	public static function getPropertiesInfo( $className ) {
		$reflection = new \ReflectionClass ( $className );
		$properties=$reflection->getProperties();
		$field = [];
		if($properties){
			foreach ( $properties as $property ) {
				if($property->isPublic()){
					$p_doc = $property->getDocComment();
					$name=$property->getName();
					$field[$name]= self::docParserInstance()->parse( $p_doc);
				}
				unset($property);
			}
		}

		return $field;
	}

	/**
	 * 获取属性原始注解信息，未转成数组
	 * @param $className
	 *
	 * @return array
	 */
	public static function getPropertiesDocStr( $className ) {
		$reflection = new \ReflectionClass ( $className );
		$properties=$reflection->getProperties();
		$field = [];
		if($properties){
			foreach ( $properties as $property ) {
				if($property->isPublic()){
					$p_doc = $property->getDocComment();
					$name=$property->getName();
					$field[$name]= $p_doc;
				}
				unset($property);
			}
		}

		return $field;
	}




	/**
	 * 数组转成doc注解
	 * @param array $array
	 *
	 * @return string
	 */
	public static function ArrayToDoc(array $array){
		$docStr = '/**'. PHP_EOL;
		foreach ( $array as $key => $value ) {
			$key=$key=='description'?'':$key;
			$docStr.=" * @ $key $value \n";
		}
		$docStr.=' */'.PHP_EOL.PHP_EOL;

		return $docStr;
	}

	public static $docParserInstance=null;

	/**
	 * @return null|\Scar\Doc\DocParser
	 */
	public static function docParserInstance()
	{
		if ( is_null( self::$docParserInstance ) ) {
			self::$docParserInstance = new \Scar\Doc\DocParser();
		}

		return self::$docParserInstance;
	}


}