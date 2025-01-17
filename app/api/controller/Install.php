<?php
declare (strict_types=1);

namespace app\api\controller;

use app\admin\model\Admin as AdminModel;
use app\admin\model\User as UserModel;
use app\common\controller\Api;
use ba\Filesystem;
use ba\Random;
use ba\Terminal;
use ba\Version;
use think\App;
use think\db\exception\PDOException;
use think\facade\Config;
use think\facade\Db;

class Install extends Api
{
    protected bool $useSystemSettings = false;

    static string $ok   = 'ok';
    static string $fail = 'fail';
    static string $warn = 'warn';

    static string $lockFileName = 'install.lock';

    static string $dbConfigFileName    = 'database.php';
    static string $buildConfigFileName = 'buildadmin.php';

    static string $distDir = 'web' . DIRECTORY_SEPARATOR . 'dist';

    static array $needDependentVersion = [
        'php'  => '8.0.2',
        'npm'  => '9.8.1',
        'cnpm' => '7.1.0',
        'node' => '18.18.2',
        'yarn' => '1.2.0',
        'pnpm' => '6.32.13',
    ];

    static string $InstallationCompletionMark = 'install-end';


    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    public function terminal(): void
    {
        if ($this->isInstallComplete()) {
            return;
        }

        (new Terminal())->exec(false);
    }

    public function changePackageManager(): void
    {
        if ($this->isInstallComplete()) {
            return;
        }

        $newPackageManager = request()->post('manager', Config::get('terminal.npm_package_manager'));
        if (Terminal::changeTerminalConfig()) {
            $this->success('', [
                'manager' => $newPackageManager
            ]);
        } else {
            $this->error(__('Failed to switch package manager. Please modify the configuration file manually:%s', ['根目录/config/buildadmin.php']));
        }
    }

