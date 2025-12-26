<?php

declare(strict_types=1);

namespace HPlus\Corp\Model;

use Hyperf\Database\Model\Events\Created;
use Hyperf\Database\Model\Events\Deleted;
use Hyperf\Database\Model\Events\Updated;
use Hyperf\Database\Model\SoftDeletes;
use Hyperf\DbConnection\Model\Model;
use Psr\SimpleCache\CacheInterface;
use function Hyperf\Config\config;
use function Hyperf\Support\make;

/**
 * 协作者模型
 * 
 * 支持任意资源类型的协作者权限管理，内置多级缓存：
 * - Redis 缓存：用户资源 ID 列表（5分钟）
 * - 静态缓存：单进程内避免重复查询
 */
class Collaborator extends Model
{
    use SoftDeletes;

    /**
     * 资源类型常量（业务方可自定义任意整数）
     */
    public const RESOURCE_TYPE_CORP = 1;
    public const RESOURCE_TYPE_AGENT = 2;
    public const RESOURCE_TYPE_SCRIPT = 10;
    public const RESOURCE_TYPE_LINE = 11;
    public const RESOURCE_TYPE_TASK = 12;

    /**
     * 权限范围常量
     */
    public const SCOPE_VIEW = 1;
    public const SCOPE_EDIT = 2;
    public const SCOPE_OWNER = 3;

    /**
     * 状态常量
     */
    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;

    /**
     * 缓存配置
     */
    protected const CACHE_PREFIX = 'corp:collab:';
    protected const CACHE_TTL = 300;

    /**
     * 静态缓存（进程级，避免单次请求重复查询）
     */
    private static array $localCache = [];

    protected array $fillable = [
        'user_id',
        'resource_id',
        'resource_type',
        'scopes',
        'status',
    ];

