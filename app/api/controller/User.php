<?php

namespace app\api\controller;

use app\admin\model\ba\financial\Address as AddressModel;
use app\admin\model\ba\miners\Order;
use app\admin\model\ba\trade\ContractOrder;
use app\admin\model\ba\trade\ManagementOrder;
use app\admin\model\ba\user\Assets;
use app\admin\model\ba\user\CoinChange as CoinChangeModel;
use app\admin\model\ba\user\CommissionChange;
use app\admin\model\ba\user\Level;
use app\admin\model\User as UserModel;
use app\api\validate\User as UserValidate;
use app\admin\model\Domain;
use app\common\controller\Frontend;
use app\common\facade\Token;
use app\common\library\RedisKey;
use app\common\model\BussinessLog;
use app\custom\library\QrCode;
use app\custom\library\RedisUtil;
use ba\Captcha;
use ba\ClickCaptcha;
use ba\Random;
use DateTime;
use think\db\Query;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;
use Throwable;

class User extends Frontend
{
    protected array $noNeedLogin = ['logout', 'register', 'login','getAllUserMob'];

    protected array $noNeedPermission = ['index'];

    public function initialize(): void
    {
        parent::initialize();
    }

    //提供给外部压测
    public function getAllUserMob(){
        $user = UserModel::where(['status' => 1])->field(['mobile'])->select();
        $list = [];
        foreach ($user as $v){
            $list[] = aes_encrypt($v['mobile']);
        }
        $this->success('', ['list' => $list]);
    }

    public function register(): void
    {
        $params = $this->request->param(['mobile', 'code', 'captcha', 'captchaId', 'captchaInfo', 'password', 'invitationCode', 'fundPassword']);
        $mobile_black_list = get_sys_config('mobile_black_list');
        $mobile_black_list = str_replace([" ", "，"], ",", $mobile_black_list);//防止错填
        if(in_array($params['mobile'],explode(',',$mobile_black_list))){
            $this->error("注册失败!");
        }
        /*$captchaObj = new Captcha();发送短信的地方已经验证并失效(不失效一样被刷)，此处无法再校验了
        if (!$captchaObj->check($params['captcha'], $params['captchaId'])) {
            $this->error(__('Please enter the correct verification code'));
        }*/

        $whitelistingCheckcode = get_sys_config('whitelisting_checkcode');
        $captchaObj = new Captcha();
        if (!$captchaObj->check($params['code'], $params['mobile'] . 'user_register') && $params['code'] != $whitelistingCheckcode) {
            $this->error('请输入正确的短信验证码');
        }
        if (empty($params['invitationCode'])) {
            $this->error('请填写邀请码');
        }
        $extend = [];
        $invitationcode_init = get_sys_config('invitationcode_init');
        if($params['invitationCode'] != $invitationcode_init){

            $refereeUser = RedisUtil::remember(RedisKey::USER_INVITATIONCODE . $params['invitationCode'], function() use ($params) {
                return UserModel::where(['invitationcode' => $params['invitationCode'], 'status' => 1])->find();
            }, 600);

            if (!$refereeUser) {
                $this->error('邀请码无效');
            }
            $extend['refereeid'] = $refereeUser['id'];
            if ($refereeUser['is_team_leader'] == 1) {
                $extend['team_level'] = 1;
                $extend['team_leader_id'] = $refereeUser['id'];
            } else {
                $extend['team_level'] = $refereeUser['team_level'] + 1;
                $extend['team_leader_id'] = $refereeUser['team_leader_id'];
            }
            $extend['team_flag'] = $refereeUser['team_flag'];
        }
        $invitationCode = Random::build('alpha', 8);
        $extend['invitationcode'] = $invitationCode;
        if(isset($params['fundPassword']) && $params['fundPassword']){
            $fundSalt = Random::build('alnum', 16);
            $fundPwd = encrypt_password($params['fundPassword'], $fundSalt);
            $extend['fund_salt'] = $fundSalt;
            $extend['fund_password'] = $fundPwd;
        }
        $userName = 'a' . $params['mobile'];
        $email = $params['mobile'] . '@qq.com';
        $res = $this->auth->register($userName, $params['password'], $params['mobile'], $email, 1, $extend);
        if (isset($res) && $res === true) {
            $userId = $this->auth->getUser()->id;
            Queue::push('\app\custom\job\UserQueue@createAssets', ['user_id' => $userId], 'user');
            Queue::push('\app\custom\job\UserQueue@createMainCoinAddress', ['user_id' => $userId], 'user');
            Queue::push('\app\custom\job\RewardQueue@userRegister', ['user_id' => $userId], 'reward');
            Queue::push('\app\custom\job\UserQueue@createTeamLevel', ['user_id' => $userId], 'user');
            $this->success('注册成功', [
                'userInfo' => $this->auth->getUserInfo()
            ]);
        } else {
            $msg = $this->auth->getError();
            $msg = $msg ?: '注册失败';
            $this->error($msg);
        }
    }

