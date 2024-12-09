<?php

namespace app\api\controller;

use app\admin\model\ba\coin\Coin;
use app\admin\model\User as UserModel;
use app\common\controller\Frontend;
use app\admin\model\Domain;
use app\common\library\RedisKey;
use app\custom\library\RedisUtil;
use think\facade\Cache;

/**
 * 中转页判断
 */
class Transfer extends Frontend
{
    protected array $noNeedLogin = ['index'];

    public function initialize(): void
    {
        parent::initialize();
    }

    public function index() {
        $invitationCode = $this->request->param('invitationCode');
        if (!empty($invitationCode)) {
            $user = RedisUtil::remember(RedisKey::TRANSFER_USER_INVITATIONCODE . $invitationCode, function() use ($invitationCode) {
                return UserModel::where(['invitationcode' => $invitationCode])->field('id, is_team_leader, team_leader_id')->find();
            }, 300);
            if (!empty($user)) {
                if ($user['is_team_leader'] == 1) {
                    $where[] = ['team_leader_id', '=', $user['id']];
                } else {
                    $where[] = ['team_leader_id', '=', $user['team_leader_id']];
                }
            }
        }
        // 获取请求头中的域名
        $url = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');

        // 解析 URL 并获取域名部分
        $parsedUrl = parse_url($url, PHP_URL_HOST);
//        $where[] = ['domain_name', 'like', '%' . str_replace('%', '\%', $parsedUrl) . '%'];
        $where[] = ['status', '=', 1];

        $domainInfo = RedisUtil::remember(RedisKey::DOMAIN_NAME . $parsedUrl, function() use ($where) {
            return Domain::where($where)->find();
        }, 600);

        if (empty($domainInfo)) {
            $this->error('域名已禁用');
        } else {
            $this->success('success', $domainInfo);
        }
    }
}
