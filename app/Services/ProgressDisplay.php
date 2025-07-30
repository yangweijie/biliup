<?php

namespace App\Services;

class ProgressDisplay
{
    private int $totalFiles = 0;
    private int $currentFile = 0;
    private array $fileStatuses = [];
    private string $currentOperation = '';
    private int $startTime;

    public function __construct()
    {
        $this->startTime = time();
    }

    /**
     * 初始化进度显示
     */
    public function initialize(int $totalFiles): void
    {
        $this->totalFiles = $totalFiles;
        $this->currentFile = 0;
        $this->fileStatuses = [];
        
        $this->clearScreen();
        $this->showHeader();
    }

    /**
     * 更新当前文件进度
     */
    public function updateFile(int $fileIndex, string $fileName, string $status, string $operation = ''): void
    {
        $this->currentFile = $fileIndex;
        $this->currentOperation = $operation;
        $this->fileStatuses[$fileIndex] = [
            'name' => $fileName,
            'status' => $status,
            'operation' => $operation,
            'timestamp' => time()
        ];
        
        $this->refresh();
    }

    /**
     * 更新操作状态
     */
    public function updateOperation(string $operation): void
    {
        $this->currentOperation = $operation;
        $this->refresh();
    }

    /**
     * 标记文件完成
     */
    public function completeFile(int $fileIndex, bool $success, string $message = ''): void
    {
        if (isset($this->fileStatuses[$fileIndex])) {
            $this->fileStatuses[$fileIndex]['status'] = $success ? 'success' : 'failed';
            $this->fileStatuses[$fileIndex]['message'] = $message;
            $this->fileStatuses[$fileIndex]['completed_at'] = time();
        }
        
        $this->refresh();
    }

    /**
     * 刷新显示
     */
    private function refresh(): void
    {
        $this->clearScreen();
        $this->showHeader();
        $this->showProgress();
        $this->showCurrentOperation();
        $this->showFileList();
        $this->showStats();
    }

