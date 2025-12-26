<?php

declare(strict_types=1);

namespace HPlus\Corp\Crud\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * CRUD 权限注解
 * 
 * 在控制器类上标注，自动注册 CRUD 相关权限
 * 
 * 使用方式：
 * ```php
 * #[CrudPermission(prefix: 'order', actions: ['list', 'detail', 'create', 'update', 'delete'])]
 * class OrderController extends CrudController
 * {
 *     // ...
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
class CrudPermission extends AbstractAnnotation
{
    public function __construct(
        /**
         * 权限前缀
         */
        public string $prefix = '',
        
        /**
         * 权限动作列表
         */
        public array $actions = ['list', 'detail', 'create', 'update', 'delete'],
        
        /**
         * 模块名称（用于生成权限菜单）
         */
        public string $moduleName = '',
    ) {
    }

    /**
     * 获取所有权限标识
     */
    public function getPermissions(): array
    {
        $permissions = [];
        
        foreach ($this->actions as $action) {
            $permissions[] = "{$this->prefix}.{$action}";
        }
        
        return $permissions;
    }

    /**
     * 获取权限配置（用于生成权限菜单）
     */
    public function getPermissionConfig(): array
    {
        $actionNames = [
            'list' => '列表',
            'detail' => '详情',
            'create' => '创建',
            'update' => '编辑',
            'delete' => '删除',
            'export' => '导出',
            'import' => '导入',
        ];

        $config = [];
        
        foreach ($this->actions as $action) {
            $config[] = [
                'slug' => "{$this->prefix}.{$action}",
                'name' => ($this->moduleName ?: $this->prefix) . '-' . ($actionNames[$action] ?? $action),
                'parent_slug' => $this->prefix,
            ];
        }
        
        return $config;
    }
}

