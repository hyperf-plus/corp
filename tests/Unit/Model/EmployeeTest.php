<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use HPlus\Corp\Model\Employee;
use PHPUnit\Framework\TestCase;

class EmployeeTest extends TestCase
{
    public function testGetAllDepartmentIds(): void
    {
        $employee = new Employee([
            'department_id' => 1,
            'department_ids' => [1, 2, 3],
        ]);

        $result = $employee->getAllDepartmentIds();
        $this->assertContains(1, $result);
        $this->assertContains(2, $result);
        $this->assertContains(3, $result);
    }

    public function testGetAllDepartmentIdsWithoutDepartmentIds(): void
    {
        $employee = new Employee([
            'department_id' => 1,
        ]);

        $result = $employee->getAllDepartmentIds();
        $this->assertEquals([1], $result);
    }

    public function testIsActive(): void
    {
        $employee = new Employee(['status' => Employee::STATUS_ACTIVE]);
        $this->assertTrue($employee->isActive());

        $employee = new Employee(['status' => Employee::STATUS_DISABLED]);
        $this->assertFalse($employee->isActive());
    }

    public function testSetPrimaryDepartment(): void
    {
        $employee = new Employee([
            'department_id' => 1,
            'department_ids' => [1, 2],
        ]);

        $employee->setPrimaryDepartment(3);

        $this->assertEquals(3, $employee->department_id);
        $this->assertContains(3, $employee->department_ids);
    }

    public function testAddToDepartment(): void
    {
        $employee = new Employee([
            'department_id' => 1,
            'department_ids' => [1, 2],
        ]);

        $employee->addToDepartment(3);

        $this->assertContains(3, $employee->department_ids);
    }

    public function testRemoveFromDepartment(): void
    {
        $employee = new Employee([
            'department_id' => 1,
            'department_ids' => [1, 2, 3],
        ]);

        $employee->removeFromDepartment(2);

        $this->assertNotContains(2, $employee->department_ids);
        $this->assertContains(1, $employee->department_ids);
        $this->assertContains(3, $employee->department_ids);
    }
}

