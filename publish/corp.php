<?php

declare(strict_types=1);

return [
    // 模型类配置（支持用户重写）
    'models' => [
        'corp' => \HPlus\Corp\Model\Corp::class,
        'department' => \HPlus\Corp\Model\Department::class,
        'employee' => \HPlus\Corp\Model\Employee::class,
        'role' => \HPlus\Corp\Model\Role::class,
        'role_user' => \HPlus\Corp\Model\RoleUser::class,
        'permission' => \HPlus\Corp\Model\Permission::class,
        'role_permission' => \HPlus\Corp\Model\RolePermission::class,
    ],

    // 表名配置（支持自定义表前缀）
    'tables' => [
        'corps' => 'corps',
        'departments' => 'departments',
        'employees' => 'employees',
        'roles' => 'roles',
        'role_users' => 'role_users',
        'permissions' => 'permissions',
        'role_permissions' => 'role_permissions',
    ],
];

