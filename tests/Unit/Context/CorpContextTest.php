<?php

declare(strict_types=1);

namespace Tests\Unit\Context;

use HPlus\Corp\Context\CorpContext;
use Hyperf\Context\Context;
use PHPUnit\Framework\TestCase;

class CorpContextTest extends TestCase
{
    protected function tearDown(): void
    {
        CorpContext::clear();
    }

    public function testSetAndGetCorpId(): void
    {
        CorpContext::setCorpId(100);
        $this->assertEquals(100, CorpContext::getCorpId());
    }

    public function testSetAndGetEmployeeId(): void
    {
        CorpContext::setEmployeeId(200);
        $this->assertEquals(200, CorpContext::getEmployeeId());
    }

    public function testSetAndGetDepartmentId(): void
    {
        CorpContext::setDepartmentId(300);
        $this->assertEquals(300, CorpContext::getDepartmentId());
    }

    public function testSetAndIsAdmin(): void
    {
        CorpContext::setIsAdmin(true);
        $this->assertTrue(CorpContext::isAdmin());

        CorpContext::setIsAdmin(false);
        $this->assertFalse(CorpContext::isAdmin());
    }

    public function testSetAndGetAuthRange(): void
    {
        CorpContext::setAuthRange(4);
        $this->assertEquals(4, CorpContext::getAuthRange());
    }

    public function testSkipDataScope(): void
    {
        CorpContext::skipDataScope();
        $this->assertTrue(CorpContext::isSkipDataScope());

        CorpContext::restoreDataScope();
        $this->assertFalse(CorpContext::isSkipDataScope());
    }

    public function testWithoutDataScope(): void
    {
        $result = CorpContext::withoutDataScope(function () {
            $this->assertTrue(CorpContext::isSkipDataScope());
            return 'test';
        });

        $this->assertEquals('test', $result);
        $this->assertFalse(CorpContext::isSkipDataScope());
    }

    public function testClear(): void
    {
        CorpContext::setCorpId(100);
        CorpContext::setEmployeeId(200);
        CorpContext::setIsAdmin(true);

        CorpContext::clear();

        $this->assertEquals(0, CorpContext::getCorpId());
        $this->assertEquals(0, CorpContext::getEmployeeId());
        $this->assertFalse(CorpContext::isAdmin());
    }
}


