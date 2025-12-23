<?php

declare(strict_types=1);

namespace HPlus\Corp\Event;

use Psr\EventDispatcher\EventDispatcherInterface;
use Hyperf\Context\ApplicationContext;

/**
 * 事件基类
 */
abstract class Event
{
    /**
     * 事件类型
     */
    public string $type;

    /**
     * 企业ID
     */
    public int $corpId = 0;

    /**
     * 触发时间
     */
    public int $timestamp;

    /**
     * 变更前数据
     */
    public array $before = [];

    /**
     * 变更后数据
     */
    public array $after = [];

    /**
     * 变更字段
     */
    public array $changed = [];

    /**
     * 额外数据
     */
    public array $extra = [];

    public function __construct(array $data = [])
    {
        $this->timestamp = time();
        
        foreach ($data as $key => $value) {
            $camelKey = $this->snakeToCamel($key);
            $setter = 'set' . ucfirst($camelKey);
            
            if (method_exists($this, $setter)) {
                $this->$setter($value);
            } elseif (property_exists($this, $camelKey)) {
                $this->$camelKey = $value;
            }
        }
    }

    /**
     * 转数组
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this as $key => $value) {
            $result[$this->camelToSnake($key)] = $value;
        }
        return $result;
    }

    /**
     * 派发事件
     */
    public static function dispatch(array $data): void
    {
        $event = new static($data);
        $container = ApplicationContext::getContainer();
        if ($container->has(EventDispatcherInterface::class)) {
            $container->get(EventDispatcherInterface::class)->dispatch($event);
        }
    }

    protected function snakeToCamel(string $snake): string
    {
        return lcfirst(str_replace('_', '', ucwords($snake, '_')));
    }

    protected function camelToSnake(string $camel): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $camel));
    }
}

