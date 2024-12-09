<?php

namespace app\admin\model\ba\report;

use app\admin\model\User;
use think\Model;

/**
 * TeamStatistics
 */
class TeamStatistics extends Model
{
    // 表名
    protected $name = 'report_team_statistics';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    protected $append = [
        'children'
    ];

    public function getChildrenAttr()
    {
        $invitedUserIds = User::where(['refereeid' => $this->user->id])->column('id');
        $children = $this->withJoin(['user'], 'LEFT')->where('user_id', 'in', $invitedUserIds)->select();
        $children->visible(['user' => ['username', 'wallet_addr', 'mobile', 'name']]);
        foreach ($children as $child) {
            $child['user']['name'] = '【层级-' . $child['user']['team_level'] . '】：' . $child['user']['name'];
        }
        return $children;
    }


    public function user(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\User::class, 'user_id', 'id');
    }
}
