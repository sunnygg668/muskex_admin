<?php

namespace modules\export;

use Throwable;
use app\common\library\Menu;
use app\admin\model\AdminRule;

class Export
{

    /**
     * @throws Throwable
     */
    public function install(): void
    {
        $examplesMenu = AdminRule::where('name', 'examples')->value('id');
        if (!$examplesMenu) {
            $menu = [
                [
                    'type'      => 'menu_dir',
                    'title'     => '开发示例',
                    'name'      => 'examples',
                    'path'      => 'examples',
                    'icon'      => 'fa fa-code',
                    'menu_type' => 'tab',
                ]
            ];
            Menu::create($menu);
            $examplesMenu = AdminRule::where('name', 'examples')->value('id');
        }
        $menu = [
            [
                'type'      => 'menu',
                'title'     => '导出基础示例',
                'name'      => 'examples/export',
                'path'      => 'examples/export',
                'icon'      => 'el-icon-Download',
                'menu_type' => 'tab',
                'component' => '/src/views/backend/examples/export/index.vue',
                'keepalive' => '1',
                'pid'       => $examplesMenu,
                'children'  => [
                    ['type' => 'button', 'title' => '查看', 'name' => 'examples/export/index'],
                    ['type' => 'button', 'title' => '添加', 'name' => 'examples/export/add'],
                    ['type' => 'button', 'title' => '编辑', 'name' => 'examples/export/edit'],
                    ['type' => 'button', 'title' => '删除', 'name' => 'examples/export/del'],
                    ['type' => 'button', 'title' => '快速排序', 'name' => 'examples/export/sortable'],
                    ['type' => 'button', 'title' => '导出', 'name' => 'examples/export/export']
                ],
            ]
        ];
        Menu::create($menu);
    }

    /**
     * @throws Throwable
     */
    public function uninstall(): void
    {
        Menu::delete('examples/export', true);
    }

    /**
     * @throws Throwable
     */
    public function enable(): void
    {
        Menu::enable('examples/export');
    }

    /**
     * @throws Throwable
     */
    public function disable(): void
    {
        Menu::disable('examples/export');
    }

}