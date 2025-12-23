<?php

declare(strict_types=1);

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

/**
 * 创建角色权限关联表
 */
class CreateRolePermissionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('role_id')->comment('角色ID');
            $table->unsignedBigInteger('permission_id')->comment('权限ID');
            $table->timestamps();
            
            $table->unique(['role_id', 'permission_id'], 'uk_role_permission');
            $table->index('permission_id', 'idx_permission');
            
            $table->comment('角色权限关联表');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
}

