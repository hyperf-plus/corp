<?php

declare(strict_types=1);

namespace HPlus\Corp\Model\Concern;

use HPlus\Corp\Context\CorpContext;
use Hyperf\Database\Model\Builder;

/**
 * 企业范围 Trait（仅过滤 corp_id，不过滤数据权限）
 * 
 * 适用于只需要按企业隔离，不需要按角色数据权限过滤的场景
 * 
 * 使用方式：
 * ```php
 * class YourModel extends Model
 * {
 *     use HasCorpScope;
 * }
 * ```
 */
trait HasCorpScope
{
    /**
     * 启动 Trait
     */
    protected static function bootHasCorpScope(): void
    {
        // 添加企业范围过滤
        static::addGlobalScope('corp_scope', function (Builder $builder) {
            $corpId = CorpContext::getCorpId();
            if ($corpId > 0) {
                $model = $builder->getModel();
                $builder->where($model->getTable() . '.corp_id', $corpId);
            }
        });

        // 创建时自动注入 corp_id
        static::creating(function ($model) {
            if (empty($model->corp_id)) {
                $model->corp_id = CorpContext::getCorpId();
            }
        });
    }

    /**
     * 跳过企业范围过滤
     */
    public static function withoutCorpScope(): Builder
    {
        return static::withoutGlobalScope('corp_scope');
    }

    /**
     * 指定企业查询
     */
    public static function ofCorp(int $corpId): Builder
    {
        return static::withoutGlobalScope('corp_scope')
            ->where((new static())->getTable() . '.corp_id', $corpId);
    }
}

