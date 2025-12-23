<?php

declare(strict_types=1);

namespace HPlus\Corp\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * 权限注解
 * 
 * 使用方式：
 * ```php
 * #[Permission('user.create')]
 * #[Permission('user.create,user.edit')]  // 多个权限用逗号分隔，满足任一即可
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Permission extends AbstractAnnotation
{
    /**
     * @param string $value 权限标识，多个用逗号分隔
     */
    public function __construct(
        public string $value = ''
    ) {}
}
