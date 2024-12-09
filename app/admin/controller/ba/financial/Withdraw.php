<?php

namespace app\admin\controller\ba\financial;

use app\admin\model\ba\user\Assets;
use app\admin\model\User;
use app\custom\library\UDun;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\db\Query;
use think\facade\Db;
use Throwable;
use app\common\controller\Backend;
use app\admin\model\ba\financial\Bank;
use app\admin\model\ba\financial\Withdraw as WithdrawModel;

class Withdraw extends Backend
{
    /**
     * Withdraw模型对象
     * @var object
     * @phpstan-var \app\admin\model\ba\financial\Withdraw
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time', 'update_time'];

    protected array $withJoinTable = ['user', 'coin', 'financialCard', 'refereeUser', 'teamLeader'];

    protected string|array $quickSearchField = ['id', 'user.username', 'user.name'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\ba\financial\Withdraw;
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

        $w = [];
        foreach ($where as $key => $search) {
            if ($search[0] == 'refereeUser.mobile') {
                $mobile = $search[2];
                $refereeUser = User::where('mobile', $mobile)->find();
                if ($refereeUser) {
                    $childIds = Db::query('select queryChildrenUsers(:refereeid) as childIds', ['refereeid' => $refereeUser->id])[0]['childIds'];
                    $w = function (Query $query) use ($childIds) {
                        $query->where('user.id', 'in', $childIds);
                    };
                } else {
                    $w = ['user.id' => 0];
                }
                unset($where[$key]);
            }
        }
        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->where($w)
            ->order($order)
            ->paginate($limit);
        $res->visible(['user' => ['wallet_addr', 'name', 'username', 'avatar', 'team_level', 'mobile'], 'coin' => ['name'], 'financialCard' => ['financial_bank_id','account_name','bank_num'], 'refereeUser' => ['mobile'], 'teamLeader' => ['wallet_addr', 'name', 'mobile']]);
        $items = $res->items();
        foreach ($items as &$item) {
            if ($item['financialCard']) {
                $bank = Bank::where('id', $item['financialCard']['financial_bank_id'])->find();
                $item['bankName'] = $bank ? $bank->name : '';
            }
        }
        $this->success('', [
            'list'   => $items,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    public function audit()
    {
        $id = $this->request->param('id');
        $status = $this->request->param('status');
        $withdraw = $this->model->find($id);
        if ($status == 4) {
            $udunMainCoinCode = get_sys_config('udun_main_coin_code');
            $udunMainCodeContract = get_sys_config('udun_main_code_contract');
            $res = UDun::uDunDispatch()->withdraw($withdraw->order_no, $udunMainCoinCode, $udunMainCodeContract, $withdraw->wallet_address, $withdraw->actual_coin);
            if ($res['code'] != 200) {
                $this->error('提现失败，状态码：' . $res['code']);
            }
        }
        $withdraw->save(['status' => $status]);
        $this->success('设置成功');
    }

    public function cancel()
    {
        $ids  = $this->request->param('ids');
        $withdrawList = $this->model->where('id', 'in', $ids)->select();
        if (!$withdrawList) {
            $this->error(__('Record not found'));
        }
        $dataLimitAdminIds = $this->getDataLimitAdminIds();
        if ($dataLimitAdminIds) {
            foreach ($withdrawList as $withdraw) {
                if (!in_array($withdraw[$this->dataLimitField], $dataLimitAdminIds)) {
                    $this->error(__('You have no permission'));
                }
            }
        }
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $remark = $this->request->param('remark');
                foreach ($withdrawList as $withdraw) {
                    $withdraw->save(['remark' => $remark, 'status' => 2]);
                    $assets = Assets::updateMainCoinAssetsBalance($withdraw->user_id, $withdraw->coin_num, 'coin_withdraw_return');
                    $assets = Assets::updateMainCoinAssetsBalance($withdraw->user_id, $withdraw->fee_coin, 'coin_withdraw_fee_return');
                }
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            $this->success(__('Update successful'));
        }
        $this->success('');
    }

    public function summary()
    {
        $whitelistUserIds = User::where('is_whitelist', 1)->column('id');
        $noWhitelistUserQuery = function (Query $query) use ($whitelistUserIds) {
            $query->where('user_id', 'not in', $whitelistUserIds);
        };
        $noWithdrawMoney = WithdrawModel::where($noWhitelistUserQuery)->where('type', 0)->where('status', 'in', '0,4')->sum('money');
        $noWithdrawMoneyNum = WithdrawModel::where($noWhitelistUserQuery)->where('type', 0)->where('status', 'in', '0,4')->count();
        $noWithdrawCoin = WithdrawModel::where($noWhitelistUserQuery)->where('type', 1)->where('status', 'in', '0,4')->sum('coin_num');
        $noWithdrawCoinNum = WithdrawModel::where($noWhitelistUserQuery)->where('type', 1)->where('status', 'in', '0,4')->count();
        $result = [
            'noWithdrawMoney' => $noWithdrawMoney,
            'noWithdrawMoneyNum' => $noWithdrawMoneyNum,
            'noWithdrawCoin' => $noWithdrawCoin,
            'noWithdrawCoinNum' => $noWithdrawCoinNum,
        ];
        $this->success('', $result);
    }

    public function exportNoAudit()
    {
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $w = ['type' => '0'];
        foreach ($where as $key => $search) {
            if ($search[0] == 'refereeUser.mobile') {
                $mobile = $search[2];
                $refereeUser = User::where('mobile', $mobile)->find();
                if ($refereeUser) {
                    $childIds = Db::query('select queryChildrenUsers(:refereeid) as childIds', ['refereeid' => $refereeUser->id])[0]['childIds'];
                    $w = function (Query $query) use ($childIds) {
                        $query->where('user.id', 'in', $childIds);
                    };
                } else {
                    $w = ['user.id' => 0];
                }
                unset($where[$key]);
            }
        }
        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->where($w)
            ->order($order)
            ->select();
        $res->visible(['user' => ['name', 'username', 'avatar', 'team_level', 'mobile'], 'coin' => ['name'], 'financialCard' => ['financial_bank_id','account_name','bank_num'], 'refereeUser' => ['mobile'], 'teamLeader' => ['name', 'mobile']]);
        foreach ($res as &$item) {
            if ($item['financialCard']) {
                $bank = Bank::where('id', $item['financialCard']['financial_bank_id'])->find();
                $item['bankName'] = $bank ? $bank->name : '';
            }
        }
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setCellValue([1, 1], '姓名');
        $sheet->setCellValue([2, 1], '金额');
        $sheet->setCellValue([3, 1], '银行名称');
        $sheet->setCellValue([4, 1], '卡号');
//        $sheet->setCellValue([5, 1], '手机号');
//        $sheet->setCellValue([6, 1], '状态');
//        $sheet->setCellValue([7, 1], '提交时间');
        $h = 2;
        foreach ($res as $v) {
            $sheet->setCellValue([1, $h], !empty($v['financialCard']) ? $v['financialCard']['account_name'] : '');
            $sheet->setCellValue([2, $h], $v['money']);
            $sheet->setCellValue([3, $h], $v['bankName']);
            $sheet->setCellValueExplicit([4, $h], !empty($v['financialCard']) ? $v['financialCard']['bank_num'] : '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
//            $sheet->setCellValue([5, $h], $v['user']['mobile']);
//            $sheet->setCellValue([6, $h], $v['status_text']);
//            $sheet->setCellValue([7, $h], date('Y-m-d H:i:s', $v['create_time']));
            $h++;
        }

        $writer = new Xlsx($spreadsheet);
        $file   = time() . '.xlsx';
        ob_end_clean();
        header('Content-Type: application/vnd.ms-excel');
        header('Access-Control-Expose-Headers:Content-Disposition');
        header('Content-Disposition: attachment;filename=' . $file);
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
    }

}
