<?php

namespace App\Services;

use Laravel\Dusk\Browser;
use Illuminate\Support\Facades\Log;

class ExceptionHandler
{
    private UploadLogger $logger;
    private string $screenshotPath;

    public function __construct(UploadLogger $logger)
    {
        $this->logger = $logger;
        $this->screenshotPath = 'tests/Browser/screenshots';
    }

    /**
     * 处理上传异常
     */
    public function handleUploadException(\Exception $e, Browser $browser, string $filePath, string $context = ''): array
    {
        $fileName = basename($filePath);
        $errorType = $this->classifyError($e->getMessage());
        
        $errorInfo = [
            'file_path' => $filePath,
            'file_name' => $fileName,
            'error_type' => $errorType,
            'error_message' => $e->getMessage(),
            'context' => $context,
            'timestamp' => now()->toDateTimeString(),
            'is_recoverable' => $this->isRecoverableError($errorType),
            'suggested_action' => $this->getSuggestedAction($errorType),
        ];

        // 记录错误日志
        $this->logger->logError("上传异常: {$fileName}", $errorInfo, $e);

        // 保存错误截图
        $screenshotName = $this->saveErrorScreenshot($browser, $fileName, $errorType);
        $errorInfo['screenshot'] = $screenshotName;

        // 保存页面源码（如果需要）
        if ($this->shouldSavePageSource($errorType)) {
            $sourceName = $this->savePageSource($browser, $fileName, $errorType);
            $errorInfo['page_source'] = $sourceName;
        }

        // 保存控制台日志
        $consoleLogs = $this->getConsoleLogs($browser);
        if (!empty($consoleLogs)) {
            $errorInfo['console_logs'] = $consoleLogs;
        }

        return $errorInfo;
    }

