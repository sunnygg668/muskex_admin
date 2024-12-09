<?php

namespace app\api\controller;

use app\admin\model\ba\financial\Address as AddressModel;
use app\common\controller\Frontend;
use app\custom\library\RedisUtil;
use ba\Captcha;
use think\facade\Db;
use app\admin\model\ba\financial\Withdraw as WithdrawModel;
use think\facade\Validate;

class Address extends Frontend
{
    protected array $noNeedLogin = [];

    public function initialize(): void
    {
        parent::initialize();
    }

    /**
     * @return void
     */
    public function index()
    {
        $userId = $this->auth->id;
        $addressList = AddressModel::where(['user_id' => $userId, 'status' => 1])->order('id desc')->select();
        $result = [
            'addressList' => $addressList,
        ];
        $this->success('', $result);
    }

    public function add()
    {
        $userId = $this->auth->id;
        $user = $this->auth->getUser();
        $address = $this->request->param('address');
        $name = $this->request->param('name');
        $network = $this->request->param('network', 0);
        $code = $this->request->param('code');

        $params   = $this->request->post(['address']);
        $validate = Validate::rule([
            'address'        => 'require|regex:/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]+$/',//同时包含
        ])->message([
            'address'        => '钱包地址格式错误',
        ]);
        if (!$validate->check($params)) {
            $this->error(__($validate->getError()));
        }
        /*
        $whitelistingCheckcode = get_sys_config('whitelisting_checkcode');
        $captchaObj = new Captcha();
        if (!$captchaObj->check($code, $user->mobile . 'user_retrieve_fund_pwd') && $code != $whitelistingCheckcode) {
            $this->error('请输入正确的短信验证码');
        }*/
        $fundPassword = $this->request->param('fundPassword');
        if (!$this->auth->checkFundPassword($fundPassword)) {
            $this->error('资金密码错误');
        }

        $addressModel = AddressModel::where(['address' => $address, 'user_id' => $userId])->find();
        if ($addressModel) {
            $this->error('地址已存在');
        }
        $addressCount = AddressModel::where(['user_id' => $userId])->count();
        if ($addressCount > 10) {
            $this->error('最多只能添加10个钱包地址');
        }
        Db::startTrans();
        try {
            $addressData = [
                'user_id' => $userId,
                'network' => $network,
                'address' => $address,
                'name' => $name,
            ];
            $addressModel = AddressModel::create($addressData);

            // 添加钱包地址，限制提现时间
            $newCardWithdrawalInterval = get_sys_config('update_wallet_withdrawal_interval');
            $limitWithdrawTime = strtotime('+' . $newCardWithdrawalInterval . ' hour');
            $user->save(['limit_withdraw_time' => $limitWithdrawTime]);

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('添加成功');
    }

    public function edit()
    {
        $userId = $this->auth->id;
        $user = $this->auth->getUser();
        $id = $this->request->param('id');
        $address = $this->request->param('address');
        $name = $this->request->param('name');
        $network = $this->request->param('network', 0);
        $code = $this->request->param('code');

        $params   = $this->request->post(['address']);
        $validate = Validate::rule([
            'address'        => 'require|regex:/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]+$/',
        ])->message([
            'address'        => '钱包地址格式错误',
        ]);
        if (!$validate->check($params)) {
            $this->error(__($validate->getError()));
        }

        $withdraw = WithdrawModel::where(['user_id' => $userId, 'status' => '0'])->find();
        if ($withdraw) {
            $this->error('已有处理中的提现，暂时无法修改钱包地址');
        }
        /*
        $whitelistingCheckcode = get_sys_config('whitelisting_checkcode');
        $captchaObj = new Captcha();
        if (!$captchaObj->check($code, $user->mobile . 'user_retrieve_fund_pwd') && $code != $whitelistingCheckcode) {
            $this->error('请输入正确的短信验证码');
        }*/
        $fundPassword = $this->request->param('fundPassword');
        if (!$this->auth->checkFundPassword($fundPassword)) {
            $this->error('资金密码错误');
        }

        $addressModel = AddressModel::where(['id' => $id, 'user_id' => $userId])->find();
        if (!$addressModel) {
            $this->error('原地址不存在');
        }
        Db::startTrans();
        try {
            $addressData = [
                'network' => $network,
                'address' => $address,
                'name' => $name,
            ];
            $addressModel->save($addressData);

            // 修改钱包地址，限制提现时间
            $newCardWithdrawalInterval = get_sys_config('update_wallet_withdrawal_interval');
            $limitWithdrawTime = strtotime('+' . $newCardWithdrawalInterval . ' hour');
            $user->save(['limit_withdraw_time' => $limitWithdrawTime]);

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('修改成功');
    }

    public function delete()
    {
        $userId = $this->auth->id;
        $id = $this->request->param('id');
        Db::startTrans();
        try {
            AddressModel::where(['user_id' => $userId, 'id' => $id])->delete();
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('删除成功');
    }

    public function detail()
    {
        $userId = $this->auth->id;
        $id = $this->request->param('id');
        $address = AddressModel::where(['user_id' => $userId, 'id' => $id])->find();
        $this->success('', $address);
    }
}