    public function envBaseCheck(): void
    {
        if ($this->isInstallComplete()) {
            $this->error(__('The system has completed installation. If you need to reinstall, please delete the %s file first', ['public/' . self::$lockFileName]), []);
        }
        if (env('database.type')) {
            $this->error(__('The .env file with database configuration was detected. Please clean up and try again!'));
        }

        $phpVersion        = phpversion();
        $phpVersionCompare = Version::compare(self::$needDependentVersion['php'], $phpVersion);
        if (!$phpVersionCompare) {
            $phpVersionLink = [
                [
                    'name' => __('need') . ' >= ' . self::$needDependentVersion['php'],
                    'type' => 'text'
                ],
                [
                    'name'  => __('How to solve?'),
                    'title' => __('Click to see how to solve it'),
                    'type'  => 'faq',
                    'url'   => 'https://wonderful-code.gitee.io/guide/install/preparePHP.html'
                ]
            ];
        }

        $dbConfigFile     = config_path() . self::$dbConfigFileName;
        $configIsWritable = Filesystem::pathIsWritable(config_path()) && Filesystem::pathIsWritable($dbConfigFile);
        if (!$configIsWritable) {
            $configIsWritableLink = [
                [
                    'name'  => __('View reason'),
                    'title' => __('Click to view the reason'),
                    'type'  => 'faq',
                    'url'   => 'https://wonderful-code.gitee.io/guide/install/dirNoPermission.html'
                ]
            ];
        }

        $publicIsWritable = Filesystem::pathIsWritable(public_path());
        if (!$publicIsWritable) {
            $publicIsWritableLink = [
                [
                    'name'  => __('View reason'),
                    'title' => __('Click to view the reason'),
                    'type'  => 'faq',
                    'url'   => 'https://wonderful-code.gitee.io/guide/install/dirNoPermission.html'
                ]
            ];
        }

        $phpPdo = extension_loaded("PDO");
        if (!$phpPdo) {
            $phpPdoLink = [
                [
                    'name' => __('PDO extensions need to be installed'),
                    'type' => 'text'
                ],
                [
                    'name'  => __('How to solve?'),
                    'title' => __('Click to see how to solve it'),
                    'type'  => 'faq',
                    'url'   => 'https://wonderful-code.gitee.io/guide/install/missingExtension.html'
                ]
            ];
        }

        $phpGd2 = extension_loaded('gd') && function_exists('imagettftext');
        if (!$phpGd2) {
            $phpGd2Link = [
                [
                    'name' => __('The gd extension and freeType library need to be installed'),
                    'type' => 'text'
                ],
                [
                    'name'  => __('How to solve?'),
                    'title' => __('Click to see how to solve it'),
                    'type'  => 'faq',
                    'url'   => 'https://wonderful-code.gitee.io/guide/install/gdFail.html'
                ]
            ];
        }

        $phpProc = function_exists('proc_open') && function_exists('proc_close') && function_exists('proc_get_status');
        if (!$phpProc) {
            $phpProcLink = [
                [
                    'name'  => __('View reason'),
                    'title' => __('proc_open or proc_close functions in PHP Ini is disabled'),
                    'type'  => 'faq',
                    'url'   => 'https://wonderful-code.gitee.io/guide/install/disablement.html'
                ],
                [
                    'name'  => __('How to modify'),
                    'title' => __('Click to view how to modify'),
                    'type'  => 'faq',
                    'url'   => 'https://wonderful-code.gitee.io/guide/install/disablement.html'
                ],
                [
                    'name'  => __('Security assurance?'),
                    'title' => __('Using the installation service correctly will not cause any potential security problems. Click to view the details'),
                    'type'  => 'faq',
                    'url'   => 'https://wonderful-code.gitee.io/guide/install/senior.html'
                ],
            ];
        }

        $this->success('', [
            'php_version'        => [
                'describe' => $phpVersion,
                'state'    => $phpVersionCompare ? self::$ok : self::$fail,
                'link'     => $phpVersionLink ?? [],
            ],
            'config_is_writable' => [
                'describe' => self::writableStateDescribe($configIsWritable),
                'state'    => $configIsWritable ? self::$ok : self::$fail,
                'link'     => $configIsWritableLink ?? []
            ],
            'public_is_writable' => [
                'describe' => self::writableStateDescribe($publicIsWritable),
                'state'    => $publicIsWritable ? self::$ok : self::$fail,
                'link'     => $publicIsWritableLink ?? []
            ],
            'php_pdo'            => [
                'describe' => $phpPdo ? __('already installed') : __('Not installed'),
                'state'    => $phpPdo ? self::$ok : self::$fail,
                'link'     => $phpPdoLink ?? []
            ],
            'php_gd2'            => [
                'describe' => $phpGd2 ? __('already installed') : __('Not installed'),
                'state'    => $phpGd2 ? self::$ok : self::$fail,
                'link'     => $phpGd2Link ?? []
            ],
            'php_proc'           => [
                'describe' => $phpProc ? __('Allow execution') : __('disabled'),
                'state'    => $phpProc ? self::$ok : self::$warn,
                'link'     => $phpProcLink ?? []
            ],
        ]);
    }
    public function envNpmCheck(): void
    {
        if ($this->isInstallComplete()) {
            $this->error('', [], 2);
        }

        $packageManager = request()->post('manager', 'none');

        $npmVersion        = Version::getVersion('npm');
        $npmVersionCompare = Version::compare(self::$needDependentVersion['npm'], $npmVersion);
        if (!$npmVersionCompare || !$npmVersion) {
            $npmVersionLink = [
                [
                    'name' => __('need') . ' >= ' . self::$needDependentVersion['npm'],
                    'type' => 'text'
                ],
                [
                    'name'  => __('How to solve?'),
                    'title' => __('Click to see how to solve it'),
                    'type'  => 'faq',
                    'url'   => 'https://wonderful-code.gitee.io/guide/install/prepareNpm.html'
                ]
            ];
        }

        if (in_array($packageManager, ['npm', 'cnpm', 'pnpm', 'yarn'])) {
            $pmVersion        = Version::getVersion($packageManager);
            $pmVersionCompare = Version::compare(self::$needDependentVersion[$packageManager], $pmVersion);

            if (!$pmVersion) {
                $pmVersionLink[] = [
                    'name' => __('need') . ' >= ' . self::$needDependentVersion[$packageManager],
                    'type' => 'text'
                ];
                if ($npmVersionCompare) {
                    $pmVersionLink[] = [
                        'name'  => __('Click Install %s', [$packageManager]),
                        'title' => '',
                        'type'  => 'install-package-manager'
                    ];
                } else {
                    $pmVersionLink[] = [
                        'name' => __('Please install NPM first'),
                        'type' => 'text'
                    ];
                }
            } elseif (!$pmVersionCompare) {
                $pmVersionLink[] = [
                    'name' => __('need') . ' >= ' . self::$needDependentVersion[$packageManager],
                    'type' => 'text'
                ];
                $pmVersionLink[] = [
                    'name' => __('Please upgrade %s version', [$packageManager]),
                    'type' => 'text'
                ];
            }
        } elseif ($packageManager == 'ni') {
            $pmVersion        = __('nothing');
            $pmVersionCompare = true;
        } else {
            $pmVersion        = __('nothing');
            $pmVersionCompare = false;
        }

        $nodejsVersion        = Version::getVersion('node');
        $nodejsVersionCompare = Version::compare(self::$needDependentVersion['node'], $nodejsVersion);
        if (!$nodejsVersionCompare || !$nodejsVersion) {
            $nodejsVersionLink = [
                [
                    'name' => __('need') . ' >= ' . self::$needDependentVersion['node'],
                    'type' => 'text'
                ],
                [
                    'name'  => __('How to solve?'),
                    'title' => __('Click to see how to solve it'),
                    'type'  => 'faq',
                    'url'   => 'https://wonderful-code.gitee.io/guide/install/prepareNodeJs.html'
                ]
            ];
        }

        $this->success('', [
            'npm_version'         => [
                'describe' => $npmVersion ?: __('Acquisition failed'),
                'state'    => $npmVersionCompare ? self::$ok : self::$warn,
                'link'     => $npmVersionLink ?? [],
            ],
            'nodejs_version'      => [
                'describe' => $nodejsVersion ?: __('Acquisition failed'),
                'state'    => $nodejsVersionCompare ? self::$ok : self::$warn,
                'link'     => $nodejsVersionLink ?? []
            ],
            'npm_package_manager' => [
                'describe' => $pmVersion ?: __('Acquisition failed'),
                'state'    => $pmVersionCompare ? self::$ok : self::$warn,
                'link'     => $pmVersionLink ?? [],
            ]
        ]);
    }

