<?php

namespace modules\dataexport;

use Throwable;
use ba\Filesystem;
use FilesystemIterator;
use app\common\library\Menu;
use app\admin\model\AdminRule;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class Dataexport
{
    private string $uid = 'dataexport';

    /**
     * 安装
     * @throws Throwable
     */
    public function install(): void
    {
        $pMenu = AdminRule::where('name', 'routine')->value('id');
        $menu  = [
            [
                'type'      => 'menu',
                'title'     => '数据导出管理',
                'name'      => 'routine/dataexport',
                'path'      => 'routine/dataexport',
                'icon'      => 'fa fa-cloud-download',
                'menu_type' => 'tab',
                'component' => '/src/views/backend/routine/dataexport/index.vue.bak',
                'keepalive' => '1',
                'pid'       => $pMenu ? $pMenu : 0,
                'children'  => [
                    ['type' => 'button', 'title' => '查看', 'name' => 'routine/dataexport/index'],
                    ['type' => 'button', 'title' => '添加', 'name' => 'routine/dataexport/add'],
                    ['type' => 'button', 'title' => '编辑', 'name' => 'routine/dataexport/edit'],
                    ['type' => 'button', 'title' => '删除', 'name' => 'routine/dataexport/del'],
                    ['type' => 'button', 'title' => '执行任务', 'name' => 'routine/dataexport/start'],
                ],
            ],
            [
                'type'      => 'menu',
                'title'     => '导出任务控制',
                'name'      => 'routine/dataexport/taskControl',
                'path'      => 'routine/dataexport/taskControl/:id',
                'icon'      => 'fa fa-cloud-download',
                'menu_type' => 'tab',
                'component' => '/src/views/backend/routine/dataexport/taskControl.vue',
                'keepalive' => '1',
                'extend'    => 'add_rules_only',
                'pid'       => $pMenu ? $pMenu : 0,
            ]
        ];
        Menu::create($menu);
    }

    /**
     * 卸载
     * @throws Throwable
     */
    public function uninstall(): void
    {
        Menu::delete('routine/dataexport', true);
        Menu::delete('routine/dataexport/taskControl', true);
    }

    /**
     * 启用
     * @throws Throwable
     */
    public function enable(): void
    {
        Menu::enable('routine/dataexport');
        Menu::enable('routine/dataexport/taskControl');
    }

    /**
     * 禁用
     * @throws Throwable
     */
    public function disable(): void
    {
        Menu::disable('routine/dataexport');
        Menu::disable('routine/dataexport/taskControl');
    }

    /**
     * 更新
     * @throws Throwable
     */
    public function update(): void
    {
        // 兼容系统v1.1.2的语言包按需加载
        // 寻找安装时备份中的 lang/pages 文件，如果有，还原到 lang/backend 内而不是原位置
        $ebakDir = root_path() . 'modules' . DIRECTORY_SEPARATOR . 'ebak' . DIRECTORY_SEPARATOR;
        $zipFile = $ebakDir . $this->uid . '-install.zip';
        $zipDir  = false;
        if (is_file($zipFile)) {
            try {
                $zipDir = Filesystem::unzip($zipFile);
            } catch (Throwable) {
                // skip
            }
        }
        if ($zipDir) {
            $oldBaInputs = [
                Filesystem::fsFit('web\src\lang\pages\zh-cn\routine\dataexport.ts'),
                Filesystem::fsFit('web\src\lang\pages\en\routine\dataexport.ts')
            ];
            foreach ($oldBaInputs as $oldBaInput) {
                @unlink(root_path() . $oldBaInput);
            }

            foreach (
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($zipDir, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                ) as $item
            ) {
                $ebakFile = Filesystem::fsFit($iterator->getSubPathName());
                if (!$item->isDir() && in_array($ebakFile, $oldBaInputs)) {
                    $newFileName = str_replace(DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR, $ebakFile);
                    copy($item, root_path() . $newFileName);
                }
            }
        }
        Filesystem::delDir($zipDir);
        Filesystem::delEmptyDir(root_path() . 'web/src/lang/pages/en');
        Filesystem::delEmptyDir(root_path() . 'web/src/lang/pages/zh-cn');
    }
}