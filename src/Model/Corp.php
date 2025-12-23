<?php

declare(strict_types=1);

namespace HPlus\Corp\Model;

use Hyperf\Database\Model\Relations\HasMany;
use Hyperf\Database\Model\SoftDeletes;
use Hyperf\DbConnection\Model\Model;
use Hyperf\ModelCache\Cacheable;
use Hyperf\ModelCache\CacheableInterface;

/**
 * 企业模型
 * 
 * @property int $corp_id 企业ID
 * @property string $name 企业名称
 * @property string $corp_code 企业编码
 * @property string|null $domain 企业域名
 * @property string|null $icon_url 企业图标
 * @property string|null $desc 企业描述
 * @property int $status 状态
 * @property int $creator_id 创建人ID
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Corp extends Model implements CacheableInterface
{
    use Cacheable, SoftDeletes;

    protected ?string $table = 'corps';
    protected string $primaryKey = 'corp_id';

    protected array $fillable = [
        'name', 'corp_code', 'domain', 'icon_url', 'desc', 'status', 'creator_id',
    ];

    protected array $casts = [
        'corp_id' => 'integer',
        'status' => 'integer',
        'creator_id' => 'integer',
    ];

    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class, 'corp_id', 'corp_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'corp_id', 'corp_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'corp_id', 'corp_id');
    }

    public function isEnabled(): bool
    {
        return $this->status === self::STATUS_ENABLED;
    }

    /**
     * 根据编码获取企业ID（带缓存）
     */
    public static function getIdByCode(string $corpCode): int
    {
        return (int) static::query()->where('corp_code', $corpCode)->value('corp_id');
    }
}
