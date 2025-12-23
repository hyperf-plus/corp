# HPlus Corp - Hyperf 组织架构包

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue)](https://php.net)
[![Hyperf Version](https://img.shields.io/badge/hyperf-%7E3.1.0-green)](https://hyperf.io)
[![License](https://img.shields.io/badge/license-MIT-brightgreen)](LICENSE)

Hyperf 组织架构基础包，提供企业、部门、员工、角色等核心模型和服务。

## 特性

- 📦 **开箱即用** - 完整的组织架构数据模型
- 🚀 **高性能** - 内置 Hyperf 模型缓存
- 🌳 **树形结构** - 部门支持物化路径，高效子树查询
- 🔒 **数据隔离** - 自动按企业/部门/员工过滤数据
- 🔐 **权限系统** - 完整的 RBAC 权限管理
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

### 4. 使用服务

```php
use HPlus\Corp\Service\DepartmentService;
use HPlus\Corp\Service\EmployeeService;

// 获取部门树
$departmentService = make(DepartmentService::class);
$tree = $departmentService->getTree($corpId);

// 获取部门及子部门下的员工
$employeeService = make(EmployeeService::class);
$employees = $employeeService->getByDepartmentWithChildren($departmentId);

// 分配角色
$employeeService->assignRoles($employeeId, [1, 2, 3]);
```

## 数据模型

### 企业 (Corp)
| 字段 | 类型 | 说明 |
|------|------|------|
| corp_id | bigint | 企业ID |
| name | string | 企业名称 |
| corp_code | string | 企业编码（唯一） |
| domain | string | 企业域名 |
| icon_url | string | 企业图标 |
| desc | text | 企业描述 |
| status | tinyint | 状态：0-禁用，1-启用 |

### 部门 (Department)
| 字段 | 类型 | 说明 |
|------|------|------|
| department_id | bigint | 部门ID |
| corp_id | bigint | 企业ID |
| name | string | 部门名称 |
| parent_id | bigint | 上级部门ID |
| full_path | string | 物化路径，如 /1/2/3/ |
| level | smallint | 层级深度 |
| order | int | 排序 |
| supervisor_id | bigint | 负责人ID |
| employee_count | int | 员工数量（缓存） |

### 员工 (Employee)
| 字段 | 类型 | 说明 |
|------|------|------|
| employee_id | bigint | 员工ID |
| corp_id | bigint | 企业ID |
| department_id | bigint | 主部门ID |
| department_ids | json | 所属部门ID列表（支持多部门） |
| name | string | 姓名 |
| mobile | string | 手机号 |
| email | string | 邮箱 |
| employee_number | string | 员工号 |
| avatar | string | 头像 |
| position | string | 职位 |
| gender | tinyint | 性别 |
| status | tinyint | 状态 |
| is_admin | tinyint | 是否管理员 |

### 角色 (Role)
| 字段 | 类型 | 说明 |
|------|------|------|
| role_id | bigint | 角色ID |
| corp_id | bigint | 企业ID |
| role_name | string | 角色名称 |
| slug | string | 角色标识 |
| auth_range | tinyint | 数据权限范围：1-仅本人，2-本部门，3-本部门及下属，4-全部 |
| status | tinyint | 状态 |

### 权限 (Permission)
| 字段 | 类型 | 说明 |
|------|------|------|
| permission_id | bigint | 权限ID |
| name | string | 权限名称 |
| slug | string | 权限标识（唯一） |
| parent_id | bigint | 父级ID |
| app_code | string | 应用编码 |
| order | int | 排序 |

## 查询作用域

```php
// 部门
Department::query()->root()->get();           // 顶级部门
Department::query()->enabled()->get();        // 启用的部门
Department::query()->ordered()->get();        // 按排序查询
Department::query()->descendantsOf($id)->get(); // 获取子树

// 员工
Employee::query()->active()->get();           // 在职员工
Employee::query()->ofDepartment($id)->get();  // 按主部门
Employee::query()->inDepartment($id)->get();  // 按所属部门（含兼职）
Employee::query()->inDepartments([1,2])->get(); // 按多部门
Employee::query()->admin()->get();            // 管理员

// 角色
Role::query()->enabled()->get();              // 启用的角色
Role::query()->ordered()->get();              // 按排序查询

// 权限
$permissionService = make(PermissionService::class);
$tree = $permissionService->getTree();        // 权限树
$permissionService->setRolePermissions($roleId, [1, 2, 3]); // 设置角色权限
```

## 数据范围过滤

### 快速使用

**1. 注册中间件**

```php
// config/autoload/middlewares.php
return [
    'http' => [
        \HPlus\Corp\Middleware\CorpContextMiddleware::class,
    ],
];
```

**2. 模型引入 Trait**

```php
use HPlus\Corp\Model\Concern\HasDataScope;

class Order extends Model
{
    use HasDataScope;  // 自动按数据范围过滤
    
    protected array $fillable = ['corp_id', 'employee_id', 'department_id', ...];
}
```

**3. 自动生效**

```php
// 查询时自动根据当前用户的角色 auth_range 过滤数据：
// - 1: 仅本人 → WHERE employee_id = 当前员工
// - 2: 本部门 → WHERE department_id = 当前部门
// - 3: 本部门及下属 → WHERE department_id IN (部门及子部门)
// - 4: 全部 → 不额外过滤

$orders = Order::query()->get();  // 自动过滤

// 创建时自动注入 corp_id、employee_id、department_id
Order::create(['amount' => 100]);  // 自动填充上下文字段
```

### 手动设置上下文

```php
use HPlus\Corp\Context\CorpContext;

// 设置上下文
CorpContext::set(
    corpId: 1,
    employeeId: 100,
    departmentId: 10,
    authRange: 3,
    isAdmin: false
);

// 单独设置
CorpContext::setCorpId(1);
CorpContext::setAuthRange(3);

// 获取
$corpId = CorpContext::getCorpId();
$authRange = CorpContext::getAuthRange();
```

### 跳过数据范围过滤

```php
// 方式1：查询时跳过
Order::withoutDataScope()->get();

// 方式2：回调中跳过
Order::skipDataScope(function () {
    return Order::query()->get();
});

// 方式3：全局跳过
CorpContext::skipDataScope();
// ... 执行查询
CorpContext::restoreDataScope();
```

### 仅企业隔离（不过滤数据权限）

```php
use HPlus\Corp\Model\Concern\HasCorpScope;

class Config extends Model
{
    use HasCorpScope;  // 只按 corp_id 过滤，不按数据权限
}
```

## 自定义模型

支持用户重写模型，在配置文件中指定自定义模型类：

```php
// config/autoload/corp.php
return [
    'models' => [
        'corp' => \App\Model\Corp::class,           // 自定义企业模型
        'department' => \App\Model\Department::class,
        'employee' => \App\Model\Employee::class,   // 自定义员工模型
        'role' => \App\Model\Role::class,
        'role_user' => \App\Model\RoleUser::class,
    ],
];
```

自定义模型继承基础模型即可：

```php
namespace App\Model;

use HPlus\Corp\Model\Employee as BaseEmployee;

class Employee extends BaseEmployee
{
    // 添加自定义字段
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

// 或者
$employee = CorpManager::make('employee');
```

## 多部门支持

员工支持加入多个部门，主部门只有一个：

```php
// 设置主部门（自动加入部门列表）
$employee->setPrimaryDepartment($deptId);

// 添加到部门
$employee->addToDepartment($deptId);

// 从部门移除
$employee->removeFromDepartment($deptId);

// 获取所有部门
$departments = $employee->departments();

// 获取所有部门ID
$ids = $employee->getAllDepartmentIds();

// 批量设置部门
$employeeService->setDepartments($employeeId, [1, 2, 3], primaryDepartmentId: 1);
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

    // 多个权限用逗号分隔，满足任一即可
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

- 启动时自动收集注解，运行时切面校验，**无需额外中间件**
- 管理员（`CorpContext::isAdmin() = true`）自动跳过
- 权限不足抛出 `PermissionDeniedException`（403）

### 手动检查

```php
// 员工模型方法
$employee->hasPermission('user:create');      // 检查单个权限
$employee->hasAnyPermission(['user:create', 'user:edit']); // 检查任一权限
$employee->getPermissionSlugs();              // 获取所有权限标识
$employee->getAuthRange();                    // 获取数据权限范围

// 权限服务
$permissionService = make(PermissionService::class);
$permissionService->hasPermission($employeeId, 'user:create');
$permissionService->getEmployeePermissionIds($employeeId);
$permissionService->setRolePermissions($roleId, [1, 2, 3]);
```

## 模型缓存

所有模型默认支持 Hyperf 模型缓存：

```php
// 从缓存获取
$corp = Corp::findFromCache($corpId);
$employees = Employee::findManyFromCache([1, 2, 3]);
```

## License

MIT

