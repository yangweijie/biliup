<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class FileScanner
{
    private string $scanDirectory;
    private string $processedFilesPath;
    private array $processedFiles = [];

    public function __construct()
    {
        $this->scanDirectory = env('SCAN_DIRECTORY', 'D:\git\php\musicbox\public\storage\music\4');
        $this->processedFilesPath = $this->getStoragePath('processed_files.json');
        $this->loadProcessedFiles();
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
     * 加载已处理文件列表
     */
    private function loadProcessedFiles(): void
    {
        if (file_exists($this->processedFilesPath)) {
            $content = file_get_contents($this->processedFilesPath);
            $this->processedFiles = json_decode($content, true) ?: [];
        }
    }

    /**
     * 保存已处理文件列表
     */
    private function saveProcessedFiles(): void
    {
        $dir = dirname($this->processedFilesPath);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents(
            $this->processedFilesPath, 
            json_encode($this->processedFiles, JSON_PRETTY_PRINT)
        );
    }

    /**
     * 扫描目录获取所有 MP4 文件
     */
    public function scanMp4Files(): array
    {
        if (!is_dir($this->scanDirectory)) {
            Log::error("扫描目录不存在: {$this->scanDirectory}");
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->scanDirectory)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'mp4') {
                $filePath = $file->getRealPath();
                
                // 检查文件大小，过滤 0 字节文件
                if ($file->getSize() > 0) {
                    $files[] = $filePath;
                }
            }
        }

        // 按文件名排序
        sort($files);
        
        // Log::info("扫描到 " . count($files) . " 个有效的 MP4 文件");
        return $files;
    }

    /**
     * 获取未处理的文件列表
     */
    public function getUnprocessedFiles(): array
    {
        $allFiles = $this->scanMp4Files();
        $unprocessedFiles = [];

        foreach ($allFiles as $file) {
            $fileHash = $this->getFileHash($file);
            if (!isset($this->processedFiles[$fileHash])) {
                $unprocessedFiles[] = $file;
            }
        }

        // Log::info("找到 " . count($unprocessedFiles) . " 个未处理的文件");
        return $unprocessedFiles;
    }

    /**
     * 标记文件为已处理
     */
    public function markAsProcessed(string $filePath, bool $success = true, string $message = ''): void
    {
        $fileHash = $this->getFileHash($filePath);
        $this->processedFiles[$fileHash] = [
            'file_path' => $filePath,
            'file_name' => basename($filePath),
            'processed_at' => date('Y-m-d H:i:s'),
            'success' => $success,
            'message' => $message,
            'file_size' => filesize($filePath),
        ];

        $this->saveProcessedFiles();
        
        $status = $success ? '成功' : '失败';
        // Log::info("文件标记为已处理 ({$status}): " . basename($filePath));
    }

    /**
     * 获取文件哈希值
     */
    private function getFileHash(string $filePath): string
    {
        return md5($filePath . filesize($filePath));
    }

    /**
     * 获取处理统计信息
     */
    public function getProcessingStats(): array
    {
        $total = count($this->processedFiles);
        $successful = 0;
        $failed = 0;

        foreach ($this->processedFiles as $file) {
            if ($file['success']) {
                $successful++;
            } else {
                $failed++;
            }
        }

        return [
            'total_processed' => $total,
            'successful' => $successful,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
        ];
    }

    /**
     * 重置处理记录
     */
    public function resetProcessedFiles(): void
    {
        $this->processedFiles = [];
        $this->saveProcessedFiles();
        // Log::info("已重置处理记录");
    }

    /**
     * 创建测试文件（用于测试循环上传）
     */
    public function createTestFiles(string $sourceFile, int $count = 3): array
    {
        if (!file_exists($sourceFile)) {
            throw new \Exception("源文件不存在: $sourceFile");
        }

        $testFiles = [];
        $sourceDir = dirname($sourceFile);
        $baseName = pathinfo($sourceFile, PATHINFO_FILENAME);
        $extension = pathinfo($sourceFile, PATHINFO_EXTENSION);

        // Log::info("开始创建测试文件", [
        //     'source_file' => $sourceFile,
        //     'count' => $count,
        //     'source_size' => filesize($sourceFile)
        // ]);

        for ($i = 1; $i <= $count; $i++) {
            $testFileName = "{$baseName}-test-{$i}.{$extension}";
            $testFilePath = $sourceDir . DIRECTORY_SEPARATOR . $testFileName;

            // 复制文件
            if (!copy($sourceFile, $testFilePath)) {
                // Log::error("复制文件失败: $testFilePath");
                continue;
            }

            // 创建一个简单的图片数据来改变文件内容
            $imageData = $this->createSimpleImageData($i);
            file_put_contents($testFilePath, $imageData, FILE_APPEND);

            $testFiles[] = $testFilePath;

            // Log::info("创建测试文件: " . basename($testFilePath), [
            //     'size' => filesize($testFilePath),
            //     'md5' => md5_file($testFilePath)
            // ]);
        }

        // Log::info("创建了 " . count($testFiles) . " 个测试文件");
        return $testFiles;
    }

    /**
     * 创建简单的图片数据以改变文件 MD5
     */
    private function createSimpleImageData(int $index): string
    {
        // 创建一个简单的 1x1 像素的 PNG 图片数据
        $width = $index;
        $height = $index;

        // PNG 文件头
        $pngHeader = "\x89PNG\r\n\x1a\n";

        // 简单的图片数据
        $imageData = str_repeat(chr($index % 256), $width * $height * 3);

        return $pngHeader . $imageData . str_repeat("\x00", $index * 100);
    }

    /**
     * 清理测试文件
     */
    public function cleanupTestFiles(): void
    {
        $allFiles = $this->scanMp4Files();
        $deletedCount = 0;

        foreach ($allFiles as $file) {
            $fileName = basename($file);
            if (strpos($fileName, '-test-') !== false) {
                unlink($file);
                $deletedCount++;
            }
        }

        // Log::info("清理了 {$deletedCount} 个测试文件");
    }

    /**
     * 获取扫描目录
     */
    public function getScanDirectory(): string
    {
        return $this->scanDirectory;
    }

    /**
     * 设置扫描目录
     */
    public function setScanDirectory(string $directory): void
    {
        $this->scanDirectory = $directory;
    }

    /**
     * 获取文件详细信息
     */
    public function getFileInfo(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $fileInfo = [
            'path' => $filePath,
            'name' => basename($filePath),
            'size' => filesize($filePath),
            'size_human' => $this->formatFileSize(filesize($filePath)),
            'extension' => strtolower(pathinfo($filePath, PATHINFO_EXTENSION)),
            'created_at' => date('Y-m-d H:i:s', filectime($filePath)),
            'modified_at' => date('Y-m-d H:i:s', filemtime($filePath)),
            'md5' => md5_file($filePath),
            'is_test_file' => strpos(basename($filePath), '-test-') !== false,
        ];

        // 尝试获取视频信息（如果可能）
        $fileInfo['video_info'] = $this->getVideoInfo($filePath);

        return $fileInfo;
    }

    /**
     * 格式化文件大小
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * 获取视频信息（简单版本）
     */
    private function getVideoInfo(string $filePath): array
    {
        $info = [
            'duration' => null,
            'resolution' => null,
            'bitrate' => null,
        ];

        // 这里可以集成 FFmpeg 或其他视频分析工具
        // 目前返回基本信息
        return $info;
    }

    /**
     * 验证文件是否为有效的 MP4
     */
    public function isValidMp4(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        // 检查文件扩展名
        if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'mp4') {
            return false;
        }

        // 检查文件大小
        if (filesize($filePath) === 0) {
            return false;
        }

        // 检查文件头（MP4 文件通常以 ftyp 开头）
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return false;
        }

        $header = fread($handle, 12);
        fclose($handle);

        // MP4 文件的魔术字节检查
        return strpos($header, 'ftyp') !== false;
    }

    /**
     * 获取目录统计信息
     */
    public function getDirectoryStats(): array
    {
        $allFiles = $this->scanMp4Files();
        $unprocessedFiles = $this->getUnprocessedFiles();

        $totalSize = 0;
        $validFiles = 0;
        $testFiles = 0;

        foreach ($allFiles as $file) {
            $size = filesize($file);
            $totalSize += $size;

            if ($this->isValidMp4($file)) {
                $validFiles++;
            }

            if (strpos(basename($file), '-test-') !== false) {
                $testFiles++;
            }
        }

        return [
            'scan_directory' => $this->scanDirectory,
            'total_files' => count($allFiles),
            'valid_files' => $validFiles,
            'test_files' => $testFiles,
            'unprocessed_files' => count($unprocessedFiles),
            'total_size' => $totalSize,
            'total_size_human' => $this->formatFileSize($totalSize),
            'directory_exists' => is_dir($this->scanDirectory),
            'directory_readable' => is_readable($this->scanDirectory),
        ];
    }

    /**
     * 移除已处理文件的记录
     */
    public function removeProcessedFile(string $filePath): void
    {
        $fileHash = $this->getFileHash($filePath);
        if (isset($this->processedFiles[$fileHash])) {
            unset($this->processedFiles[$fileHash]);
            $this->saveProcessedFiles();
            // Log::info("移除已处理文件记录: " . basename($filePath));
        }
    }

    /**
     * 检查文件是否已处理
     */
    public function isFileProcessed(string $filePath): bool
    {
        $fileHash = $this->getFileHash($filePath);
        return isset($this->processedFiles[$fileHash]);
    }

    /**
     * 获取已处理文件的详细信息
     */
    public function getProcessedFileInfo(string $filePath): ?array
    {
        $fileHash = $this->getFileHash($filePath);
        return $this->processedFiles[$fileHash] ?? null;
    }
}
