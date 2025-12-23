<?php

declare(strict_types=1);

namespace HPlus\Corp\Aspect;

use HPlus\Corp\Annotation\Permission;
use HPlus\Corp\Context\CorpContext;
use HPlus\Corp\Exception\PermissionDeniedException;
use HPlus\Corp\Service\PermissionCacheService;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;

/**
 * 权限验证切面
 */
#[Aspect]
class PermissionAspect extends AbstractAspect
{
    public array $annotations = [
        Permission::class,
    ];

    public function __construct(
        protected PermissionCacheService $permissionCacheService
    ) {}

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $permission = $this->getPermission($proceedingJoinPoint);
        if (!$permission || empty($permission->value)) {
            return $proceedingJoinPoint->process();
        }

        if (CorpContext::isAdmin()) {
            return $proceedingJoinPoint->process();
        }

        $employeeId = CorpContext::getEmployeeId();
        if (!$employeeId) {
            throw new PermissionDeniedException('未登录', 401);
        }

        $this->checkPermission($permission->value, $employeeId);

        return $proceedingJoinPoint->process();
    }

    protected function getPermission(ProceedingJoinPoint $joinPoint): ?Permission
    {
        $metadata = $joinPoint->getAnnotationMetadata();

        if (!empty($metadata->method[Permission::class])) {
            return $metadata->method[Permission::class];
        }

        if (!empty($metadata->class[Permission::class])) {
            return $metadata->class[Permission::class];
        }

        return null;
    }

    protected function checkPermission(string $permissionStr, int $employeeId): void
    {
        $required = array_filter(array_map('trim', explode(',', $permissionStr)));
        if (empty($required)) {
            return;
        }

        $userPermissions = $this->permissionCacheService->getEmployeePermissions($employeeId);

        foreach ($required as $perm) {
            if (in_array($perm, $userPermissions, true)) {
                return;
            }
        }

        throw new PermissionDeniedException();
    }
}
