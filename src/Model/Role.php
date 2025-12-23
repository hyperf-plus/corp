<?php

declare(strict_types=1);

namespace HPlus\Corp\Model;

use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Relations\BelongsTo;
use Hyperf\Database\Model\Relations\BelongsToMany;
use Hyperf\Database\Model\Relations\HasMany;
use Hyperf\Database\Model\SoftDeletes;
use Hyperf\DbConnection\Model\Model;
use Hyperf\ModelCache\Cacheable;
use Hyperf\ModelCache\CacheableInterface;

/**
 * 角色模型
 * 
 * @property int $role_id 角色ID
 * @property int $corp_id 企业ID
 * @property string $role_name 角色名称
 * @property string|null $slug 角色标识
 * @property int $parent_id 父级角色ID
 * @property string|null $desc 角色描述
 * @property int $auth_range 数据权限范围
 * @property int $status 状态
 * @property int $order 排序
 */
class Role extends Model implements CacheableInterface
{
    use Cacheable, SoftDeletes;

    protected ?string $table = 'roles';
    protected string $primaryKey = 'role_id';

    protected array $fillable = [
        'corp_id', 'role_name', 'slug', 'parent_id', 'desc', 'auth_range', 'status', 'order',
    ];

    protected array $casts = [
        'role_id' => 'integer',
        'corp_id' => 'integer',
        'parent_id' => 'integer',
        'auth_range' => 'integer',
        'status' => 'integer',
        'order' => 'integer',
    ];

    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;

    // 数据权限范围
    public const AUTH_RANGE_SELF = 1;           // 仅本人
    public const AUTH_RANGE_DEPARTMENT = 2;     // 本部门
    public const AUTH_RANGE_DEPARTMENT_SUB = 3; // 本部门及下属
    public const AUTH_RANGE_ALL = 4;            // 全部

    public function corp(): BelongsTo
    {
        return $this->belongsTo(Corp::class, 'corp_id', 'corp_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'role_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'role_id');
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(
            Employee::class, 'role_users', 'role_id', 'employee_id', 'role_id', 'employee_id'
        );
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class, 'role_permissions', 'role_id', 'permission_id', 'role_id', 'permission_id'
        );
    }

    /**
     * 作用域：启用状态
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ENABLED);
    }

    /**
     * 作用域：排序
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order')->orderBy('role_id');
    }

    public function isEnabled(): bool
    {
        return $this->status === self::STATUS_ENABLED;
    }

    public function getAuthRangeText(): string
    {
        return match ($this->auth_range) {
            self::AUTH_RANGE_SELF => '仅本人',
            self::AUTH_RANGE_DEPARTMENT => '本部门',
            self::AUTH_RANGE_DEPARTMENT_SUB => '本部门及下属',
            self::AUTH_RANGE_ALL => '全部',
            default => '未知',
        };
    }
}