    public function testDatabase(): void
    {
        $database = [
            'hostname' => $this->request->post('hostname'),
            'username' => $this->request->post('username'),
            'password' => $this->request->post('password'),
            'hostport' => $this->request->post('hostport'),
            'database' => '',
        ];

        $conn = $this->connectDb($database);
        if ($conn['code'] == 0) {
            $this->error($conn['msg']);
        } else {
            $this->success('', [
                'databases' => $conn['databases']
            ]);
        }
    }

    public function baseConfig(): void
    {
        if ($this->isInstallComplete()) {
            $this->error(__('The system has completed installation. If you need to reinstall, please delete the %s file first', ['public/' . self::$lockFileName]));
        }

        $envOk    = $this->commandExecutionCheck();
        $rootPath = str_replace('\\', '/', root_path());
        if ($this->request->isGet()) {
            $this->success('', [
                'rootPath'            => $rootPath,
                'executionWebCommand' => $envOk
            ]);
        }
        $connectData = $databaseParam = $this->request->only(['hostname', 'username', 'password', 'hostport', 'database', 'prefix']);
        $connectData['database'] = '';
        $connect                 = $this->connectDb($connectData, true);
        if ($connect['code'] == 0) {
            $this->error($connect['msg']);
        }

        if (!in_array($databaseParam['database'], $connect['databases'])) {
            $sql = "CREATE DATABASE IF NOT EXISTS `{$databaseParam['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            $connect['pdo']->exec($sql);
        }

        $dbConfigFile    = config_path() . self::$dbConfigFileName;
        $dbConfigContent = @file_get_contents($dbConfigFile);
        $callback        = function ($matches) use ($databaseParam) {
            $value = $databaseParam[$matches[1]] ?? '';
            return "'$matches[1]'$matches[2]=>$matches[3]env('database.$matches[1]', '$value'),";
        };
        $dbConfigText    = preg_replace_callback("/'(hostname|database|username|password|hostport|prefix)'(\s+)=>(\s+)env\('database\.(.*)',\s+'(.*)'\),/", $callback, $dbConfigContent);
        $result          = @file_put_contents($dbConfigFile, $dbConfigText);
        if (!$result) {
            $this->error(__('File has no write permission:%s', ['config/' . self::$dbConfigFileName]));
        }

        $envFile        = root_path() . '.env-example';
        $envFileContent = @file_get_contents($envFile);
        if ($envFileContent) {
            $databasePos = stripos($envFileContent, '[DATABASE]');
            if ($databasePos !== false) {
                $envFileContent = substr($envFileContent, 0, $databasePos);
            }
            $envFileContent .= "\n" . '[DATABASE]' . "\n";
            $envFileContent .= 'TYPE = mysql' . "\n";
            $envFileContent .= 'HOSTNAME = ' . $databaseParam['hostname'] . "\n";
            $envFileContent .= 'DATABASE = ' . $databaseParam['database'] . "\n";
            $envFileContent .= 'USERNAME = ' . $databaseParam['username'] . "\n";
            $envFileContent .= 'PASSWORD = ' . $databaseParam['password'] . "\n";
            $envFileContent .= 'HOSTPORT = ' . $databaseParam['hostport'] . "\n";
            $envFileContent .= 'PREFIX = ' . $databaseParam['prefix'] . "\n";
            $envFileContent .= 'CHARSET = utf8mb4' . "\n";
            $envFileContent .= 'DEBUG = true' . "\n";
            $result         = @file_put_contents($envFile, $envFileContent);
            if (!$result) {
                $this->error(__('File has no write permission:%s', ['/' . $envFile]));
            }
        }
        $oldTokenKey        = Config::get('buildadmin.token.key');
        $newTokenKey        = Random::build('alnum', 32);
        $buildConfigFile    = config_path() . self::$buildConfigFileName;
        $buildConfigContent = @file_get_contents($buildConfigFile);
        $buildConfigContent = preg_replace("/'key'(\s+)=>(\s+)'$oldTokenKey'/", "'key'\$1=>\$2'$newTokenKey'", $buildConfigContent);
        $result             = @file_put_contents($buildConfigFile, $buildConfigContent);
        if (!$result) {
            $this->error(__('File has no write permission:%s', ['config/' . self::$buildConfigFileName]));
        }
        $result = @file_put_contents(public_path() . self::$lockFileName, date('Y-m-d H:i:s'));
        if (!$result) {
            $this->error(__('File has no write permission:%s', ['public/' . self::$lockFileName]));
        }
        $this->success('', [
            'rootPath'            => $rootPath,
            'executionWebCommand' => $envOk
        ]);
    }

