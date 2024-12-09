<?php

namespace app\admin\model\ba\user;

use think\Model;

/**
 * CoinChangeTypes
 */
class CoinChangeTypes extends Model
{
    // 表名
    protected $name = 'user_coin_change_types';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    protected $updateTime = false;

}