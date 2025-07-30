<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class UploadLogger
{
    private string $logFile;
    private string $sessionId;

    public function __construct()
    {
        $this->sessionId = date('Y-m-d_H-i-s') . '_' . uniqid();
        $this->logFile = $this->getStoragePath('logs/bilibili_upload_' . $this->sessionId . '.log');

        // 确保日志目录存在
        $logDir = dirname($this->logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $this->logSessionStart();
    }

    /**
     * 获取存储路径
     */
    private function getStoragePath(string $path = ''): string
    {
        $basePath = getcwd() . DIRECTORY_SEPARATOR . 'storage';
        return $path ? $basePath . DIRECTORY_SEPARATOR . $path : $basePath;
    }

    /**
     * 记录会话开始
     */
    private function logSessionStart(): void
    {
        $this->writeLog('INFO', '=== Bilibili 上传会话开始 ===', [
            'session_id' => $this->sessionId,
            'timestamp' => now()->toDateTimeString(),
            'scan_directory' => env('SCAN_DIRECTORY'),
            'category' => env('BILIBILI_CATEGORY'),
            'tags' => env('BILIBILI_TAGS'),
            'activity' => env('BILIBILI_ACTIVITY'),
        ]);
    }

    /**
     * 记录登录状态
     */
    public function logLogin(bool $success, string $method = 'cookie', string $message = ''): void
    {
        $level = $success ? 'INFO' : 'ERROR';
        $this->writeLog($level, "登录{$method}: " . ($success ? '成功' : '失败'), [
            'method' => $method,
            'success' => $success,
            'message' => $message,
        ]);
    }

    /**
     * 记录文件扫描结果
     */
    public function logFileScan(int $totalFiles, int $unprocessedFiles, array $files = []): void
    {
        $this->writeLog('INFO', '文件扫描完成', [
            'total_files' => $totalFiles,
            'unprocessed_files' => $unprocessedFiles,
            'files' => array_map('basename', $files),
        ]);
    }

    /**
     * 记录文件上传开始
     */
    public function logUploadStart(string $filePath, int $currentIndex, int $totalFiles): void
    {
        $this->writeLog('INFO', "开始上传文件 {$currentIndex}/{$totalFiles}", [
            'file_path' => $filePath,
            'file_name' => basename($filePath),
            'file_size' => filesize($filePath),
            'current_index' => $currentIndex,
            'total_files' => $totalFiles,
        ]);
    }

    /**
     * 记录上传进度
     */
    public function logUploadProgress(string $filePath, string $progress): void
    {
        $this->writeLog('INFO', "上传进度: {$progress}", [
            'file_name' => basename($filePath),
            'progress' => $progress,
        ]);
    }

    /**
     * 记录上传结果
     */
    public function logUploadResult(string $filePath, bool $success, string $message = '', array $details = []): void
    {
        $level = $success ? 'INFO' : 'ERROR';
        $status = $success ? '成功' : '失败';
        
        $this->writeLog($level, "文件上传{$status}: " . basename($filePath), array_merge([
            'file_path' => $filePath,
            'file_name' => basename($filePath),
            'success' => $success,
            'message' => $message,
        ], $details));
    }

    /**
     * 记录错误信息
     */
    public function logError(string $message, array $context = [], \Exception $exception = null): void
    {
        $logData = array_merge($context, [
            'message' => $message,
        ]);

        if ($exception) {
            $logData['exception'] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        $this->writeLog('ERROR', $message, $logData);
    }

    /**
     * 记录截图信息
     */
    public function logScreenshot(string $screenshotName, string $reason = ''): void
    {
        $this->writeLog('INFO', '截图已保存', [
            'screenshot_name' => $screenshotName,
            'reason' => $reason,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * 记录会话统计
     */
    public function logSessionStats(array $stats): void
    {
        $this->writeLog('INFO', '=== 会话统计信息 ===', $stats);
    }

    /**
     * 记录会话结束
     */
    public function logSessionEnd(array $finalStats = []): void
    {
        $this->writeLog('INFO', '=== Bilibili 上传会话结束 ===', array_merge([
            'session_id' => $this->sessionId,
            'end_timestamp' => now()->toDateTimeString(),
            'duration' => $this->getSessionDuration(),
        ], $finalStats));
    }

    /**
     * 写入日志
     */
    private function writeLog(string $level, string $message, array $context = []): void
    {
        $timestamp = now()->toDateTimeString();
        $logEntry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        // 写入文件
        $logLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);

        // 同时写入 Laravel 日志
        Log::channel('daily')->log(strtolower($level), $message, $context);

        // 输出到控制台
        $this->outputToConsole($level, $message, $context);
    }

    /**
     * 输出到控制台
     */
    private function outputToConsole(string $level, string $message, array $context = []): void
    {
        $timestamp = now()->format('H:i:s');
        $levelColors = [
            'INFO' => "\033[32m",    // 绿色
            'ERROR' => "\033[31m",   // 红色
            'WARNING' => "\033[33m", // 黄色
            'DEBUG' => "\033[36m",   // 青色
        ];

        $color = $levelColors[$level] ?? "\033[0m";
        $reset = "\033[0m";

        echo "{$color}[{$timestamp}] {$level}: {$message}{$reset}\n";

        // 如果有重要的上下文信息，也输出
        if (!empty($context) && in_array($level, ['ERROR', 'WARNING'])) {
            $contextStr = json_encode($context, JSON_UNESCAPED_UNICODE);
            echo "{$color}Context: {$contextStr}{$reset}\n";
        }
    }

    /**
     * 获取会话持续时间
     */
    private function getSessionDuration(): string
    {
        $startTime = filemtime($this->logFile);
        $duration = time() - $startTime;
        
        $hours = floor($duration / 3600);
        $minutes = floor(($duration % 3600) / 60);
        $seconds = $duration % 60;
        
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    /**
     * 获取日志文件路径
     */
    public function getLogFile(): string
    {
        return $this->logFile;
    }

    /**
     * 获取会话 ID
     */
    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * 获取最近的日志文件列表
     */
    public static function getRecentLogFiles(int $limit = 10): array
    {
        $logDir = getcwd() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        $files = glob($logDir . DIRECTORY_SEPARATOR . 'bilibili_upload_*.log');

        // 按修改时间排序
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        return array_slice($files, 0, $limit);
    }

    /**
     * 清理旧日志文件
     */
    public static function cleanupOldLogs(int $daysToKeep = 7): int
    {
        $logDir = getcwd() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        $files = glob($logDir . DIRECTORY_SEPARATOR . 'bilibili_upload_*.log');
        $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);
        $deletedCount = 0;

        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
                $deletedCount++;
            }
        }

        return $deletedCount;
    }
}