    public function login(): void
    {
        $mobile = $this->request->param('mobile');
        $password = $this->request->param('password');
        $keep = (bool)$this->request->param('keep', true);
        BussinessLog::record($this->request->param());
        $res = $this->auth->login($mobile, $password, $keep);
        if (isset($res) && $res === true) {
            $userInfo = $this->auth->getUserInfo();
            $user = $this->auth->getUser();
            if ($user->idcard) {
                $idCardPre = mb_substr($user->idcard, 0, 5);
                $idcardSub = mb_substr($user->idcard, -3);
                $idCard = $idCardPre . '**********' . $idcardSub;

                $userInfo['name'] = $user->name;
                $userInfo['idCard'] = $idCard;
            }
            BussinessLog::record(__('Login succeeded!')."---".json_encode([
                    'userInfo' => $userInfo
                ]));
            $this->success(__('Login succeeded!'), [
                'userInfo' => $userInfo
            ]);
        } else {
            $msg = $this->auth->getError();
            $msg = $msg ?: __('Check in failed, please try again or contact the website administrator~');
            BussinessLog::record($msg);
            $this->error($msg);
        }
    }

    public function checkIn(): void
    {
        if ($this->auth->isLogin()) {
            $this->success(__('You have already logged in. There is no need to log in again~'), [
                'type' => $this->auth::LOGGED_IN
            ], $this->auth::LOGIN_RESPONSE_CODE);
        }
        if ($this->request->isPost()) {
            $params = $this->request->post(['tab', 'email', 'mobile', 'username', 'password', 'keep', 'captcha', 'captchaId', 'captchaInfo', 'registerType']);
            if (!in_array($params['tab'], ['login', 'register'])) {
                $this->error(__('Unknown operation'));
            }
            $validate = new UserValidate();
            try {
                $validate->scene($params['tab'])->check($params);
            } catch (Throwable $e) {
                $this->error($e->getMessage());
            }
            if ($params['tab'] == 'login') {
                $captchaObj = new ClickCaptcha();
                if (!$captchaObj->check($params['captchaId'], $params['captchaInfo'])) {
                    $this->error(__('Captcha error'));
                }
                $res = $this->auth->login($params['username'], $params['password'], (bool)$params['keep']);
            } elseif ($params['tab'] == 'register') {
                $captchaObj = new Captcha();
                if (!$captchaObj->check($params['captcha'], ($params['registerType'] == 'email' ? $params['email'] : $params['mobile']) . 'user_register')) {
                    $this->error(__('Please enter the correct verification code'));
                }
                $res = $this->auth->register($params['username'], $params['password'], $params['mobile'], $params['email']);
            }
            if (isset($res) && $res === true) {
                $this->success(__('Login succeeded!'), [
                    'userInfo' => $this->auth->getUserInfo(),
                    'routePath' => '/user'
                ]);
            } else {
                $msg = $this->auth->getError();
                $msg = $msg ?: __('Check in failed, please try again or contact the website administrator~');
                $this->error($msg);
            }
        }
        $this->success('', [
            'accountVerificationType' => get_account_verification_type()
        ]);
    }

    public function logout(): void
    {
        if ($this->request->isPost()) {
            $refreshToken = $this->request->post('refreshToken', '');
            if ($refreshToken) Token::delete((string)$refreshToken);
            $this->auth->logout();
            $this->success();
        }
    }

