<?php

namespace Tests\Unit;

use App\Services\FileScanner;
use PHPUnit\Framework\TestCase;
use Illuminate\Support\Facades\File;

class FileScannerTest extends TestCase
{
    private FileScanner $fileScanner;
    private string $testDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fileScanner = new FileScanner();
        $this->testDirectory = sys_get_temp_dir() . '/biliup_test_' . uniqid();
        
        // 创建测试目录
        mkdir($this->testDirectory, 0755, true);
        $this->fileScanner->setScanDirectory($this->testDirectory);
    }

    protected function tearDown(): void
    {
        // 清理测试目录
        if (is_dir($this->testDirectory)) {
            $this->removeDirectory($this->testDirectory);
        }
        parent::tearDown();
    }

    public function test_can_scan_empty_directory(): void
    {
        $files = $this->fileScanner->scanMp4Files();
        $this->assertEmpty($files);
    }

    public function test_can_detect_mp4_files(): void
    {
        // 创建测试文件
        $testFile = $this->testDirectory . '/test.mp4';
        file_put_contents($testFile, 'fake mp4 content');

        $files = $this->fileScanner->scanMp4Files();
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('test.mp4', $files[0]);
    }

    public function test_ignores_zero_byte_files(): void
    {
        // 创建空文件
        $emptyFile = $this->testDirectory . '/empty.mp4';
        touch($emptyFile);

        // 创建有内容的文件
        $validFile = $this->testDirectory . '/valid.mp4';
        file_put_contents($validFile, 'content');

        $files = $this->fileScanner->scanMp4Files();
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('valid.mp4', $files[0]);
    }

    public function test_can_mark_file_as_processed(): void
    {
        $testFile = $this->testDirectory . '/test.mp4';
        file_put_contents($testFile, 'content');

        // 标记为已处理
        $this->fileScanner->markAsProcessed($testFile, true, 'Test success');

        // 检查是否已处理
        $this->assertTrue($this->fileScanner->isFileProcessed($testFile));
    }

    public function test_can_get_unprocessed_files(): void
    {
        // 创建两个文件
        $file1 = $this->testDirectory . '/file1.mp4';
        $file2 = $this->testDirectory . '/file2.mp4';
        file_put_contents($file1, 'content1');
        file_put_contents($file2, 'content2');

        // 标记一个为已处理
        $this->fileScanner->markAsProcessed($file1, true);

        $unprocessedFiles = $this->fileScanner->getUnprocessedFiles();
        $this->assertCount(1, $unprocessedFiles);
        $this->assertStringEndsWith('file2.mp4', $unprocessedFiles[0]);
    }

    public function test_can_get_processing_stats(): void
    {
        $testFile = $this->testDirectory . '/test.mp4';
        file_put_contents($testFile, 'content');

        // 标记为成功处理
        $this->fileScanner->markAsProcessed($testFile, true, 'Success');

        $stats = $this->fileScanner->getProcessingStats();
        $this->assertEquals(1, $stats['total_processed']);
        $this->assertEquals(1, $stats['successful']);
        $this->assertEquals(0, $stats['failed']);
        $this->assertEquals(100, $stats['success_rate']);
    }

    public function test_can_get_file_info(): void
    {
        $testFile = $this->testDirectory . '/test.mp4';
        file_put_contents($testFile, 'test content');

        $fileInfo = $this->fileScanner->getFileInfo($testFile);
        
        $this->assertEquals('test.mp4', $fileInfo['name']);
        $this->assertEquals(12, $fileInfo['size']); // 'test content' length
        $this->assertEquals('12 B', $fileInfo['size_human']);
        $this->assertEquals('mp4', $fileInfo['extension']);
        $this->assertFalse($fileInfo['is_test_file']);
    }

    public function test_can_detect_test_files(): void
    {
        $testFile = $this->testDirectory . '/video-test-1.mp4';
        file_put_contents($testFile, 'content');

        $fileInfo = $this->fileScanner->getFileInfo($testFile);
        $this->assertTrue($fileInfo['is_test_file']);
    }

    public function test_can_get_directory_stats(): void
    {
        // 创建测试文件
        $file1 = $this->testDirectory . '/normal.mp4';
        $file2 = $this->testDirectory . '/test-file-1.mp4';
        file_put_contents($file1, 'content1');
        file_put_contents($file2, 'content2');

        $stats = $this->fileScanner->getDirectoryStats();
        
        $this->assertEquals($this->testDirectory, $stats['scan_directory']);
        $this->assertEquals(2, $stats['total_files']);
        $this->assertEquals(1, $stats['test_files']);
        $this->assertEquals(2, $stats['unprocessed_files']);
        $this->assertTrue($stats['directory_exists']);
        $this->assertTrue($stats['directory_readable']);
    }

    public function test_can_reset_processed_files(): void
    {
        $testFile = $this->testDirectory . '/test.mp4';
        file_put_contents($testFile, 'content');

        // 标记为已处理
        $this->fileScanner->markAsProcessed($testFile, true);
        $this->assertTrue($this->fileScanner->isFileProcessed($testFile));

        // 重置
        $this->fileScanner->resetProcessedFiles();
        $this->assertFalse($this->fileScanner->isFileProcessed($testFile));
    }

    /**
     * 递归删除目录
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
