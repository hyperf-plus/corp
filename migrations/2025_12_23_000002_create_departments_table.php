<?php

declare(strict_types=1);

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

/**
 * 创建部门表
 * 
 * 设计说明：
 * - full_path 采用物化路径设计，支持高效的子树查询
 * - 配合 order 字段实现同级排序
 * - employee_count 缓存员工数量，避免频繁 COUNT 查询
 */
class CreateDepartmentsTable extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->bigIncrements('department_id')->comment('部门ID');
            $table->unsignedBigInteger('corp_id')->comment('企业ID');
            $table->string('name', 100)->comment('部门名称');
            $table->unsignedBigInteger('parent_id')->default(0)->comment('上级部门ID，0表示顶级部门');
            $table->string('full_path', 500)->default('/')->comment('物化路径，如：/1/2/3/');
            $table->smallInteger('level')->default(1)->unsigned()->comment('层级深度');
            $table->integer('order')->default(0)->comment('排序');
            $table->unsignedBigInteger('supervisor_id')->default(0)->comment('部门负责人ID');
            $table->integer('employee_count')->default(0)->unsigned()->comment('员工数量（缓存）');
            $table->tinyInteger('status')->default(1)->unsigned()->comment('状态：0-禁用，1-启用');
            $table->timestamps();
            $table->softDeletes();
            
            // 企业内部门唯一索引（可选，根据业务需求）
            // $table->unique(['corp_id', 'name', 'parent_id'], 'uk_corp_name_parent');
            
            // 核心查询索引
            $table->index(['corp_id', 'parent_id'], 'idx_corp_parent');
            $table->index(['corp_id', 'status'], 'idx_corp_status');
            $table->index('full_path', 'idx_full_path');  // 支持 LIKE 'path%' 子树查询
            $table->index('supervisor_id', 'idx_supervisor');
            
            // 排序查询优化
            $table->index(['corp_id', 'parent_id', 'order'], 'idx_corp_parent_order');
            
            $table->comment('部门表');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
}
