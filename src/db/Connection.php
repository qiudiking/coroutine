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

namespace Scar\db;

use Scar\Container;
use Scar\Db;
use Scar\db\connector\Pool\MysqlPool;
use Scar\db\exception\BindParamException;
use Scar\db\exception\DbException;
use Scar\db\exception\MysqlException;
use Swoole\Coroutine;


/**
 * Class Connection
 * @package Scar
 * @method Query table(string $table) 指定数据表（含前缀）
 * @method Query name(string $name) 指定数据表（不含前缀）
 *
 */
abstract class Connection
{

	/**
	 * @var \Swoole\Coroutine\MySQL\Statement
	 */
    protected $Statement;

    /** @var string 当前SQL指令 */
    protected $queryStr = '';
    // 返回或者影响记录数
    protected $numRows = 0;
    // 事务指令数
    protected $transTimes = 0;
    // 错误信息
    protected $error = '';

    protected $links = [];

	/**
	 * @var \Swoole\Coroutine\MySQL
	 */
    protected $linkID;
    protected $linkRead;
    protected $linkWrite;


    // 监听回调
    protected static $event = 'Connection_event';
    // 使用Builder类
    protected $builder;
    // 数据库连接参数配置
    protected $config = [
        // 数据库类型
        'type'            => '',
        // 服务器地址
        'hostname'        => '',
        // 数据库名
        'database'        => '',
        // 用户名
        'username'        => '',
        // 密码
        'password'        => '',
        // 端口
        'hostport'        => '',
        // 连接dsn
        'dsn'             => '',
        // 数据库连接参数
        'params'          => [],
        // 数据库编码默认采用utf8
        'charset'         => 'utf8',
        // 数据库表前缀
        'prefix'          => '',
        // 数据库调试模式
        'debug'           => false,
        // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
        'deploy'          => 0,
        // 数据库读写是否分离 主从式有效
        'rw_separate'     => false,
        // 读写分离后 主服务器数量
        'master_num'      => 1,
        // 指定从服务器序号
        'slave_no'        => '',
        // 模型写入后自动读取主服务器
        'read_master'     => false,
        // 是否严格检查字段是否存在
        'fields_strict'   => true,
        // 数据集返回类型
        'resultset_type'  => 'array',
        // 自动写入时间戳字段
        'auto_timestamp'  => false,
        // 时间字段取出后的默认时间格式
        'datetime_format' => 'Y-m-d H:i:s',
        // 是否需要进行SQL性能分析
        'sql_explain'     => false,
        // Builder类
        'builder'         => '',
        // Query类
        'query'           => '\\Scar\\db\\Query',
        // 是否需要断线重连
        'break_reconnect' => false,
	    //'建立连接超时时间s'
	    'timeout'         => 3,
	    //开启严格模式，query方法返回的数据也将转为强类型
	    'strict_type'     => false,
	    //开启fetch模式, 可与pdo一样使用fetch/fetchAll逐行或获取全部结果集(4.0版本以上)
	    'fetch_mode'      =>true,
    ];


    // 绑定参数
    protected $bind = [];


    /**
     * 构造函数 读取数据库配置信息
     * @access public
     * @param array $config 数据库配置数组
     */
    public function __construct(array $config = [])
    {
        if (!empty($config)) {
            $this->config = array_merge($this->config, $config);
        }
    }

    /**
     * 获取新的查询对象
     * @access protected
     * @return Query
     */
    protected function getQuery()
    {
        $class = $this->config['query'];
        return new $class($this);
    }

    /**
     * 获取当前连接器类对应的Builder类
     * @access public
     * @return string
     */
    public function getBuilder()
    {
        if (!empty($this->builder)) {
            return $this->builder;
        } else {
            return $this->getConfig('builder') ?: '\\Scar\\db\\builder\\' . ucfirst($this->getConfig('type'));
        }
    }

    /**
     * 调用Query类的查询方法
     * @access public
     * @param string    $method 方法名称
     * @param array     $args 调用参数
     * @return mixed
     */
    public function __call($method, $args)
    {
        return call_user_func_array([$this->getQuery(), $method], $args);
    }

    /**
     * 解析pdo连接的dsn信息
     * @access protected
     * @param array $config 连接信息
     * @return string
     */
    abstract protected function parseDsn($config);

