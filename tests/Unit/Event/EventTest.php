<?php

declare(strict_types=1);

namespace Tests\Unit\Event;

use HPlus\Corp\Event\EmployeeEvent;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    public function testEmployeeEventCreation(): void
    {
        $data = [
            'type' => EmployeeEvent::ROLE_CHANGED,
            'corp_id' => 1,
            'employee_id' => 100,
            'name' => '张三',
            'old_role_ids' => [1, 2],
            'new_role_ids' => [1, 3],
        ];

        $event = new EmployeeEvent($data);

        $this->assertEquals(EmployeeEvent::ROLE_CHANGED, $event->type);
        $this->assertEquals(1, $event->corpId);
        $this->assertEquals(100, $event->employeeId);
        $this->assertEquals('张三', $event->name);
        $this->assertEquals([1, 2], $event->oldRoleIds);
        $this->assertEquals([1, 3], $event->newRoleIds);
        $this->assertIsInt($event->timestamp);
    }

    public function testEmployeeEventToArray(): void
    {
        $data = [
            'type' => EmployeeEvent::CREATED,
            'corp_id' => 1,
            'employee_id' => 100,
            'name' => '李四',
        ];

        $event = new EmployeeEvent($data);
        $array = $event->toArray();

        $this->assertIsArray($array);
        $this->assertEquals(EmployeeEvent::CREATED, $array['type']);
        $this->assertEquals(1, $array['corp_id']);
        $this->assertEquals(100, $array['employee_id']);
        $this->assertEquals('李四', $array['name']);
    }

    public function testEventWithSnakeCaseData(): void
    {
        $data = [
            'type' => EmployeeEvent::DEPARTMENT_CHANGED,
            'corp_id' => 1,
            'employee_id' => 100,
            'old_department_ids' => [1, 2],
            'new_department_ids' => [2, 3],
        ];

        $event = new EmployeeEvent($data);

        $this->assertEquals([1, 2], $event->oldDepartmentIds);
        $this->assertEquals([2, 3], $event->newDepartmentIds);
    }
}