    /**
     * 分类错误类型
     */
    private function classifyError(string $errorMessage): string
    {
        $errorMessage = strtolower($errorMessage);

        $errorPatterns = [
            'network' => ['network', 'connection', 'timeout', 'dns', 'unreachable'],
            'authentication' => ['login', 'auth', 'unauthorized', 'forbidden', 'cookie'],
            'file' => ['file not found', 'invalid file', 'file size', 'format'],
            'upload' => ['upload failed', 'upload timeout', 'upload error'],
            'page' => ['element not found', 'selector', 'wait', 'click'],
            'server' => ['server error', '500', '502', '503', '504'],
            'browser' => ['browser', 'driver', 'session', 'webdriver'],
            'validation' => ['validation', 'required', 'invalid input'],
        ];

        foreach ($errorPatterns as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($errorMessage, $pattern) !== false) {
                    return $type;
                }
            }
        }

        return 'unknown';
    }

    /**
     * 检查错误是否可恢复
     */
    private function isRecoverableError(string $errorType): bool
    {
        $recoverableTypes = ['network', 'upload', 'page', 'server'];
        return in_array($errorType, $recoverableTypes);
    }

    /**
     * 获取建议的处理方案
     */
    private function getSuggestedAction(string $errorType): string
    {
        $suggestions = [
            'network' => '检查网络连接，稍后重试',
            'authentication' => '重新登录，检查账号状态',
            'file' => '检查文件格式和大小，确保文件有效',
            'upload' => '重试上传，检查文件大小限制',
            'page' => '刷新页面，检查页面元素是否变化',
            'server' => '服务器错误，稍后重试',
            'browser' => '重启浏览器，检查驱动程序',
            'validation' => '检查输入数据，修正验证错误',
            'unknown' => '未知错误，查看详细日志',
        ];

        return $suggestions[$errorType] ?? $suggestions['unknown'];
    }

    /**
     * 保存错误截图
     */
    private function saveErrorScreenshot(Browser $browser, string $fileName, string $errorType): ?string
    {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $screenshotName = "error_{$errorType}_{$fileName}_{$timestamp}";
            
            // 确保截图目录存在
            if (!file_exists($this->screenshotPath)) {
                mkdir($this->screenshotPath, 0755, true);
            }
            
            $browser->screenshot($screenshotName);
            $this->logger->logScreenshot($screenshotName, "错误截图: {$errorType}");
            
            return $screenshotName;
        } catch (\Exception $e) {
            Log::error("保存错误截图失败", [
                'error' => $e->getMessage(),
                'file_name' => $fileName,
                'error_type' => $errorType
            ]);
            return null;
        }
    }

    /**
     * 检查是否应该保存页面源码
     */
    private function shouldSavePageSource(string $errorType): bool
    {
        $typesNeedingSource = ['page', 'validation', 'unknown'];
        return in_array($errorType, $typesNeedingSource);
    }

    /**
     * 保存页面源码
     */
    private function savePageSource(Browser $browser, string $fileName, string $errorType): ?string
    {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $sourceName = "source_{$errorType}_{$fileName}_{$timestamp}.html";
            $sourcePath = "tests/Browser/source/{$sourceName}";
            
            // 确保源码目录存在
            $sourceDir = dirname($sourcePath);
            if (!file_exists($sourceDir)) {
                mkdir($sourceDir, 0755, true);
            }
            
            $pageSource = $browser->driver->getPageSource();
            file_put_contents($sourcePath, $pageSource);
            
            Log::info("页面源码已保存", [
                'source_file' => $sourceName,
                'file_name' => $fileName,
                'error_type' => $errorType
            ]);
            
            return $sourceName;
        } catch (\Exception $e) {
            Log::error("保存页面源码失败", [
                'error' => $e->getMessage(),
                'file_name' => $fileName,
                'error_type' => $errorType
            ]);
            return null;
        }
    }

    /**
     * 获取控制台日志
     */
    private function getConsoleLogs(Browser $browser): array
    {
        try {
            $logs = $browser->driver->manage()->getLog('browser');
            $filteredLogs = [];
            
            foreach ($logs as $log) {
                // 只保留错误和警告级别的日志
                if (in_array($log['level'], ['SEVERE', 'WARNING'])) {
                    $filteredLogs[] = [
                        'level' => $log['level'],
                        'message' => $log['message'],
                        'timestamp' => $log['timestamp'],
                    ];
                }
            }
            
            return $filteredLogs;
        } catch (\Exception $e) {
            Log::warning("获取控制台日志失败", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * 处理登录异常
     */
    public function handleLoginException(\Exception $e, Browser $browser, string $context = ''): array
    {
        $errorType = $this->classifyError($e->getMessage());
        
        $errorInfo = [
            'error_type' => $errorType,
            'error_message' => $e->getMessage(),
            'context' => $context,
            'timestamp' => now()->toDateTimeString(),
            'is_recoverable' => $this->isRecoverableError($errorType),
            'suggested_action' => $this->getSuggestedAction($errorType),
        ];

        // 记录错误日志
        $this->logger->logError("登录异常", $errorInfo, $e);

        // 保存错误截图
        $screenshotName = $this->saveErrorScreenshot($browser, 'login', $errorType);
        $errorInfo['screenshot'] = $screenshotName;

        return $errorInfo;
    }

    /**
     * 生成错误报告
     */
    public function generateErrorReport(array $errors): string
    {
        $report = "=== 错误报告 ===\n";
        $report .= "生成时间: " . now()->toDateTimeString() . "\n";
        $report .= "错误总数: " . count($errors) . "\n\n";

        $errorsByType = [];
        foreach ($errors as $error) {
            $type = $error['error_type'] ?? 'unknown';
            $errorsByType[$type][] = $error;
        }

        foreach ($errorsByType as $type => $typeErrors) {
            $report .= "=== {$type} 错误 (" . count($typeErrors) . " 个) ===\n";
            foreach ($typeErrors as $error) {
                $report .= "- " . ($error['file_name'] ?? 'N/A') . ": " . $error['error_message'] . "\n";
                $report .= "  建议: " . $error['suggested_action'] . "\n";
                if (isset($error['screenshot'])) {
                    $report .= "  截图: " . $error['screenshot'] . "\n";
                }
                $report .= "\n";
            }
        }

        return $report;
    }
}