    protected function isInstallComplete(): bool
    {
        if (is_file(public_path() . self::$lockFileName)) {
            $contents = @file_get_contents(public_path() . self::$lockFileName);
            if ($contents == self::$InstallationCompletionMark) {
                return true;
            }
        }
        return false;
    }

    public function commandExecComplete(): void
    {
        if ($this->isInstallComplete()) {
            $this->error(__('The system has completed installation. If you need to reinstall, please delete the %s file first', ['public/' . self::$lockFileName]));
        }
        $param = $this->request->only(['type', 'adminname', 'adminpassword', 'sitename']);
        if ($param['type'] == 'web') {
            $result = @file_put_contents(public_path() . self::$lockFileName, self::$InstallationCompletionMark);
            if (!$result) {
                $this->error(__('File has no write permission:%s', ['public/' . self::$lockFileName]));
            }
        } else {
            $adminModel             = new AdminModel();
            $defaultAdmin           = $adminModel->where('username', 'admin')->find();
            $defaultAdmin->username = $param['adminname'];
            $defaultAdmin->nickname = ucfirst($param['adminname']);
            $defaultAdmin->save();
            if (isset($param['adminpassword']) && $param['adminpassword']) {
                $adminModel->resetPassword($defaultAdmin->id, $param['adminpassword']);
            }
            $user = new UserModel();
            $user->resetPassword(1, Random::build());
            \app\admin\model\Config::where('name', 'site_name')->update([
                'value' => $param['sitename']
            ]);
        }
        $this->success();
    }

