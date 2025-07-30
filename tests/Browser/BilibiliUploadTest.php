<?php

namespace Tests\Browser;

use App\Services\FileScanner;
use App\Services\CookieManager;
use App\Services\UploadLogger;
use App\Services\RetryManager;
use App\Services\ExceptionHandler;
use App\Services\ProgressDisplay;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\Bilibili\LoginPage;
use Tests\Browser\Pages\Bilibili\UploadPage;
use Tests\DuskTestCase;
use Illuminate\Support\Facades\Log;

class BilibiliUploadTest extends DuskTestCase
{
    private FileScanner $fileScanner;
    private CookieManager $cookieManager;
    private UploadLogger $logger;
    private RetryManager $retryManager;
    private ExceptionHandler $exceptionHandler;
    private ProgressDisplay $progressDisplay;
    private LoginPage $loginPage;
    private UploadPage $uploadPage;
    private array $sessionErrors = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->fileScanner = new FileScanner();
        $this->cookieManager = new CookieManager();
        $this->logger = new UploadLogger();
        $this->retryManager = new RetryManager();
        $this->exceptionHandler = new ExceptionHandler($this->logger);
        $this->progressDisplay = new ProgressDisplay();
        $this->loginPage = new LoginPage();
        $this->uploadPage = new UploadPage();
    }

    /**
     * 测试 Bilibili 视频批量上传
     */
    public function testBatchUpload(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->maximize();

            echo "=== Bilibili 自动投稿工具启动 ===\n";

            // 1. 检查登录状态
            if (!$this->handleLogin($browser)) {
                $this->fail("登录失败，无法继续");
                return;
            }

            // 2. 扫描待上传文件
            $this->scanAndLogFiles();

            $files = $this->fileScanner->getUnprocessedFiles();
            if (empty($files)) {
                $this->logger->logFileScan(0, 0, []);
                echo "没有找到待上传的文件\n";
                return;
            }

            // 3. 初始化进度显示
            $this->progressDisplay->initialize(count($files));

            // 4. 批量上传文件
            $this->processFiles($browser, $files);

            // 5. 显示最终结果
            $this->progressDisplay->showFinalResult();

            // 6. 显示处理结果
            $this->showProcessingStats();

            // 7. 生成错误报告
            $this->generateSessionReport();
        });
    }

    /**
     * 处理登录逻辑
     */
    private function handleLogin(Browser $browser): bool
    {
        try {
            return $this->retryManager->executeLogin(function() use ($browser) {
                return $this->performLogin($browser);
            }, ['browser_session' => $browser->driver->getSessionId()]);
        } catch (\Exception $e) {
            $errorInfo = $this->exceptionHandler->handleLoginException($e, $browser, '登录流程');
            $this->sessionErrors[] = $errorInfo;
            return false;
        }
    }

    /**
     * 执行登录操作
     */
    private function performLogin(Browser $browser): bool
    {
        $this->logger->logLogin(false, 'check', '开始检查登录状态');

        // 显示 Cookie 状态
        $cookieStats = $this->cookieManager->getCookieStats();
        if ($cookieStats['file_exists']) {
            $this->logger->logLogin(true, 'cookie_found', "找到 Cookie 文件，包含 {$cookieStats['cookie_count']} 个 Cookie");

            if ($cookieStats['is_expired']) {
                $this->logger->logLogin(false, 'cookie_expired', 'Cookie 已过期');
            } else {
                $this->logger->logLogin(true, 'cookie_valid', "Cookie 有效，还有 {$cookieStats['days_until_expiry']} 天过期");
            }
        } else {
            $this->logger->logLogin(false, 'no_cookie', '未找到 Cookie 文件');
        }

        // 尝试加载保存的 Cookie
        if ($this->loginPage->loadCookies($browser)) {
            $this->logger->logLogin(true, 'cookie_loaded', '已加载保存的 Cookie');

            // 检查登录状态
            if ($this->loginPage->isLoggedIn($browser)) {
                $this->logger->logLogin(true, 'login_valid', '登录状态有效');
                return true;
            } else {
                $this->logger->logLogin(false, 'login_invalid', 'Cookie 已失效，需要重新登录');
                // 备份失效的 Cookie
                $this->cookieManager->backupCookieFile();
            }
        }

        // 需要重新登录
        $this->logger->logLogin(false, 'need_login', '开始二维码登录流程');
        $browser->visit($this->loginPage);

        if ($this->loginPage->waitForQrLogin($browser)) {
            $this->loginPage->saveCookies($browser);
            $this->logger->logLogin(true, 'qr_login', '二维码登录成功');
            return true;
        }

        $this->logger->logLogin(false, 'qr_login_failed', '二维码登录失败');
        throw new \Exception('二维码登录失败');
    }

    /**
     * 处理文件列表
     */
    private function processFiles(Browser $browser, array $files): void
    {
        $totalFiles = count($files);
        $currentIndex = 0;

        foreach ($files as $filePath) {
            $currentIndex++;
            $fileInfo = $this->fileScanner->getFileInfo($filePath);

            // 更新进度显示
            $this->progressDisplay->updateFile($currentIndex, $fileInfo['name'], 'processing', '准备上传');

            $this->logger->logUploadStart($filePath, $currentIndex, $totalFiles);

            // 验证文件有效性
            $this->progressDisplay->updateOperation('验证文件格式');
            if (!$this->fileScanner->isValidMp4($filePath)) {
                $message = "文件不是有效的 MP4 格式";
                $this->fileScanner->markAsProcessed($filePath, false, $message);
                $this->logger->logUploadResult($filePath, false, $message);
                $this->progressDisplay->completeFile($currentIndex, false, $message);
                continue;
            }

            // 保存截图
            $this->saveScreenshot($browser, "before_upload_{$currentIndex}", "上传前截图");

            try {
                // 使用重试机制执行上传
                $this->progressDisplay->updateFile($currentIndex, $fileInfo['name'], 'uploading', '开始上传');

                $success = $this->retryManager->executeUpload(function() use ($browser, $filePath, $fileInfo, $currentIndex) {
                    // 访问上传页面
                    $this->progressDisplay->updateOperation('访问上传页面');
                    $browser->visit($this->uploadPage);

                    // 执行上传流程
                    $this->progressDisplay->updateOperation('执行上传流程');
                    $result = $this->uploadPage->completeUpload($browser, $filePath, [
                        'title' => $this->generateTitle($fileInfo['name']),
                    ]);

                    if (!$result) {
                        throw new \Exception("上传流程失败");
                    }

                    return $result;
                }, $fileInfo['name'], $fileInfo);

                if ($success) {
                    $this->fileScanner->markAsProcessed($filePath, true, "上传成功");
                    $this->logger->logUploadResult($filePath, true, "上传成功", $fileInfo);
                    $this->progressDisplay->completeFile($currentIndex, true, "上传成功");

                    // 保存成功截图
                    $this->saveScreenshot($browser, "upload_success_{$currentIndex}", "上传成功");
                } else {
                    $message = "上传流程失败";
                    $this->fileScanner->markAsProcessed($filePath, false, $message);
                    $this->logger->logUploadResult($filePath, false, $message, $fileInfo);
                    $this->progressDisplay->completeFile($currentIndex, false, $message);

                    // 保存失败截图
                    $this->saveScreenshot($browser, "upload_failed_{$currentIndex}", "上传失败");
                }

            } catch (\Exception $e) {
                // 使用异常处理器处理错误
                $errorInfo = $this->exceptionHandler->handleUploadException(
                    $e,
                    $browser,
                    $filePath,
                    "处理文件 {$currentIndex}/{$totalFiles}"
                );

                $this->sessionErrors[] = $errorInfo;
                $this->fileScanner->markAsProcessed($filePath, false, $errorInfo['error_message']);
                $this->logger->logUploadResult($filePath, false, $errorInfo['error_message'], $fileInfo);
                $this->progressDisplay->completeFile($currentIndex, false, $errorInfo['error_type'] . ': ' . $errorInfo['error_message']);

                // 如果是可恢复的错误，可以考虑继续处理下一个文件
                if (!$errorInfo['is_recoverable']) {
                    $this->progressDisplay->updateOperation('检测到严重错误，建议检查系统状态');
                    sleep(2); // 给用户时间看到消息
                }
            }

            // 等待一段时间再处理下一个文件
            if ($currentIndex < $totalFiles) {
                $waitTime = config('dusk.bilibili.wait_between_uploads', 3);
                $this->progressDisplay->updateOperation("等待 {$waitTime} 秒后处理下一个文件");
                sleep($waitTime);
            }
        }
    }

    /**
     * 生成视频标题
     */
    private function generateTitle(string $fileName): string
    {
        // 移除文件扩展名
        $title = pathinfo($fileName, PATHINFO_FILENAME);

        // 移除测试后缀
        $title = preg_replace('/-test-\d+$/', '', $title);

        // 限制标题长度
        if (strlen($title) > 80) {
            $title = substr($title, 0, 80);
        }

        return $title;
    }

    /**
     * 保存截图
     */
    private function saveScreenshot(Browser $browser, string $name, string $reason = ''): void
    {
        $timestamp = date('Y-m-d_H-i-s');
        $screenshotName = "{$name}_{$timestamp}";
        $screenshotPath = "tests/Browser/screenshots/{$screenshotName}.png";

        try {
            $browser->screenshot($screenshotName);
            $this->logger->logScreenshot($screenshotName, $reason);
            echo "截图已保存: {$screenshotPath}\n";
        } catch (\Exception $e) {
            $this->logger->logError("保存截图失败", [
                'screenshot_name' => $screenshotName,
                'reason' => $reason
            ], $e);
            echo "保存截图失败: " . $e->getMessage() . "\n";
        }
    }

    /**
     * 扫描并记录文件信息
     */
    private function scanAndLogFiles(): void
    {
        $allFiles = $this->fileScanner->scanMp4Files();
        $unprocessedFiles = $this->fileScanner->getUnprocessedFiles();
        $dirStats = $this->fileScanner->getDirectoryStats();

        $this->logger->logFileScan(count($allFiles), count($unprocessedFiles), $unprocessedFiles);

        echo "=== 文件扫描结果 ===\n";
        echo "扫描目录: {$dirStats['scan_directory']}\n";
        echo "总文件数: {$dirStats['total_files']}\n";
        echo "有效文件数: {$dirStats['valid_files']}\n";
        echo "测试文件数: {$dirStats['test_files']}\n";
        echo "未处理文件数: {$dirStats['unprocessed_files']}\n";
        echo "总大小: {$dirStats['total_size_human']}\n";
        echo "==================\n";

        // 显示未处理文件列表
        if (!empty($unprocessedFiles)) {
            echo "\n待上传文件列表:\n";
            foreach ($unprocessedFiles as $index => $file) {
                $fileInfo = $this->fileScanner->getFileInfo($file);
                $status = $fileInfo['is_test_file'] ? '[测试]' : '';
                echo sprintf("%d. %s %s (%s)\n",
                    $index + 1,
                    $fileInfo['name'],
                    $status,
                    $fileInfo['size_human']
                );
            }
            echo "\n";
        }
    }

    /**
     * 显示处理统计信息
     */
    private function showProcessingStats(): void
    {
        $stats = $this->fileScanner->getProcessingStats();

        $this->logger->logSessionStats($stats);

        echo "\n=== 处理结果统计 ===\n";
        echo "总处理文件数: {$stats['total_processed']}\n";
        echo "成功上传: {$stats['successful']}\n";
        echo "失败: {$stats['failed']}\n";
        echo "成功率: {$stats['success_rate']}%\n";
        echo "==================\n";
    }

    /**
     * 生成会话报告
     */
    private function generateSessionReport(): void
    {
        $stats = $this->fileScanner->getProcessingStats();

        // 记录会话结束
        $this->logger->logSessionEnd([
            'total_errors' => count($this->sessionErrors),
            'processing_stats' => $stats,
            'retry_stats' => $this->retryManager->getRetryStats(),
        ]);

        // 如果有错误，生成错误报告
        if (!empty($this->sessionErrors)) {
            echo "\n=== 错误报告 ===\n";
            echo "本次会话共发生 " . count($this->sessionErrors) . " 个错误\n";

            $errorReport = $this->exceptionHandler->generateErrorReport($this->sessionErrors);
            echo $errorReport;

            // 保存错误报告到文件
            $reportPath = storage_path('logs/error_report_' . date('Y-m-d_H-i-s') . '.txt');
            file_put_contents($reportPath, $errorReport);
            echo "详细错误报告已保存到: {$reportPath}\n";
        } else {
            echo "\n✓ 本次会话没有发生错误\n";
        }

        echo "\n=== 会话结束 ===\n";
        echo "日志文件: " . $this->logger->getLogFile() . "\n";
        echo "会话ID: " . $this->logger->getSessionId() . "\n";
    }

    /**
     * 测试创建测试文件
     */
    public function testCreateTestFiles(): void
    {
        $scanDir = $this->fileScanner->getScanDirectory();
        $files = $this->fileScanner->scanMp4Files();

        if (empty($files)) {
            echo "扫描目录中没有找到 MP4 文件: {$scanDir}\n";
            return;
        }

        $sourceFile = $files[0];
        echo "使用源文件创建测试文件: " . basename($sourceFile) . "\n";

        $testFiles = $this->fileScanner->createTestFiles($sourceFile, 3);

        echo "创建了 " . count($testFiles) . " 个测试文件:\n";
        foreach ($testFiles as $file) {
            echo "- " . basename($file) . "\n";
        }
    }

    /**
     * 测试清理测试文件
     */
    public function testCleanupTestFiles(): void
    {
        echo "清理测试文件...\n";
        $this->fileScanner->cleanupTestFiles();
        echo "测试文件清理完成\n";
    }
}
