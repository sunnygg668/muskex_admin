<?php

namespace app\admin\model\ba\market;

use think\Model;

/**
 * Notice
 */
class Notice extends Model
{
    // 表名
    protected $name = 'market_notice';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    // 字段类型转换
    protected $type = [
        'release_time' => 'timestamp:Y-m-d H:i:s',
    ];

    public function getContentAttr($value): string
    {
        return !$value ? '' : htmlspecialchars_decode($value);
    }

}