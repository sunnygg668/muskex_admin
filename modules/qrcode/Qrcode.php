<?php

namespace modules\qrcode;

use Throwable;
use app\common\library\Menu;
use app\admin\model\AdminRule;

class Qrcode
{
    /**
     * 安装
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
                'title'     => '二维码生成示例',
                'name'      => 'examples/qrcode',
                'path'      => 'examples/qrcode',
                'icon'      => 'fa fa-qrcode',
                'menu_type' => 'tab',
                'component' => '/src/views/backend/examples/qrcode.vue',
                'keepalive' => '1',
                'pid'       => $examplesMenu,
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
        Menu::delete('examples/qrcode', true);
    }

    /**
     * 启用
     * @throws Throwable
     */
    public function enable(): void
    {
        Menu::enable('examples/qrcode');
    }

    /**
     * 禁用
     * @throws Throwable
     */
    public function disable(): void
    {
        Menu::disable('examples/qrcode');
    }

}