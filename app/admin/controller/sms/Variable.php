<?php

namespace app\admin\controller\sms;

use app\common\controller\Backend;
use modules\sms\library\Helper;

/**
 * 模板变量管理
 *
 */
class Variable extends Backend
{
    /**
     * Variable模型对象
     * @var object
     * @phpstan-var \app\admin\model\sms\Variable
     */
    protected object $model;

    protected string|array $quickSearchField = ['title', 'name'];

    protected string|array $defaultSortField = 'id,desc';

    protected string|array $preExcludeFields = ['createtime', 'updatetime'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\sms\Variable;
    }

    public function getVar($id)
    {
        $val = Helper::calcVar($id);
        if ($val) {
            $this->success('', $val);
        }
        $this->error('计算失败，请检查变量设置');
    }

}