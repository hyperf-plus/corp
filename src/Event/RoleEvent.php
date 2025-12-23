<?php

declare(strict_types=1);

namespace HPlus\Corp\Event;

/**
 * 角色事件
 */
class RoleEvent extends Event
{
    // 事件类型常量
    public const CREATED = 'created';
    public const UPDATED = 'updated';
    public const DELETED = 'deleted';
    public const PERMISSION_CHANGED = 'permission_changed';
    public const MEMBER_CHANGED = 'member_changed';

    /**
     * 角色ID
     */
    public int $roleId = 0;

    /**
     * 角色名称
     */
    public string $name = '';

    /**
     * 角色标识
     */
    public string $slug = '';

    /**
     * 旧权限ID列表
     */
    public array $oldPermissionIds = [];

    /**
     * 新权限ID列表
     */
    public array $newPermissionIds = [];

    /**
     * 新增的员工ID列表
     */
    public array $addedEmployeeIds = [];

    /**
     * 移除的员工ID列表
     */
    public array $removedEmployeeIds = [];
}

