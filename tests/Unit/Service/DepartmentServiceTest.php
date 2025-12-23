<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use HPlus\Corp\CorpManager;
use HPlus\Corp\Model\Department;
use HPlus\Corp\Service\DepartmentService;
use Hyperf\Database\Model\Collection;
use Mockery;
use PHPUnit\Framework\TestCase;

class DepartmentServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testGetTree(): void
    {
        $service = new DepartmentService();
        $corpId = 1;
        $parentId = 0;

        $mockCollection = Mockery::mock(Collection::class);
        $mockQuery = Mockery::mock();
        
        $model = CorpManager::departmentModel();
        $mockModel = Mockery::mock($model);
        
        $mockModel::shouldReceive('query')
            ->once()
            ->andReturn($mockQuery);
        
        $mockQuery->shouldReceive('where')
            ->with('corp_id', $corpId)
            ->once()
            ->andReturnSelf();
        
        $mockQuery->shouldReceive('where')
            ->with('parent_id', $parentId)
            ->once()
            ->andReturnSelf();
        
        $mockQuery->shouldReceive('enabled')
            ->once()
            ->andReturnSelf();
        
        $mockQuery->shouldReceive('ordered')
            ->once()
            ->andReturnSelf();
        
        $mockQuery->shouldReceive('with')
            ->with('childrenRecursive')
            ->once()
            ->andReturnSelf();
        
        $mockQuery->shouldReceive('get')
            ->once()
            ->andReturn($mockCollection);

        $result = $service->getTree($corpId, $parentId);
        $this->assertInstanceOf(Collection::class, $result);
    }

    public function testCreateDepartment(): void
    {
        $service = new DepartmentService();
        $data = [
            'corp_id' => 1,
            'name' => '技术部',
            'parent_id' => 0,
        ];

        $mockDept = Mockery::mock(Department::class);
        $mockDept->department_id = 1;
        $mockDept->corp_id = 1;
        $mockDept->name = '技术部';
        $mockDept->full_path = '/';
        $mockDept->level = 1;

        $model = CorpManager::departmentModel();
        $mockModel = Mockery::mock($model);
        
        $mockModel::shouldReceive('query')
            ->andReturn(Mockery::mock());
        
        $mockModel::shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return $arg['corp_id'] === 1 && $arg['name'] === '技术部';
            }))
            ->andReturn($mockDept);
        
        $mockDept->shouldReceive('save')
            ->once()
            ->andReturnTrue();

        $result = $service->create($data);
        $this->assertNotNull($result);
    }

    public function testGetDescendantIds(): void
    {
        $service = new DepartmentService();
        $departmentId = 1;

        $mockDept = Mockery::mock(Department::class);
        $mockDept->full_path = '/1/';

        $model = CorpManager::departmentModel();
        $mockModel = Mockery::mock($model);
        
        $mockModel::shouldReceive('find')
            ->with($departmentId)
            ->once()
            ->andReturn($mockDept);
        
        $mockQuery = Mockery::mock();
        $mockModel::shouldReceive('query')
            ->once()
            ->andReturn($mockQuery);
        
        $mockQuery->shouldReceive('where')
            ->with('full_path', 'like', '/1/%')
            ->once()
            ->andReturnSelf();
        
        $mockQuery->shouldReceive('pluck')
            ->with('department_id')
            ->once()
            ->andReturnSelf();
        
        $mockQuery->shouldReceive('toArray')
            ->once()
            ->andReturn([1, 2, 3]);

        $result = $service->getDescendantIds($departmentId);
        $this->assertIsArray($result);
    }
}

