<?php

declare(strict_types=1);

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

/**
 * 创建角色表
 */
class CreateRolesTable extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->bigIncrements('role_id')->comment('角色ID');
            $table->unsignedBigInteger('corp_id')->comment('企业ID');
            $table->string('role_name', 50)->comment('角色名称');
            $table->string('slug', 50)->nullable()->comment('角色标识');
            $table->unsignedBigInteger('parent_id')->default(0)->comment('父级角色ID');
            $table->text('desc')->nullable()->comment('角色描述');
            $table->tinyInteger('auth_range')->default(1)->unsigned()->comment('数据权限范围：1-仅本人，2-本部门，3-本部门及下属，4-全部');
            $table->tinyInteger('status')->default(1)->unsigned()->comment('状态：0-禁用，1-启用');
            $table->integer('order')->default(0)->comment('排序');
            $table->timestamps();
            $table->softDeletes();
            
            // 企业内角色名唯一
            $table->unique(['corp_id', 'role_name', 'deleted_at'], 'uk_corp_role_name');
            
            // 查询索引
            $table->index(['corp_id', 'status'], 'idx_corp_status');
            $table->index(['corp_id', 'parent_id'], 'idx_corp_parent');
            $table->index('slug', 'idx_slug');
            
            $table->comment('角色表');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
}
