<?php

declare(strict_types=1);

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

/**
 * 创建企业表
 */
class CreateCorpsTable extends Migration
{
    public function up(): void
    {
        Schema::create('corps', function (Blueprint $table) {
            $table->bigIncrements('corp_id')->comment('企业ID');
            $table->string('name', 100)->comment('企业名称');
            $table->string('corp_code', 50)->comment('企业编码');
            $table->string('domain', 100)->nullable()->comment('企业域名');
            $table->string('icon_url', 500)->nullable()->comment('企业图标');
            $table->text('desc')->nullable()->comment('企业描述');
            $table->tinyInteger('status')->default(1)->unsigned()->comment('状态：0-禁用，1-启用');
            $table->unsignedBigInteger('creator_id')->default(0)->comment('创建人ID');
            $table->timestamps();
            $table->softDeletes();
            
            // 唯一索引
            $table->unique('corp_code', 'uk_corp_code');
            
            // 查询优化索引
            $table->index('status', 'idx_status');
            $table->index('created_at', 'idx_created_at');
            $table->index(['status', 'deleted_at'], 'idx_status_deleted');
            
            $table->comment('企业表');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corps');
    }
}