    /**
     * 清屏
     */
    private function clearScreen(): void
    {
        // 在 Windows 和 Unix 系统上清屏
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            system('cls');
        } else {
            system('clear');
        }
    }

    /**
     * 显示标题
     */
    private function showHeader(): void
    {
        echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
        echo "║                          🎬 Bilibili 自动投稿工具                           ║\n";
        echo "╚══════════════════════════════════════════════════════════════════════════════╝\n";
        echo "\n";
    }

    /**
     * 显示总体进度
     */
    private function showProgress(): void
    {
        $percentage = $this->totalFiles > 0 ? round(($this->currentFile / $this->totalFiles) * 100, 1) : 0;
        $progressBar = $this->generateProgressBar($percentage);
        
        echo "📊 总体进度: {$this->currentFile}/{$this->totalFiles} ({$percentage}%)\n";
        echo "   {$progressBar}\n";
        echo "\n";
    }

    /**
     * 生成进度条
     */
    private function generateProgressBar(float $percentage, int $width = 50): string
    {
        $filled = round(($percentage / 100) * $width);
        $empty = $width - $filled;
        
        $bar = '█' . str_repeat('█', $filled) . str_repeat('░', $empty) . '█';
        return $bar;
    }

    /**
     * 显示当前操作
     */
    private function showCurrentOperation(): void
    {
        if (!empty($this->currentOperation)) {
            echo "🔄 当前操作: {$this->currentOperation}\n";
            echo "\n";
        }
    }

    /**
     * 显示文件列表
     */
    private function showFileList(): void
    {
        echo "📁 文件处理状态:\n";
        echo "┌─────┬──────────────────────────────────────────┬──────────┬────────────────────┐\n";
        echo "│ #   │ 文件名                                   │ 状态     │ 操作               │\n";
        echo "├─────┼──────────────────────────────────────────┼──────────┼────────────────────┤\n";
        
        $displayCount = min(10, $this->totalFiles); // 只显示最近的10个文件
        $startIndex = max(0, $this->currentFile - $displayCount + 1);
        
        for ($i = $startIndex; $i < $startIndex + $displayCount && $i <= $this->currentFile; $i++) {
            if (isset($this->fileStatuses[$i])) {
                $file = $this->fileStatuses[$i];
                $status = $this->getStatusIcon($file['status']);
                $fileName = $this->truncateString($file['name'], 38);
                $operation = $this->truncateString($file['operation'], 18);
                
                printf("│ %-3d │ %-38s │ %-8s │ %-18s │\n", 
                    $i + 1, $fileName, $status, $operation);
            }
        }
        
        echo "└─────┴──────────────────────────────────────────┴──────────┴────────────────────┘\n";
        echo "\n";
    }

    /**
     * 获取状态图标
     */
    private function getStatusIcon(string $status): string
    {
        $icons = [
            'pending' => '⏳ 等待',
            'processing' => '🔄 处理中',
            'uploading' => '⬆️ 上传中',
            'success' => '✅ 成功',
            'failed' => '❌ 失败',
            'skipped' => '⏭️ 跳过',
        ];
        
        return $icons[$status] ?? '❓ 未知';
    }

    /**
     * 截断字符串
     */
    private function truncateString(string $str, int $length): string
    {
        if (mb_strlen($str) <= $length) {
            return $str;
        }
        
        return mb_substr($str, 0, $length - 3) . '...';
    }

    /**
     * 显示统计信息
     */
    private function showStats(): void
    {
        $stats = $this->calculateStats();
        $elapsed = time() - $this->startTime;
        $elapsedFormatted = $this->formatDuration($elapsed);
        
        echo "📈 统计信息:\n";
        echo "   ✅ 成功: {$stats['success']}   ❌ 失败: {$stats['failed']}   ⏳ 待处理: {$stats['pending']}\n";
        echo "   ⏱️ 已用时: {$elapsedFormatted}";
        
        if ($stats['success'] > 0 && $elapsed > 0) {
            $avgTime = round($elapsed / $stats['success'], 1);
            $remaining = $stats['pending'] * $avgTime;
            $remainingFormatted = $this->formatDuration($remaining);
            echo "   ⏰ 预计剩余: {$remainingFormatted}";
        }
        
        echo "\n";
    }

    /**
     * 计算统计信息
     */
    private function calculateStats(): array
    {
        $stats = [
            'success' => 0,
            'failed' => 0,
            'pending' => 0,
            'processing' => 0
        ];
        
        foreach ($this->fileStatuses as $file) {
            switch ($file['status']) {
                case 'success':
                    $stats['success']++;
                    break;
                case 'failed':
                    $stats['failed']++;
                    break;
                case 'processing':
                case 'uploading':
                    $stats['processing']++;
                    break;
                default:
                    $stats['pending']++;
                    break;
            }
        }
        
        $stats['pending'] += max(0, $this->totalFiles - count($this->fileStatuses));
        
        return $stats;
    }

    /**
     * 格式化持续时间
     */
    private function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;
        
        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        } else {
            return sprintf('%02d:%02d', $minutes, $seconds);
        }
    }

    /**
     * 显示最终结果
     */
    public function showFinalResult(): void
    {
        $stats = $this->calculateStats();
        $totalTime = time() - $this->startTime;
        
        $this->clearScreen();
        $this->showHeader();
        
        echo "🎉 上传任务完成!\n\n";
        
        echo "📊 最终统计:\n";
        echo "   总文件数: {$this->totalFiles}\n";
        echo "   ✅ 成功: {$stats['success']}\n";
        echo "   ❌ 失败: {$stats['failed']}\n";
        echo "   ⏱️ 总用时: " . $this->formatDuration($totalTime) . "\n";
        
        if ($this->totalFiles > 0) {
            $successRate = round(($stats['success'] / $this->totalFiles) * 100, 1);
            echo "   📈 成功率: {$successRate}%\n";
        }
        
        echo "\n";
        
        if ($stats['failed'] > 0) {
            echo "⚠️ 有 {$stats['failed']} 个文件上传失败，请检查日志文件了解详情。\n";
        }
        
        echo "📄 详细日志和截图已保存到相应目录。\n";
    }
}
