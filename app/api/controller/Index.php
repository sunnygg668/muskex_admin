<?php

namespace app\api\controller;

use app\admin\model\ba\coin\Coin;
use app\admin\model\ba\financial\Card;
use app\admin\model\ba\market\Carousel;
use app\admin\model\ba\market\News;
use app\admin\model\ba\market\Notice;
use app\admin\model\ba\user\Level;
use app\admin\model\User as UserModel;
use app\common\controller\Frontend;
use app\common\library\RedisKey;
use app\common\library\token\TokenExpirationException;
use app\custom\library\BinanceUtil;
use app\custom\library\RedisUtil;
use ba\Tree;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Db;

class Index extends Frontend
{
    protected array $noNeedLogin = ['appInfo','opcacheReset','ping'];

    public function initialize(): void
    {
        parent::initialize();
    }

    public function ping(){
        $this->success('ok');
    }

    /**
     * 清理全局opcache缓存
     * @return void
     */
    public function opcacheReset(){
        opcache_reset();
        $this->success('清理全局opcache缓存完成');
    }

    public function home()
    {
        $coinList = RedisUtil::remember(RedisKey::COIN_HOME_RECOMMEND, function() {
            return Coin::where(['status' => '1', 'home_recommend' => '1'])->field('id, alias,logo_image, kline_type, margin, name')->order('weigh desc')->select();
        }, 300);
        $inviteRegisterRule = get_sys_config('invite_register_rule');

        $noticeList = RedisUtil::remember(RedisKey::NOTICE_LIST, function() {
            return Notice::whereTime('release_time', '<=', time())->order('is_top desc, release_time desc')->limit(5)->select();
        }, 300);

        $newsList = RedisUtil::remember(RedisKey::NEWS_LIST, function() {
            $newsAll = News::whereTime('release_time', '<=', time())->order('id desc')->limit(3)->select();
            foreach ($newsAll as $news) {
                $news['content'] = mb_substr(strip_tags($news['content']), 0, 60);
            }
            return $newsAll;
        }, 300);

        $appVersion = get_sys_config('app_version');
        $androidDownloadUrl = get_sys_config('android_download_url');
        $iosDownloadUrl = get_sys_config('ios_download_url');
        $wgtDownloadUrl = get_sys_config('wgt_download_url');
        $appVersionDesc = get_sys_config('app_version_desc');
        $openFaceRecognition = (int)get_sys_config('open_face_recognition');
        $customerServiceLink = get_sign_sys_config('customer_service_link')['customer_service_link']['value'];
        $openFastPayment = (int)get_sys_config('open_fast_payment');
        $result = [
            'coinList' => $coinList,
            'inviteRegisterRule' => $inviteRegisterRule,
            'noticeList' => $noticeList,
            'newsList' => $newsList,
            'appVersion' => $appVersion,
            'androidDownloadUrl' => $androidDownloadUrl,
            'iosDownloadUrl' => $iosDownloadUrl,
            'wgtDownloadUrl' => $wgtDownloadUrl,
            'appVersionDesc' => $appVersionDesc,
            'openFaceRecognition' => $openFaceRecognition,
            'customerServiceLink' => $customerServiceLink,
            'openFastPayment' => $openFastPayment,
        ];
        $this->success('', $result);
    }

    public function appInfo()
    {
        $appVersion = get_sys_config('app_version');
        $androidDownloadUrl = get_sys_config('android_download_url');
        $iosDownloadUrl = get_sys_config('ios_download_url');
        $wgtDownloadUrl = get_sys_config('wgt_download_url');
        $appVersionDesc = get_sys_config('app_version_desc');
        $result = [
            'appVersion' => $appVersion,
            'androidDownloadUrl' => $androidDownloadUrl,
            'iosDownloadUrl' => $iosDownloadUrl,
            'wgtDownloadUrl' => $wgtDownloadUrl,
            'appVersionDesc' => $appVersionDesc,
        ];
        $this->success('', $result);
    }

    public function userHome()
    {
        $userId = $this->auth->id;
        $card = Card::where(['user_id' => $userId, 'status' => 1])->find();
        $canFinancialRecharge = $card ? 1 : 0;
        $result = [
            'canFinancialRecharge' => $canFinancialRecharge
        ];
        $this->success('', $result);
    }

    public function coinInfo()
    {
        $middleKlineTypes = request()->param('klineTypes');
        $middleKlineTypes = explode(',', $middleKlineTypes);
        $homeKlineTypes = get_sys_config('home_kline_types');
        $homeKlineTypes = explode(',', $homeKlineTypes);
        $client = BinanceUtil::getBinanceRedis();
        $ticker = json_decode($client->get('ticker'), true);
        $middleTicker = [];
        foreach ($middleKlineTypes as $klineType) {
            $key = strtolower(str_replace('/', '', $klineType)) . '@ticker';
            if (array_key_exists($key, $ticker)) {
                $middleTicker[] = ['kline_type' => $klineType, 'data' => $ticker[$key]['data']];
            }
        }
        $homeTicker = [];
        foreach ($homeKlineTypes as $klineType) {
            $key = strtolower(str_replace('/', '', $klineType)) . '@ticker';
            $homeTicker[] = ['kline_type' => $klineType, 'data' => $ticker[$key]['data']];
        }
        $result = [
            'homeTicker' => $homeTicker,
            'middleTicker' => $middleTicker
        ];
        $this->success('', $result);
    }

