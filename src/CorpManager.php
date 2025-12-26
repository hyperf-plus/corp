<?php

declare(strict_types=1);

namespace HPlus\Corp;

use function Hyperf\Config\config;

/**
 * 模型管理器
 * 
 * 统一获取可配置的模型类
 */
class CorpManager
{
    /**
     * 获取企业模型类
     */
    public static function corpModel(): string
    {
        return config('corp.models.corp', \HPlus\Corp\Model\Corp::class);
    }

    /**
     * 获取部门模型类
     */
    public static function departmentModel(): string
    {
        return config('corp.models.department', \HPlus\Corp\Model\Department::class);
    }

    /**
     * 获取员工模型类
     */
    public static function employeeModel(): string
    {
        return config('corp.models.employee', \HPlus\Corp\Model\Employee::class);
    }

    /**
     * 获取角色模型类
     */
    public static function roleModel(): string
    {
        return config('corp.models.role', \HPlus\Corp\Model\Role::class);
    }

    /**
     * 获取角色用户关联模型类
     */
    public static function roleUserModel(): string
    {
        return config('corp.models.role_user', \HPlus\Corp\Model\RoleUser::class);
    }

    /**
     * 获取权限模型类
     */
    public static function permissionModel(): string
    {
        return config('corp.models.permission', \HPlus\Corp\Model\Permission::class);
    }

    /**
     * 获取角色权限关联模型类
     */
    public static function rolePermissionModel(): string
    {
        return config('corp.models.role_permission', \HPlus\Corp\Model\RolePermission::class);
    }

    /**
     * 获取协作者模型类
     */
    public static function collaboratorModel(): string
    {
        return config('corp.models.collaborator', \HPlus\Corp\Model\Collaborator::class);
    }

    /**
     * 创建模型实例
     */
    public static function make(string $type): object
    {
        $class = match ($type) {
            'corp' => self::corpModel(),
            'department' => self::departmentModel(),
            'employee' => self::employeeModel(),
            'role' => self::roleModel(),
            'role_user' => self::roleUserModel(),
            'permission' => self::permissionModel(),
            'role_permission' => self::rolePermissionModel(),
            'collaborator' => self::collaboratorModel(),
            default => throw new \InvalidArgumentException("Unknown model type: {$type}"),
        };

        return new $class();
    }
}
