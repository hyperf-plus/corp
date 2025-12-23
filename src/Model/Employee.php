<?php

declare(strict_types=1);

namespace HPlus\Corp\Model;

use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Collection;
use Hyperf\Database\Model\Relations\BelongsTo;
use Hyperf\Database\Model\Relations\BelongsToMany;
use Hyperf\Database\Model\SoftDeletes;
use Hyperf\DbConnection\Model\Model;
use Hyperf\ModelCache\Cacheable;
use Hyperf\ModelCache\CacheableInterface;

/**
 * 员工模型
 * 
 * @property int $employee_id 员工ID
 * @property int $corp_id 企业ID
 * @property int $department_id 主部门ID
 * @property array|null $department_ids 所属部门ID列表
 * @property string $name 姓名
 * @property string|null $mobile 手机号
 * @property string|null $email 邮箱
 * @property string|null $employee_number 员工号
 * @property string|null $avatar 头像
 * @property string|null $position 职位
 * @property int $gender 性别
 * @property int $status 状态
 * @property int $leader_id 直属领导ID
 * @property int $is_admin 是否管理员
 * @property string|null $join_at 入职日期
 * @property string|null $out_at 离职日期
 * @property \Carbon\Carbon|null $last_login_at 最后登录时间
 */
class Employee extends Model implements CacheableInterface
{
    use Cacheable, SoftDeletes;

    protected ?string $table = 'employees';
    protected string $primaryKey = 'employee_id';

    protected array $fillable = [
        'corp_id', 'department_id', 'department_ids', 'name', 'mobile', 'email',
        'employee_number', 'avatar', 'position', 'gender', 'status', 'leader_id',
        'is_admin', 'join_at', 'out_at', 'last_login_at',
    ];

    protected array $casts = [
        'employee_id' => 'integer',
        'corp_id' => 'integer',
        'department_id' => 'integer',
        'department_ids' => 'array',
        'gender' => 'integer',
        'status' => 'integer',
        'leader_id' => 'integer',
        'is_admin' => 'integer',
        'last_login_at' => 'datetime',
    ];

    // 状态常量
    public const STATUS_NOT_ACTIVATED = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_RESIGNED = 2;
    public const STATUS_SUSPENDED = 3;

    // 性别常量
    public const GENDER_UNKNOWN = 0;
    public const GENDER_MALE = 1;
    public const GENDER_FEMALE = 2;

    public function corp(): BelongsTo
    {
        return $this->belongsTo(Corp::class, 'corp_id', 'corp_id');
    }

    /**
     * 主部门
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    /**
     * 所有所属部门
     */
    public function departments(): Collection
    {
        $ids = $this->department_ids ?? [];
        if (empty($ids)) {
            return new Collection();
        }
        return Department::findManyFromCache($ids);
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(self::class, 'leader_id', 'employee_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class, 'role_users', 'employee_id', 'role_id', 'employee_id', 'role_id'
        );
    }

    /**
     * 作用域：在职员工
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * 作用域：按主部门筛选
     */
    public function scopeOfDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * 作用域：按所属部门筛选（包含兼职部门）
     */
    public function scopeInDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->where(function ($q) use ($departmentId) {
            $q->where('department_id', $departmentId)
              ->orWhereJsonContains('department_ids', $departmentId);
        });
    }

    /**
     * 作用域：按多个部门筛选
     */
    public function scopeInDepartments(Builder $query, array $departmentIds): Builder
    {
        return $query->where(function ($q) use ($departmentIds) {
            $q->whereIn('department_id', $departmentIds);
            foreach ($departmentIds as $deptId) {
                $q->orWhereJsonContains('department_ids', $deptId);
            }
        });
    }

    /**
     * 作用域：管理员
     */
    public function scopeAdmin(Builder $query): Builder
    {
        return $query->where('is_admin', 1);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isResigned(): bool
    {
        return $this->status === self::STATUS_RESIGNED;
    }

    public function isAdmin(): bool
    {
        return $this->is_admin === 1;
    }

    /**
     * 获取员工所有权限
     */
    public function getPermissions(): array
    {
        $permissions = [];
        foreach ($this->roles as $role) {
            if ($role->isEnabled()) {
                foreach ($role->permissions as $permission) {
                    $permissions[$permission->permission_id] = $permission;
                }
            }
        }
        return array_values($permissions);
    }

    /**
     * 获取员工所有权限标识
     */
    public function getPermissionSlugs(): array
    {
        return array_column($this->getPermissions(), 'slug');
    }

    /**
     * 检查是否有指定权限
     */
    public function hasPermission(string $slug): bool
    {
        return in_array($slug, $this->getPermissionSlugs());
    }

    /**
     * 检查是否有任一权限
     */
    public function hasAnyPermission(array $slugs): bool
    {
        return !empty(array_intersect($slugs, $this->getPermissionSlugs()));
    }

    /**
     * 获取数据权限范围（取角色中最大的）
     */
    public function getAuthRange(): int
    {
        $maxRange = Role::AUTH_RANGE_SELF;
        foreach ($this->roles as $role) {
            if ($role->isEnabled() && $role->auth_range > $maxRange) {
                $maxRange = $role->auth_range;
            }
        }
        return $maxRange;
    }

    /**
     * 添加到部门
     */
    public function addToDepartment(int $departmentId): void
    {
        $ids = $this->department_ids ?? [];
        if (!in_array($departmentId, $ids)) {
            $ids[] = $departmentId;
            $this->department_ids = array_values(array_unique($ids));
            $this->save();
        }
    }

    /**
     * 从部门移除
     */
    public function removeFromDepartment(int $departmentId): void
    {
        $ids = $this->department_ids ?? [];
        $ids = array_filter($ids, fn($id) => $id !== $departmentId);
        $this->department_ids = array_values($ids);
        $this->save();
    }

    /**
     * 设置主部门
     */
    public function setPrimaryDepartment(int $departmentId): void
    {
        $this->department_id = $departmentId;
        $this->addToDepartment($departmentId);
    }

    /**
     * 获取所有部门ID（含主部门）
     */
    public function getAllDepartmentIds(): array
    {
        $ids = $this->department_ids ?? [];
        if ($this->department_id > 0 && !in_array($this->department_id, $ids)) {
            array_unshift($ids, $this->department_id);
        }
        return array_values(array_unique($ids));
    }
}
