<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use HPlus\Corp\Model\Department;
use PHPUnit\Framework\TestCase;

class DepartmentTest extends TestCase
{
    public function testIsRoot(): void
    {
        $dept = new Department(['parent_id' => 0]);
        $this->assertTrue($dept->isRoot());

        $dept = new Department(['parent_id' => 1]);
        $this->assertFalse($dept->isRoot());
    }

    public function testIsEnabled(): void
    {
        $dept = new Department(['status' => Department::STATUS_ENABLED]);
        $this->assertTrue($dept->isEnabled());

        $dept = new Department(['status' => Department::STATUS_DISABLED]);
        $this->assertFalse($dept->isEnabled());
    }

    public function testGetAncestorIds(): void
    {
        $dept = new Department(['full_path' => '/1/2/3/']);
        $ancestors = $dept->getAncestorIds();

        $this->assertEquals([1, 2], $ancestors);
    }

    public function testGetAncestorIdsForRoot(): void
    {
        $dept = new Department(['full_path' => '/1/']);
        $ancestors = $dept->getAncestorIds();

        $this->assertEquals([], $ancestors);
    }
}

