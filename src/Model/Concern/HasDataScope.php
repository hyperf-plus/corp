<?php

declare(strict_types=1);

namespace HPlus\Corp\Model\Concern;

use HPlus\Corp\Context\CorpContext;
use HPlus\Corp\Model\Collaborator;
use HPlus\Corp\Scope\DataScope;
use Hyperf\Database\Model\Builder;

/**
 * 数据范围 Trait（用户级数据隔离）
 * 
 * 功能：
 * 1. 查询时自动根据角色 auth_range 过滤数据
 * 2. 可选启用协作者，被授权的数据也可见
 * 3. 创建时自动注入 corp_id、employee_id、department_id
 * 4. 创建时自动将创建者添加为协作者（可选）
 * 
 * 使用方式：
 * ```php
 * // 普通业务数据（仅角色数据范围）
 * class Order extends Model
 * {
 *     use HasDataScope;
 * }
 * 
 * // 支持协作者的资源
 * class Script extends Model
 * {
 *     use HasDataScope;
 *     
 *     protected bool $enableCollaborator = true;    // 启用协作者
 *     protected int $resourceType = 10;             // 资源类型
 *     protected string $resourceIdColumn = 'id';    // 资源ID字段（默认主键）
 *     protected bool $autoAddCreatorAsCollaborator = true; // 创建时自动添加创建者
 * }
 * ```
 */
trait HasDataScope
{
    /**
     * 启动 Trait
     */
    protected static function bootHasDataScope(): void
    {
        // 添加数据范围过滤
        static::addGlobalScope('data_scope', new DataScope());

        // 创建事件：自动注入上下文 + 添加协作者
        static::creating(function ($model) {
            $model->injectContextFields();
        });

        static::created(function ($model) {
            $model->autoAddCreatorCollaborator();
        });
    }

    /**
     * 注入上下文字段
     */
    protected function injectContextFields(): void
    {
        // 注入 corp_id
        if ($this->shouldInjectField('corp_id') && empty($this->corp_id)) {
            $this->corp_id = CorpContext::getCorpId();
        }

        // 注入 employee_id
        if ($this->shouldInjectField('employee_id') && empty($this->employee_id)) {
            $this->employee_id = CorpContext::getEmployeeId();
        }

        // 注入 department_id
        if ($this->shouldInjectField('department_id') && empty($this->department_id)) {
            $this->department_id = CorpContext::getDepartmentId();
        }
    }

    /**
     * 检查是否应该注入字段
     */
    protected function shouldInjectField(string $field): bool
    {
        return in_array($field, $this->getFillable()) || property_exists($this, $field);
    }

    /**
     * 自动将创建者添加为协作者
     */
    protected function autoAddCreatorCollaborator(): void
    {
        $enableCollaborator = $this->enableCollaborator ?? false;
        $autoAdd = $this->autoAddCreatorAsCollaborator ?? true;
        $resourceType = $this->resourceType ?? 0;

        if (!$enableCollaborator || !$autoAdd || $resourceType <= 0) {
            return;
        }

        $employeeId = CorpContext::getEmployeeId();
        if (!$employeeId) {
            return;
        }

        $resourceIdColumn = $this->resourceIdColumn ?? $this->getKeyName();
        $resourceId = $this->{$resourceIdColumn};

        if ($resourceId) {
            Collaborator::addCollaborator($employeeId, $resourceId, $resourceType, Collaborator::SCOPE_OWNER);
        }
    }

    /**
     * 获取资源类型
     */
    public function getResourceType(): int
    {
        return $this->resourceType ?? 0;
    }

    /**
     * 获取资源ID字段
     */
    public function getResourceIdColumn(): string
    {
        return $this->resourceIdColumn ?? $this->getKeyName();
    }

    /**
     * 获取资源ID
     */
    public function getResourceId(): mixed
    {
        return $this->{$this->getResourceIdColumn()};
    }

    /**
     * 是否启用协作者
     */
    public function isCollaboratorEnabled(): bool
    {
        return $this->enableCollaborator ?? false;
    }

    // ==================== 查询方法 ====================

    /**
     * 跳过数据范围过滤
     */
    public static function withoutDataScope(): Builder
    {
        return static::withoutGlobalScope('data_scope');
    }

    /**
     * 在回调中跳过数据范围过滤
     */
    public static function skipDataScope(callable $callback): mixed
    {
        return CorpContext::withoutDataScope($callback);
    }

    /**
     * 指定企业查询（跳过数据范围）
     */
    public static function ofCorp(int $corpId): Builder
    {
        return static::withoutGlobalScope('data_scope')
            ->where((new static())->getTable() . '.corp_id', $corpId);
    }

    // ==================== 协作者管理方法 ====================

    /**
     * 添加协作者
     */
    public function addCollaborator(int $userId, int $scope = Collaborator::SCOPE_VIEW): bool
    {
        if (!$this->isCollaboratorEnabled()) {
            return false;
        }

        Collaborator::addCollaborator($userId, $this->getResourceId(), $this->getResourceType(), $scope);
        return true;
    }

    /**
     * 移除协作者
     */
    public function removeCollaborator(int $userId): bool
    {
        if (!$this->isCollaboratorEnabled()) {
            return false;
        }

        return Collaborator::removeCollaborator($userId, $this->getResourceId(), $this->getResourceType());
    }

    /**
     * 获取所有协作者ID
     */
    public function getCollaboratorIds(): array
    {
        if (!$this->isCollaboratorEnabled()) {
            return [];
        }

        return Collaborator::getResourceUserIds($this->getResourceId(), $this->getResourceType());
    }

    /**
     * 设置协作者（替换现有）
     */
    public function setCollaborators(array $userIds, int $scope = Collaborator::SCOPE_VIEW): bool
    {
        if (!$this->isCollaboratorEnabled()) {
            return false;
        }

        Collaborator::setResourceCollaborators($this->getResourceId(), $this->getResourceType(), $userIds, $scope);
        return true;
    }

    /**
     * 检查用户是否有协作权限
     */
    public function hasCollaboratorPermission(int $userId, int $scope = Collaborator::SCOPE_VIEW): bool
    {
        if (!$this->isCollaboratorEnabled()) {
            return false;
        }

        return Collaborator::hasPermission($userId, $this->getResourceId(), $this->getResourceType(), $scope);
    }

    /**
     * 检查当前用户是否有协作权限
     */
    public function currentUserHasCollaboratorPermission(int $scope = Collaborator::SCOPE_VIEW): bool
    {
        $employeeId = CorpContext::getEmployeeId();
        if (!$employeeId) {
            return false;
        }

        return $this->hasCollaboratorPermission($employeeId, $scope);
    }
}
