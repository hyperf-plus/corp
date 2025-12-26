<?php

declare(strict_types=1);

namespace HPlus\Corp\Model\Concern;

use HPlus\Corp\Context\CorpContext;
use HPlus\Corp\Model\Collaborator;
use Hyperf\Database\Model\Builder;

/**
 * 协作者数据范围 Trait
 * 
 * 基于协作者权限进行数据隔离，支持任意资源类型
 * 
 * 使用方式：
 * ```php
 * class Script extends Model
 * {
 *     use HasCollaboratorScope;
 *     
 *     // 资源类型（必须定义）
 *     protected int $resourceType = 10;
 *     
 *     // 资源ID字段（默认为主键）
 *     protected string $resourceIdColumn = 'id';
 *     
 *     // 是否允许管理员查看全部（默认 true）
 *     protected bool $adminViewAll = true;
 * }
 * ```
 */
trait HasCollaboratorScope
{
    /**
     * Scope 名称
     */
    private const COLLABORATOR_SCOPE = 'collaborator_scope';

    /**
     * 启动 Trait
     */
    protected static function bootHasCollaboratorScope(): void
    {
        static::addGlobalScope(self::COLLABORATOR_SCOPE, function (Builder $builder) {
            static::applyCollaboratorScope($builder);
        });
    }

    /**
     * 应用协作者数据范围过滤
     */
    protected static function applyCollaboratorScope(Builder $builder): void
    {
        // 跳过过滤
        if (CorpContext::isSkipDataScope()) {
            return;
        }

        $employeeId = CorpContext::getEmployeeId();
        if (!$employeeId) {
            return;
        }

        $model = $builder->getModel();
        $adminViewAll = $model->adminViewAll ?? true;

        // 管理员跳过过滤
        if (CorpContext::isAdmin() && $adminViewAll) {
            return;
        }

        $resourceType = $model->resourceType ?? Collaborator::RESOURCE_TYPE_CORP;
        $resourceIdColumn = $model->resourceIdColumn ?? $model->getKeyName();
        $table = $model->getTable();
        $qualifiedColumn = "{$table}.{$resourceIdColumn}";

        // 获取用户可访问的资源ID
        $accessibleIds = Collaborator::getUserResourceIds($employeeId, $resourceType);

        if (empty($accessibleIds)) {
            // 没有权限，返回空结果
            $builder->whereRaw('1 = 0');
            return;
        }

        $builder->whereIn($qualifiedColumn, $accessibleIds);
    }

    /**
     * 获取资源类型
     */
    public function getResourceType(): int
    {
        return $this->resourceType ?? Collaborator::RESOURCE_TYPE_CORP;
    }

    /**
     * 获取资源ID字段
     */
    public function getResourceIdColumn(): string
    {
        return $this->resourceIdColumn ?? $this->getKeyName();
    }

    /**
     * 跳过协作者过滤
     */
    public static function withoutCollaboratorScope(): Builder
    {
        return static::withoutGlobalScope(self::COLLABORATOR_SCOPE);
    }

    /**
     * 查询指定资源
     */
    public static function forResource(int $resourceId): Builder
    {
        $model = new static();
        return static::withoutGlobalScope(self::COLLABORATOR_SCOPE)
            ->where($model->getResourceIdColumn(), $resourceId);
    }

    /**
     * 查询多个资源
     */
    public static function forResources(array $resourceIds): Builder
    {
        $model = new static();
        return static::withoutGlobalScope(self::COLLABORATOR_SCOPE)
            ->whereIn($model->getResourceIdColumn(), $resourceIds);
    }

    /**
     * 检查当前用户是否有资源权限
     */
    public static function hasResourcePermission(int $resourceId, int $scope = Collaborator::SCOPE_VIEW): bool
    {
        $employeeId = CorpContext::getEmployeeId();
        if (!$employeeId) {
            return false;
        }

        if (CorpContext::isAdmin()) {
            return true;
        }

        $model = new static();
        return Collaborator::hasPermission(
            $employeeId,
            $resourceId,
            $model->getResourceType(),
            $scope
        );
    }

    /**
     * 添加当前用户为协作者
     */
    public function addCurrentUserAsCollaborator(int $scope = Collaborator::SCOPE_OWNER): void
    {
        $employeeId = CorpContext::getEmployeeId();
        if (!$employeeId) {
            return;
        }

        Collaborator::addCollaborator(
            $employeeId,
            $this->{$this->getResourceIdColumn()},
            $this->getResourceType(),
            $scope
        );
    }

    /**
     * 添加协作者
     */
    public function addCollaborator(int $userId, int $scope = Collaborator::SCOPE_VIEW): void
    {
        Collaborator::addCollaborator(
            $userId,
            $this->{$this->getResourceIdColumn()},
            $this->getResourceType(),
            $scope
        );
    }

    /**
     * 移除协作者
     */
    public function removeCollaborator(int $userId): bool
    {
        return Collaborator::removeCollaborator(
            $userId,
            $this->{$this->getResourceIdColumn()},
            $this->getResourceType()
        );
    }

    /**
     * 获取所有协作者ID
     */
    public function getCollaboratorIds(): array
    {
        return Collaborator::getResourceUserIds(
            $this->{$this->getResourceIdColumn()},
            $this->getResourceType()
        );
    }

    /**
     * 设置协作者（替换现有）
     */
    public function setCollaborators(array $userIds, int $scope = Collaborator::SCOPE_VIEW): void
    {
        Collaborator::setResourceCollaborators(
            $this->{$this->getResourceIdColumn()},
            $this->getResourceType(),
            $userIds,
            $scope
        );
    }
}