    /**
     * 取得数据表的字段信息
     * @access public
     * @param string $tableName
     * @return array
     */
    abstract public function getFields($tableName);

    /**
     * 取得数据库的表信息
     * @access public
     * @param string $dbName
     * @return array
     */
    abstract public function getTables($dbName);

    /**
     * SQL性能分析
     * @access protected
     * @param string $sql
     * @return array
     */
    abstract protected function getExplain($sql);

    /**
     * 对返数据表字段信息进行大小写转换出来
     * @access public
     * @param array $info 字段信息
     * @return array
     */
    public function fieldCase($info)
    {
        return $info;
    }

    /**
     * 获取数据库的配置参数
     * @access public
     * @param string $config 配置名称
     * @return mixed
     */
    public function getConfig($config = '')
    {
        return $config ? $this->config[$config] : $this->config;
    }

    /**
     * 设置数据库的配置参数
     * @access public
     * @param string|array      $config 配置名称
     * @param mixed             $value 配置值
     * @return void
     */
    public function setConfig($config, $value = '')
    {
        if (is_array($config)) {
            $this->config = array_merge($this->config, $config);
        } else {
            $this->config[$config] = $value;
        }
    }

	/**
	 * 连接数据库方法
	 * @param array $config
	 * @param int   $linkNum
	 * @param bool  $autoConnection
	 *
	 * @return \Swoole\Coroutine\MySQL
	 * @throws \Exception
	 */
    public function connect(array $config = [], $linkNum = 0, $autoConnection = false)
    {
    	if( isset($this->links[$linkNum]) === false ){
		    if (!$config) {
			    $config = $this->config;
		    } else {
			    $config = array_merge($this->config, $config);
		    }
		    $mysqlPool = Container::getInstance()->get(MysqlPool::class);
		    if( Coroutine::getCid() === -1 ){
				throw new MysqlException('当前不在协程环境中',-100);
		    }
		    $mysql = $mysqlPool->get( $config['hostname'].$config['database'] );
		    if ( $mysql === false ) {
			    // 数据返回类型
			    try {
				    if ($config['debug']) {
					    $startTime = microtime(true);
				    }
				    $mysql = new Coroutine\MySQL();
				    $connect_res = $mysql->connect(
					    [
						    'host' => $config['hostname'],
						    'user' => $config['username'],
						    'password' => $config['password'],
						    'database' => $config['database'],
						    'port'    => $config['hostport'],
						    'timeout' => $config['timeout'],
						    'charset' => $config['charset'],
						    'strict_type' => $config['strict_type'], //开启严格模式，query方法返回的数据也将转为强类型
						    'fetch_mode' => $config['fetch_mode'], //开启fetch模式, 可与pdo一样使用fetch/fetchAll逐行或获取全部结果集(4.0版本以上)
					    ]
				    );
				    if( $connect_res === false ){
					    throw new MysqlException( $mysql->connect_error,$mysql->connect_errno );

				    }
				    $this->links[$linkNum] = $mysql;
				    if ($config['debug']) {
					    // 记录数据库连接信息
					    \SeasLog::info('[ DB ] CONNECT:[ UseTime:' . number_format(microtime(true) - $startTime, 6) . 's ] ' . $config['dsn']);
				    }
			    } catch (MysqlException $e) {
				    if ($autoConnection) {
					    return $this->connect( $autoConnection, $linkNum );
				    } else {
					    throw $e;
				    }
			    }
		    }else{
			    if( $mysql->connected === false ){
				    return $this->connect( $config, $linkNum,$autoConnection );
			    }
			    $this->links[$linkNum] = $mysql;
		    }
    	}
	    $this->links[$linkNum]->setDefer( false  );
        return $this->links[$linkNum];
    }

    /**
     * 释放查询结果
     * @access public
     */
    public function free()
    {
        $this->Statement = null;
    }

	/**
	 * 获取PDO对象
	 * @return bool
	 */
    public function getPdo()
    {
        if (!$this->linkID) {
            return false;
        } else {
            return $this->linkID;
        }
    }

