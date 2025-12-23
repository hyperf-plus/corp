<?php

declare(strict_types=1);

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

/**
 * 创建权限表
 */
class CreatePermissionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->bigIncrements('permission_id')->comment('权限ID');
            $table->string('name', 100)->comment('权限名称');
            $table->string('slug', 100)->comment('权限标识');
            $table->unsignedBigInteger('parent_id')->default(0)->comment('父级ID');
            $table->string('app_code', 50)->nullable()->comment('应用编码');
            $table->text('desc')->nullable()->comment('权限描述');
            $table->integer('order')->default(0)->comment('排序');
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique('slug', 'uk_slug');
            $table->index('parent_id', 'idx_parent');
            $table->index('app_code', 'idx_app_code');
            
            $table->comment('权限表');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
}

