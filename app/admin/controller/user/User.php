<?php

namespace app\admin\controller\user;

use app\admin\model\User as UserModel;
use app\common\controller\Backend;
use ba\Random;
use think\facade\Db;
use think\facade\Queue;
use Throwable;

class User extends Backend
{
    /**
     * @var object
     * @phpstan-var UserModel
     */
    protected object $model;

    protected array $withJoinTable = [
        'refereeUser' => ['wallet_addr', 'username', 'nickname', 'mobile', 'name'],
        'level' => ['id', 'name', 'level', 'logo_image'],
        'teamLeader' => ['wallet_addr', 'username', 'nickname', 'mobile', 'name'],
    ];

    // 排除字段
    protected string|array $preExcludeFields = ['last_login_time', 'login_failure', 'password', 'fund_password', 'salt', 'fund_salt'];

    protected string|array $quickSearchField = ['username', 'nickname', 'mobile', 'id', 'name'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new UserModel();
    }

    /**
     * 查看
     * @throws Throwable
     */
    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = $this->model
            ->withoutField('password,salt')
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    /**
     * 添加
     * @throws Throwable
     */
    public function add(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            if (isset($data['invitationCode']) && !empty($data['invitationCode'])) {
                $refereeUser = UserModel::where(['invitationcode' => $data['invitationCode'], 'status' => 1])->find();
                if (!$refereeUser) {
                    $this->error('邀请码无效');
                }
                $data['refereeid'] = $refereeUser->id;
                if ($refereeUser->is_team_leader == 1) {
                    $data['team_level'] = 1;
                    $data['team_leader_id'] = $refereeUser->id;
                } else {
                    $data['team_level'] = $refereeUser->team_level + 1;
                    $data['team_leader_id'] = $refereeUser->team_leader_id;
                }
                $data['team_flag'] = $refereeUser->team_flag;
            }

            $salt   = Random::build('alnum', 16);
            $passwd = encrypt_password($data['password'], $salt);
            $invitationCode = Random::build('alpha', 8);

            $data   = $this->excludeFields($data);
            $result = false;
            $this->model->startTrans();
            try {
                $data['salt']     = $salt;
                $data['password'] = $passwd;
                $data['invitationcode'] = $invitationCode;
                // 模型验证
                if ($this->modelValidate) {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    if (class_exists($validate)) {
                        $validate = new $validate;
                        if ($this->modelSceneValidate) $validate->scene('add');
                        $validate->check($data);
                    }
                }
                $result = $this->model->save($data);
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $userId = $this->model->id;
                Queue::push('\app\custom\job\UserQueue@createAssets', ['user_id' => $userId], 'user');
                Queue::push('\app\custom\job\UserQueue@createMainCoinAddress', ['user_id' => $userId], 'user');
                Queue::push('\app\custom\job\RewardQueue@userRegister', ['user_id' => $userId], 'reward');
                Queue::push('\app\custom\job\UserQueue@createTeamLevel', ['user_id' => $userId], 'user');
                $this->success(__('Added successfully'));
            } else {
                $this->error(__('No rows were added'));
            }
        }

        $this->error(__('Parameter error'));
    }

    /**
     * 编辑
     * @param string|int|null $id
     * @throws Throwable
     */
    public function edit(string|int $id = null): void
    {
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }

        if ($this->request->isPost()) {
            $password = $this->request->post('password', '');
            if ($password) {
                $this->model->resetPassword($id, $password);
            }

            $fundPassword = $this->request->post('fund_password', '');
            if ($fundPassword) {
                $this->model->resetFundPassword($id, $fundPassword);
            }

            if($this->request->post('is_certified') == 2 && (!$this->request->post('name') || !$this->request->post('idcard'))){
                $this->error(__('Parameter %s can not be empty', ['姓名/身份证号']));
            }

            parent::edit();
        }

        unset($row->salt);
        $row->password = '';
        $row->fund_password = '';
        $this->success('', [
            'row' => $row
        ]);
    }

    /**
     * 重写select
     * @throws Throwable
     */
    public function select(): void
    {
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);

        foreach ($res as $re) {
            $re->nickname_text = $re->username . '(ID:' . $re->id . ')';
        }

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    /**
     * 切换团队成员的可提现状态
     *
     * @return void
     */
    public function toggleWithdraw(): void
    {
        $id = $this->request->param('id');
        $canWithdraw = $this->request->param('canWithdraw');

        $user = $this->model->find($id);

        // 获取所有下级会员
        $childIds = Db::query('select queryChildrenUsers(:refereeid) as childIds', ['refereeid' => $user->id])[0]['childIds'];
        $childIds .= ',' . $user->id;
        UserModel::where('id', 'in', $childIds)->update(['is_can_withdraw' => $canWithdraw]);
        $this->success('设置成功');
    }

    /**
     * 切换团队成员的启用状态
     *
     * @return void
     */
    public function toggleEnable(): void
    {
        $id = $this->request->param('id');
        $enable = $this->request->param('enable');

        $user = $this->model->find($id);

        // 获取所有下级会员
        $childIds = Db::query('select queryChildrenUsers(:refereeid) as childIds', ['refereeid' => $user->id])[0]['childIds'];
        $childIds .= ',' . $user->id;
        UserModel::where('id', 'in', $childIds)->update(['status' => $enable]);
        $this->success('设置成功');
    }

    public function teamLeader(): void
    {
        $quickSearch  = $this->request->get("quickSearch/s", '');
        $initKey      = $this->request->get("initKey/s", '');

        $where = ['is_team_leader' => 1];
        if($initKey){
            switch ($initKey) {
                case 'ba_user.id':
                    $initKey = "username";
                    break;
            }
            $where[] = [$initKey, 'like', '%' . str_replace('%', '\%', $quickSearch) . '%'];
        }

        $res = $this->model
            ->withoutField('password,salt')
            ->where($where)
            ->order("id desc")->select();

        $this->success('', [
            'list'   => $res
        ]);
    }

}