	/**
	 *  执行查询 返回数据集
	 * @access public
	 * @param string        $sql sql指令
	 * @param array         $bind 参数绑定
	 * @param bool          $master 是否在主服务器读操作
	 * @param bool          $pdo 是否返回OStatement对象
	 *
	 * @return array|bool|\Swoole\Coroutine\MySQL\Statement
	 * @throws \Scar\db\exception\MysqlException
	 * @throws \Throwable
	 */
    public function query($sql, $bind = [], $master = false, $pdo = false)
    {
        $this->initConnect($master);
        if (!$this->linkID) {
            return false;
        }
	    $this->disposeBindValue( $sql,$bind );
        // 记录SQL语句
        $this->queryStr = $sql;
        if ($bind) {
            $this->bind = $bind;
        }
		isset(Coroutine::getContext()[Db::$queryTimes])?
			Coroutine::getContext()[Db::$queryTimes]++:Coroutine::getContext()[Db::$queryTimes] = 1;
        try {
            // 调试开始
            //$this->debug(true);

            // 释放前次的查询结果
            if (!empty($this->Statement)) {
                $this->free();
            }
            // 预处理
            if (empty($this->Statement)) {

	            $this->Statement = $this->linkID->prepare($sql);
	            if( $this->Statement === false ){
		            throw new MysqlException($this->linkID->error,$this->linkID->errno);
	            }
	            if($this->linkID->getDefer()){
		            $this->Statement = $this->linkID->recv();
		            if( false === $this->Statement ){
			            throw new MysqlException($this->linkID->error,$this->linkID->errno);
		            }
		            $res = $this->Statement->execute( $bind );
		            if($res === false ){
			            throw new MysqlException( $this->linkID->error,$this->linkID->errno );
		            }
		            $res = $this->linkID->recv();
		            if( $res === false ){
			            throw new MysqlException( $this->linkID->error,$this->linkID->errno );
		            }
	            }else{
		            $res = $this->Statement->execute( $bind );
		            if( $res === false ){
			            throw new MysqlException($this->linkID->error,$this->linkID->errno);
		            }
	            }
            }

            // 是否为存储过程调用
            $procedure = in_array(strtolower(substr(trim($sql), 0, 4)), ['call', 'exec']);
            // 参数绑定
	        /*if ($procedure) {
				$this->bindParam($bind);
			} else {
				$this->bindValue($bind);
			}*/
            // 执行查询


            // 调试结束
            //$this->debug(false, '', $master);
            // 返回结果集
            return $this->getResult($pdo,$procedure);
        } catch (MysqlException $e) {
            if ($this->isBreak($e)) {
                return $this->close()->query($sql, $bind, $master, $pdo);
            }
            throw new MysqlException($e->getMessage(), $e->getCode());
        } catch (\Throwable $e) {
            if ($this->isBreak($e)) {
                return $this->close()->query($sql, $bind, $master, $pdo);
            }
            throw $e;
        } catch (\Exception $e) {
            if ($this->isBreak($e)) {
                return $this->close()->query($sql, $bind, $master, $pdo);
            }
            throw $e;
        }
    }

	/**
	 * 执行语句
	 * @access public
	 * @param  string        $sql sql指令
	 * @param  array         $bind 参数绑定
	 * @param  Query         $query 查询对象
	 *
	 * @return bool|int
	 * @throws \Scar\db\exception\MysqlException
	 * @throws \Throwable
	 */
    public function execute($sql, $bind = [], Query $query = null)
    {
        $this->initConnect(true);
        if (!$this->linkID) {
            return false;
        }
        //处理sql和参数
        $this->disposeBindValue( $sql,$bind );
	    // 记录SQL语句
        $this->queryStr = $sql;
        if ($bind) {
            $this->bind = $bind;
        }

        isset( Coroutine::getContext()[Db::$executeTimes])?Coroutine::getContext()[Db::$executeTimes]++
	        :Coroutine::getContext()[Db::$executeTimes]=1;
        try {
            // 调试开始
            //$this->debug(true);

            //释放前次的查询结果
            if (!empty($this->Statement) ) {
                $this->free();
            }
            // 预处理
            if ( empty($this->Statement) ) {
            	if( $this->linkID->getDefer() ){
		            if( $this->linkID->prepare($sql) ){
			            $this->Statement = $this->linkID->recv();
		            }else{
			            throw new MysqlException( $this->linkID->error,$this->linkID->errno );
		            }
		            if( $this->Statement === false ){
			            throw new MysqlException( $this->linkID->error,$this->linkID->errno );
		            }
		            $res = $this->Statement->execute( $bind );
		            if($res === false ){
			            throw new MysqlException( $this->linkID->error,$this->linkID->errno );
		            }
		            $data = $this->linkID->recv();
		            if( $data === false ){
			            throw new MysqlException( $this->linkID->error,$this->linkID->errno );
		            }
            	}else{
		            $this->Statement = $this->linkID->prepare($sql);
		            if( $this->Statement === false ){
			            throw new MysqlException( $this->linkID->error,$this->linkID->errno );
		            }

		            $res = $this->Statement->execute( $bind );
		            if($res === false ){
			            throw new MysqlException( $this->linkID->error,$this->linkID->errno );
		            }

	            }
            }
	        $this->numRows =  $this->linkID->affected_rows;
            return $this->numRows;
        } catch (MysqlException $e) {
            if ($this->isBreak($e)) {
                return $this->close()->execute($sql, $bind, $query);
            }
            throw new MysqlException($e->getMessage(),$e->getCode());
        } catch (\Throwable $e) {
            if ($this->isBreak($e)) {
                return $this->close()->execute($sql, $bind, $query);
            }
            throw $e;
        } catch (MysqlException $e) {
            if ($this->isBreak($e)) {
                return $this->close()->execute($sql, $bind, $query);
            }
            throw $e;
        }
    }

