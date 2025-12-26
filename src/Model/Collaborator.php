<?php

declare(strict_types=1);

namespace HPlus\Corp\Model;

use HPlus\Corp\Context\CorpContext;
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
 * 支持任意资源类型的协作者权限管理
 * 
 * 资源类型可自定义，建议在业务代码中定义常量类：
 * ```php
 * class ResourceType {
 *     public const CORP = 1;
 *     public const SCRIPT = 10;   // 话术
 *     public const LINE = 11;     // 线路
 *     public const TASK = 12;     // 任务
 * }
 * ```
 */
class Collaborator extends Model
{
    use SoftDeletes;

    /**
     * 资源类型常量（业务方可自定义）
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
     * 缓存前缀
     */
    protected const CACHE_PREFIX = 'corp:collaborator:';
    protected const CACHE_TTL = 300;

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

    /**
     * 模型事件：创建后清理缓存
     */
    public function created(Created $event): void
    {
        $this->clearCache();
    }

    /**
     * 模型事件：更新后清理缓存
     */
    public function updated(Updated $event): void
    {
        $this->clearCache();
    }

    /**
     * 模型事件：删除后清理缓存
     */
    public function deleted(Deleted $event): void
    {
        $this->clearCache();
    }

    /**
     * 清理当前记录相关缓存
     */
    protected function clearCache(): void
    {
        self::clearUserResourceCache($this->user_id, $this->resource_type);
    }

    /**
     * 清理用户资源缓存
     */
    public static function clearUserResourceCache(int $userId, int $resourceType): void
    {
        try {
            $cache = make(CacheInterface::class);
            $cache->delete(self::CACHE_PREFIX . "{$userId}:{$resourceType}");
        } catch (\Throwable $e) {
            // 缓存服务不可用时忽略
        }
    }

    /**
     * 获取用户可访问的资源ID列表（带缓存）
     */
    public static function getUserResourceIds(int $userId, int $resourceType): array
    {
        $cacheKey = self::CACHE_PREFIX . "{$userId}:{$resourceType}";

        try {
            $cache = make(CacheInterface::class);
            $cached = $cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        } catch (\Throwable $e) {
            // 缓存不可用，直接查库
        }

        $resourceIds = static::query()
            ->where('user_id', $userId)
            ->where('resource_type', $resourceType)
            ->where('status', self::STATUS_ENABLED)
            ->pluck('resource_id')
            ->toArray();

        try {
            $cache = make(CacheInterface::class);
            $cache->set($cacheKey, $resourceIds, self::CACHE_TTL);
        } catch (\Throwable $e) {
            // 忽略缓存写入失败
        }

        return $resourceIds;
    }

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
     * 检查用户是否有权限
     */
    public static function hasPermission(int $userId, int $resourceId, int $resourceType, int $scope = self::SCOPE_VIEW): bool
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('resource_id', $resourceId)
            ->where('resource_type', $resourceType)
            ->where('scopes', '>=', $scope)
            ->where('status', self::STATUS_ENABLED)
            ->exists();
    }

    /**
     * 获取资源的所有协作者ID
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

    /**
     * 设置资源的协作者（替换现有）
     */
    public static function setResourceCollaborators(int $resourceId, int $resourceType, array $userIds, int $scopes = self::SCOPE_VIEW): void
    {
        // 获取原有用户，用于清理缓存
        $oldUserIds = static::getResourceUserIds($resourceId, $resourceType);

        // 删除原有记录
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
}

