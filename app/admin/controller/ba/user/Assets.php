<?php

namespace app\admin\controller\ba\user;

use app\admin\model\User;
use Throwable;
use app\common\controller\Backend;
use app\admin\model\ba\user\Assets as AssetsModel;

class Assets extends Backend
{
    /**
     * Assets模型对象
     * @var object
     * @phpstan-var \app\admin\model\ba\user\Assets
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time', 'update_time'];

    protected array $withJoinTable = ['user', 'coin'];

    protected string|array $quickSearchField = ['user.wallet_addr', 'user.mobile', 'user.nickname'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\ba\user\Assets;
    }

    /**
     * 查看
     * @throws Throwable
     */
    public function index(): void
    {
        // 如果是 select 则转发到 select 方法，若未重写该方法，其实还是继续执行 index
        if ($this->request->param('select')) {
            $this->select();
        }
        /**
         * 1. withJoin 不可使用 alias 方法设置表别名，别名将自动使用关联模型名称（小写下划线命名规则）
         * 2. 以下的别名设置了主表别名，同时便于拼接查询参数等
         * 3. paginate 数据集可使用链式操作 each(function($item, $key) {}) 遍历处理
         */
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);
        $res->visible(['user' => ['wallet_addr', 'nickname', 'name', 'username', 'mobile', 'team_leader_id', 'is_whitelist'], 'coin' => ['name']]);

        $items = $res->items();
        foreach ($items as &$item) {
            $item['teamLeader'] = User::where('id', $item->user['team_leader_id'])->field('name, mobile')->find();
        }

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    /**
     * 编辑
     * @throws Throwable
     */
    public function edit(): void
    {
        $pk  = $this->model->getPk();
        $id  = $this->request->param($pk);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }
        $dataLimitAdminIds = $this->getDataLimitAdminIds();
        if ($dataLimitAdminIds && !in_array($row[$this->dataLimitField], $dataLimitAdminIds)) {
            $this->error(__('You have no permission'));
        }
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }
            $data   = $this->excludeFields($data);
            $result = false;
            $this->model->startTrans();
            try {
                // 模型验证
                if ($this->modelValidate) {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    if (class_exists($validate)) {
                        $validate = new $validate;
                        if ($this->modelSceneValidate) $validate->scene('edit');
                        $data[$pk] = $row[$pk];
                        $validate->check($data);
                    }
                }
                $minusBalance = bcsub($data['balance'], $row->balance, 2);
                if ($minusBalance != 0) {
                    $type = $minusBalance > 0 ? 'system_recharge' : 'system_deduction';
                    AssetsModel::updateCoinAssetsBalance($row->user_id, $row->coin_id, $minusBalance, $type);
                    unset($data['balance']);
                    // 后台上分，限制提现时间
                    $newCardWithdrawalInterval = get_sys_config('per_recharge_withdrawal_interval');
                    $limitWithdrawTime = strtotime('+' . $newCardWithdrawalInterval . ' hour');
                    (new User())->where(['id' => $row->user_id])->update(['limit_withdraw_time' => $limitWithdrawTime]);
                }
                $minusFreeze = bcsub($data['freeze'], $row->freeze, 2);
                if ($minusFreeze != 0) {
                    $type = $minusFreeze > 0 ? 'system_freeze' : 'system_unfreeze';
                    AssetsModel::updateCoinAssetsBalance($row->user_id, $row->coin_id, -$minusFreeze, $type);
                }
                $result = $row->save($data);
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Update successful'));
            } else {
                $this->error(__('No rows updated'));
            }
        }

        $this->success('', [
            'row' => $row
        ]);
    }
}
