<?php

declare(strict_types=1);

namespace HPlus\Corp\Event;

/**
 * 员工事件
 */
class EmployeeEvent extends Event
{
    // 事件类型常量
    public const CREATED = 'created';
    public const UPDATED = 'updated';
    public const DELETED = 'deleted';
    public const DEPARTMENT_CHANGED = 'department_changed';
    public const ROLE_CHANGED = 'role_changed';
    public const STATUS_CHANGED = 'status_changed';

    /**
     * 员工ID
     */
    public int $employeeId = 0;

    /**
     * 员工姓名
     */
    public string $name = '';

    /**
     * 主部门ID
     */
    public int $departmentId = 0;

    /**
     * 旧部门ID列表
     */
    public array $oldDepartmentIds = [];

    /**
     * 新部门ID列表
     */
    public array $newDepartmentIds = [];

    /**
     * 旧角色ID列表
     */
    public array $oldRoleIds = [];

    /**
     * 新角色ID列表
     */
    public array $newRoleIds = [];

    /**
     * 旧状态
     */
    public int $oldStatus = 0;

    /**
     * 新状态
     */
    public int $newStatus = 0;
}

