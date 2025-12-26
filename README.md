# HPlus Corp - Hyperf 组织架构包

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue)](https://php.net)
[![Hyperf Version](https://img.shields.io/badge/hyperf-%7E3.1.0-green)](https://hyperf.io)
[![License](https://img.shields.io/badge/license-MIT-brightgreen)](LICENSE)

Hyperf 组织架构基础包，提供企业、部门、员工、角色、数据隔离等完整功能。

## 特性

- 📦 **开箱即用** - 完整的组织架构数据模型
- 🚀 **高性能** - 多级缓存（静态 + Redis）
- 🌳 **树形结构** - 部门物化路径，高效子树查询
- 🔒 **数据隔离** - 企业级 / 用户级两种隔离方式
- 👥 **协作者** - 支持任意资源类型的协作权限
- 🔐 **权限系统** - 完整的 RBAC + 注解权限
- 🔧 **易扩展** - 模型可重写，配置灵活

## 安装

```bash
composer require hyperf-plus/corp
```

## 快速开始

```bash
# 发布配置和迁移
php bin/hyperf.php vendor:publish hyperf-plus/corp

# 运行迁移
php bin/hyperf.php migrate
```

## 数据隔离

### 两种隔离方式

| Trait | 隔离维度 | 适用场景 |
|-------|---------|---------|
| `HasCorpScope` | 企业级 | 通知、配置、日志（企业内所有人可见） |
| `HasDataScope` | 用户级 | 业务数据（不同用户看到不同数据） |

### 1. 企业级隔离（HasCorpScope）

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

// 指定企业
Notification::ofCorp($corpId)->get();
```

### 2. 用户级隔离（HasDataScope）

基于角色数据范围自动过滤：

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
Order::create(['amount' => 100]); // 自动注入 corp_id、employee_id、department_id
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
Collaborator::RESOURCE_TYPE_SCRIPT = 10;  // 话术
Collaborator::RESOURCE_TYPE_LINE = 11;    // 线路
Collaborator::RESOURCE_TYPE_TASK = 12;    // 任务
```

**直接使用 Collaborator 模型：**

```php
use HPlus\Corp\Model\Collaborator;

// 添加
Collaborator::addCollaborator($userId, $resourceId, $resourceType);

// 移除
Collaborator::removeCollaborator($userId, $resourceId, $resourceType);

// 检查
Collaborator::hasPermission($userId, $resourceId, $resourceType);

// 获取用户可访问的资源ID（带缓存）
$ids = Collaborator::getUserResourceIds($userId, $resourceType);

// 批量设置
Collaborator::setResourceCollaborators($resourceId, $resourceType, $userIds);
```

## 上下文管理

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

CorpContext::setAuthRange(3);
CorpContext::getAuthRange();

CorpContext::setIsAdmin(true);
CorpContext::isAdmin();

// 临时跳过数据范围
CorpContext::withoutDataScope(function () {
    return Order::query()->get();
});
```

**注册中间件自动设置上下文：**

```php
// config/autoload/middlewares.php
return [
    'http' => [
        \HPlus\Corp\Middleware\CorpContextMiddleware::class,
    ],
];
```

## 权限系统

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
```

- 启动时收集注解，运行时切面校验，**无需额外中间件**
- 管理员自动跳过
- 权限不足抛出 `PermissionDeniedException`

### 手动检查

```php
$employee->hasPermission('user:create');
$employee->hasRole('admin');

$permissionService->hasPermission($employeeId, 'user:create');
$permissionService->setRolePermissions($roleId, [1, 2, 3]);
```

## 自定义模型

```php
// config/autoload/corp.php
return [
    'models' => [
        'employee' => \App\Model\Employee::class,
    ],
];
```

```php
use HPlus\Corp\Model\Employee as BaseEmployee;

class Employee extends BaseEmployee
{
    protected array $fillable = [
        ...parent::$fillable,
        'wechat_openid',
    ];
}
```

## 多部门支持

```php
$employee->setPrimaryDepartment($deptId);
$employee->addToDepartment($deptId);
$employee->removeFromDepartment($deptId);
$employee->getAllDepartmentIds();
```

## 性能优化

- **协作者缓存**：静态缓存 + Redis 缓存（5分钟）
- **部门子树缓存**：上下文缓存 + 静态缓存
- **字段检测缓存**：静态缓存（避免重复查库）
- **模型缓存**：Hyperf 原生模型缓存

```php
$corp = Corp::findFromCache($corpId);
$employees = Employee::findManyFromCache([1, 2, 3]);
```

## License

MIT
