<?php
declare(strict_types=1);

namespace Services\AI\Pipeline;

/**
 * 管道上下文对象
 * 在管道各个阶段（Pipes）之间传递数据
 */
class PipelineContext
{
    private string $userInput;
    private array $messages = [];
    private array $tools = [];
    private array $memory = [];
    private ?array $modelResponse = null;
    private ?array $toolResults = null;
    private array $metadata = [];
    private bool $shouldContinue = true;
    private ?string $errorMessage = null;
    private array $executionLog = [];

    public function __construct(string $userInput, array $metadata = [])
    {
        $this->userInput = $userInput;
        $this->metadata = $metadata;
        $this->messages[] = [
            'role' => 'user',
            'content' => $userInput
        ];
    }

    public function getUserInput(): string
    {
        return $this->userInput;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function addMessage(string $role, string $content): void
    {
        $this->messages[] = [
            'role' => $role,
            'content' => $content
        ];
    }

    public function setMessages(array $messages): void
    {
        $this->messages = $messages;
    }

    public function getTools(): array
    {
        return $this->tools;
    }

    public function setTools(array $tools): void
    {
        $this->tools = $tools;
    }

    public function getMemory(): array
    {
        return $this->memory;
    }

    public function setMemory(array $memory): void
    {
        $this->memory = $memory;
    }

    public function getModelResponse(): ?array
    {
        return $this->modelResponse;
    }

    public function setModelResponse(?array $response): void
    {
        $this->modelResponse = $response;
    }

    public function getToolResults(): ?array
    {
        return $this->toolResults;
    }

    public function setToolResults(?array $results): void
    {
        $this->toolResults = $results;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function shouldContinue(): bool
    {
        return $this->shouldContinue;
    }

    public function stop(string $reason = ''): void
    {
        $this->shouldContinue = false;
        if ($reason) {
            $this->errorMessage = $reason;
        }
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function logExecution(string $step, string $message): void
    {
        $this->executionLog[] = [
            'step' => $step,
            'message' => $message,
            'timestamp' => microtime(true)
        ];
    }

    public function getExecutionLog(): array
    {
        return $this->executionLog;
    }
}
