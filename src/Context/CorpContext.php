<?php

declare(strict_types=1);

namespace HPlus\Corp\Context;

use Hyperf\Context\Context;

/**
 * 企业上下文管理
 * 
 * 管理当前请求的企业、员工、部门信息
 */
class CorpContext
{
    private const KEY_CORP_ID = 'corp.corp_id';
    private const KEY_EMPLOYEE_ID = 'corp.employee_id';
    private const KEY_DEPARTMENT_ID = 'corp.department_id';
    private const KEY_AUTH_RANGE = 'corp.auth_range';
    private const KEY_DEPT_IDS = 'corp.accessible_dept_ids';
    private const KEY_IS_ADMIN = 'corp.is_admin';
    private const KEY_SKIP_SCOPE = 'corp.skip_data_scope';

    /**
     * 设置企业ID
     */
    public static function setCorpId(int $corpId): void
    {
        Context::set(self::KEY_CORP_ID, $corpId);
    }

    /**
     * 获取企业ID
     */
    public static function getCorpId(): int
    {
        return (int) Context::get(self::KEY_CORP_ID, 0);
    }

    /**
     * 设置员工ID
     */
    public static function setEmployeeId(int $employeeId): void
    {
        Context::set(self::KEY_EMPLOYEE_ID, $employeeId);
    }

    /**
     * 获取员工ID
     */
    public static function getEmployeeId(): int
    {
        return (int) Context::get(self::KEY_EMPLOYEE_ID, 0);
    }

    /**
     * 设置部门ID
     */
    public static function setDepartmentId(int $departmentId): void
    {
        Context::set(self::KEY_DEPARTMENT_ID, $departmentId);
    }

    /**
     * 获取部门ID
     */
    public static function getDepartmentId(): int
    {
        return (int) Context::get(self::KEY_DEPARTMENT_ID, 0);
    }

    /**
     * 设置数据权限范围
     */
    public static function setAuthRange(int $authRange): void
    {
        Context::set(self::KEY_AUTH_RANGE, $authRange);
    }

    /**
     * 获取数据权限范围
     */
    public static function getAuthRange(): int
    {
        return (int) Context::get(self::KEY_AUTH_RANGE, 1);
    }

    /**
     * 设置可访问的部门ID列表
     */
    public static function setAccessibleDeptIds(array $deptIds): void
    {
        Context::set(self::KEY_DEPT_IDS, $deptIds);
    }

    /**
     * 获取可访问的部门ID列表
     */
    public static function getAccessibleDeptIds(): array
    {
        return Context::get(self::KEY_DEPT_IDS, []);
    }

    /**
     * 设置是否管理员
     */
    public static function setIsAdmin(bool $isAdmin): void
    {
        Context::set(self::KEY_IS_ADMIN, $isAdmin);
    }

    /**
     * 是否管理员
     */
    public static function isAdmin(): bool
    {
        return (bool) Context::get(self::KEY_IS_ADMIN, false);
    }

    /**
     * 跳过数据范围过滤
     */
    public static function skipDataScope(): void
    {
        Context::set(self::KEY_SKIP_SCOPE, true);
    }

    /**
     * 恢复数据范围过滤
     */
    public static function restoreDataScope(): void
    {
        Context::set(self::KEY_SKIP_SCOPE, false);
    }

    /**
     * 是否跳过数据范围过滤
     */
    public static function isSkipDataScope(): bool
    {
        return (bool) Context::get(self::KEY_SKIP_SCOPE, false);
    }

    /**
     * 临时跳过数据范围过滤执行回调
     */
    public static function withoutDataScope(callable $callback): mixed
    {
        self::skipDataScope();
        try {
            return $callback();
        } finally {
            self::restoreDataScope();
        }
    }

    /**
     * 批量设置上下文
     */
    public static function set(int $corpId, int $employeeId, int $departmentId = 0, int $authRange = 1, bool $isAdmin = false): void
    {
        self::setCorpId($corpId);
        self::setEmployeeId($employeeId);
        self::setDepartmentId($departmentId);
        self::setAuthRange($authRange);
        self::setIsAdmin($isAdmin);
    }

    /**
     * 清空上下文
     */
    public static function clear(): void
    {
        Context::set(self::KEY_CORP_ID, 0);
        Context::set(self::KEY_EMPLOYEE_ID, 0);
        Context::set(self::KEY_DEPARTMENT_ID, 0);
        Context::set(self::KEY_AUTH_RANGE, 1);
        Context::set(self::KEY_DEPT_IDS, []);
        Context::set(self::KEY_IS_ADMIN, false);
        Context::set(self::KEY_SKIP_SCOPE, false);
    }
}