    protected array $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'resource_id' => 'integer',
        'resource_type' => 'integer',
        'scopes' => 'integer',
        'status' => 'integer',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('corp.collaborator.table', 'collaborators');
    }

    // ==================== 模型事件（自动清理缓存） ====================

    public function created(Created $event): void
    {
        $this->clearRelatedCache();
    }

    public function updated(Updated $event): void
    {
        $this->clearRelatedCache();
    }

    public function deleted(Deleted $event): void
    {
        $this->clearRelatedCache();
    }

    protected function clearRelatedCache(): void
    {
        self::clearUserResourceCache($this->user_id, $this->resource_type);
    }

    // ==================== 缓存管理 ====================

    /**
     * 清理用户资源缓存
     */
    public static function clearUserResourceCache(int $userId, int $resourceType): void
    {
        // 清理静态缓存
        $localKey = "{$userId}:{$resourceType}";
        unset(self::$localCache[$localKey]);

        // 清理 Redis 缓存
        try {
            $cache = make(CacheInterface::class);
            $cache->delete(self::CACHE_PREFIX . $localKey);
        } catch (\Throwable $e) {
            // 忽略缓存服务不可用
        }
    }

    /**
     * 清理所有静态缓存（用于长驻进程）
     */
    public static function clearLocalCache(): void
    {
        self::$localCache = [];
    }

    // ==================== 核心查询方法 ====================

    /**
     * 获取用户可访问的资源ID列表（多级缓存）
     * 
     * 缓存层级：
     * 1. 静态缓存（进程内）
     * 2. Redis 缓存（5分钟）
     * 3. 数据库查询
     */
    public static function getUserResourceIds(int $userId, int $resourceType): array
    {
        $localKey = "{$userId}:{$resourceType}";

        // 1. 静态缓存
        if (isset(self::$localCache[$localKey])) {
            return self::$localCache[$localKey];
        }

        // 2. Redis 缓存
        $cacheKey = self::CACHE_PREFIX . $localKey;
        try {
            $cache = make(CacheInterface::class);
            $cached = $cache->get($cacheKey);
            if ($cached !== null) {
                self::$localCache[$localKey] = $cached;
                return $cached;
            }
        } catch (\Throwable $e) {
            // 缓存不可用，继续查库
        }

        // 3. 数据库查询
        $resourceIds = static::query()
            ->where('user_id', $userId)
            ->where('resource_type', $resourceType)
            ->where('status', self::STATUS_ENABLED)
            ->pluck('resource_id')
            ->toArray();

        // 写入缓存
        self::$localCache[$localKey] = $resourceIds;
        try {
            $cache = make(CacheInterface::class);
            $cache->set($cacheKey, $resourceIds, config('corp.collaborator.cache_ttl', self::CACHE_TTL));
        } catch (\Throwable $e) {
            // 忽略
        }

        return $resourceIds;
    }

    /**
     * 检查用户是否有权限（优先走缓存）
     */
    public static function hasPermission(int $userId, int $resourceId, int $resourceType, int $scope = self::SCOPE_VIEW): bool
    {
        // 先检查缓存的资源列表
        $resourceIds = self::getUserResourceIds($userId, $resourceType);
        if (!in_array($resourceId, $resourceIds)) {
            return false;
        }

        // 再检查权限级别（需要查库，但只有在资源ID命中时才查）
        if ($scope > self::SCOPE_VIEW) {
            return static::query()
                ->where('user_id', $userId)
                ->where('resource_id', $resourceId)
                ->where('resource_type', $resourceType)
                ->where('scopes', '>=', $scope)
                ->where('status', self::STATUS_ENABLED)
                ->exists();
        }

        return true;
    }

    // ==================== 协作者管理方法 ====================

    /**
     * 添加协作者
     */
    public static function addCollaborator(int $userId, int $resourceId, int $resourceType, int $scopes = self::SCOPE_VIEW): static
    {
        return static::updateOrCreate(
            [
                'user_id' => $userId,
                'resource_id' => $resourceId,
                'resource_type' => $resourceType,
            ],
            [
                'scopes' => $scopes,
                'status' => self::STATUS_ENABLED,
            ]
        );
    }

    /**
     * 移除协作者
     */
    public static function removeCollaborator(int $userId, int $resourceId, int $resourceType): bool
    {
        $deleted = static::query()
            ->where('user_id', $userId)
            ->where('resource_id', $resourceId)
            ->where('resource_type', $resourceType)
            ->delete();

        if ($deleted) {
            self::clearUserResourceCache($userId, $resourceType);
        }

        return $deleted > 0;
    }

    /**
     * 获取资源的所有协作者用户ID
     */
    public static function getResourceUserIds(int $resourceId, int $resourceType): array
    {
        return static::query()
            ->where('resource_id', $resourceId)
            ->where('resource_type', $resourceType)
            ->where('status', self::STATUS_ENABLED)
            ->pluck('user_id')
            ->toArray();
    }

    /**
     * 批量添加协作者
     */
    public static function batchAddCollaborators(array $userIds, int $resourceId, int $resourceType, int $scopes = self::SCOPE_VIEW): int
    {
        $count = 0;
        foreach ($userIds as $userId) {
            static::addCollaborator($userId, $resourceId, $resourceType, $scopes);
            $count++;
        }
        return $count;
    }

    /**
     * 设置资源的协作者（替换现有）
     */
    public static function setResourceCollaborators(int $resourceId, int $resourceType, array $userIds, int $scopes = self::SCOPE_VIEW): void
    {
        $oldUserIds = static::getResourceUserIds($resourceId, $resourceType);

        // 删除旧记录
        static::query()
            ->where('resource_id', $resourceId)
            ->where('resource_type', $resourceType)
            ->delete();

        // 添加新记录
        foreach ($userIds as $userId) {
            static::create([
                'user_id' => $userId,
                'resource_id' => $resourceId,
                'resource_type' => $resourceType,
                'scopes' => $scopes,
                'status' => self::STATUS_ENABLED,
            ]);
        }

        // 清理所有相关用户缓存
        $allUserIds = array_unique(array_merge($oldUserIds, $userIds));
        foreach ($allUserIds as $userId) {
            self::clearUserResourceCache($userId, $resourceType);
        }
    }

    /**
     * 清除资源的所有协作者
     */
    public static function clearResourceCollaborators(int $resourceId, int $resourceType): int
    {
        $userIds = static::getResourceUserIds($resourceId, $resourceType);

        $deleted = static::query()
            ->where('resource_id', $resourceId)
            ->where('resource_type', $resourceType)
            ->delete();

        foreach ($userIds as $userId) {
            self::clearUserResourceCache($userId, $resourceType);
        }

        return $deleted;
    }
}
