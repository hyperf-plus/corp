<?php

declare(strict_types=1);

namespace HPlus\Corp\Model\Concern;

use HPlus\Corp\Context\CorpContext;
use HPlus\Corp\Scope\DataScope;
use Hyperf\Database\Model\Events\Creating;

/**
 * 数据范围 Trait
 * 
 * 模型引入此 Trait 后自动：
 * 1. 创建时自动注入 corp_id、employee_id、department_id
 * 2. 查询时根据角色的 auth_range 自动过滤数据
 * 
 * 使用方式：
 * ```php
 * class YourModel extends Model
 * {
 *     use HasDataScope;
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
    }

    /**
     * 创建时自动注入上下文字段
     */
    public function initializeHasDataScope(): void
    {
        // 在 creating 事件中注入
        static::creating(function ($model) {
            // 注入 corp_id
            if (property_exists($model, 'corp_id') || in_array('corp_id', $model->getFillable())) {
                if (empty($model->corp_id)) {
                    $model->corp_id = CorpContext::getCorpId();
                }
            }

            // 注入 employee_id
            if (property_exists($model, 'employee_id') || in_array('employee_id', $model->getFillable())) {
                if (empty($model->employee_id)) {
                    $model->employee_id = CorpContext::getEmployeeId();
                }
            }

            // 注入 department_id
            if (property_exists($model, 'department_id') || in_array('department_id', $model->getFillable())) {
                if (empty($model->department_id)) {
                    $model->department_id = CorpContext::getDepartmentId();
                }
            }
        });
    }

    /**
     * 跳过数据范围过滤
     */
    public static function withoutDataScope(): \Hyperf\Database\Model\Builder
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
}