    /**
     * 根据参数绑定组装最终的SQL语句 便于调试
     * @access public
     * @param string    $sql 带参数绑定的sql语句
     * @param array     $bind 参数绑定列表
     * @return string
     */
    public function getRealSql($sql, array $bind = [])
    {
        if (is_array($sql)) {
            $sql = implode(';', $sql);
        }

        foreach ($bind as $key => $val) {
            $value = is_array($val) ? $val[0] : $val;
            $type  = is_array($val) ? $val[1] : \PDO::PARAM_STR;
            if (\PDO::PARAM_STR == $type) {
                $value = $this->quote($value);
            } elseif (\PDO::PARAM_INT == $type) {
                $value = (float) $value;
            }
            // 判断占位符
            $sql = is_numeric($key) ?
            substr_replace($sql, $value, strpos($sql, '?'), 1) :
            str_replace(
                [':' . $key . ')', ':' . $key . ',', ':' . $key . ' ', ':' . $key . PHP_EOL],
                [$value . ')', $value . ',', $value . ' ', $value . PHP_EOL],
                $sql . ' ');
        }
        return rtrim($sql);
    }

	/**
	 * 绑定数据
	 * @param $sql
	 * @param $bind
	 *
	 * @throws \Scar\db\exception\MysqlException
	 */
    protected function disposeBindValue( &$sql, &$bind )
    {
	    if( strpos($sql,':') ){
		    foreach ($bind as $key => $value){
			    $length = strpos($sql,':');
			    $str = substr($sql,$length+1,mb_strlen($key));
			     if($str  != $key ) throw new MysqlException('sql bind params error',332211);
			    $sql = str_replace(':'.$key,'?',$sql);
			    $bind2[] = is_array($value) ? $value[0] : $value;
		    }
		    $bind = $bind2;
	    }
    }

    /**
     * 参数绑定
     * 支持 ['name'=>'value','id'=>123] 对应命名占位符
     * 或者 ['value',123] 对应问号占位符
     * @access public
     * @param array $bind 要绑定的参数列表
     * @return void
     * @throws BindParamException
     */
    protected function bindValue(array $bind = [])
    {
        foreach ($bind as $key => $val) {
            // 占位符
            $param = is_numeric($key) ? $key + 1 : ':' . $key;
            if (is_array($val)) {
                if (\PDO::PARAM_INT == $val[1] && '' === $val[0]) {
                    $val[0] = 0;
                }
                $result = $this->Statement->bindValue($param, $val[0], $val[1]);
            } else {
                $result = $this->Statement->bindValue($param, $val);
            }
            if (!$result) {
                throw new BindParamException(
                    "Error occurred  when binding parameters '{$param}'",
                    $this->config,
                    $this->getLastsql(),
                    $bind
                );
            }
        }
    }

