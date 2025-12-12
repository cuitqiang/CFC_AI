<?php
declare(strict_types=1);

namespace Services\AI\Providers;

/**
 * AI 提供者接口
 * 定义所有 AI 模型提供者必须实现的方法
 */
interface ProviderInterface
{
    /**
     * 发送聊天请求
     *
     * @param array $messages 消息列表
     * @param array $options 请求选项（model, temperature, tools等）
     * @return array 模型响应
     */
    public function chat(array $messages, array $options = []): array;

    /**
     * 流式聊天请求
     *
     * @param array $messages 消息列表
     * @param array $options 请求选项
     * @param callable $callback 流式回调函数
     * @return void
     */
    public function streamChat(array $messages, array $options, callable $callback): void;

    /**
     * 计算消息的 token 数量
     *
     * @param array $messages 消息列表
     * @return int Token 数量
     */
    public function countTokens(array $messages): int;

    /**
     * 获取提供者名称
     *
     * @return string 提供者名称
     */
    public function getName(): string;

    /**
     * 获取支持的模型列表
     *
     * @return array 模型列表
     */
    public function getSupportedModels(): array;

    /**
     * 检查是否支持某个模型
     *
     * @param string $model 模型名称
     * @return bool 是否支持
     */
    public function supportsModel(string $model): bool;
}
