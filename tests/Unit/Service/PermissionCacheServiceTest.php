<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use HPlus\Corp\CorpManager;
use HPlus\Corp\Service\PermissionCacheService;
use Hyperf\Context\ApplicationContext;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;

class PermissionCacheServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testGetEmployeeRoleIdsWithCache(): void
    {
        $cache = Mockery::mock(CacheInterface::class);
        $cache->shouldReceive('get')
            ->with('corp:emp_roles:1')
            ->once()
            ->andReturn([1, 2]);

        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('has')
            ->with(CacheInterface::class)
            ->once()
            ->andReturnTrue();
        $container->shouldReceive('get')
            ->with(CacheInterface::class)
            ->once()
            ->andReturn($cache);

        ApplicationContext::setContainer($container);

        $service = new PermissionCacheService();
        $result = $service->getEmployeeRoleIds(1);

        $this->assertEquals([1, 2], $result);
    }

    public function testGetEmployeeRoleIdsWithoutCache(): void
    {
        $cache = Mockery::mock(CacheInterface::class);
        $cache->shouldReceive('get')
            ->with('corp:emp_roles:1')
            ->once()
            ->andReturn(null);
        $cache->shouldReceive('set')
            ->with('corp:emp_roles:1', Mockery::any(), 300)
            ->once();

        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('has')
            ->with(CacheInterface::class)
            ->andReturnTrue();
        $container->shouldReceive('get')
            ->with(CacheInterface::class)
            ->andReturn($cache);

        ApplicationContext::setContainer($container);

        $roleUserModel = CorpManager::roleUserModel();
        $roleModel = CorpManager::roleModel();
        
        $mockRoleUserQuery = Mockery::mock();
        $roleUserModel::shouldReceive('query')
            ->once()
            ->andReturn($mockRoleUserQuery);
        
        $mockRoleUserQuery->shouldReceive('where')
            ->with('employee_id', 1)
            ->once()
            ->andReturnSelf();
        
        $mockRoleUserQuery->shouldReceive('pluck')
            ->with('role_id')
            ->once()
            ->andReturnSelf();
        
        $mockRoleUserQuery->shouldReceive('toArray')
            ->once()
            ->andReturn([1, 2]);

        $mockRoleQuery = Mockery::mock();
        $roleModel::shouldReceive('query')
            ->once()
            ->andReturn($mockRoleQuery);
        
        $mockRoleQuery->shouldReceive('whereIn')
            ->with('role_id', [1, 2])
            ->once()
            ->andReturnSelf();
        
        $mockRoleQuery->shouldReceive('where')
            ->with('status', 1)
            ->once()
            ->andReturnSelf();
        
        $mockRoleQuery->shouldReceive('pluck')
            ->with('role_id')
            ->once()
            ->andReturnSelf();
        
        $mockRoleQuery->shouldReceive('toArray')
            ->once()
            ->andReturn([1, 2]);

        $service = new PermissionCacheService();
        $result = $service->getEmployeeRoleIds(1);

        $this->assertIsArray($result);
    }

    public function testGetRolePermissions(): void
    {
        $cache = Mockery::mock(CacheInterface::class);
        $cache->shouldReceive('get')
            ->with('corp:role_perms:1')
            ->once()
            ->andReturn(['user.create', 'user.edit']);

        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('has')
            ->with(CacheInterface::class)
            ->andReturnTrue();
        $container->shouldReceive('get')
            ->with(CacheInterface::class)
            ->andReturn($cache);

        ApplicationContext::setContainer($container);

        $service = new PermissionCacheService();
        $result = $service->getRolePermissions(1);

        $this->assertEquals(['user.create', 'user.edit'], $result);
    }

    public function testClearEmployeeRoleCache(): void
    {
        $cache = Mockery::mock(CacheInterface::class);
        $cache->shouldReceive('delete')
            ->with('corp:emp_roles:1')
            ->once();

        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('has')
            ->with(CacheInterface::class)
            ->andReturnTrue();
        $container->shouldReceive('get')
            ->with(CacheInterface::class)
            ->andReturn($cache);

        ApplicationContext::setContainer($container);

        $service = new PermissionCacheService();
        $service->clearEmployeeRoleCache(1);

        $this->assertTrue(true);
    }

    public function testClearRolePermissionCache(): void
    {
        $cache = Mockery::mock(CacheInterface::class);
        $cache->shouldReceive('delete')
            ->with('corp:role_perms:1')
            ->once();

        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('has')
            ->with(CacheInterface::class)
            ->andReturnTrue();
        $container->shouldReceive('get')
            ->with(CacheInterface::class)
            ->andReturn($cache);

        ApplicationContext::setContainer($container);

        $service = new PermissionCacheService();
        $service->clearRolePermissionCache(1);

        $this->assertTrue(true);
    }
}

