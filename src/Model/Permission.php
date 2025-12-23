<?php

declare(strict_types=1);

namespace HPlus\Corp\Model;

use Hyperf\Database\Model\Relations\BelongsTo;
use Hyperf\Database\Model\Relations\BelongsToMany;
use Hyperf\Database\Model\Relations\HasMany;
use Hyperf\Database\Model\SoftDeletes;
use Hyperf\DbConnection\Model\Model;
use Hyperf\ModelCache\Cacheable;
use Hyperf\ModelCache\CacheableInterface;

/**
 * 权限模型
 * 
 * @property int $permission_id 权限ID
 * @property string $name 权限名称
 * @property string $slug 权限标识
 * @property int $parent_id 父级ID
 * @property string|null $app_code 应用编码
 * @property string|null $desc 权限描述
 * @property int $order 排序
 */
class Permission extends Model implements CacheableInterface
{
    use Cacheable, SoftDeletes;

    protected ?string $table = 'permissions';
    protected string $primaryKey = 'permission_id';

    protected array $fillable = [
        'name', 'slug', 'parent_id', 'app_code', 'desc', 'order',
    ];

    protected array $casts = [
        'permission_id' => 'integer',
        'parent_id' => 'integer',
        'order' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'permission_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'permission_id')->orderBy('order');
    }

    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class, 'role_permissions', 'permission_id', 'role_id', 'permission_id', 'role_id'
        );
    }
}

