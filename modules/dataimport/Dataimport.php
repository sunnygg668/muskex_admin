<?php

namespace modules\dataimport;

use Throwable;
use app\common\library\Menu;
use app\admin\model\AdminRule;

class Dataimport
{
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
                'title'     => '数据导入管理',
                'name'      => 'routine/dataimport',
                'path'      => 'routine/dataimport',
                'icon'      => 'fa fa-upload',
                'menu_type' => 'tab',
                'component' => '/src/views/backend/routine/dataimport/index.vue.bak',
                'keepalive' => '1',
                'pid'       => $pMenu ? $pMenu : 0,
                'children'  => [
                    ['type' => 'button', 'title' => '查看', 'name' => 'routine/dataimport/index'],
                    ['type' => 'button', 'title' => '导入数据', 'name' => 'routine/dataimport/add'],
                    ['type' => 'button', 'title' => '下载模板', 'name' => 'routine/dataimport/downloadImportTemplate'],
                    ['type' => 'button', 'title' => '删除', 'name' => 'routine/dataimport/del'],
                ],
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
        Menu::delete('routine/dataimport', true);
    }

    /**
     * 启用
     * @throws Throwable
     */
    public function enable(): void
    {
        Menu::enable('routine/dataimport');
    }

    /**
     * 禁用
     * @throws Throwable
     */
    public function disable(): void
    {
        Menu::disable('routine/dataimport');
    }
}