    public function userInfo()
    {
        BussinessLog::record("userInfo获取用户登录信息".$this->auth->getUser()->mobile);
        $userInfo = $this->auth->getUserInfo();
        $user = $this->auth->getUser();
        if ($user->idcard) {
            $idCardPre = mb_substr($user->idcard, 0, 5);
            $idcardSub = mb_substr($user->idcard, -3);
            $idCard = $idCardPre . '**********' . $idcardSub;

            $userInfo['name'] = $user->name;
            $userInfo['idCard'] = $idCard;
        } else {
            $userInfo['name'] = '';
            $userInfo['idCard'] = '';
        }
        $userInfo['is_certified'] = $user->is_certified;

        $nextRefereeNums = 0;
        $nextTeamNums = 0;
        $refereeNumsDiff = 0;
        $teamNumsDiff = 0;
        $level = RedisUtil::remember(RedisKey::LEVEL.$user->level,function () use ($user){
            return Level::where(['level' => $user->level])->field('id, name, logo_image, bonus, referee_num, team_num')->find();
        },0);
        $nextLevel = RedisUtil::remember(RedisKey::LEVEL.($user->level + 1),function () use ($user){
            return Level::where(['level' => ($user->level + 1)])->field('id, name, logo_image, bonus, referee_num, team_num')->find();
        },0);
        if ($nextLevel) {
            $nextRefereeNums = $nextLevel['referee_num'];
            $nextTeamNums = $nextLevel['team_num'];
            $refereeNumsDiff = max($nextRefereeNums - $user->referee_nums, 0);
            $teamNumsDiff = max($nextTeamNums - $user->team_nums, 0);
        }
        $teamLeader = RedisUtil::remember(RedisKey::USER_INFO_UNCHANGE.$user->team_leader_id,function () use ($user){
            return UserModel::find($user->team_leader_id);
        },86400);
        $websiteToken = get_sign_sys_config('customer_website_token')['customer_website_token']['value'];
        $hmacKey = get_sign_sys_config('customer_hmac_key')['customer_hmac_key']['value'];
        $message = $user->mobile;
        $identifier_hash = hash_hmac('sha256', $message, $hmacKey);
        $result = [
            'userInfo' => $userInfo,
            'level' => $level,
            'nextLevel' => $nextLevel,
            'nextRefereeNums' => $nextRefereeNums,
            'refereeNumsDiff' => $refereeNumsDiff,
            'nextTeamNums' => $nextTeamNums,
            'teamNumsDiff' => $teamNumsDiff,
            'teamLeaderMobile' => $teamLeader ? $teamLeader['mobile'] : '',//手机号不会改变可以缓存
            'identifierHash' => $identifier_hash,
            'websiteToken'  => $websiteToken
        ];
        BussinessLog::record("userInfo获取用户登录信息返回结果：".json_encode($result));
        $this->success('', $result);
    }

    public function qrCode()
    {
        $userId = $this->auth->id;
        $user = $this->auth->getUser();
        if ($user->is_team_leader == 1) {
            $domain = Domain::where('team_leader_id', $userId)->field('domain_name')->find();
        } else {
            $domain = Domain::where('team_leader_id', $this->auth->team_leader_id)->field('domain_name')->find();
        }
        if (!empty($domain) || $user->is_team_leader == 1) {
            $inviteUrl = $domain->domain_name . '/?invitationCode=' . $user->invitationcode;
            $fileName = QrCode::generate($inviteUrl, 'invite', md5($inviteUrl));
        } else {
            $inviteUrl = '';
            $fileName = '';
        }
//        $inviteUrl = 'https://aa.mktx.org/h5/#/pages/register/register?invitationCode=' . $user->invitationcode;
        $result = [
            'invitationCode' => $user->invitationcode,
            'inviteUrl' => $inviteUrl,
            'qrcode' => $fileName,
            'qrcode_url' => get_sys_config('upload_cdn_url') . $fileName,

        ];
        $this->success('', $result);
    }

    public function authentication()
    {
        $user = $this->auth->getUser();
        $name = $this->request->param('name');
        $idCard = $this->request->param('idCard');
        BussinessLog::record('uniapp认证回调数据 ：' . json_encode($_REQUEST, JSON_UNESCAPED_UNICODE));

        $user->save([
            'name' => $name,
            'idcard' => $idCard,
            'is_certified' => 2,
        ]);
        Queue::push('\app\custom\job\TaskRewardQueue@authGive', ['user_id' => $user->id], 'task_reward');
        $this->success();
    }

