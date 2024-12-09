<?php

declare(strict_types=1);

namespace app\custom\model;

use EasySwoole\FastDb\AbstractInterface\AbstractEntity;
use EasySwoole\FastDb\Attributes\Property;

class BaUserLevel extends AbstractEntity
{
    #[Property(isPrimaryKey: true)]
    public int $id;
    #[Property]
//    public string $logo_image;
    #[Property]
    public string $name;
    #[Property]
    public int $level;
    #[Property]
//    public int $rebate_layers;
    #[Property]
    public int $referee_num;
    #[Property]
    public int $team_num;
    #[Property]
    public int $member_level;
    #[Property]
    public int $member_level_num;
    #[Property]
//    public string $bonus;
    #[Property]
//    public string $layer_1_ratio;
    #[Property]
//    public string $layer_2_ratio;
    #[Property]
//    public string $layer_3_ratio;
    #[Property]
//    public string $layer_4_ratio;
    #[Property]
//    public string $layer_5_ratio;
    #[Property]
    public int $is_open;

    public function tableName(): string
    {
        return 'ba_user_level';
    }
}