    public function levelInfo()
    {
        $userId = $this->auth->id;
        $levelList = Level::where('level', '>', 0)
            ->where('is_open', '1')
            ->order('level asc')
            ->select();
        $todayInviteCount = UserModel::where(['refereeid' => $userId, 'is_activation' => 1])
            ->whereDay('create_time')
            ->count();
        $weekInviteCount = UserModel::where(['refereeid' => $userId, 'is_activation' => 1])
            ->whereWeek('create_time')
            ->count();
        $monthInviteCount = UserModel::where(['refereeid' => $userId, 'is_activation' => 1])
            ->whereMonth('create_time')
            ->count();
        $todayInviteReachedGive = get_sys_config('today_invite_reached_give');
        $weekInviteReachedGive = get_sys_config('week_invite_reached_give');
        $monthInviteReachedGive = get_sys_config('month_invite_reached_give');
        $result = [
            'levelList' => $levelList,
            'todayInviteCount' => $todayInviteCount,
            'weekInviteCount' => $weekInviteCount,
            'monthInviteCount' => $monthInviteCount,
            'todayInviteReachedGive' => $todayInviteReachedGive,
            'weekInviteReachedGive' => $weekInviteReachedGive,
            'monthInviteReachedGive' => $monthInviteReachedGive,
        ];
        $this->success('', $result);
    }


    public function carouselList()
    {
        $list = RedisUtil::remember(RedisKey::CAROUSEL, function() {
            return Carousel::where(['status' => 1])->order('weigh desc')->select();
        }, 0);

        if($list){
            $upload_cdn_url = get_sys_config('upload_cdn_url');
            foreach ($list as &$v) {//json必须用引用，对象不用
                $editor = strip_tags($v['editor']);
                if (empty($editor) && empty($v['url'])) {
                    $v['canOpen'] = 0;
                } else {
                    $v['canOpen'] = 1;
                    unset($v['editor']);
                }
                $v['image'] =  $upload_cdn_url. $v['image'];
            }
        }
        $this->success('', $list);
    }

    public function carouselDetail()
    {
        $id = $this->request->param('id');
        $carousel = Carousel::find($id);
        $this->success('', $carousel);
    }

    public function helpCenter()
    {
        $list = get_sys_config('','help_center',false);
        $this->success('', $list);
    }

    public function helpDetail()
    {
        $name = $this->request->param('name');
        $detail = get_sys_config($name,'help_center',false);
        $this->success('', $detail);
    }

    public function downloadTutorial()
    {
        $downloadTutorial = get_sys_config('download_tutorial');
        $this->success('', $downloadTutorial);
    }

    public function installTutorial()
    {
        $installTutorial = get_sys_config('install_tutorial');
        $this->success('', $installTutorial);
    }

    public function otherTutorial()
    {
        $otherTutorial = get_sys_config('other_tutorial');
        $this->success('', $otherTutorial);
    }

    public function index(): void
    {
        $menus = [];
        if ($this->auth->isLogin()) {
            $rules     = [];
            $userMenus = $this->auth->getMenus();
            foreach ($userMenus as $item) {
                if ($item['type'] == 'menu_dir') {
                    $menus[] = $item;
                } else if ($item['type'] != 'menu') {
                    $rules[] = $item;
                }
            }
            $rules = array_values($rules);
        } else {
            $requiredLogin = $this->request->get('requiredLogin/b', false);
            if ($requiredLogin) {
                try {
                    $token = get_auth_token(['ba', 'user', 'token']);
                    $this->auth->init($token);
                } catch (TokenExpirationException) {
                    $this->error(__('Token expiration'), [], 409);
                }
                $this->error(__('Please login first'), [
                    'type' => $this->auth::NEED_LOGIN
                ], $this->auth::LOGIN_RESPONSE_CODE);
            }
            $rules = Db::name('user_rule')
                ->where('status', '1')
                ->where('no_login_valid', 1)
                ->where('type', 'in', ['route', 'nav', 'button'])
                ->order('weigh', 'desc')
                ->select()
                ->toArray();
            $rules = Tree::instance()->assembleChild($rules);
        }
        $this->success('', [
            'site'             => [
                'siteName'     => get_sys_config('site_name'),
                'recordNumber' => get_sys_config('record_number'),
                'version'      => get_sys_config('version'),
                'cdnUrl'       => full_url(),
                'upload'       => get_upload_config(),
            ],
            'openMemberCenter' => Config::get('buildadmin.open_member_center'),
            'userInfo'         => $this->auth->getUserInfo(),
            'rules'            => $rules,
            'menus'            => $menus,
        ]);
    }
}