    /**
     * 存储过程的输入输出参数绑定
     * @access public
     * @param array $bind 要绑定的参数列表
     * @return void
     * @throws BindParamException
     */
    protected function bindParam($bind)
    {
        foreach ($bind as $key => $val) {
            $param = is_numeric($key) ? $key + 1 : ':' . $key;
            if (is_array($val)) {
                array_unshift($val, $param);
                $result = call_user_func_array([$this->Statement, 'bindParam'], $val);
            } else {
                $result = $this->Statement->bindValue($param, $val);
            }
            if (!$result) {
                $param = array_shift($val);
                throw new BindParamException(
                    "Error occurred  when binding parameters '{$param}'",
                    $this->config,
                    $this->getLastsql(),
                    $bind
                );
            }
        }
    }

    /**
     * 获得数据集数组
     * @access protected
     * @param bool   $pdo 是否返回PDOStatement
     * @param bool   $procedure 是否存储过程
     * @return \Swoole\Coroutine\MySQL\Statement|array
     */
    protected function getResult($pdo = false, $procedure = false)
    {
        if ($pdo) {
            // 返回Statement对象处理
            return $this->Statement;
        }
        if ($procedure) {
            // 存储过程返回结果
            return $this->procedure();
        }
        $result        = $this->Statement->fetchAll();
        $this->numRows = count($result);
        return $result;
    }

    /**
     * 获得存储过程数据集
     * @access protected
     * @return array
     */
    protected function procedure()
    {
        $item = [];
        do {
            $result = $this->getResult();
            if ($result) {
                $item[] = $result;
            }
        } while ($this->Statement->nextResult());
        $this->numRows = count($item);
        return $item;
    }

