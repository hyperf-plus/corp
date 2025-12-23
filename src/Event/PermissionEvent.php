<?php

declare(strict_types=1);

namespace HPlus\Corp\Event;

/**
 * 权限事件
 */
class PermissionEvent extends Event
{
    // 事件类型常量
    public const CREATED = 'created';
    public const UPDATED = 'updated';
    public const DELETED = 'deleted';

    /**
     * 权限ID
     */
    public int $permissionId = 0;

    /**
     * 权限名称
     */
    public string $name = '';

    /**
     * 权限标识
     */
    public string $slug = '';
}

