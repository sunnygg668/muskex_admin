<?php
namespace app\api\controller;

use app\common\controller\Frontend;
use app\admin\model\User as UserModel;
use app\common\model\BussinessLog;
use think\facade\Config;
use app\custom\library\Ali;
use think\facade\Log;
use think\facade\Queue;


class Certification extends Frontend
{
    /**
     * @var array|string[]
     */
    protected array $noNeedLogin = ['callback'];


    public function checkCertNoBlackList()
    {
        $params = $this->request->post(['certNo']);
        $certno_black_list = get_sys_config('real_person_auth');
        $certno_black_list = str_replace([" ", "，"], ",", $certno_black_list);//防止错填
        $is_certno_black_list = false;
        if(in_array($params['certNo'],explode(',',$certno_black_list))){
            $is_certno_black_list = true;
        }
        $this->success('', ['is_certno_black_list'=>$is_certno_black_list]);
    }


    /**
     * 实名认证
     * @return void
     */
    public function realNameAuthentication(): void
    {
        $params = $this->request->post(['certName', 'certNo', 'metaInfo', 'returnUrl']);
        if (empty($params['certName']) || empty($params['certNo']) || empty($params['metaInfo'])) {
            $this->error(__("Parameter cannot be empty"));
        }
        BussinessLog::record('实名认证数据 ：' . json_encode($params, JSON_UNESCAPED_UNICODE));

        $params['metaInfo'] = str_replace('&quot;', '"', $params['metaInfo']);
        $user = $this->auth->getUser();

        $callbackUr = Config::get("ali.callback_url");
        $result = Ali::initFaceVerify($params, $callbackUr);
        BussinessLog::record('实名认证返回数据 ：' . json_encode($result, JSON_UNESCAPED_UNICODE));

        if ($result['code'] == 200) {
            // 更新用户信息
            $user->save([
                'name'      => $params['certName'],
                'idcard'    => $params['certNo'],
                'certify_id'=> $result['resultObject']['certifyId']
            ]);
            $this->success('', $result);
        } else {
            $this->error('', $result);
        }
    }

    /**
     * 提现认证
     * @return void
     */
    public function withdrawAuth():void
    {
        $params = $this->request->post(['metaInfo', 'returnUrl']);
        if (empty($params['metaInfo']) || empty($params['returnUrl'])) {
            $this->error(__("Parameter cannot be empty"));
        }
        $params['metaInfo'] = str_replace('&quot;', '"', $params['metaInfo']);

        $user = $this->auth->getUser();
        $params['certName'] = $user->name;
        $params['certNo'] = $user->idcard;
        $result = Ali::initFaceVerify($params);

        if ($result['code'] == 200) {
            $this->success('', $result);
        } else {
            $this->error('', $result);
        }
    }

    /**
     * 获取认证结果
     * @return void
     */
    public function results():void
    {
        $params = $this->request->param(['certifyId']);
        BussinessLog::record('获取认证结果：' . json_encode($params, JSON_UNESCAPED_UNICODE));
        if (empty($params['certifyId'])) {
            $this->error(__("Parameter cannot be empty"));
        }

        $result = Ali::certificationResults($params['certifyId']);

        if ($result['code'] == 200 && $result['resultObject']['passed'] == 'T') {
            $this->success('', $result);
        } else {
            $this->error('', $result);
        }
    }

    /**
     * 认证回调通知
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function callback() {
        $certifyId  = $this->request->param('certifyId');
        $passed     = $this->request->param('passed');
        BussinessLog::record('认证回调数据 ：' . json_encode($_REQUEST, JSON_UNESCAPED_UNICODE));

        $user = UserModel::where('certify_id', $certifyId)->find();
        if (!empty($user)) {
            if ($passed == 200) {
                $user->save([
                    'is_certified' => 2, // 认证成功
                ]);
                Queue::push('\app\custom\job\TaskRewardQueue@authGive', ['user_id' => $user->id], 'task_reward');
            } else {
                $user->save([
                    'is_certified' => 3, // 认证失败
                ]);
            }
        }
    }
}
