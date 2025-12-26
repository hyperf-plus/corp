# HPlus Corp - Hyperf 组织架构包

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue)](https://php.net)
[![Hyperf Version](https://img.shields.io/badge/hyperf-%7E3.1.0-green)](https://hyperf.io)
[![License](https://img.shields.io/badge/license-MIT-brightgreen)](LICENSE)

Hyperf 组织架构基础包，提供企业、部门、员工、角色、数据隔离、协作者等完整功能。

## 特性

- 📦 **开箱即用** - 完整的组织架构数据模型
- 🚀 **高性能** - 内置 Hyperf 模型缓存
- 🌳 **树形结构** - 部门支持物化路径，高效子树查询
- 🔒 **数据隔离** - 自动按企业/部门/员工过滤数据
- 🔐 **权限系统** - 完整的 RBAC 权限管理
- 👥 **协作者** - 支持任意资源类型的协作权限
- 🔧 **易扩展** - 精简核心字段，业务扩展自由

## 安装

```bash
composer require hyperf-plus/corp
```

## 快速开始

### 1. 发布配置和迁移

```bash
php bin/hyperf.php vendor:publish hyperf-plus/corp
```

### 2. 运行迁移

```bash
php bin/hyperf.php migrate
```

### 3. 使用模型

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

## 数据隔离

### 1. 企业隔离（HasCorpScope）

仅按 `corp_id` 隔离，适合企业级配置等场景：

```php
use HPlus\Corp\Model\Concern\HasCorpScope;

class Config extends Model
{
    use HasCorpScope;
}

// 查询自动过滤当前企业
$configs = Config::query()->get();

// 跳过过滤
Config::withoutCorpScope()->get();

// 指定企业查询
Config::ofCorp($corpId)->get();
```

### 2. 数据范围（HasDataScope）

基于角色的 `auth_range` 自动过滤数据：

```php
use HPlus\Corp\Model\Concern\HasDataScope;

class Order extends Model
{
    use HasDataScope;
    
    protected array $fillable = ['corp_id', 'employee_id', 'department_id', ...];
}

// 查询自动根据 auth_range 过滤：
// - 1: 仅本人 → WHERE employee_id = 当前员工
// - 2: 本部门 → WHERE department_id = 当前部门
// - 3: 本部门及下属 → WHERE department_id IN (部门及子部门)
// - 4: 全部 → 不额外过滤

$orders = Order::query()->get();

// 创建时自动注入 corp_id、employee_id、department_id
Order::create(['amount' => 100]);
```

### 3. 协作者隔离（HasCollaboratorScope）

基于协作者权限隔离，支持任意资源类型：

```php
use HPlus\Corp\Model\Concern\HasCollaboratorScope;
use HPlus\Corp\Model\Collaborator;

class Script extends Model
{
    use HasCollaboratorScope;
    
    // 资源类型（必须定义）
    protected int $resourceType = Collaborator::RESOURCE_TYPE_SCRIPT;
    
    // 资源ID字段（默认为主键）
    protected string $resourceIdColumn = 'id';
    
    // 管理员是否可查看全部（默认 true）
    protected bool $adminViewAll = true;
}

// 查询自动过滤有权限的数据
$scripts = Script::query()->get();

// 跳过过滤
Script::withoutCollaboratorScope()->get();
```

**协作者管理：**

```php
use HPlus\Corp\Model\Collaborator;

// 添加协作者
Collaborator::addCollaborator(
    userId: 1, 
    resourceId: 100, 
    resourceType: Collaborator::RESOURCE_TYPE_SCRIPT,
    scopes: Collaborator::SCOPE_EDIT
);

// 移除协作者
Collaborator::removeCollaborator(userId: 1, resourceId: 100, resourceType: 10);

// 检查权限
Collaborator::hasPermission(userId: 1, resourceId: 100, resourceType: 10);

// 获取用户可访问的资源
$ids = Collaborator::getUserResourceIds(userId: 1, resourceType: 10);

// 批量设置协作者
Collaborator::setResourceCollaborators(resourceId: 100, resourceType: 10, userIds: [1, 2, 3]);

// 从模型实例管理
$script->addCollaborator(userId: 1);
$script->removeCollaborator(userId: 1);
$script->setCollaborators([1, 2, 3]);
$script->getCollaboratorIds();
```

**资源类型常量（可自定义）：**

```php
Collaborator::RESOURCE_TYPE_CORP = 1;    // 企业
Collaborator::RESOURCE_TYPE_AGENT = 2;   // 代理商
Collaborator::RESOURCE_TYPE_SCRIPT = 10; // 话术
Collaborator::RESOURCE_TYPE_LINE = 11;   // 线路
Collaborator::RESOURCE_TYPE_TASK = 12;   // 任务
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

**注册中间件自动设置上下文：**

```php
// config/autoload/middlewares.php
return [
    'http' => [
        \HPlus\Corp\Middleware\CorpContextMiddleware::class,
    ],
];
```

## 权限检查

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

// 类级别注解
#[Permission('user')]
class UserController
{
    public function list() {}  // 需要 user 权限
}
```

- 启动时自动收集注解，运行时切面校验，**无需额外中间件**
- 管理员自动跳过校验
- 权限不足抛出 `PermissionDeniedException`

### 手动检查

```php
$employee->hasPermission('user:create');
$employee->hasRole('admin');

$permissionService = make(PermissionService::class);
$permissionService->hasPermission($employeeId, 'user:create');
$permissionService->setRolePermissions($roleId, [1, 2, 3]);
```

## 自定义模型

```php
// config/autoload/corp.php
return [
    'models' => [
        'employee' => \App\Model\Employee::class,
        'collaborator' => \App\Model\Collaborator::class,
    ],
];
```

```php
namespace App\Model;

use HPlus\Corp\Model\Employee as BaseEmployee;

class Employee extends BaseEmployee
{
    protected array $fillable = [
        ...parent::$fillable,
        'wechat_openid',
    ];
}
```

通过 `CorpManager` 获取模型类：

```php
use HPlus\Corp\CorpManager;

$employeeClass = CorpManager::employeeModel();
$collaboratorClass = CorpManager::collaboratorModel();
```

## 多部门支持

```php
// 设置主部门
$employee->setPrimaryDepartment($deptId);

// 添加到部门
$employee->addToDepartment($deptId);

// 从部门移除
$employee->removeFromDepartment($deptId);

// 获取所有部门ID
$ids = $employee->getAllDepartmentIds();

// 批量设置
$employeeService->setDepartments($employeeId, [1, 2, 3], primaryDepartmentId: 1);
```

## 模型缓存

所有模型支持 Hyperf 模型缓存：

```php
$corp = Corp::findFromCache($corpId);
$employees = Employee::findManyFromCache([1, 2, 3]);
```

## License

MIT
