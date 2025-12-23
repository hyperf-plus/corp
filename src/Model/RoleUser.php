<?php

declare(strict_types=1);

namespace HPlus\Corp\Model;

use Hyperf\Database\Model\Relations\BelongsTo;
use Hyperf\DbConnection\Model\Model;

/**
 * 角色用户关联模型
 * 
 * @property int $id
 * @property int $role_id 角色ID
 * @property int $employee_id 员工ID
 * @property int $corp_id 企业ID
 */
class RoleUser extends Model
{
    protected ?string $table = 'role_users';

    protected array $fillable = ['role_id', 'employee_id', 'corp_id'];

    protected array $casts = [
        'id' => 'integer',
        'role_id' => 'integer',
        'employee_id' => 'integer',
        'corp_id' => 'integer',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}

