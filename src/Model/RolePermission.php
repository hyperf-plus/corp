<?php

declare(strict_types=1);

namespace HPlus\Corp\Model;

use Hyperf\Database\Model\Relations\BelongsTo;
use Hyperf\DbConnection\Model\Model;

/**
 * 角色权限关联模型
 * 
 * @property int $id
 * @property int $role_id 角色ID
 * @property int $permission_id 权限ID
 */
class RolePermission extends Model
{
    protected ?string $table = 'role_permissions';

    protected array $fillable = ['role_id', 'permission_id'];

    protected array $casts = [
        'id' => 'integer',
        'role_id' => 'integer',
        'permission_id' => 'integer',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id', 'permission_id');
    }
}

