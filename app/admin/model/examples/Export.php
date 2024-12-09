<?php

namespace app\admin\model\examples;

use think\Model;

/**
 * Export
 */
class Export extends Model
{
    // 表名
    protected $name = 'export_example';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;


    protected static function onAfterInsert($model)
    {
        if ($model->weigh == 0) {
            $pk = $model->getPk();
            $model->where($pk, $model[$pk])->update(['weigh' => $model[$pk]]);
        }
    }

}