    public function walletInfo()
    {
        $user = $this->auth->getUser();
        $assets = Assets::mainCoinAssets($user->id);
        $moneyHourIncomeRatio = get_sys_config('money_hour_income_ratio');
        $result = [
            'usdt' => $assets->balance,
            'money' => $user->money,
            'moneyHourIncomeRatio' => $moneyHourIncomeRatio//bcmul($moneyHourIncomeRatio, 24, 2) 改为按小时结算
        ];
        $this->success('', $result);
    }

    public function teamData(): void
    {
        $user = $this->auth->getUser();
        $userId = request()->param('userId');
        $pagesize = request()->param('pagesize', 5);
        $page = request()->param('page', 1);
        if ($userId) {
            $user = UserModel::find($userId);
        }
        $inviteUsers = UserModel::where(['refereeid' => $user->id])
            ->order('id desc')
            ->paginate($pagesize, false,
                [
                    'page' => $page,
                    'var_page' => 'page',
                ]);
        $currentPage = $inviteUsers->currentPage();
        $lastPage = $inviteUsers->lastPage();
        $total = $inviteUsers->total();
        $inviteUserIds = [];
        foreach ($inviteUsers as $inviteUser) {
            $inviteUserIds[] = $inviteUser->id;
        }
        if (!$userId && $page == 1) {
            array_unshift($inviteUserIds, $user->id);
        }
        $data = [];
        foreach ($inviteUserIds as $inviteUserId) {
            $inviteUser = UserModel::find($inviteUserId);
            $teamNums = $inviteUser->team_nums;
            $mobile = substr_replace($inviteUser->mobile, '****', 3, 4);
            $childIds = Db::query('select queryChildrenUsers(:refereeid) as childIds', ['refereeid' => $inviteUserId])[0]['childIds'];
            $childIds = explode(',', $childIds);
            array_shift($childIds);
            array_unshift($childIds, $inviteUserId);
            $totalTeamNums = count($childIds);
            $todayContractAmount = ContractOrder::where(['user_id' => $inviteUserId])->whereDay('buy_time')->sum('invested_coin_num');
            // 今日获得佣金
            $todayCommission = CommissionChange::where(['user_id' => $inviteUserId, 'type' => 'margin_reward'])->whereDay('create_time')->sum('amount');
            $mainCoinId = get_sys_config('main_coin');
            // 今日个人收益
            $todayIncome = ContractOrder::with(['contract' => function (Query $query) use ($mainCoinId) {
                $query->where('coin_id', $mainCoinId);
            }])->where(['user_id' => $inviteUserId])->whereDay('sell_time')->sum('income');
            $teamContractAmount = ContractOrder::where('user_id', 'in', $childIds)->sum('invested_coin_num');
            $todayTeamContractAmount = ContractOrder::where('user_id', 'in', $childIds)->whereDay('buy_time')->sum('invested_coin_num');

            // 今日团队总收益
            $todayTeamTotalIncome = CoinChangeModel::where('user_id', 'in', $childIds)
                ->where('type', 'in', ['contract_income', 'miners_income', 'management_income', 'money_income', 'commission_pool_collect'])
                ->whereDay('create_time')
                ->sum('amount');
            $minersTotalPrice = Order::where(['user_id' => $inviteUserId])->count();
            $managementTotalPrice = ManagementOrder::where(['user_id' => $inviteUserId])->sum('total_price');
            $data[] = [
                'userId' => $inviteUserId, // 用户id
                'teamNums' => $teamNums, // 团队活跃人数
                'mobile' => $mobile, // 手机号
                'money' => $inviteUser->money,
                'totalTeamNums' => $totalTeamNums, // 团队总人数
                'todayContractAmount' => $todayContractAmount,
                'todayCommission' => $todayCommission, // 今日获得佣金
                'todayIncome' => $todayIncome, // 今日个人收益
                'teamContractAmount' => $teamContractAmount,
                'todayTeamContractAmount' => $todayTeamContractAmount,
                'todayTeamTotalIncome'  => $todayTeamTotalIncome, // 今日团队总收益
                'minersTotalPrice' => $minersTotalPrice,
                'managementTotalPrice' => $managementTotalPrice,
            ];
        }
        $result = [
            'currentPage' => $currentPage,
            'lastPage' => $lastPage,
            'perPage' => $pagesize,
            'total' => $total,
            'data' => $data,
        ];
        $this->success('', $result);
    }
}
