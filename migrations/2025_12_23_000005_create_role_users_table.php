<?php

declare(strict_types=1);

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

/**
 * 创建角色用户关联表
 */
class CreateRoleUsersTable extends Migration
{
    public function up(): void
    {
        Schema::create('role_users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('role_id')->comment('角色ID');
            $table->unsignedBigInteger('employee_id')->comment('员工ID');
            $table->unsignedBigInteger('corp_id')->comment('企业ID');
            $table->timestamps();
            
            // 唯一约束：同一员工不能重复分配同一角色
            $table->unique(['role_id', 'employee_id'], 'uk_role_employee');
            
            // 查询索引
            $table->index('employee_id', 'idx_employee');
            $table->index(['corp_id', 'role_id'], 'idx_corp_role');
            
            $table->comment('角色用户关联表');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_users');
    }
}
