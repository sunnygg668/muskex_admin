<?php

namespace app\admin\controller\routine;

use Throwable;
use ba\Filesystem;
use ba\TableManager;
use think\facade\Db;
use app\common\controller\Backend;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * 数据导入记录
 *
 */
class Dataimport extends Backend
{
    /**
     * Dataimport模型对象
     * @var object
     * @phpstan-var \app\admin\model\routine\Dataimport
     */
    protected object $model;

    protected string|array $preExcludeFields = ['id', 'create_time'];

    protected array $withJoinTable = ['admin'];

    protected string|array $quickSearchField = ['id', 'data_table', 'records', 'import_success_records'];

    protected array $noNeedPermission = ['handleXls'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\routine\Dataimport;
    }

    public function downloadImportTemplate()
    {
        $table = $this->request->get('table', '');
        if (!$table) {
            $this->error(__('Parameter error'));
        }

        $fields      = TableManager::getTableColumns($table);
        $spreadsheet = new Spreadsheet();
        $worksheet   = $spreadsheet->getActiveSheet();
        $worksheet->setTitle($table);
        // 设置表头
        $i = 0;
        foreach ($fields as $field) {
            $worksheet->setCellValue([$i + 1, 1], $field['COLUMN_NAME'] . ($field['COLUMN_COMMENT'] ? '(' . $field['COLUMN_COMMENT'] . ')' : ''));
            $i++;
        }

        // 直接下载
        ob_end_clean();
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");

        $taskName        = $table . '-template.xlsx';
        $encodedFilename = urlencode($taskName);
        $ua              = $_SERVER["HTTP_USER_AGENT"];
        if (str_contains($ua, "MSIE")) {
            header('Content-Disposition: attachment; filename="' . $encodedFilename . '"');
        } else if (str_contains($ua, "Firefox")) {
            header('Content-Disposition: attachment; filename*="utf8\'\'' . $taskName . '"');
        } else {
            header('Content-Disposition: attachment; filename="' . $taskName . '"');
        }
        header("Content-Transfer-Encoding:binary");
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    public function handleXls()
    {
        if (!$this->auth->check('routine/dataimport/add')) {
            $this->error(__('You have no permission'), [], 401);
        }
        $file  = $this->request->request('file', '');
        $table = $this->request->request('table', '');
        if (!$table) {
            $this->error('请选择数据表！');
        }
        if (!$file) {
            $this->error('请上传导入数据！');
        }

        // 读取xls文件内容
        $filePath    = Filesystem::fsFit(public_path() . $file);
        $spreadsheet = IOFactory::load($filePath);
        $sheet       = $spreadsheet->getSheet(0);
        $rowCount    = $sheet->getHighestRow();
        $data        = $sheet->toArray();
        $fields      = TableManager::getTableColumns($table);

        // 寻找字段对应数据所在的 key 值
        // 字段名、字段备注、字段名(字段备注)、字段名(字段备注+字段字典)
        $importKeys = [];
        foreach ($fields as $key => $field) {
            $title    = $field['COLUMN_NAME'] . ($field['COLUMN_COMMENT'] ? '(' . $field['COLUMN_COMMENT'] . ')' : '');
            $titleKey = array_search($title, $data[0]);
            if ($titleKey !== false) {
                $importKeys[$field['COLUMN_NAME']] = $titleKey;
                continue;
            }

            $nameKey = array_search($field['COLUMN_NAME'], $data[0]);
            if ($nameKey !== false) {
                $importKeys[$field['COLUMN_NAME']] = $nameKey;
                continue;
            }

            if ($field['COLUMN_COMMENT']) {
                $commentKey = array_search($field['COLUMN_COMMENT'], $data[0]);
                if ($commentKey !== false) {
                    $importKeys[$field['COLUMN_NAME']] = $commentKey;
                    continue;
                }

                if (strpos($field['COLUMN_COMMENT'], ':')) {
                    $comment      = explode(':', $field['COLUMN_COMMENT']);
                    $cleanComment = $comment[0];
                    $commentKey   = array_search($cleanComment, $data[0]);
                    if ($commentKey !== false) {
                        $importKeys[$field['COLUMN_NAME']] = $commentKey;
                        continue;
                    }

                    $titleCleanComment = $field['COLUMN_NAME'] . '(' . $cleanComment . ')';
                    $commentKey        = array_search($titleCleanComment, $data[0]);
                    if ($commentKey !== false) {
                        $importKeys[$field['COLUMN_NAME']] = $commentKey;
                    }
                    $fields[$key]['COLUMN_COMMENT'] = $cleanComment;
                }
            }
        }

        $importPre = [];
        foreach ($data as $key => $item) {
            if ($key == 0) continue;
            $importPreItem = [];
            foreach ($importKeys as $importKey => $importValueKey) {
                $importPreItem[$importKey] = $item[$importValueKey];
            }
            $importPre[] = $importPreItem;
        }

        if ($this->request->isPost()) {
            // 导入到表
            $nowTime    = time();
            $nowYmdHis  = date('Y-m-d H:i:s');
            $timeFields = ['createtime', 'create_time', 'updatetime', 'update_time'];
            foreach ($importPre as &$item) {
                foreach ($timeFields as $timeField) {
                    if (array_key_exists($timeField, $fields) && (!isset($item[$timeField]) || !$item[$timeField])) {
                        if ($fields[$timeField]['DATA_TYPE'] == 'int' || $fields[$timeField]['DATA_TYPE'] == 'bigint') {
                            $item[$timeField] = $nowTime;
                        } elseif ($fields[$timeField]['DATA_TYPE'] == 'datetime') {
                            $item[$timeField] = $nowYmdHis;
                        }
                    }
                }
            }
            Db::startTrans();
            $res = 0;
            try {
                $res = Db::name($table)->strict(false)->limit(500)->insertAll($importPre);
                Db::name('dataimport')->insert([
                    'data_table'             => $table,
                    'admin_id'               => $this->auth->id,
                    'file'                   => $file,
                    'records'                => $rowCount - 1,
                    'import_success_records' => $res,
                    'radio'                  => 'import',
                    'create_time'            => $nowTime,
                ]);
                Db::commit();
                @unlink($filePath);
            } catch (Throwable $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            $this->success('总计' . ($rowCount - 1) . '行数据，成功导入' . $res . '条！', [
                'data' => $importPre,
            ]);
        }

        if ($rowCount > 101) {
            $importPre = array_merge(array_slice($importPre, 0, 50), array_slice($importPre, $rowCount - 51, $rowCount));
        }

        $this->success('', [
            'fields'   => $fields,
            'rowCount' => ($rowCount - 1),
            'data'     => $importPre,
        ]);
    }

    /**
     * 查看
     * @throws Throwable
     */
    public function index(): void
    {
        $this->request->filter(['strip_tags', 'trim']);
        // 如果是select则转发到select方法,若select未重写,其实还是继续执行index
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);
        $res->visible(['admin' => ['username']]);

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
        $this->success('', [
            'tables' => $this->getTableList(),
        ]);
    }

    protected function getTableList(): array
    {
        $tablePrefix     = config('database.connections.mysql.prefix');
        $outExcludeTable = [
            // 功能表
            'admin',
            'admin_group',
            'area',
            'token',
            'captcha',
            'attachment',
            'admin_log',
            'admin_group_access',
            'user_money_log',
            'user_score_log',
            'dataimport',
            'dataexport',
            'crud_log',
            'security_data_recycle_log',
            'security_sensitive_data_log',
        ];

        $outTables = [];
        $tables    = TableManager::getTableList();
        $pattern   = '/^' . $tablePrefix . '/i';
        foreach ($tables as $table => $tableComment) {
            $table = preg_replace($pattern, '', $table);
            if (!in_array($table, $outExcludeTable)) {
                $outTables[$table] = $tableComment;
            }
        }
        return $outTables;
    }
}