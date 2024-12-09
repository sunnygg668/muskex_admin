<?php

namespace app\admin\model\ba\market;

use app\common\library\RedisKey;
use app\custom\library\RedisUtil;
use think\Model;

/**
 * Carousel
 */
class Carousel extends Model
{
    // 表名
    protected $name = 'market_carousel';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    protected static function onAfterInsert($model)
    {
        if ($model->weigh == 0) {
            $pk = $model->getPk();
            if (strlen($model[$pk]) >= 19) {
                $model->where($pk, $model[$pk])->update(['weigh' => $model->count()]);
            } else {
                $model->where($pk, $model[$pk])->update(['weigh' => $model[$pk]]);
            }
        }
        RedisUtil::del(RedisKey::CAROUSEL);
    }

    protected static function onAfterUpdate($model){
        RedisUtil::del(RedisKey::CAROUSEL);
    }

    protected static function onAfterDelete($model){
        RedisUtil::del(RedisKey::CAROUSEL);
    }

    public function getEditorAttr($value): string
    {
        return !$value ? '' : htmlspecialchars_decode($value);
    }
}