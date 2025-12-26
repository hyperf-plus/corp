<?php

declare(strict_types=1);

namespace HPlus\Corp\Crud\Traits;

use HPlus\Corp\Context\CorpContext;
use HPlus\Corp\Crud\CrudService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;

/**
 * CRUD Trait - 用于在现有控制器中添加 CRUD 功能
 * 
 * 使用方式：
 * ```php
 * class OrderController extends AbstractController
 * {
 *     use HasCrud;
 *     
 *     protected string $crudService = OrderService::class;
 *     protected string $permissionPrefix = 'order';
 * }
 * ```
 */
trait HasCrud
{
    #[Inject]
    protected RequestInterface $request;

    /**
     * 服务类名
     */
    protected string $crudService;

    /**
     * 权限前缀
     */
    protected string $permissionPrefix = '';

    /**
     * 服务实例缓存
     */
    private ?CrudService $crudServiceInstance = null;

    /**
     * 获取 CRUD 服务
     */
    protected function crud(): CrudService
    {
        if ($this->crudServiceInstance === null) {
            $this->crudServiceInstance = make($this->crudService);
        }
        return $this->crudServiceInstance;
    }

    /**
     * 列表
     */
    public function list(): array
    {
        $this->checkCrudPermission('list');
        
        $params = $this->getCrudParams();
        $result = $this->crud()->list($params);
        
        return $this->crudSuccess($result);
    }

    /**
     * 全部
     */
    public function all(): array
    {
        $this->checkCrudPermission('list');
        
        $params = $this->getCrudParams();
        $items = $this->crud()->all($params);
        
        return $this->crudSuccess(['items' => $items]);
    }

    /**
     * 详情
     */
    public function detail(int $id): array
    {
        $this->checkCrudPermission('detail');
        
        $result = $this->crud()->detail($id);
        
        return $result 
            ? $this->crudSuccess($result) 
            : $this->crudError('数据不存在', 404);
    }

    /**
     * 创建
     */
    public function create(): array
    {
        $this->checkCrudPermission('create');
        
        $data = $this->request->all();
        $model = $this->crud()->create($data);
        
        return $this->crudSuccess($model->toArray(), '创建成功');
    }

    /**
     * 更新
     */
    public function update(int $id): array
    {
        $this->checkCrudPermission('update');
        
        $data = $this->request->all();
        $model = $this->crud()->update($id, $data);
        
        return $model 
            ? $this->crudSuccess($model->toArray(), '更新成功')
            : $this->crudError('数据不存在', 404);
    }

    /**
     * 删除
     */
    public function delete(int $id): array
    {
        $this->checkCrudPermission('delete');
        
        $result = $this->crud()->delete($id);
        
        return $result 
            ? $this->crudSuccess(null, '删除成功')
            : $this->crudError('删除失败', 400);
    }

    /**
     * 批量删除
     */
    public function batchDelete(): array
    {
        $this->checkCrudPermission('delete');
        
        $ids = $this->request->input('ids', []);
        $count = $this->crud()->batchDelete($ids);
        
        return $this->crudSuccess(['deleted' => $count], "成功删除 {$count} 条");
    }

    /**
     * 获取查询参数
     */
    protected function getCrudParams(): array
    {
        return array_filter($this->request->all(), fn($v) => $v !== null && $v !== '');
    }

    /**
     * 检查权限
     */
    protected function checkCrudPermission(string $action): void
    {
        if (CorpContext::isAdmin() || empty($this->permissionPrefix)) {
            return;
        }

        $permission = "{$this->permissionPrefix}.{$action}";
        $employeeId = CorpContext::getEmployeeId();
        
        if (!$employeeId) {
            throw new \HPlus\Corp\Exception\PermissionDeniedException('请先登录');
        }

        $permissionService = make(\HPlus\Corp\Service\PermissionService::class);
        if (!$permissionService->hasPermission($employeeId, $permission)) {
            throw new \HPlus\Corp\Exception\PermissionDeniedException("没有权限: {$permission}");
        }
    }

    /**
     * 成功响应
     */
    protected function crudSuccess(mixed $data = null, string $message = 'success'): array
    {
        return ['code' => 0, 'message' => $message, 'data' => $data];
    }

    /**
     * 错误响应
     */
    protected function crudError(string $message, int $code = 400): array
    {
        return ['code' => $code, 'message' => $message, 'data' => null];
    }
}

