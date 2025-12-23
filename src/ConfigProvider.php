<?php

declare(strict_types=1);

namespace HPlus\Corp;

use HPlus\Corp\Aspect\PermissionAspect;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [],
            'aspects' => [
                PermissionAspect::class,
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'HPlus Corp 配置文件',
                    'source' => __DIR__ . '/../publish/corp.php',
                    'destination' => BASE_PATH . '/config/autoload/corp.php',
                ],
                [
                    'id' => 'migrations',
                    'description' => 'HPlus Corp 数据库迁移文件',
                    'source' => __DIR__ . '/../migrations',
                    'destination' => BASE_PATH . '/migrations',
                ],
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
        ];
    }
}

