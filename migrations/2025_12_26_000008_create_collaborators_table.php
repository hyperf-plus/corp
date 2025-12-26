<?php

declare(strict_types=1);

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

/**
 * 创建协作者表
 */
class CreateCollaboratorsTable extends Migration
{
    public function up(): void
    {
        Schema::create('collaborators', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->comment('用户ID/员工ID');
            $table->unsignedBigInteger('resource_id')->comment('资源ID');
            $table->tinyInteger('resource_type')->default(1)->comment('资源类型：1-企业，10-话术，11-线路，12-任务');
            $table->tinyInteger('scopes')->default(1)->comment('权限：1-查看，2-编辑，3-所属人');
            $table->tinyInteger('status')->default(1)->comment('状态：0-禁用，1-启用');
            $table->timestamps();
            $table->softDeletes();

            // 索引
            $table->index(['user_id', 'resource_type', 'status'], 'idx_user_resource_type');
            $table->index(['resource_id', 'resource_type', 'status'], 'idx_resource_status');
            $table->unique(['user_id', 'resource_id', 'resource_type'], 'uk_user_resource');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaborators');
    }
}

