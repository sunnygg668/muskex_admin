<?php

declare(strict_types=1);

namespace app\custom\model;

use EasySwoole\FastDb\AbstractInterface\AbstractEntity;
use EasySwoole\FastDb\Attributes\Property;

class BaUserTeamLevel extends AbstractEntity
{
    #[Property(isPrimaryKey: true)]
    public int $id;
    #[Property]
    public int $user_id;
    #[Property]
    public int $user_level;
    #[Property]
    public int $team_nums;
    #[Property]
    public int $referee_nums;
    #[Property]
    public ?int $create_time;
    #[Property]
    public ?int $update_time;

    public function tableName(): string
    {
        return 'ba_user_team_level';
    }
}
