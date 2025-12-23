<?php

declare(strict_types=1);

namespace HPlus\Corp\Event;

/**
 * 部门事件
 */
class DepartmentEvent extends Event
{
    // 事件类型常量
    public const CREATED = 'created';
    public const UPDATED = 'updated';
    public const DELETED = 'deleted';
    public const MOVED = 'moved';
    public const SUPERVISOR_CHANGED = 'supervisor_changed';

    /**
     * 部门ID
     */
    public int $departmentId = 0;

    /**
     * 部门名称
     */
    public string $name = '';

    /**
     * 父部门ID
     */
    public int $parentId = 0;

    /**
     * 旧父部门ID（移动时）
     */
    public int $oldParentId = 0;

    /**
     * 新父部门ID（移动时）
     */
    public int $newParentId = 0;

    /**
     * 旧负责人ID
     */
    public int $oldSupervisorId = 0;

    /**
     * 新负责人ID
     */
    public int $newSupervisorId = 0;
}

