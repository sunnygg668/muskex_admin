<?php

namespace app\admin\model\ba\user;

use app\common\library\RedisKey;
use app\custom\library\RedisUtil;
use think\Model;

/**
 * Level
 */
class Level extends Model
{
    // 表名
    protected $name = 'user_level';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;


    public function getBonusAttr($value): float
    {
        return (float)$value;
    }

    public function getLayer_1RatioAttr($value): float
    {
        return (float)$value;
    }

    public function getLayer_2RatioAttr($value): float
    {
        return (float)$value;
    }

    public function getLayer_3RatioAttr($value): float
    {
        return (float)$value;
    }

    public function getLayer_4RatioAttr($value): float
    {
        return (float)$value;
    }

    public function getLayer_5RatioAttr($value): float
    {
        return (float)$value;
    }

    public function getLogoImageAttr($value): string
    {
        return !$value ? '' : get_sys_config('upload_cdn_url') . $value;
    }

    public function memberLevel(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\ba\user\Level::class, 'member_level', 'level');
    }

    protected static function onAfterInsert($model)
    {
        RedisUtil::del(RedisKey::LEVEL.$model->level);//可以不加
    }

    protected static function onAfterUpdate($model){
        RedisUtil::del(RedisKey::LEVEL.$model->level);
    }

    protected static function onAfterDelete($model){
        RedisUtil::del(RedisKey::LEVEL.$model->level);
    }
}
