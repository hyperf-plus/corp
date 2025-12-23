<?php

declare(strict_types=1);

namespace HPlus\Corp\Service;

use HPlus\Corp\CorpManager;
use Hyperf\Context\ApplicationContext;
use Psr\SimpleCache\CacheInterface;

/**
 * 权限缓存服务
 */
class PermissionCacheService
{
    protected const CACHE_PREFIX_EMP_ROLES = 'corp:emp_roles:';
    protected const CACHE_PREFIX_ROLE_PERMS = 'corp:role_perms:';
    protected const CACHE_TTL = 300;

    /**
     * 获取员工角色ID（缓存）
     */
    public function getEmployeeRoleIds(int $employeeId): array
    {
        $cache = $this->getCache();
        $cacheKey = self::CACHE_PREFIX_EMP_ROLES . $employeeId;

        if ($cache) {
            $cached = $cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $roleUserModel = CorpManager::roleUserModel();
        $roleModel = CorpManager::roleModel();

        $roleIds = $roleUserModel::query()
            ->where('employee_id', $employeeId)
            ->pluck('role_id')
            ->toArray();

        if (empty($roleIds)) {
            $cache?->set($cacheKey, [], self::CACHE_TTL);
            return [];
        }

        $enabledRoleIds = $roleModel::query()
            ->whereIn('role_id', $roleIds)
            ->where('status', 1)
            ->pluck('role_id')
            ->toArray();

        $cache?->set($cacheKey, $enabledRoleIds, self::CACHE_TTL);
        return $enabledRoleIds;
    }

    /**
     * 获取角色权限标识（缓存）
     */
    public function getRolePermissions(int $roleId): array
    {
        $cache = $this->getCache();
        $cacheKey = self::CACHE_PREFIX_ROLE_PERMS . $roleId;

        if ($cache) {
            $cached = $cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $rolePermModel = CorpManager::rolePermissionModel();
        $permModel = CorpManager::permissionModel();

        $permIds = $rolePermModel::query()
            ->where('role_id', $roleId)
            ->pluck('permission_id')
            ->toArray();

        if (empty($permIds)) {
            $cache?->set($cacheKey, [], self::CACHE_TTL);
            return [];
        }

        $slugs = $permModel::query()
            ->whereIn('permission_id', $permIds)
            ->pluck('slug')
            ->toArray();

        $cache?->set($cacheKey, $slugs, self::CACHE_TTL);
        return $slugs;
    }

    /**
     * 获取员工所有权限标识
     */
    public function getEmployeePermissions(int $employeeId): array
    {
        $roleIds = $this->getEmployeeRoleIds($employeeId);
        if (empty($roleIds)) {
            return [];
        }

        $permissions = [];
        foreach ($roleIds as $roleId) {
            $permissions = array_merge($permissions, $this->getRolePermissions($roleId));
        }

        return array_unique($permissions);
    }

    /**
     * 清除员工角色缓存
     */
    public function clearEmployeeRoleCache(int $employeeId): void
    {
        $this->getCache()?->delete(self::CACHE_PREFIX_EMP_ROLES . $employeeId);
    }

    /**
     * 清除角色权限缓存
     */
    public function clearRolePermissionCache(int $roleId): void
    {
        $this->getCache()?->delete(self::CACHE_PREFIX_ROLE_PERMS . $roleId);
    }

    protected function getCache(): ?CacheInterface
    {
        $container = ApplicationContext::getContainer();
        return $container->has(CacheInterface::class) ? $container->get(CacheInterface::class) : null;
    }
}

