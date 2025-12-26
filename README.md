# HPlus Corp - Hyperf 组织架构包

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue)](https://php.net)
[![Hyperf Version](https://img.shields.io/badge/hyperf-%7E3.1.0-green)](https://hyperf.io)
[![License](https://img.shields.io/badge/license-MIT-brightgreen)](LICENSE)

Hyperf 组织架构基础包，提供企业、部门、员工、角色、权限、数据隔离等完整功能。支持企业级和用户级两种数据隔离方式，内置协作者权限管理，开箱即用。

## ✨ 特性

- 📦 **开箱即用** - 完整的组织架构数据模型（企业、部门、员工、角色、权限）
- 🚀 **高性能** - 多级缓存优化（静态缓存 + Redis + 上下文缓存）
- 🌳 **树形结构** - 部门物化路径（Materialized Path），高效子树查询
- 🔒 **数据隔离** - 企业级 / 用户级两种隔离方式，自动过滤
- 👥 **协作者** - 支持任意资源类型的协作权限管理
- 🔐 **权限系统** - 完整的 RBAC + 注解权限（切面自动校验）
- 🔧 **易扩展** - 模型可重写，配置灵活，低耦合

## 📦 安装

```bash
composer require hyperf-plus/corp
```

## 🚀 快速开始

### 1. 发布配置和迁移

```bash
php bin/hyperf.php vendor:publish hyperf-plus/corp
```

### 2. 运行迁移

```bash
php bin/hyperf.php migrate
```

### 3. 注册中间件（自动设置上下文）

```php
// config/autoload/middlewares.php
return [
    'http' => [
        \HPlus\Corp\Middleware\CorpContextMiddleware::class,
    ],
];
```

### 4. 使用模型

```php
use HPlus\Corp\Model\Corp;
use HPlus\Corp\Model\Department;
use HPlus\Corp\Model\Employee;
use HPlus\Corp\Model\Role;

// 创建企业
$corp = Corp::create([
    'name' => '测试企业',
    'corp_code' => 'test001',
]);

// 创建部门
$dept = Department::create([
    'corp_id' => $corp->corp_id,
    'name' => '技术部',
    'parent_id' => 0,
]);

// 创建员工
$employee = Employee::create([
    'corp_id' => $corp->corp_id,
    'department_id' => $dept->department_id,
    'name' => '张三',
    'mobile' => '13800138000',
]);

// 创建角色
$role = Role::create([
    'corp_id' => $corp->corp_id,
    'role_name' => '管理员',
    'auth_range' => Role::AUTH_RANGE_ALL,
]);
```

## 🔒 数据隔离

### 两种隔离方式

| Trait | 隔离维度 | 适用场景 | 过滤逻辑 |
|-------|---------|---------|---------|
| `HasCorpScope` | 企业级 | 通知、配置、日志等 | `WHERE corp_id = ?` |
| `HasDataScope` | 用户级 | 业务数据（订单、话术等） | `corp_id + 角色数据范围 + 协作者权限` |

### 1. 企业级隔离（HasCorpScope）

适用于企业内所有人都可见的数据：

```php
use HPlus\Corp\Model\Concern\HasCorpScope;

class Notification extends Model
{
    use HasCorpScope;
}

// 查询自动过滤当前企业
$notifications = Notification::query()->get();

// 跳过过滤
Notification::withoutCorpScope()->get();

// 指定企业查询
Notification::ofCorp($corpId)->get();
```

### 2. 用户级隔离（HasDataScope）

基于角色数据范围自动过滤，支持协作者权限：

```php
use HPlus\Corp\Model\Concern\HasDataScope;

class Order extends Model
{
    use HasDataScope;
    
    protected array $fillable = ['corp_id', 'employee_id', 'department_id', ...];
}

// 自动根据角色 auth_range 过滤：
// - 1: 仅本人 → WHERE employee_id = 当前员工
// - 2: 本部门 → WHERE department_id = 当前部门
// - 3: 本部门及下属 → WHERE department_id IN (部门及子部门)
// - 4: 全部 → 不额外过滤

$orders = Order::query()->get();  // 自动过滤

// 创建时自动注入上下文字段
Order::create(['amount' => 100]); // 自动填充 corp_id、employee_id、department_id
```

### 3. 启用协作者（HasDataScope 扩展功能）

支持协作者的资源，用户可见数据 = **角色数据范围 ∪ 被授权协作的数据**：

```php
use HPlus\Corp\Model\Concern\HasDataScope;
use HPlus\Corp\Model\Collaborator;

class Script extends Model
{
    use HasDataScope;
    
    // 启用协作者
    protected bool $enableCollaborator = true;
    
    // 资源类型（自定义整数）
    protected int $resourceType = 10;
    
    // 资源ID字段（默认主键）
    protected string $resourceIdColumn = 'id';
    
    // 创建时自动将创建者添加为协作者（默认 true）
    protected bool $autoAddCreatorAsCollaborator = true;
}

// 查询：返回"角色权限内的" + "被授权协作的"
$scripts = Script::query()->get();

// 协作者管理
$script->addCollaborator(userId: 1, scope: Collaborator::SCOPE_EDIT);
$script->removeCollaborator(userId: 1);
$script->setCollaborators([1, 2, 3]);
$script->getCollaboratorIds();

// 检查权限
$script->hasCollaboratorPermission(userId: 1);
$script->currentUserHasCollaboratorPermission();
```

**资源类型常量（可自定义任意整数）：**

```php
Collaborator::RESOURCE_TYPE_CORP = 1;     // 企业
Collaborator::RESOURCE_TYPE_AGENT = 2;    // 代理商
Collaborator::RESOURCE_TYPE_SCRIPT = 10;   // 话术
Collaborator::RESOURCE_TYPE_LINE = 11;     // 线路
Collaborator::RESOURCE_TYPE_TASK = 12;     // 任务
```

**直接使用 Collaborator 模型：**

```php
use HPlus\Corp\Model\Collaborator;

// 添加协作者
Collaborator::addCollaborator($userId, $resourceId, $resourceType);

// 移除协作者
Collaborator::removeCollaborator($userId, $resourceId, $resourceType);

// 检查权限
Collaborator::hasPermission($userId, $resourceId, $resourceType);

// 获取用户可访问的资源ID（带缓存）
$ids = Collaborator::getUserResourceIds($userId, $resourceType);

// 批量设置协作者
Collaborator::setResourceCollaborators($resourceId, $resourceType, $userIds);
```

## 📋 上下文管理

```php
use HPlus\Corp\Context\CorpContext;

// 批量设置
CorpContext::set(
    corpId: 1,
    employeeId: 100,
    departmentId: 10,
    authRange: 3,
    isAdmin: false
);

// 单独设置/获取
CorpContext::setCorpId(1);
CorpContext::getCorpId();

CorpContext::setEmployeeId(100);
CorpContext::getEmployeeId();

CorpContext::setAuthRange(3);
CorpContext::getAuthRange();

CorpContext::setIsAdmin(true);
CorpContext::isAdmin();

// 临时跳过数据范围过滤
CorpContext::withoutDataScope(function () {
    return Order::query()->get();
});
```

## 🔐 权限系统

### 注解权限（切面自动校验）

```php
use HPlus\Corp\Annotation\Permission;

class UserController
{
    #[Permission('user.create')]
    public function create() {}

    #[Permission('user.edit')]
    public function update() {}

    // 多个权限满足任一即可
    #[Permission('user.delete,admin')]
    public function delete() {}
}

// 类级别注解（整个控制器生效）
#[Permission('user')]
class UserController
{
    public function list() {}  // 需要 user 权限
}
```

**特性：**
- ✅ 启动时自动收集注解，运行时切面校验，**无需额外中间件**
- ✅ 管理员（`CorpContext::isAdmin() = true`）自动跳过
- ✅ 权限不足抛出 `PermissionDeniedException`（403）

### 手动检查

```php
// 员工模型方法
$employee->hasPermission('user:create');
$employee->hasRole('admin');

// 权限服务
$permissionService = make(PermissionService::class);
$permissionService->hasPermission($employeeId, 'user:create');
$permissionService->setRolePermissions($roleId, [1, 2, 3]);
```

## 🔧 自定义模型

支持用户重写模型，在配置文件中指定自定义模型类：

```php
// config/autoload/corp.php
return [
    'models' => [
        'corp' => \App\Model\Corp::class,
        'employee' => \App\Model\Employee::class,
        'collaborator' => \App\Model\Collaborator::class,
    ],
];
```

自定义模型继承基础模型即可：

```php
namespace App\Model;

use HPlus\Corp\Model\Employee as BaseEmployee;

class Employee extends BaseEmployee
{
    protected array $fillable = [
        ...parent::$fillable,
        'wechat_openid',
        'ding_id',
    ];

    // 添加自定义关联
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
```

通过 `CorpManager` 获取配置的模型类：

```php
use HPlus\Corp\CorpManager;

$employeeClass = CorpManager::employeeModel();
$employee = $employeeClass::find(1);
```

## 👥 多部门支持

员工支持加入多个部门，主部门只有一个：

```php
// 设置主部门（自动加入部门列表）
$employee->setPrimaryDepartment($deptId);

// 添加到部门
$employee->addToDepartment($deptId);

// 从部门移除
$employee->removeFromDepartment($deptId);

// 获取所有部门ID
$ids = $employee->getAllDepartmentIds();

// 批量设置部门
$employeeService = make(EmployeeService::class);
$employeeService->setDepartments($employeeId, [1, 2, 3], primaryDepartmentId: 1);
```

## ⚡ 性能优化

### 多级缓存策略

| 缓存类型 | 层级 | 有效期 | 用途 |
|---------|------|--------|------|
| **静态缓存** | 进程级 | 单次请求 | 协作者ID列表、部门子树、字段检测 |
| **Redis 缓存** | 分布式 | 5分钟 | 协作者ID列表 |
| **上下文缓存** | 请求级 | 单次请求 | 部门ID列表 |
| **模型缓存** | Hyperf 原生 | 可配置 | 模型数据 |

```php
// 使用模型缓存
$corp = Corp::findFromCache($corpId);
$employees = Employee::findManyFromCache([1, 2, 3]);
```

### 查询优化

- ✅ 优先使用 `IN` 查询而非子查询
- ✅ 字段检测优先检查 `fillable`，避免查库
- ✅ 部门子树使用物化路径（`full_path`）高效查询
- ✅ 协作者权限合并到主查询，避免多次查询

## 📚 数据模型

### 企业 (Corp)
- `corp_id`, `name`, `corp_code`, `status`, `icon_url`, `desc` 等

### 部门 (Department)
- `department_id`, `corp_id`, `name`, `parent_id`, `full_path`, `level`, `order`, `supervisor_id`, `employee_count` 等
- 支持树形结构，使用物化路径（Materialized Path）

### 员工 (Employee)
- `employee_id`, `corp_id`, `department_id`, `department_ids`（JSON），`name`, `mobile`, `email`, `status`, `is_admin` 等
- 支持多部门（主部门 + 兼职部门）

### 角色 (Role)
- `role_id`, `corp_id`, `role_name`, `slug`, `auth_range`（数据权限范围），`status` 等
- `auth_range`: 1-仅本人, 2-本部门, 3-本部门及下属, 4-全部

### 权限 (Permission)
- `permission_id`, `name`, `slug`, `parent_id`, `app_code`, `order` 等
- 支持树形结构

### 协作者 (Collaborator)
- `id`, `user_id`, `resource_id`, `resource_type`, `scopes`, `status` 等
- 支持任意资源类型的协作权限

## 🎯 使用场景

### 场景 1：企业级数据（通知、配置）
```php
class Notification extends Model
{
    use HasCorpScope;  // 仅企业隔离
}
```

### 场景 2：普通业务数据（订单、客户）
```php
class Order extends Model
{
    use HasDataScope;  // 企业 + 角色数据范围
}
```

### 场景 3：协作资源（话术、任务）
```php
class Script extends Model
{
    use HasDataScope;
    
    protected bool $enableCollaborator = true;
    protected int $resourceType = 10;
}
```

## 📝 事件系统

组织架构变更自动触发事件，支持监听扩展：

```php
use HPlus\Corp\Event\EmployeeEvent;
use HPlus\Corp\Event\DepartmentEvent;
use HPlus\Corp\Event\RoleEvent;

// 监听员工角色变更
Event::listen(EmployeeEvent::ROLE_CHANGED, function (EmployeeEvent $event) {
    // $event->employeeId
    // $event->oldRoleIds
    // $event->newRoleIds
});

// 监听部门变更
Event::listen(DepartmentEvent::CREATED, function (DepartmentEvent $event) {
    // $event->departmentId
    // $event->data
});
```

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

## 📄 License

MIT
