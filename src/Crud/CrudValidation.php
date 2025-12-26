<?php

declare(strict_types=1);

namespace HPlus\Corp\Crud;

use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use function Hyperf\Support\make;

/**
 * CRUD 验证器
 * 
 * 使用方式：
 * ```php
 * class OrderService extends CrudService
 * {
 *     protected function getCreateRules(): array
 *     {
 *         return [
 *             'customer_id' => 'required|integer',
 *             'amount' => 'required|numeric|min:0',
 *         ];
 *     }
 * }
 * ```
 */
trait CrudValidation
{
    /**
     * 创建验证规则
     */
    protected function getCreateRules(): array
    {
        return [];
    }

    /**
     * 更新验证规则
     */
    protected function getUpdateRules(): array
    {
        return [];
    }

    /**
     * 验证消息
     */
    protected function getValidationMessages(): array
    {
        return [];
    }

    /**
     * 验证属性名
     */
    protected function getValidationAttributes(): array
    {
        return [];
    }

    /**
     * 验证创建数据
     */
    protected function validateCreateData(array $data): void
    {
        $rules = $this->getCreateRules();
        if (empty($rules)) {
            return;
        }

        $this->validate($data, $rules);
    }

    /**
     * 验证更新数据
     */
    protected function validateUpdateData(array $data): void
    {
        $rules = $this->getUpdateRules();
        if (empty($rules)) {
            return;
        }

        $this->validate($data, $rules);
    }

    /**
     * 执行验证
     */
    protected function validate(array $data, array $rules): void
    {
        $factory = make(ValidatorFactoryInterface::class);
        
        $validator = $factory->make(
            $data,
            $rules,
            $this->getValidationMessages(),
            $this->getValidationAttributes()
        );

        if ($validator->fails()) {
            $errors = $validator->errors()->first();
            throw new \InvalidArgumentException($errors);
        }
    }
}

