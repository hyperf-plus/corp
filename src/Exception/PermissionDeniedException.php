<?php

declare(strict_types=1);

namespace HPlus\Corp\Exception;

use RuntimeException;

/**
 * 权限拒绝异常
 */
class PermissionDeniedException extends RuntimeException
{
    public function __construct(string $message = '没有操作权限', int $code = 403)
    {
        parent::__construct($message, $code);
    }
}

