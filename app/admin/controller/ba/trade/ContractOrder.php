<?php

namespace app\admin\controller\ba\trade;

use app\admin\model\ba\user\Assets;
use think\facade\Db;
use Throwable;
use app\common\controller\Backend;

class ContractOrder extends Backend
{
    /**
     * ContractOrder模型对象
     * @var object
     * @phpstan-var \app\admin\model\ba\trade\ContractOrder
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'income', 'buy_time', 'sell_time'];

    protected array $withJoinTable = ['user', 'contract', 'refereeUser', 'teamLeader'];

    protected string|array $quickSearchField = ['user.mobile'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\ba\trade\ContractOrder;
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
        $res->visible(['user' => ['wallet_addr', 'nickname', 'username', 'name', 'mobile', 'is_whitelist'], 'contract' => ['coin_id'], 'refereeUser' => ['name', 'mobile'], 'teamLeader' => ['name', 'mobile']]);

        $this->success('', [
            'list' => $res->items(),
            'total' => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    public function payment()
    {
        $ids = $this->request->param('ids');
        $idArray = explode(',', $ids);
        $orders = $this->model->where('id', 'in', $idArray)
            ->where('income', '<', 0)
            ->where('payment_status', 0)
            ->select();
        Db::startTrans();
        try {
            foreach ($orders as $order) {
                $paymentNum = abs($order->income);
                Assets::updateMainCoinAssetsBalance($order->user_id, $paymentNum, 'contract_payment');
                $order->save(['payment_status' => 1]);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('赔付成功');
    }
}
