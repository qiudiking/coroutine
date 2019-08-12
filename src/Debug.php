<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace Scar;


use Swoole\Coroutine;

class Debug
{
    /**
     * @var array 区间时间信息
     */
    protected static $info = self::class.'info';

    /**
     * @var array 区间内存信息
     */
    protected static $mem = self::class.'mem';

    /**
     * 记录时间（微秒）和内存使用情况
     * @access public
     * @param  string $name  标记位置
     * @param  mixed  $value 标记值(留空则取当前 time 表示仅记录时间 否则同时记录时间和内存)
     * @return void
     */
    public static function remark($name, $value = '')
    {
	    Coroutine::getContext()[self::$info][$name] = is_float($value) ? $value : microtime(true);

        if ('time' != $value) {
            Coroutine::getContext()[self::$mem]['mem'][$name]  = is_float($value) ? $value : memory_get_usage();
	        Coroutine::getContext()[self::$mem]['peak'][$name] = memory_get_peak_usage();
        }
    }

    /**
     * 统计某个区间的时间（微秒）使用情况 返回值以秒为单位
     * @access public
     * @param  string  $start 开始标签
     * @param  string  $end   结束标签
     * @param  integer $dec   小数位
     * @return string
     */
    public static function getRangeTime($start, $end, $dec = 6)
    {
        if (!isset(Coroutine::getContext()[self::$info][$end])) {
	        Coroutine::getContext()[self::$info][$end] = microtime(true);
        }

        return number_format((Coroutine::getContext()[self::$info][$end] - Coroutine::getContext()[self::$info][$start]), $dec);
    }


}
