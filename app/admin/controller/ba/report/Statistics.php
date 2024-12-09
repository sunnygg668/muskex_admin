<?php

namespace app\admin\controller\ba\report;

use app\common\controller\Backend;
use app\common\library\RedisKey;
use app\custom\library\RedisUtil;

class Statistics extends Backend
{
    /**
     * Statistics模型对象
     * @var object
     * @phpstan-var \app\admin\model\ba\report\Statistics
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\ba\report\Statistics;
    }

    public function total()
    {
        $data = RedisUtil::get(RedisKey::REPORT_STATISTICS_TOTAL);
        $this->success('', $data);
    }
}