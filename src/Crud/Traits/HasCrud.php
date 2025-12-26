<?php

declare(strict_types=1);

namespace HPlus\Corp\Crud\Traits;

use HPlus\Corp\Crud\CrudService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;

/**
 * CRUD Trait - 配合 hyperf-plus/route + hyperf-plus/validate 使用
 * 
 * 使用方式：
 * ```php
 * use HPlus\Route\Annotation\ApiController;
 * use HPlus\Route\Annotation\GetApi;
 * use HPlus\Route\Annotation\PostApi;
 * use HPlus\Validate\Annotations\RequestValidation;
 * use HPlus\Corp\Annotation\Permission;
 * 
 * #[ApiController(prefix: '/api/orders')]
 * class OrderController
 * {
 *     use HasCrud;
 *     
 *     protected string $service = OrderService::class;
 *     
 *     #[GetApi]
 *     #[Permission('order.list')]
 *     public function list() { return $this->handleList(); }
 *     
 *     #[GetApi(path: '/{id}')]
 *     #[Permission('order.detail')]
 *     public function detail(int $id) { return $this->handleDetail($id); }
 *     
 *     #[PostApi]
 *     #[Permission('order.create')]
 *     #[RequestValidation(rules: ['name' => 'required'])]
 *     public function create() { return $this->handleCreate(); }
 *     
 *     #[PostApi(path: '/{id}')]
 *     #[Permission('order.update')]
 *     #[RequestValidation(rules: ['name' => 'required'])]
 *     public function update(int $id) { return $this->handleUpdate($id); }
 *     
 *     #[PostApi(path: '/{id}/delete')]
 *     #[Permission('order.delete')]
 *     public function delete(int $id) { return $this->handleDelete($id); }
 * }
 * ```
 */
trait HasCrud
{
    #[Inject]
    protected RequestInterface $request;

    /**
     * 服务类名（子类必须定义）
     */
    protected string $service;

    /**
     * 服务实例缓存
     */
    private ?CrudService $serviceInstance = null;

    /**
     * 获取服务实例
     */
    protected function crud(): CrudService
    {
        return $this->serviceInstance ??= make($this->service);
    }

    // ==================== CRUD 处理方法 ====================

    /**
     * 处理列表
     */
    protected function handleList(): array
    {
        $params = $this->getParams();
        return $this->ok($this->crud()->list($params));
    }

    /**
     * 处理全部（不分页）
     */
    protected function handleAll(): array
    {
        $params = $this->getParams();
        return $this->ok(['items' => $this->crud()->all($params)]);
    }

    /**
     * 处理详情
     */
    protected function handleDetail(int $id): array
    {
        $result = $this->crud()->detail($id);
        return $result ? $this->ok($result) : $this->fail('数据不存在', 404);
    }

    /**
     * 处理创建
     */
    protected function handleCreate(): array
    {
        $data = $this->request->all();
        $model = $this->crud()->create($data);
        return $this->ok($model->toArray(), '创建成功');
    }

    /**
     * 处理更新
     */
    protected function handleUpdate(int $id): array
    {
        $data = $this->request->all();
        $model = $this->crud()->update($id, $data);
        return $model ? $this->ok($model->toArray(), '更新成功') : $this->fail('数据不存在', 404);
    }

    /**
     * 处理删除
     */
    protected function handleDelete(int $id): array
    {
        return $this->crud()->delete($id) 
            ? $this->ok(null, '删除成功') 
            : $this->fail('删除失败');
    }

    /**
     * 处理批量删除
     */
    protected function handleBatchDelete(): array
    {
        $ids = $this->request->input('ids', []);
        if (empty($ids) || !is_array($ids)) {
            return $this->fail('请选择数据', 422);
        }
        $count = $this->crud()->batchDelete($ids);
        return $this->ok(['deleted' => $count], "删除 {$count} 条");
    }

    /**
     * 处理更新状态
     */
    protected function handleUpdateStatus(int $id): array
    {
        $status = (int) $this->request->input('status');
        return $this->crud()->updateStatus($id, $status) 
            ? $this->ok(null, '更新成功') 
            : $this->fail('更新失败');
    }

    /**
     * 处理批量更新状态
     */
    protected function handleBatchUpdateStatus(): array
    {
        $ids = $this->request->input('ids', []);
        $status = (int) $this->request->input('status');
        if (empty($ids) || !is_array($ids)) {
            return $this->fail('请选择数据', 422);
        }
        $count = $this->crud()->batchUpdateStatus($ids, $status);
        return $this->ok(['updated' => $count], "更新 {$count} 条");
    }

    // ==================== 辅助方法 ====================

    /**
     * 获取查询参数
     */
    protected function getParams(): array
    {
        return array_filter($this->request->all(), fn($v) => $v !== null && $v !== '');
    }

    /**
     * 成功响应
     */
    protected function ok(mixed $data = null, string $message = 'success'): array
    {
        return ['code' => 0, 'message' => $message, 'data' => $data];
    }

    /**
     * 失败响应
     */
    protected function fail(string $message = 'error', int $code = 400): array
    {
        return ['code' => $code, 'message' => $message, 'data' => null];
    }
}
