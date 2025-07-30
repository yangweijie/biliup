<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class RetryManager
{
    private int $maxAttempts;
    private int $retryDelay;
    private array $retryableExceptions;

    public function __construct()
    {
        $this->maxAttempts = env('BILIBILI_RETRY_ATTEMPTS', 3);
        $this->retryDelay = env('BILIBILI_RETRY_DELAY', 5);
        $this->retryableExceptions = [
            'timeout',
            'network',
            'connection',
            'server error',
            'service unavailable',
            'gateway timeout',
        ];
    }

    /**
     * 执行带重试的操作
     */
    public function execute(callable $operation, string $operationName = 'Operation', array $context = []): mixed
    {
        $attempt = 1;
        $lastException = null;

        while ($attempt <= $this->maxAttempts) {
            try {
                Log::info("执行操作: {$operationName} (尝试 {$attempt}/{$this->maxAttempts})", $context);
                
                $result = $operation();
                
                if ($attempt > 1) {
                    Log::info("操作成功 (重试后): {$operationName}", array_merge($context, [
                        'attempt' => $attempt,
                        'total_attempts' => $this->maxAttempts
                    ]));
                }
                
                return $result;
            } catch (\Exception $e) {
                $lastException = $e;
                $errorMessage = $e->getMessage();
                
                Log::warning("操作失败: {$operationName} (尝试 {$attempt}/{$this->maxAttempts})", array_merge($context, [
                    'error' => $errorMessage,
                    'attempt' => $attempt,
                    'is_retryable' => $this->isRetryableError($errorMessage)
                ]));

                // 检查是否应该重试
                if ($attempt >= $this->maxAttempts || !$this->isRetryableError($errorMessage)) {
                    break;
                }

                // 等待后重试
                $delay = $this->calculateDelay($attempt);
                Log::info("等待 {$delay} 秒后重试: {$operationName}");
                sleep($delay);
                
                $attempt++;
            }
        }

        // 所有尝试都失败了
        Log::error("操作最终失败: {$operationName}", array_merge($context, [
            'total_attempts' => $attempt - 1,
            'final_error' => $lastException->getMessage(),
            'trace' => $lastException->getTraceAsString()
        ]));

        throw $lastException;
    }

    /**
     * 检查错误是否可重试
     */
    private function isRetryableError(string $errorMessage): bool
    {
        $errorMessage = strtolower($errorMessage);
        
        foreach ($this->retryableExceptions as $retryablePattern) {
            if (strpos($errorMessage, $retryablePattern) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 计算重试延迟（指数退避）
     */
    private function calculateDelay(int $attempt): int
    {
        // 指数退避：基础延迟 * 2^(尝试次数-1)
        return $this->retryDelay * pow(2, $attempt - 1);
    }

    /**
     * 设置最大重试次数
     */
    public function setMaxAttempts(int $maxAttempts): self
    {
        $this->maxAttempts = $maxAttempts;
        return $this;
    }

    /**
     * 设置重试延迟
     */
    public function setRetryDelay(int $retryDelay): self
    {
        $this->retryDelay = $retryDelay;
        return $this;
    }

    /**
     * 添加可重试的异常模式
     */
    public function addRetryableException(string $pattern): self
    {
        $this->retryableExceptions[] = $pattern;
        return $this;
    }

    /**
     * 获取重试统计信息
     */
    public function getRetryStats(): array
    {
        return [
            'max_attempts' => $this->maxAttempts,
            'retry_delay' => $this->retryDelay,
            'retryable_exceptions' => $this->retryableExceptions,
        ];
    }

    /**
     * 执行带重试的文件上传操作
     */
    public function executeUpload(callable $uploadOperation, string $fileName, array $context = []): bool
    {
        try {
            return $this->execute($uploadOperation, "上传文件: {$fileName}", array_merge($context, [
                'file_name' => $fileName,
                'operation_type' => 'file_upload'
            ]));
        } catch (\Exception $e) {
            Log::error("文件上传最终失败: {$fileName}", array_merge($context, [
                'error' => $e->getMessage(),
                'operation_type' => 'file_upload'
            ]));
            return false;
        }
    }

    /**
     * 执行带重试的登录操作
     */
    public function executeLogin(callable $loginOperation, array $context = []): bool
    {
        try {
            return $this->execute($loginOperation, "用户登录", array_merge($context, [
                'operation_type' => 'login'
            ]));
        } catch (\Exception $e) {
            Log::error("登录最终失败", array_merge($context, [
                'error' => $e->getMessage(),
                'operation_type' => 'login'
            ]));
            return false;
        }
    }

    /**
     * 执行带重试的页面操作
     */
    public function executePageOperation(callable $pageOperation, string $operationName, array $context = []): mixed
    {
        return $this->execute($pageOperation, "页面操作: {$operationName}", array_merge($context, [
            'operation_type' => 'page_operation',
            'page_operation' => $operationName
        ]));
    }
}
