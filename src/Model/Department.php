<?php

declare(strict_types=1);

namespace HPlus\Corp\Model;

use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Relations\BelongsTo;
use Hyperf\Database\Model\Relations\HasMany;
use Hyperf\Database\Model\SoftDeletes;
use Hyperf\DbConnection\Model\Model;
use Hyperf\ModelCache\Cacheable;
use Hyperf\ModelCache\CacheableInterface;

/**
 * 部门模型
 * 
 * @property int $department_id 部门ID
 * @property int $corp_id 企业ID
 * @property string $name 部门名称
 * @property int $parent_id 上级部门ID
 * @property string $full_path 物化路径
 * @property int $level 层级深度
 * @property int $order 排序
 * @property int $supervisor_id 负责人ID
 * @property int $employee_count 员工数量
 * @property int $status 状态
 */
class Department extends Model implements CacheableInterface
{
    use Cacheable, SoftDeletes;

    protected ?string $table = 'departments';
    protected string $primaryKey = 'department_id';

    protected array $fillable = [
        'corp_id', 'name', 'parent_id', 'full_path', 'level', 'order',
        'supervisor_id', 'employee_count', 'status',
    ];

    protected array $casts = [
        'department_id' => 'integer',
        'corp_id' => 'integer',
        'parent_id' => 'integer',
        'level' => 'integer',
        'order' => 'integer',
        'supervisor_id' => 'integer',
        'employee_count' => 'integer',
        'status' => 'integer',
    ];

    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;

    public function corp(): BelongsTo
    {
        return $this->belongsTo(Corp::class, 'corp_id', 'corp_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'department_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'department_id')->orderBy('order');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'department_id', 'department_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id', 'employee_id');
    }

    /**
     * 递归加载子部门
     */
    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    /**
     * 作用域：获取子树（基于物化路径）
     */
    public function scopeDescendantsOf(Builder $query, int $departmentId): Builder
    {
        $dept = static::find($departmentId);
        if (!$dept) {
            return $query->whereRaw('1 = 0');
        }
        return $query->where('full_path', 'like', $dept->full_path . '%');
    }

    /**
     * 作用域：顶级部门
     */
    public function scopeRoot(Builder $query): Builder
    {
        return $query->where('parent_id', 0);
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
        return $query->orderBy('order')->orderBy('department_id');
    }

    public function isRoot(): bool
    {
        return $this->parent_id === 0;
    }

    public function isEnabled(): bool
    {
        return $this->status === self::STATUS_ENABLED;
    }

    /**
     * 获取所有祖先部门ID
     */
    public function getAncestorIds(): array
    {
        $ids = array_filter(explode('/', trim($this->full_path, '/')));
        array_pop($ids); // 移除自己
        return array_map('intval', $ids);
    }
}
