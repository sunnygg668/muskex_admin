<?php

declare(strict_types=1);

namespace app\custom\model;

use EasySwoole\FastDb\AbstractInterface\AbstractEntity;
use EasySwoole\FastDb\Attributes\Property;

class BaUser extends AbstractEntity
{
    #[Property(isPrimaryKey: true)]
    public int $id;
//    #[Property]
//    public int $group_id;
//    #[Property]
//    public string $username;
//    #[Property]
//    public string $nickname;
//    #[Property]
//    public string $email;
//    #[Property]
//    public string $mobile;
//    #[Property]
//    public string $avatar;
//    #[Property]
//    public int $gender;
//    #[Property]
//    public ?string $birthday;
//    #[Property]
//    public float $money;
//    #[Property]
//    public int $score;
//    #[Property]
//    public ?int $last_login_time;
//    #[Property]
//    public string $last_login_ip;
//    #[Property]
//    public int $login_failure;
//    #[Property]
//    public string $join_ip;
//    #[Property]
//    public ?int $join_time;
//    #[Property]
//    public string $motto;
//    #[Property]
//    public string $password;
//    #[Property]
//    public string $salt;
//    #[Property]
//    public ?string $fund_password;
//    #[Property]
//    public ?string $fund_salt;
//    #[Property]
//    public string $status;
//    #[Property]
//    public ?int $update_time;
//    #[Property]
//    public ?int $create_time;
//    #[Property]
//    public ?int $refereeid;
//    #[Property]
//    public ?string $invitationcode;
//    #[Property]
//    public ?string $name;
//    #[Property]
//    public ?string $idcard;
//    #[Property]
//    public ?int $is_can_withdraw;
//    #[Property]
//    public ?int $is_team_leader;
//    #[Property]
//    public int $is_whitelist;
//    #[Property]
//    public ?int $is_activation;
//    #[Property]
//    public ?int $limit_withdraw_time;
//    #[Property]
//    public ?string $team_flag;
//    #[Property]
//    public ?int $team_leader_id;
    #[Property]
    public ?int $team_level;
//    #[Property]
//    public string $commission_pool;
    #[Property]
    public int $level;
    #[Property]
    public int $referee_nums;
    #[Property]
    public int $team_nums;

    public function tableName(): string
    {
        return 'ba_user';
    }
}
