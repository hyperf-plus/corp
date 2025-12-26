<?php

declare(strict_types=1);

namespace HPlus\Corp\Model\Concern;

use HPlus\Corp\Context\CorpContext;
use Hyperf\Database\Model\Builder;

/**
 * 企业范围 Trait（企业级数据隔离）
 * 
 * 仅按 corp_id 过滤，适用于企业内所有人都可见的数据：
 * - 通知、公告
 * - 企业配置
 * - 操作日志
 * - 等等...
 * 
 * 使用方式：
 * ```php
 * class Notification extends Model
 * {
 *     use HasCorpScope;
 * }
 * ```
 * 
 * 注意：如果需要按用户数据范围过滤，请使用 HasDataScope
 */
trait HasCorpScope
{
    /**
     * 启动 Trait
     */
    protected static function bootHasCorpScope(): void
    {
        // 查询时自动过滤 corp_id
        static::addGlobalScope('corp_scope', function (Builder $builder) {
            // 跳过过滤
            if (CorpContext::isSkipDataScope()) {
                return;
            }

            $corpId = CorpContext::getCorpId();
            if ($corpId > 0) {
                $table = $builder->getModel()->getTable();
                $builder->where("{$table}.corp_id", $corpId);
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
