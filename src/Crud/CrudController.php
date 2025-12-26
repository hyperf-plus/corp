<?php

declare(strict_types=1);

namespace HPlus\Corp\Crud;

use HPlus\Corp\Annotation\Permission;
use HPlus\Corp\Context\CorpContext;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 通用 CRUD 控制器基类
 * 
 * 提供标准的 RESTful API 接口，支持：
 * - 列表、详情、创建、更新、删除
 * - 批量操作
 * - 权限控制
 * 
 * 使用方式：
 * ```php
 * class OrderController extends CrudController
 * {
 *     protected string $service = OrderService::class;
 *     protected string $permissionPrefix = 'order';
 * }
 * ```
 */
abstract class CrudController
{
    #[Inject]
    protected RequestInterface $request;

    /**
     * 服务类名（必须定义）
     */
    protected string $service;

    /**
     * 权限前缀（如 'order' 会生成 order.list, order.create 等）
     */
    protected string $permissionPrefix = '';

    /**
     * 服务实例缓存
     */
    private ?CrudService $serviceInstance = null;

    /**
     * 获取服务实例
     */
    protected function getService(): CrudService
    {
        if ($this->serviceInstance === null) {
            $this->serviceInstance = make($this->service);
        }
        return $this->serviceInstance;
    }

    /**
     * 列表
     */
    public function list(): array
    {
        $this->checkPermission('list');
        
        $params = $this->getListParams();
        $result = $this->getService()->list($params);
        
        return $this->success($result);
    }

    /**
     * 全部（不分页）
     */
    public function all(): array
    {
        $this->checkPermission('list');
        
        $params = $this->getListParams();
        $items = $this->getService()->all($params);
        
        return $this->success(['items' => $items]);
    }

    /**
     * 详情
     */
    public function detail(int $id): array
    {
        $this->checkPermission('detail');
        
        $result = $this->getService()->detail($id);
        
        if ($result === null) {
            return $this->error('数据不存在', 404);
        }
        
        return $this->success($result);
    }

    /**
     * 创建
     */
    public function create(): array
    {
        $this->checkPermission('create');
        
        $data = $this->getCreateData();
        $errors = $this->validateCreate($data);
        if ($errors) {
            return $this->error($errors, 422);
        }
        
        $model = $this->getService()->create($data);
        
        return $this->success($model->toArray(), '创建成功');
    }

    /**
     * 更新
     */
    public function update(int $id): array
    {
        $this->checkPermission('update');
        
        $data = $this->getUpdateData();
        $errors = $this->validateUpdate($id, $data);
        if ($errors) {
            return $this->error($errors, 422);
        }
        
        $model = $this->getService()->update($id, $data);
        
        if ($model === null) {
            return $this->error('数据不存在', 404);
        }
        
        return $this->success($model->toArray(), '更新成功');
    }

    /**
     * 删除
     */
    public function delete(int $id): array
    {
        $this->checkPermission('delete');
        
        $result = $this->getService()->delete($id);
        
        if (!$result) {
            return $this->error('删除失败', 400);
        }
        
        return $this->success(null, '删除成功');
    }

    /**
     * 批量删除
     */
    public function batchDelete(): array
    {
        $this->checkPermission('delete');
        
        $ids = $this->request->input('ids', []);
        if (empty($ids) || !is_array($ids)) {
            return $this->error('请选择要删除的数据', 422);
        }
        
        $count = $this->getService()->batchDelete($ids);
        
        return $this->success(['deleted' => $count], "成功删除 {$count} 条数据");
    }

    /**
     * 更新状态
     */
    public function updateStatus(int $id): array
    {
        $this->checkPermission('update');
        
        $status = (int) $this->request->input('status');
        $result = $this->getService()->updateStatus($id, $status);
        
        if (!$result) {
            return $this->error('更新失败', 400);
        }
        
        return $this->success(null, '更新成功');
    }

    /**
     * 批量更新状态
     */
    public function batchUpdateStatus(): array
    {
        $this->checkPermission('update');
        
        $ids = $this->request->input('ids', []);
        $status = (int) $this->request->input('status');
        
        if (empty($ids) || !is_array($ids)) {
            return $this->error('请选择数据', 422);
        }
        
        $count = $this->getService()->batchUpdateStatus($ids, $status);
        
        return $this->success(['updated' => $count], "成功更新 {$count} 条数据");
    }

    // ==================== 参数获取 ====================

    /**
     * 获取列表参数
     */
    protected function getListParams(): array
    {
        return array_filter($this->request->all(), fn($v) => $v !== null && $v !== '');
    }

    /**
     * 获取创建数据
     */
    protected function getCreateData(): array
    {
        return $this->request->all();
    }

    /**
     * 获取更新数据
     */
    protected function getUpdateData(): array
    {
        return $this->request->all();
    }

    // ==================== 验证（子类重写） ====================

    /**
     * 创建验证
     */
    protected function validateCreate(array $data): ?string
    {
        return null;
    }

    /**
     * 更新验证
     */
    protected function validateUpdate(int $id, array $data): ?string
    {
        return null;
    }

    // ==================== 权限检查 ====================

    /**
     * 检查权限
     */
    protected function checkPermission(string $action): void
    {
        // 管理员跳过
        if (CorpContext::isAdmin()) {
            return;
        }

        // 没有配置权限前缀，跳过
        if (empty($this->permissionPrefix)) {
            return;
        }

        $permission = "{$this->permissionPrefix}.{$action}";
        
        // 获取当前用户权限
        $employeeId = CorpContext::getEmployeeId();
        if (!$employeeId) {
            throw new \HPlus\Corp\Exception\PermissionDeniedException('请先登录');
        }

        // 检查权限（通过权限服务）
        $permissionService = make(\HPlus\Corp\Service\PermissionService::class);
        if (!$permissionService->hasPermission($employeeId, $permission)) {
            throw new \HPlus\Corp\Exception\PermissionDeniedException("没有权限: {$permission}");
        }
    }

    // ==================== 响应格式 ====================

    /**
     * 成功响应
     */
    protected function success(mixed $data = null, string $message = 'success'): array
    {
        return [
            'code' => 0,
            'message' => $message,
            'data' => $data,
        ];
    }

    /**
     * 错误响应
     */
    protected function error(string $message, int $code = 400): array
    {
        return [
            'code' => $code,
            'message' => $message,
            'data' => null,
        ];
    }
}

