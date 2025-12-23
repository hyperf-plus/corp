<?php

declare(strict_types=1);

namespace HPlus\Corp\Event;

/**
 * 企业事件
 */
class CorpEvent extends Event
{
    // 事件类型常量
    public const CREATED = 'created';
    public const UPDATED = 'updated';
    public const DELETED = 'deleted';
    public const STATUS_CHANGED = 'status_changed';

    /**
     * 企业名称
     */
    public string $name = '';

    /**
     * 企业编码
     */
    public string $code = '';

    /**
     * 旧状态
     */
    public int $oldStatus = 0;

    /**
     * 新状态
     */
    public int $newStatus = 0;
}

