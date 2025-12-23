<?php

declare(strict_types=1);

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

/**
 * 创建员工表
 * 
 * 设计说明：
 * - 只保留组织架构必需的基础字段
 * - 业务扩展字段通过扩展表或 JSON 字段实现
 */
class CreateEmployeesTable extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->bigIncrements('employee_id')->comment('员工ID');
            $table->unsignedBigInteger('corp_id')->comment('企业ID');
            $table->unsignedBigInteger('department_id')->default(0)->comment('主部门ID');
            $table->json('department_ids')->nullable()->comment('所属部门ID列表');
            $table->string('name', 50)->comment('姓名');
            $table->string('mobile', 20)->nullable()->comment('手机号');
            $table->string('email', 100)->nullable()->comment('邮箱');
            $table->string('employee_number', 50)->nullable()->comment('员工号');
            $table->string('avatar', 500)->nullable()->comment('头像');
            $table->string('position', 50)->nullable()->comment('职位');
            $table->tinyInteger('gender')->default(0)->unsigned()->comment('性别：0-未知，1-男，2-女');
            $table->tinyInteger('status')->default(1)->unsigned()->comment('状态：0-待激活，1-正常，2-离职，3-暂停');
            $table->unsignedBigInteger('leader_id')->default(0)->comment('直属领导ID');
            $table->tinyInteger('is_admin')->default(0)->unsigned()->comment('是否管理员：0-否，1-是');
            $table->date('join_at')->nullable()->comment('入职日期');
            $table->date('out_at')->nullable()->comment('离职日期');
            $table->timestamp('last_login_at')->nullable()->comment('最后登录时间');
            $table->timestamps();
            $table->softDeletes();
            
            // 核心查询索引
            $table->index(['corp_id', 'status'], 'idx_corp_status');
            $table->index(['corp_id', 'department_id'], 'idx_corp_dept');
            $table->index('department_id', 'idx_department');
            $table->index('leader_id', 'idx_leader');
            
            // 登录查询索引
            $table->index(['corp_id', 'mobile'], 'idx_corp_mobile');
            $table->index(['corp_id', 'email'], 'idx_corp_email');
            $table->index(['corp_id', 'employee_number'], 'idx_corp_number');
            
            // 状态筛选索引
            $table->index(['corp_id', 'status', 'department_id'], 'idx_corp_status_dept');
            
            $table->comment('员工表');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
}
