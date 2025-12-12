<?php
declare(strict_types=1);

namespace Services\AI\Core;

use Services\AI\Providers\ProviderInterface;

/**
 * 模型路由器
 * 根据模型名称选择合适的 Provider
 */
class ModelRouter
{
    private array $providers = [];
    private string $defaultProvider = 'deepseek';

    /**
     * 注册一个 Provider
     *
     * @param string $name Provider 名称
     * @param ProviderInterface $provider Provider 实例
     */
    public function register(string $name, ProviderInterface $provider): void
    {
        $this->providers[$name] = $provider;
    }

    /**
     * 批量注册 Providers
     *
     * @param array $providers Provider 数组（name => provider）
     */
    public function registerMany(array $providers): void
    {
        foreach ($providers as $name => $provider) {
            if ($provider instanceof ProviderInterface) {
                $this->register($name, $provider);
            }
        }
    }

    /**
     * 设置默认 Provider
     *
     * @param string $name Provider 名称
     */
    public function setDefaultProvider(string $name): void
    {
        $this->defaultProvider = $name;
    }

    /**
     * 根据模型名称路由到合适的 Provider
     *
     * @param string $model 模型名称
     * @return ProviderInterface Provider 实例
     * @throws \RuntimeException 找不到合适的 Provider 时抛出
     */
    public function route(string $model): ProviderInterface
    {
        // 遍历所有 Provider，找到支持该模型的
        foreach ($this->providers as $provider) {
            if ($provider->supportsModel($model)) {
                return $provider;
            }
        }

        // 如果没找到，使用默认 Provider
        if (isset($this->providers[$this->defaultProvider])) {
            return $this->providers[$this->defaultProvider];
        }

        throw new \RuntimeException("找不到支持模型 '{$model}' 的 Provider");
    }

    /**
     * 获取 Provider（按名称）
     *
     * @param string $name Provider 名称
     * @return ProviderInterface|null Provider 实例
     */
    public function getProvider(string $name): ?ProviderInterface
    {
        return $this->providers[$name] ?? null;
    }

    /**
     * 检查 Provider 是否存在
     *
     * @param string $name Provider 名称
     */
    public function hasProvider(string $name): bool
    {
        return isset($this->providers[$name]);
    }

    /**
     * 获取所有 Provider 名称
     *
     * @return array Provider 名称列表
     */
    public function getProviderNames(): array
    {
        return array_keys($this->providers);
    }

    /**
     * 获取所有支持的模型
     *
     * @return array 模型列表（按 Provider 分组）
     */
    public function getAllSupportedModels(): array
    {
        $models = [];

        foreach ($this->providers as $name => $provider) {
            $models[$name] = $provider->getSupportedModels();
        }

        return $models;
    }

    /**
     * 检查是否支持某个模型
     *
     * @param string $model 模型名称
     * @return bool 是否支持
     */
    public function supportsModel(string $model): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->supportsModel($model)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 调用模型（自动路由）
     *
     * @param string $model 模型名称
     * @param array $messages 消息列表
     * @param array $options 选项
     * @return array 模型响应
     */
    public function chat(string $model, array $messages, array $options = []): array
    {
        $provider = $this->route($model);
        $options['model'] = $model;

        return $provider->chat($messages, $options);
    }

    /**
     * 流式调用模型（自动路由）
     *
     * @param string $model 模型名称
     * @param array $messages 消息列表
     * @param callable $callback 流式回调
     * @param array $options 选项
     */
    public function streamChat(string $model, array $messages, callable $callback, array $options = []): void
    {
        $provider = $this->route($model);
        $options['model'] = $model;

        $provider->streamChat($messages, $options, $callback);
    }
}