    private function commandExecutionCheck(): bool
    {
        $pm = Config::get('terminal.npm_package_manager');
        if ($pm == 'none') {
            return false;
        }
        $check['phpPopen']             = function_exists('proc_open') && function_exists('proc_close');
        $check['npmVersionCompare']    = Version::compare(self::$needDependentVersion['npm'], Version::getVersion('npm'));
        $check['pmVersionCompare']     = Version::compare(self::$needDependentVersion[$pm], Version::getVersion($pm));
        $check['nodejsVersionCompare'] = Version::compare(self::$needDependentVersion['node'], Version::getVersion('node'));
        $envOk = true;
        foreach ($check as $value) {
            if (!$value) {
                $envOk = false;
                break;
            }
        }
        return $envOk;
    }

    public function manualInstall(): void
    {
        $this->success('', [
            'webPath' => str_replace('\\', '/', root_path() . 'web')
        ]);
    }

    public function mvDist(): void
    {
        if (!is_file(root_path() . self::$distDir . DIRECTORY_SEPARATOR . 'index.html')) {
            $this->error(__('No built front-end file found, please rebuild manually!'));
        }

        if (Terminal::mvDist()) {
            $this->success();
        } else {
            $this->error(__('Failed to move the front-end file, please move it manually!'));
        }
    }

    private static function writableStateDescribe($writable): string
    {
        return $writable ? __('Writable') : __('No write permission');
    }

    private function connectDb(array $database, bool $returnPdo = false): array
    {
        try {
            $dbConfig                         = Config::get('database');
            $dbConfig['connections']['mysql'] = array_merge($dbConfig['connections']['mysql'], $database);
            Config::set(['connections' => $dbConfig['connections']], 'database');

            $connect = Db::connect('mysql');
            $connect->execute("SELECT 1");
        } catch (PDOException $e) {
            $errorMsg = $e->getMessage();
            return [
                'code' => 0,
                'msg'  => __('Database connection failed:%s', [mb_convert_encoding($errorMsg ?: 'unknown', 'UTF-8', 'UTF-8,GBK,GB2312,BIG5')])
            ];
        }

        $databases = [];
        $databasesExclude = ['information_schema', 'mysql', 'performance_schema', 'sys'];
        $res              = $connect->query("SHOW DATABASES");
        foreach ($res as $row) {
            if (!in_array($row['Database'], $databasesExclude)) {
                $databases[] = $row['Database'];
            }
        }
        return [
            'code'      => 1,
            'msg'       => '',
            'databases' => $databases,
            'pdo'       => $returnPdo ? $connect->getPdo() : '',
        ];
    }
}