	/**
	 * 执行数据库事务
	 * @access public
	 * @param callable $callback 数据操作方法回调
	 * @param $callback
	 *
	 * @return mixed|null
	 * @throws \Throwable
	 */
    public function transaction($callback)
    {
        $this->startTrans();
        try {
            $result = null;
            if (is_callable($callback)) {
                $result = call_user_func_array($callback, [$this]);
            }
            $this->commit();
            return $result;
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * 启动事务
     * @access public
     * @return bool|mixed
     * @throws \Exception
     */
    public function startTrans()
    {
        $this->initConnect(true);
        if (!$this->linkID) {
            return false;
        }
        $this->linkID->setDefer(false );

        ++$this->transTimes;
        try {
            if (1 == $this->transTimes) {
                $this->linkID->begin();
            } elseif ($this->transTimes > 1 && $this->supportSavepoint()) {
                $this->linkID->query(
                    $this->parseSavepoint('trans' . $this->transTimes)
                );
            }

        } catch (MysqlException $e) {
            if ($this->isBreak($e)) {
                return $this->close()->startTrans();
            }
            throw $e;
        } catch (\Exception $e) {
            if ($this->isBreak($e)) {
                return $this->close()->startTrans();
            }
            throw $e;
        } catch (\Error $e) {
            if ($this->isBreak($e)) {
                return $this->close()->startTrans();
            }
            throw $e;
        }
    }

	/**
	 * 用于非自动提交状态下面的查询提交
	 * @throws \Exception
	 */
    public function commit()
    {
        $this->initConnect(true);

        if (1 == $this->transTimes) {
            $this->linkID->commit();
	        $this->linkID->setDefer();
        }

        --$this->transTimes;
    }

	/**
	 * 事务回滚
	 * @throws \Exception
	 */
    public function rollback()
    {
        $this->initConnect(true);

        if (1 == $this->transTimes) {
            $this->linkID->rollBack();
            $this->linkID->setDefer();
        } elseif ($this->transTimes > 1 && $this->supportSavepoint()) {
            $this->linkID->query(
                $this->parseSavepointRollBack('trans' . $this->transTimes)
            );
        }

        $this->transTimes = max(0, $this->transTimes - 1);
    }

    /**
     * 是否支持事务嵌套
     * @return bool
     */
    protected function supportSavepoint()
    {
        return false;
    }

    /**
     * 生成定义保存点的SQL
     * @param $name
     * @return string
     */
    protected function parseSavepoint($name)
    {
        return 'SAVEPOINT ' . $name;
    }

    /**
     * 生成回滚到保存点的SQL
     * @param $name
     * @return string
     */
    protected function parseSavepointRollBack($name)
    {
        return 'ROLLBACK TO SAVEPOINT ' . $name;
    }

	/**
	 * 批处理执行SQL语句
	 * 批处理的指令都认为是execute操作
	 * @access public
	 * @param array $sqlArray SQL批处理指令
	 *
	 * @return bool
	 * @throws \Throwable
	 */
    public function batchQuery($sqlArray = [], $bind = [], Query $query = null)
    {
        if (!is_array($sqlArray)) {
            return false;
        }
        // 自动启动事务支持
        $this->startTrans();
        try {
            foreach ($sqlArray as $sql) {
                $this->execute($sql, $bind, $query);
            }
            // 提交事务
            $this->commit();
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }

        return true;
    }

    /**
     * 获得查询次数
     * @access public
     * @param boolean $execute 是否包含所有查询
     * @return integer
     */
    public function getQueryTimes($execute = false)
    {

    	$queryTimes = Coroutine::getContext()[Db::$queryTimes]?? 0;
	    $executeTimes = Coroutine::getContext()[Db::$executeTimes]?? 0;
        return $execute ? $queryTimes + $executeTimes : $queryTimes;
    }

    /**
     * 获得执行次数
     * @access public
     * @return integer
     */
    public function getExecuteTimes()
    {

        return Coroutine::getContext()[Db::$executeTimes]??0;
    }

    /**
     * 关闭数据库（或者重新连接）
     * @access public
     * @return $this
     */
    public function close()
    {
        $this->linkID    = null;
        $this->linkWrite = null;
        $this->linkRead  = null;
        $this->links     = [];
        return $this;
    }

    /**
     * 是否断线
     * @access protected
     * @param \Exception|\Exception  $e 异常对象
     * @return bool
     */
    protected function isBreak($e)
    {
        if (!$this->config['break_reconnect']) {
            return false;
        }

        $info = [
            'server has gone away',
            'no connection to the server',
            'Lost connection',
            'is dead or not enabled',
            'Error while sending',
            'decryption failed or bad record mac',
            'server closed the connection unexpectedly',
            'SSL connection has been closed unexpectedly',
            'Error writing data to the connection',
            'Resource deadlock avoided',
            'failed with errno',
        ];

        $error = $e->getMessage();

        foreach ($info as $msg) {
            if (false !== stripos($error, $msg)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取最近一次查询的sql语句
     * @access public
     * @return string
     */
    public function getLastSql()
    {
        return $this->getRealSql($this->queryStr, $this->bind);
    }

    /**
     * 获取最近插入的ID
     * @access public
     * @param string  $sequence     自增序列名
     * @return string
     */
    public function getLastInsID($sequence = null)
    {
        return $this->linkID->insert_id;
    }

    /**
     * 获取返回或者影响的记录数
     * @access public
     * @return integer
     */
    public function getNumRows()
    {
        return $this->numRows;
    }

    /**
     * 获取最近的错误信息
     * @access public
     * @return string
     */
    public function getError()
    {
        if ($this->Statement) {
            $error = $this->Statement->error;
            $error = $error[1] . ':' . $error[2];
        } else {
            $error = '';
        }
        if ('' != $this->queryStr) {
            $error .= "\n [ SQL语句 ] : " . $this->getLastsql();
        }
        return $error;
    }

	/**
	 * SQL指令安全过滤
	 * @access public
	 * @param string $str SQL字符串
	 * @param bool   $master 是否主库查询
	 *
	 * @return string
	 * @throws \Exception
	 */
    public function quote($str, $master = true)
    {
        $this->initConnect($master);
        return $this->linkID ? "'" . $str . "'" : $str;
    }

    /**
     * 数据库调试 记录当前SQL及分析性能
     * @access protected
     * @param boolean $start 调试开始标记 true 开始 false 结束
     * @param string  $sql 执行的SQL语句 留空自动获取
     * @param boolean $master 主从标记
     * @return void
     */
    protected function debug($start, $sql = '', $master = false)
    {
        if (!empty($this->config['debug'])) {
            // 开启数据库调试模式
            if ($start) {
                Debug::remark('queryStartTime', 'time');
            } else {
                // 记录操作结束时间
                Debug::remark('queryEndTime', 'time');
                $runtime = Debug::getRangeTime('queryStartTime', 'queryEndTime');
                $sql     = $sql ?: $this->getLastsql();
                $result  = [];
                // SQL性能分析
                if ($this->config['sql_explain'] && 0 === stripos(trim($sql), 'select')) {
                    $result = $this->getExplain($sql);
                }
                // SQL监听
                $this->trigger($sql, $runtime, $result, $master);
            }
        }
    }

    /**
     * 监听SQL执行
     * @access public
     * @param callable $callback 回调方法
     * @return void
     */
    public function listen($callback)
    {
        Coroutine::getContext()[self::$event][] = $callback;
    }

    /**
     * 触发SQL事件
     * @access protected
     * @param string    $sql SQL语句
     * @param float     $runtime SQL运行时间
     * @param mixed     $explain SQL分析
     * @param  bool     $master 主从标记
     * @return void
     */
    protected function trigger($sql, $runtime, $explain = [], $master = false)
    {
	    $event = Coroutine::getContext()[self::$event];
        if (!empty($event)) {
            foreach ($event as $callback) {
                if (is_callable($callback)) {
                    call_user_func_array($callback, [$sql, $runtime, $explain, $master]);
                }
            }
        } else {
            // 未注册监听则记录到日志中
            if ($this->config['deploy']) {
                // 分布式记录当前操作的主从
                $master = $master ? 'master|' : 'slave|';
            } else {
                $master = '';
            }

            //Log::record('[ SQL ] ' . $sql . ' [ ' . $master . 'RunTime:' . $runtime . 's ]', 'sql');
            if (!empty($explain)) {
                //Log::record('[ EXPLAIN : ' . var_export($explain, true) . ' ]', 'sql');
            }
        }
    }

	/**
	 *  初始化数据库连接
	 * @access protected
	 * @param boolean $master 是否主服务器
	 *
	 * @throws \Exception
	 */
    protected function initConnect($master = true)
    {
        if (!empty($this->config['deploy'])) {
            // 采用分布式数据库
            if ($master || $this->transTimes) {
                if (!$this->linkWrite) {
                    $this->linkWrite = $this->multiConnect(true);
                }
                $this->linkID = $this->linkWrite;
            } else {
                if (!$this->linkRead) {
                    $this->linkRead = $this->multiConnect(false);
                }
                $this->linkID = $this->linkRead;
            }
        } elseif (!$this->linkID) {
            // 默认单数据库
            $this->linkID = $this->connect();
        }
    }

	/**
	 *  连接分布式服务器
	 * @access protected
	 * @param boolean $master 主服务器
	 *
	 * @return \Swoole\Coroutine\MySQL
	 * @throws \Exception
	 */
    protected function multiConnect($master = false)
    {
        $_config = [];
        // 分布式数据库配置解析
        foreach (['username', 'password', 'hostname', 'hostport', 'database', 'dsn', 'charset'] as $name) {
            $_config[$name] = explode(',', $this->config[$name]);
        }

        // 主服务器序号
        $m = floor(mt_rand(0, $this->config['master_num'] - 1));

        if ($this->config['rw_separate']) {
            // 主从式采用读写分离
            if ($master) // 主服务器写入
            {
                $r = $m;
            } elseif (is_numeric($this->config['slave_no'])) {
                // 指定服务器读
                $r = $this->config['slave_no'];
            } else {
                // 读操作连接从服务器 每次随机连接的数据库
                $r = floor(mt_rand($this->config['master_num'], count($_config['hostname']) - 1));
            }
        } else {
            // 读写操作不区分服务器 每次随机连接的数据库
            $r = floor(mt_rand(0, count($_config['hostname']) - 1));
        }
        $dbMaster = false;
        if ($m != $r) {
            $dbMaster = [];
            foreach (['username', 'password', 'hostname', 'hostport', 'database', 'dsn', 'charset'] as $name) {
                $dbMaster[$name] = isset($_config[$name][$m]) ? $_config[$name][$m] : $_config[$name][0];
            }
        }
        $dbConfig = [];
        foreach (['username', 'password', 'hostname', 'hostport', 'database', 'dsn', 'charset'] as $name) {
            $dbConfig[$name] = isset($_config[$name][$r]) ? $_config[$name][$r] : $_config[$name][0];
        }
        return $this->connect($dbConfig, $r, $r == $m ? false : $dbMaster);
    }

    /**
     * 析构方法
     * @access public
     */
    public function __destruct()
    {
        // 释放查询
        if ($this->Statement) {
            $this->free();
        }
        // 关闭连接
        $this->close();
    }
}
