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

namespace Scar\db\connector;


use Scar\db\Connection;

/**
 * mysql数据库驱动
 */
class Mysql extends Connection
{

    protected $builder = '\\Scar\\db\\builder\\Mysql';

    /**
     * 解析pdo连接的dsn信息
     * @access protected
     * @param array $config 连接信息
     * @return string
     */
    protected function parseDsn($config)
    {
        if (!empty($config['socket'])) {
            $dsn = 'mysql:unix_socket=' . $config['socket'];
        } elseif (!empty($config['hostport'])) {
            $dsn = 'mysql:host=' . $config['hostname'] . ';port=' . $config['hostport'];
        } else {
            $dsn = 'mysql:host=' . $config['hostname'];
        }
        $dsn .= ';dbname=' . $config['database'];

        if (!empty($config['charset'])) {
            $dsn .= ';charset=' . $config['charset'];
        }
        return $dsn;
    }

	/**
	 * 取得数据表的字段信息
	 * @param string $tableName
	 *
	 * @return array
	 * @throws \Scar\db\exception\MysqlException
	 * @throws \Throwable
	 */
    public function getFields($tableName)
    {
        list($tableName) = explode(' ', $tableName);
        if (false === strpos($tableName, '`')) {
            if (strpos($tableName, '.')) {
                $tableName = str_replace('.', '`.`', $tableName);
            }
            $tableName = '`' . $tableName . '`';
        }
        $sql    = 'SHOW COLUMNS FROM ' . $tableName;
        $pdo    = $this->query($sql, [], false, true);
        $result = $pdo->fetchAll();

        $info   = [];
        if ($result) {
            foreach ($result as $key => $val) {
                $val                 = array_change_key_case($val);
                $info[$val['field']] = [
                    'name'    => $val['field'],
                    'type'    => $val['type'],
                    'notnull' => (bool) ('' === $val['null']), // not null is empty, null is yes
                    'default' => $val['default'],
                    'primary' => (strtolower($val['key']) == 'pri'),
                    'autoinc' => (strtolower($val['extra']) == 'auto_increment'),
                ];
            }
        }
        return $this->fieldCase($info);
    }

	/**
	 * 取得数据库的表信息
	 * @param string $dbName
	 *
	 * @return array
	 * @throws \Scar\db\exception\MysqlException
	 * @throws \Throwable
	 */
    public function getTables($dbName = '')
    {
        $sql    = !empty($dbName) ? 'SHOW TABLES FROM ' . $dbName : 'SHOW TABLES ';
        $pdo    = $this->query($sql, [], false, true);
        $result = $pdo->fetchAll();
        $info   = [];
        foreach ($result as $key => $val) {
            $info[$key] = current($val);
        }
        return $info;
    }

	/**
	 * SQL性能分析
	 * @param string $sql
	 *
	 * @return array
	 */
    protected function getExplain($sql)
    {
        $pdo    = $this->linkID->query("EXPLAIN " . $sql);
        $result = $pdo->fetch();
        $result = array_change_key_case($result);
        if (isset($result['extra'])) {
            if (strpos($result['extra'], 'filesort') || strpos($result['extra'], 'temporary')) {
                \SeasLog::info('SQL:' . $this->queryStr . '[' . $result['extra'] . ']');
            }
        }
        return $result;
    }

    protected function supportSavepoint()
    {
        return true;
    }

}
