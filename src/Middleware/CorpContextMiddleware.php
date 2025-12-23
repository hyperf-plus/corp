<?php

declare(strict_types=1);

namespace HPlus\Corp\Middleware;

use HPlus\Corp\Context\CorpContext;
use HPlus\Corp\CorpManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 企业上下文中间件
 * 
 * 自动从认证用户中提取企业上下文信息
 * 
 * 使用方式：在路由中间件中添加
 * ```php
 * Router::addGroup('/api', function () {
 *     // ...
 * }, ['middleware' => [CorpContextMiddleware::class]]);
 * ```
 * 
 * 或者继承此中间件自定义获取用户的方式
 */
class CorpContextMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 获取当前用户（子类可重写此方法）
        $user = $this->getCurrentUser($request);

        if ($user) {
            $this->setContext($user);
        }

        return $handler->handle($request);
    }

    /**
     * 获取当前用户
     * 
     * 子类可重写此方法，从自己的认证系统获取用户
     */
    protected function getCurrentUser(ServerRequestInterface $request): ?object
    {
        // 默认从请求属性中获取（假设认证中间件已设置）
        return $request->getAttribute('user');
    }

    /**
     * 设置上下文
     */
    protected function setContext(object $user): void
    {
        // 获取基础信息
        $corpId = $this->getCorpId($user);
        $employeeId = $this->getEmployeeId($user);
        $departmentId = $this->getDepartmentId($user);
        $isAdmin = $this->isAdmin($user);

        // 设置上下文
        CorpContext::set($corpId, $employeeId, $departmentId, 1, $isAdmin);

        // 获取并设置数据权限范围
        if ($employeeId > 0 && !$isAdmin) {
            $authRange = $this->getAuthRange($employeeId);
            CorpContext::setAuthRange($authRange);
        } elseif ($isAdmin) {
            CorpContext::setAuthRange(4); // 管理员全部权限
        }
    }

    /**
     * 获取企业ID
     */
    protected function getCorpId(object $user): int
    {
        return (int) ($user->corp_id ?? $user->getCorpId() ?? 0);
    }

    /**
     * 获取员工ID
     */
    protected function getEmployeeId(object $user): int
    {
        return (int) ($user->employee_id ?? $user->getEmployeeId() ?? $user->id ?? $user->getId() ?? 0);
    }

    /**
     * 获取部门ID
     */
    protected function getDepartmentId(object $user): int
    {
        return (int) ($user->department_id ?? $user->getDepartmentId() ?? 0);
    }

    /**
     * 是否管理员
     */
    protected function isAdmin(object $user): bool
    {
        if (method_exists($user, 'isAdmin')) {
            return $user->isAdmin();
        }
        return (bool) ($user->is_admin ?? $user->super_administrator ?? false);
    }

    /**
     * 获取数据权限范围（从角色中取最大值）
     */
    protected function getAuthRange(int $employeeId): int
    {
        $roleUserModel = CorpManager::roleUserModel();
        $roleModel = CorpManager::roleModel();

        $roleIds = $roleUserModel::query()
            ->where('employee_id', $employeeId)
            ->pluck('role_id')
            ->toArray();

        if (empty($roleIds)) {
            return 1; // 默认仅本人
        }

        $maxAuthRange = $roleModel::query()
            ->whereIn('role_id', $roleIds)
            ->where('status', 1)
            ->max('auth_range');

        return $maxAuthRange ?: 1;
    }
}

