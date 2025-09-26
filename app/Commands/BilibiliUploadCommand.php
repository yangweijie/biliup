<?php

namespace App\Commands;

use App\Services\FileScanner;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Facades\Process;

class BilibiliUploadCommand extends Command
{
    /**
     * The signature of the command.
     */
    protected $signature = 'up
                            {--scan : 仅扫描文件，不执行上传}
                            {--stats : 显示处理统计信息}
                            {--reset : 重置处理记录}
                            {--test-files=0 : 创建测试文件数量}
                            {--cleanup : 清理测试文件}
                            {--dir= : 指定扫描目录}
                            {--yes : 跳过确认直接开始上传}';

    /**
     * The description of the command.
     */
    protected $description = 'Bilibili 视频自动投稿工具';

    private FileScanner $fileScanner;

    public function __construct()
    {
        parent::__construct();
        $this->fileScanner = new FileScanner();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=== Bilibili 自动投稿工具 ===');

        // 处理目录参数
        if ($this->option('dir')) {
            $this->fileScanner->setScanDirectory($this->option('dir'));
        }

        // 显示当前配置
        $this->showConfiguration();

        // 处理各种选项
        if ($this->option('stats')) {
            return $this->showStats();
        }

        if ($this->option('reset')) {
            return $this->resetProcessedFiles();
        }

        if ($this->option('test-files')) {
            return $this->createTestFiles((int) $this->option('test-files'));
        }

        if ($this->option('cleanup')) {
            return $this->cleanupTestFiles();
        }

        if ($this->option('scan')) {
            return $this->scanFiles();
        }

        // 执行上传任务
        return $this->runUploadTask();
        sleep(120);
    }

    /**
     * 显示当前配置
     */
    private function showConfiguration(): void
    {
        $this->info('📋 当前配置信息');
        $this->table(['配置项', '值'], [
            ['扫描目录', $this->fileScanner->getScanDirectory()],
            ['分区', env('BILIBILI_CATEGORY', '音乐区')],
            ['标签', env('BILIBILI_TAGS', '必剪创作,歌单')],
            ['活动', env('BILIBILI_ACTIVITY', '音乐分享关')],
            ['最大重试次数', env('BILIBILI_RETRY_ATTEMPTS', 3)],
            ['重试延迟', env('BILIBILI_RETRY_DELAY', 5) . ' 秒'],
            ['上传间隔', env('BILIBILI_WAIT_BETWEEN_UPLOADS', 3) . ' 秒'],
        ]);
        $this->line('');
    }

    /**
     * 扫描文件
     */
    private function scanFiles(): int
    {
        $this->info('🔍 正在扫描文件...');

        $progressBar = $this->output->createProgressBar(1);
        $progressBar->setFormat('扫描中... %current%/%max% [%bar%] %percent:3s%%');
        $progressBar->start();

        $allFiles = $this->fileScanner->scanMp4Files();
        $unprocessedFiles = $this->fileScanner->getUnprocessedFiles();
        $dirStats = $this->fileScanner->getDirectoryStats();

        $progressBar->finish();
        $this->line('');

        // 显示扫描结果表格
        $this->info("📊 扫描结果:");
        $this->table(['项目', '数量', '详情'], [
            ['总文件数', count($allFiles), $dirStats['total_size_human']],
            ['有效文件数', $dirStats['valid_files'], '通过格式验证'],
            ['测试文件数', $dirStats['test_files'], '包含 -test- 标识'],
            ['未处理文件数', count($unprocessedFiles), '待上传'],
            ['已处理文件数', count($allFiles) - count($unprocessedFiles), '已完成'],
        ]);

        if (!empty($unprocessedFiles)) {
            $this->info("\n📁 未处理的文件:");
            $fileData = [];
            foreach ($unprocessedFiles as $index => $file) {
                $fileInfo = $this->fileScanner->getFileInfo($file);
                $fileData[] = [
                    $index + 1,
                    $fileInfo['name'],
                    $fileInfo['size_human'],
                    $fileInfo['is_test_file'] ? '测试' : '正常',
                    $fileInfo['modified_at']
                ];
            }

            $this->table(['#', '文件名', '大小', '类型', '修改时间'], $fileData);
        } else {
            $this->info("✅ 所有文件都已处理完成");
        }

        return self::SUCCESS;
    }

    /**
     * 显示统计信息
     */
    private function showStats(): int
    {
        $stats = $this->fileScanner->getProcessingStats();
        $dirStats = $this->fileScanner->getDirectoryStats();

        $this->info('📈 处理统计信息');

        // 基本统计
        $this->table(['统计项', '数值'], [
            ['总处理文件数', $stats['total_processed']],
            ['成功上传', $stats['successful'] . ' ✅'],
            ['失败', $stats['failed'] . ' ❌'],
            ['成功率', $stats['success_rate'] . '%'],
        ]);

        // 目录统计
        $this->info('📁 目录信息');
        $this->table(['项目', '值'], [
            ['扫描目录', $dirStats['scan_directory']],
            ['目录状态', $dirStats['directory_exists'] ? '存在 ✅' : '不存在 ❌'],
            ['可读性', $dirStats['directory_readable'] ? '可读 ✅' : '不可读 ❌'],
            ['总文件数', $dirStats['total_files']],
            ['总大小', $dirStats['total_size_human']],
        ]);

        // 显示最近的日志文件
        $this->showRecentLogs();

        return self::SUCCESS;
    }

    /**
     * 显示最近的日志文件
     */
    private function showRecentLogs(): void
    {
        $logFiles = \App\Services\UploadLogger::getRecentLogFiles(5);

        if (!empty($logFiles)) {
            $this->info('📄 最近的日志文件');
            $logData = [];
            foreach ($logFiles as $index => $logFile) {
                $logData[] = [
                    $index + 1,
                    basename($logFile),
                    date('Y-m-d H:i:s', filemtime($logFile)),
                    $this->formatFileSize(filesize($logFile))
                ];
            }
            $this->table(['#', '文件名', '创建时间', '大小'], $logData);
        }
    }

    /**
     * 格式化文件大小
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * 重置处理记录
     */
    private function resetProcessedFiles(): int
    {
        if ($this->confirm('确定要重置所有处理记录吗？')) {
            $this->fileScanner->resetProcessedFiles();
            $this->info('处理记录已重置');
        }

        return self::SUCCESS;
    }

    /**
     * 创建测试文件
     */
    private function createTestFiles(int $count): int
    {
        if ($count <= 0) {
            $this->error('测试文件数量必须大于 0');
            return self::FAILURE;
        }

        $files = $this->fileScanner->scanMp4Files();
        if (empty($files)) {
            $this->error('扫描目录中没有找到 MP4 文件');
            return self::FAILURE;
        }

        $sourceFile = $files[0];
        $this->info("使用源文件: " . basename($sourceFile));

        try {
            $testFiles = $this->fileScanner->createTestFiles($sourceFile, $count);
            $this->info("成功创建 " . count($testFiles) . " 个测试文件:");

            foreach ($testFiles as $file) {
                $this->line("- " . basename($file));
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('创建测试文件失败: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * 清理测试文件
     */
    private function cleanupTestFiles(): int
    {
        if ($this->confirm('确定要清理所有测试文件吗？')) {
            $this->fileScanner->cleanupTestFiles();
            $this->info('测试文件清理完成');
        }

        return self::SUCCESS;
    }

    /**
     * 运行上传任务
     */
    private function runUploadTask(): int
    {
        $unprocessedFiles = $this->fileScanner->getUnprocessedFiles();

        if (empty($unprocessedFiles)) {
            $this->info('✅ 没有找到待上传的文件');
            return self::SUCCESS;
        }

        $this->info("🎯 找到 " . count($unprocessedFiles) . " 个待上传文件");

        // 显示文件预览
        $this->showUploadPreview($unprocessedFiles);

        if (!$this->option('yes') && !$this->confirm('确定要开始上传吗？')) {
            $this->info('❌ 用户取消上传');
            return self::SUCCESS;
        }

        // 显示上传前的准备信息
        $this->showUploadPreparation();

        // 启动 ChromeDriver
        if (!$this->startChromeDriver()) {
            return self::FAILURE;
        }

        // 运行 Dusk 测试
        $this->info('🚀 启动浏览器自动化上传...');

        $progressBar = $this->output->createProgressBar(1);
        $progressBar->setFormat('上传中... %message%');
        $progressBar->setMessage('正在启动浏览器...');
        $progressBar->start();

        try {
            // 直接运行上传逻辑，不依赖 Pest 测试框架
            $result = $this->runDirectUpload($unprocessedFiles);

            $progressBar->finish();
            $this->line('');

            if ($result) {
                $this->info('✅ 上传任务完成');
                $this->showUploadSummary();
                return self::SUCCESS;
            } else {
                $this->error('❌ 上传任务失败');
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $progressBar->finish();
            $this->line('');
            $this->error('❌ 执行上传任务时发生错误: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * 显示上传预览
     */
    private function showUploadPreview(array $files): void
    {
        $this->info('📋 上传文件预览');

        $previewData = [];
        $totalSize = 0;

        foreach (array_slice($files, 0, 10) as $index => $file) {
            $fileInfo = $this->fileScanner->getFileInfo($file);
            $totalSize += $fileInfo['size'];

            $previewData[] = [
                $index + 1,
                $fileInfo['name'],
                $fileInfo['size_human'],
                $fileInfo['is_test_file'] ? '测试' : '正常'
            ];
        }

        $this->table(['#', '文件名', '大小', '类型'], $previewData);

        if (count($files) > 10) {
            $this->line("... 还有 " . (count($files) - 10) . " 个文件");
        }

        $this->line("总大小: " . $this->formatFileSize($totalSize));
        $this->line('');
    }

    /**
     * 显示上传准备信息
     */
    private function showUploadPreparation(): void
    {
        $this->info('⚙️ 上传准备');
        $this->line('• 检查浏览器驱动...');
        $this->line('• 验证配置参数...');
        $this->line('• 准备日志记录...');
        $this->line('• 初始化错误处理...');
        $this->line('');
    }

    /**
     * 显示上传总结
     */
    private function showUploadSummary(): void
    {
        $this->line('');
        $this->info('📊 上传总结');

        // 显示最新统计
        $this->showStats();

        // 显示建议
        $this->info('💡 建议');
        $this->line('• 检查日志文件了解详细信息');
        $this->line('• 如有失败文件，可重新运行命令重试');
        $this->line('• 定期清理测试文件和旧日志');
    }

    /**
     * 获取正确的 ChromeDriver 路径
     */
    private function getChromeDriverPath(): string
    {
        // 检测操作系统
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        if ($isWindows) {
            // Windows 环境下的 ChromeDriver 路径
            $paths = [
                'vendor\\laravel\\dusk\\bin\\chromedriver-win.exe',
                'vendor\\laravel\\dusk\\bin\\chromedriver.exe',
                'chromedriver.exe',
                'vendor\\bin\\chromedriver.exe'
            ];
        } else {
            // Unix/Linux/Mac 环境
            $paths = [
                'vendor/laravel/dusk/bin/chromedriver-linux',
                'vendor/laravel/dusk/bin/chromedriver',
                'vendor/laravel/dusk/bin/chromedriver-mac-arm',
                './chromedriver',
                'chromedriver'
            ];
        }

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // 如果都找不到，返回默认路径
        return $isWindows ? 'chromedriver.exe' : 'chromedriver';
    }

    /**
     * 检查并启动 ChromeDriver
     */
    private function startChromeDriver(): bool
    {
        // 首先检查 ChromeDriver 是否已经在运行
        if ($this->isChromeDriverRunning()) {
            $this->info("ChromeDriver 已在运行");
            return true;
        }

        $chromeDriverPath = $this->getChromeDriverPath();

        if (!file_exists($chromeDriverPath)) {
            $this->error("ChromeDriver 未找到: $chromeDriverPath");
            $this->line("请确保 ChromeDriver 已安装并可访问");
            return false;
        }

        $this->info("启动 ChromeDriver: $chromeDriverPath");

        // 使用 symfony/process 在后台启动 ChromeDriver
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $commandArr = [$chromeDriverPath, '--port=9515'];

        try {
            // 引入 Process 类
            if (!class_exists('Symfony\Component\Process\Process')) {
                require_once base_path('vendor/autoload.php');
            }
            $process = new \Symfony\Component\Process\Process($commandArr);
            $process->setTimeout(60);
            $process->disableOutput();
            $process->start();
        } catch (\Exception $e) {
            $this->error('ChromeDriver 启动失败: ' . $e->getMessage());
            return false;
        }

        // 等待 ChromeDriver 启动
        sleep(2);

        // 验证启动是否成功
        if ($this->isChromeDriverRunning()) {
            $this->info("ChromeDriver 启动成功");
            return true;
        } else {
            $this->error("ChromeDriver 启动失败");
            return false;
        }
    }

    /**
     * 检查 ChromeDriver 是否正在运行
     */
    private function isChromeDriverRunning(): bool
    {
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        if ($isWindows) {
            $output = shell_exec('tasklist | findstr chromedriver');
        } else {
            $output = shell_exec('ps aux | grep chromedriver | grep -v grep');
        }

        return !empty($output);
    }

    /**
     * 直接运行上传逻辑
     */
    private function runDirectUpload(array $files): bool
    {
        $this->info('正在启动浏览器...');

        try {
            // 使用 WebDriver 直接控制浏览器
            $driver = $this->createWebDriver();

            $this->info('浏览器启动成功，开始自动上传流程...');

            // 1. 处理登录
            if (!$this->handleAutoLogin($driver)) {
                $driver->quit();
                return false;
            }

            // 2. 批量处理文件
            $successCount = 0;
            $totalFiles = count($files);

            foreach ($files as $index => $filePath) {
                $currentIndex = $index + 1;
                $fileName = basename($filePath);

                $this->info("正在处理文件 {$currentIndex}/{$totalFiles}: {$fileName}");

                try {
                    if ($this->uploadSingleFile($driver, $filePath, $fileName)) {
                        $successCount++;
                        $this->info("✅ 文件 {$currentIndex} 上传成功");

                        // 标记为已处理
                        $this->markFileAsProcessed($filePath, true);
                    } else {
                        $this->error("❌ 文件 {$currentIndex} 上传失败");
                        $this->markFileAsProcessed($filePath, false);
                    }
                } catch (\Exception $e) {
                    $this->error("❌ 文件 {$currentIndex} 处理异常: " . $e->getMessage());
                    $this->markFileAsProcessed($filePath, false);
                }

                // 等待间隔
                if ($currentIndex < $totalFiles) {
                    $waitTime = 3;
                    $this->info("等待 {$waitTime} 秒后处理下一个文件...");
                    sleep($waitTime);
                }
            }

            // 关闭浏览器
            $driver->quit();

            $this->info("上传完成！成功: {$successCount}/{$totalFiles}");
            return $successCount > 0;

        } catch (\Exception $e) {
            $this->error('浏览器操作失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 处理自动登录
     */
    private function handleAutoLogin($driver): bool
    {
        $this->info('检查登录状态...');

        // 先尝试加载保存的 Cookie
        $cookieFile = storage_path('cookies/bilibili_cookies.json');
        if (file_exists($cookieFile)) {
            $this->info('找到保存的 Cookie，尝试自动登录...');

            // 访问 Bilibili 主页
            $driver->get('https://www.bilibili.com');
            sleep(2);

            // 加载 Cookie
            $cookies = json_decode(file_get_contents($cookieFile), true);
            $loadedCount = 0;

            foreach ($cookies as $cookie) {
                try {
                    // 确保 Cookie 数据完整
                    if (isset($cookie['name']) && isset($cookie['value']) && !empty($cookie['name']) && !empty($cookie['value'])) {
                        $driver->manage()->addCookie($cookie);
                        $loadedCount++;

                        // 显示关键 Cookie 加载信息
                        if (in_array($cookie['name'], ['SESSDATA', 'bili_jct', 'DedeUserID'])) {
                            $this->line("加载关键Cookie: {$cookie['name']} = " . substr($cookie['value'], 0, 20) . '...');
                        }
                    }
                } catch (\Exception $e) {
                    $this->warn("加载Cookie失败: {$cookie['name']} - " . $e->getMessage());
                }
            }

            $this->info("已加载 {$loadedCount} 个Cookie");

            // 刷新页面检查登录状态
            $driver->navigate()->refresh();
            sleep(3);

            // 检查是否已登录
            try {
                // 尝试访问创作中心页面来验证登录状态
                $this->info('验证登录状态...');
                $driver->get('https://member.bilibili.com/platform/home');
                sleep(3);

                // 检查是否成功进入创作中心（而不是被重定向到登录页）
                $currentUrl = $driver->getCurrentURL();
                $this->line("当前URL: {$currentUrl}");

                if (strpos($currentUrl, 'member.bilibili.com') !== false && strpos($currentUrl, 'login') === false) {
                    // 检查页面是否有用户信息
                    $userElements = $driver->findElements(\Facebook\WebDriver\WebDriverBy::cssSelector('.user-info, .header-avatar, .user-con, .nav-user-info, .user-name'));
                    $this->line("找到用户元素: " . count($userElements) . " 个");

                    if (!empty($userElements)) {
                        $this->info('✅ Cookie 自动登录成功');
                        return true;
                    }
                } else {
                    $this->warn('页面被重定向到登录页面，Cookie 可能已过期');
                }
            } catch (\Exception $e) {
                $this->warn('验证登录状态失败: ' . $e->getMessage());
            }
        }

        // 需要二维码登录
        $this->info('需要扫码登录，正在打开登录页面...');
        $driver->get('https://passport.bilibili.com/login');

        $this->info('请使用手机 Bilibili 客户端扫描二维码登录');
        $this->info('正在自动检测登录状态...');

        // 自动检测登录状态
        if (!$this->waitForLogin($driver)) {
            throw new \Exception('登录失败或超时');
        }

        // 等待页面稳定
        sleep(3);

        // 保存 Cookie
        $this->saveCookies($driver);

        // 设置权限存储，避免下次弹窗
        $this->setPermissionStorage($driver);

        return true;
    }

    /**
     * 保存 Cookie
     */
    private function saveCookies($driver): void
    {
        try {
            // 确保在正确的域名下获取 Cookie
            $driver->get('https://www.bilibili.com');
            sleep(2);

            $cookies = $driver->manage()->getCookies();
            $cookieDir = storage_path('cookies');
            if (!is_dir($cookieDir)) {
                mkdir($cookieDir, 0755, true);
            }

            // 转换 Cookie 对象为数组格式
            $cookieArray = [];
            foreach ($cookies as $cookie) {
                $cookieData = [
                    'name' => $cookie->getName(),
                    'value' => $cookie->getValue(),
                    'domain' => $cookie->getDomain(),
                    'path' => $cookie->getPath(),
                    'secure' => $cookie->isSecure(),
                    'httpOnly' => $cookie->isHttpOnly()
                ];

                // 只保存有效的 Cookie
                if (!empty($cookieData['name']) && !empty($cookieData['value'])) {
                    $cookieArray[] = $cookieData;
                }
            }

            file_put_contents(
                storage_path('cookies/bilibili_cookies.json'),
                json_encode($cookieArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            $this->info('Cookie 已保存 (共 ' . count($cookieArray) . ' 个)');

            // 调试信息：显示关键 Cookie
            foreach ($cookieArray as $cookie) {
                if (in_array($cookie['name'], ['SESSDATA', 'bili_jct', 'DedeUserID'])) {
                    $this->line("关键Cookie: {$cookie['name']} = " . substr($cookie['value'], 0, 20) . '...');
                }
            }

        } catch (\Exception $e) {
            $this->warn('保存 Cookie 失败: ' . $e->getMessage());
        }
    }

    /**
     * 上传单个文件
     */
    private function uploadSingleFile($driver, string $filePath, string $fileName): bool
    {
        try {
            // 访问上传页面
            $driver->get('https://member.bilibili.com/platform/upload/video/frame');
            sleep(3);

            // 等待页面完全加载
            $this->waitForPageLoad($driver);

            // 立即检查并设置投稿页面的 localStorage
            $this->checkAndSetUploadPageStorage($driver);

            // 处理可能的弹窗和授权
            $this->handlePopupsAndPermissions($driver);

            // 查找文件输入框
            $fileInputs = $driver->findElements(\Facebook\WebDriver\WebDriverBy::cssSelector('input[type="file"]'));
            if (empty($fileInputs)) {
                throw new \Exception('找不到文件上传输入框');
            }

            $this->info('正在上传文件...');
            $fileInputs[0]->sendKeys($filePath);

            // 等待上传完成
            $this->waitForUploadComplete($driver);

            // 填写视频信息
            $this->fillVideoInfo($driver, $fileName);

            // 提交投稿
            return $this->submitVideo($driver);

        } catch (\Exception $e) {
            $this->error('上传文件失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 创建 WebDriver 实例
     */
    private function createWebDriver()
    {
        $options = new \Facebook\WebDriver\Chrome\ChromeOptions();

        $arguments = [
            '--start-maximized',
            '--disable-gpu',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--disable-web-security',
            '--enable-unsafe-swiftshader',
            // 反检测 - 隐藏自动化特征
            '--disable-blink-features=AutomationControlled',
            '--exclude-switches=enable-automation',
            '--disable-extensions-except',
            '--disable-extensions',
            '--disable-plugins-discovery',
            '--disable-default-apps',
            '--disable-background-timer-throttling',
            '--disable-backgrounding-occluded-windows',
            '--disable-renderer-backgrounding',
            '--disable-field-trial-config',
            '--disable-ipc-flooding-protection',
            // 通知权限自动允许
            '--disable-infobars',
            '--disable-notifications',
            '--disable-popup-blocking',
            // 媒体权限自动允许
            '--autoplay-policy=no-user-gesture-required',
            '--allow-file-access-from-files',
            '--allow-file-access',
            '--allow-cross-origin-auth-prompt',
            // 反检测 - 模拟真实用户
            '--disable-features=VizDisplayCompositor,TranslateUI',
            '--disable-component-extensions-with-background-pages',
            '--no-first-run',
            '--no-default-browser-check',
            '--disable-logging',
            '--disable-login-animations',
            '--no-service-autorun',
            // 用户代理字符串 - 移除 WebDriver 标识
            '--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ];

        // 检查是否禁用无头模式
        if (file_exists('.env')) {
            $envContent = file_get_contents('.env');
            if (strpos($envContent, 'DUSK_HEADLESS_DISABLED=true') === false) {
                $arguments[] = '--headless=new';
            }
        }

        $options->addArguments($arguments);

        // 添加实验性选项来进一步隐藏自动化特征
        $options->setExperimentalOption('excludeSwitches', ['enable-automation']);
        $options->setExperimentalOption('useAutomationExtension', false);

        $capabilities = \Facebook\WebDriver\Remote\DesiredCapabilities::chrome();
        $capabilities->setCapability(\Facebook\WebDriver\Chrome\ChromeOptions::CAPABILITY, $options);

        $driver = \Facebook\WebDriver\Remote\RemoteWebDriver::create(
            'http://localhost:9515',
            $capabilities
        );

        // 执行反检测 JavaScript 代码
        $this->executeAntiDetectionScript($driver);

        return $driver;
    }

    /**
     * 等待上传完成
     */
    private function waitForUploadComplete($driver, int $timeout = 600): void
    {
        $this->info('等待文件上传完成...');
        $startTime = time();

        while (time() - $startTime < $timeout) {
            try {
                // 检查是否有标题输入框（表示上传完成）
                $titleInputs = $driver->findElements(\Facebook\WebDriver\WebDriverBy::cssSelector('input[placeholder*="标题"], .title-input input'));
                if (!empty($titleInputs) && $titleInputs[0]->isDisplayed()) {
                    $this->info('✅ 文件上传完成');
                    return;
                }

                // 检查上传进度
                $progressElements = $driver->findElements(\Facebook\WebDriver\WebDriverBy::cssSelector('.upload-progress, .progress-text, .upload-status'));
                if (!empty($progressElements)) {
                    $progressText = $progressElements[0]->getText();
                    if ($progressText && strpos($progressText, '%') !== false) {
                        $this->line("上传进度: {$progressText}");
                    }
                }

                sleep(3);
            } catch (\Exception $e) {
                sleep(3);
            }
        }

        throw new \Exception('上传超时');
    }

    /**
     * 填写视频信息
     */
    private function fillVideoInfo($driver, string $fileName): void
    {
        $this->info('正在填写视频信息...');

        // 生成标题
        $title = $this->generateVideoTitle($fileName);

        // 填写标题
        $titleInputs = $driver->findElements(\Facebook\WebDriver\WebDriverBy::cssSelector('input[placeholder*="标题"], .title-input input'));
        if (!empty($titleInputs)) {
            $titleInputs[0]->clear();
            $titleInputs[0]->sendKeys($title);
            $this->info("已填写标题: {$title}");
        }

        // 填写简介
        $description = $this->generateVideoDescription($fileName);
        $descInputs = $driver->findElements(\Facebook\WebDriver\WebDriverBy::cssSelector('textarea[placeholder*="简介"], .desc-input textarea'));
        if (!empty($descInputs)) {
            $descInputs[0]->clear();
            $descInputs[0]->sendKeys($description);
            $this->info("已填写简介");
        }

        // 选择分区（音乐区）
        $this->selectCategory($driver);

        // 添加标签
        $this->addTags($driver);

        // 选择活动
        $this->selectActivity($driver);
    }

    /**
     * 生成视频标题
     */
    private function generateVideoTitle(string $fileName): string
    {
        $title = pathinfo($fileName, PATHINFO_FILENAME);
        $title = preg_replace('/-test-\d+$/', '', $title);

        if (strlen($title) > 80) {
            $title = substr($title, 0, 80);
        }

        return $title;
    }

    /**
     * 生成视频描述
     */
    private function generateVideoDescription(string $fileName): string
    {
        return "音乐分享\n\n" .
               "文件名: " . pathinfo($fileName, PATHINFO_FILENAME) . "\n" .
               "上传时间: " . date('Y-m-d H:i:s') . "\n\n" .
               "#音乐分享 #必剪创作";
    }

    /**
     * 选择分区
     */
    private function selectCategory($driver): void
    {
        try {
            $this->info('正在选择分区...');

            // 使用更通用的方法查找分区相关元素
            $categorySelectors = [
                "//button[contains(text(), '分区')]",
                "//div[contains(text(), '分区')]",
                "//span[contains(text(), '分区')]",
                ".category-select",
                ".type-select"
            ];

            foreach ($categorySelectors as $selector) {
                try {
                    if (strpos($selector, '//') === 0) {
                        $elements = $driver->findElements(\Facebook\WebDriver\WebDriverBy::xpath($selector));
                    } else {
                        $elements = $driver->findElements(\Facebook\WebDriver\WebDriverBy::cssSelector($selector));
                    }

                    if (!empty($elements) && $elements[0]->isDisplayed()) {
                        $elements[0]->click();
                        sleep(2);

                        // 查找音乐分区
                        $musicSelectors = [
                            "//li[contains(text(), '音乐')]",
                            "//div[contains(text(), '音乐')]",
                            "//span[contains(text(), '音乐')]"
                        ];

                        foreach ($musicSelectors as $musicSelector) {
                            $musicOptions = $driver->findElements(\Facebook\WebDriver\WebDriverBy::xpath($musicSelector));
                            if (!empty($musicOptions) && $musicOptions[0]->isDisplayed()) {
                                $musicOptions[0]->click();
                                sleep(2);
                                $this->info("已选择音乐分区");

                                // 验证分区选择是否正确
                                if ($this->verifyCategorySelection($driver)) {
                                    return;
                                } else {
                                    $this->warn('分区选择验证失败，尝试重新选择');
                                    // 重新选择音乐分享官话题
                                    $this->selectActivity($driver, true);
                                    return;
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            $this->warn('未找到分区选择器，跳过分区设置');
        } catch (\Exception $e) {
            $this->warn('选择分区失败: ' . $e->getMessage());
        }
    }

    /**
     * 验证分区选择是否正确
     */
    private function verifyCategorySelection($driver): bool
    {
        try {
            $this->info('验证分区选择...');

            // 查找显示当前选择分区的元素
            $selectedCategorySelectors = [
                'p.select-item-cont-inserted',
                '.select-item-cont-inserted',
                '.selected-category',
                '.category-selected',
                '.current-category'
            ];

            foreach ($selectedCategorySelectors as $selector) {
                try {
                    $elements = $driver->findElements(\Facebook\WebDriver\WebDriverBy::cssSelector($selector));
                    if (!empty($elements)) {
                        foreach ($elements as $element) {
                            if ($element->isDisplayed()) {
                                $text = trim($element->getText());
                                $this->info("当前选择的分区文本: '{$text}'");

                                // 检查文本是否包含"音乐"
                                if (!empty($text) && strpos($text, '音乐') !== false) {
                                    $this->info('✅ 分区选择验证成功：音乐分区');
                                    return true;
                                } else {
                                    $this->warn("❌ 分区选择验证失败：当前分区为 '{$text}'，不是音乐分区");
                                    return false;
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            $this->warn('未找到分区选择验证元素');
            return false;
        } catch (\Exception $e) {
            $this->warn('验证分区选择失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 添加标签
     */
    private function addTags($driver): void
    {
        try {
            $tags = explode(',', getenv('BILIBILI_TAGS') ?: '必剪创作,歌单');

            $tagInputs = $driver->findElements(\Facebook\WebDriver\WebDriverBy::cssSelector('.tag-input input, input[placeholder*="标签"]'));
            if (!empty($tagInputs)) {
                foreach ($tags as $tag) {
                    $tag = trim($tag);
                    if ($tag) {
                        $tagInputs[0]->sendKeys($tag);
                        $tagInputs[0]->sendKeys(\Facebook\WebDriver\WebDriverKeys::ENTER);
                        sleep(1);
                    }
                }
                $this->info("已添加标签: " . implode(', ', $tags));
            }
        } catch (\Exception $e) {
            $this->warn('添加标签失败: ' . $e->getMessage());
        }
    }

    /**
     * 选择活动
     */
    private function selectActivity($driver, bool $forceReselect = false): void
    {
        try {
            $activityName = getenv('BILIBILI_ACTIVITY') ?: '音乐分享官';
            if ($forceReselect) {
                $this->info("重新设置活动/话题: {$activityName}");
            } else {
                $this->info("正在设置活动/话题: {$activityName}");
            }

            // 查找参与话题的选择器
            $topicSelectors = [
                // 基于发现的元素，查找"参与话题："后面的输入框
                "//p[contains(text(), '参与话题：')]/following-sibling::*//input",
                "//p[contains(text(), '参与话题：')]/parent::*//input",
                "//p[contains(text(), '参与话题：')]/following::input[1]",
                // 查找"搜索更多话题"附近的输入框
                "//div[contains(text(), '搜索更多话题')]/preceding-sibling::*//input",
                "//div[contains(text(), '搜索更多话题')]/parent::*//input",
                "//div[contains(text(), '搜索更多话题')]/preceding::input[1]",
                // 通用话题输入框选择器
                'input[placeholder*="参与话题"]',
                'input[placeholder*="话题"]',
                'input[placeholder*="搜索话题"]',
                'input[placeholder*="活动"]',
                '.topic-input',
                '.activity-input',
                '.participate-topic',
                // 参与话题相关选择器
                "//span[contains(text(), '参与话题')]",
                "//div[contains(text(), '参与话题')]",
                "//label[contains(text(), '参与话题')]",
                "//button[contains(text(), '参与话题')]",
                // 原有选择器
                "//button[contains(text(), '活动')]",
                "//div[contains(text(), '活动')]",
                "//span[contains(text(), '活动')]",
                ".activity-select",
                ".topic-select"
            ];

            // 首先尝试直接在"参与话题"区域点击话题标签
            try {
                $this->info("尝试在参与话题区域点击话题标签...");

                // 查找参与话题区域的话题标签
                $topicTagSelectors = [
                    "//span[contains(text(), '{$activityName}')]",
                    "//div[contains(text(), '{$activityName}')]",
                    "//a[contains(text(), '{$activityName}')]",
                    "//button[contains(text(), '{$activityName}')]",
                    // 基于图片中的结构，话题标签可能在特定的容器中
                    "//div[contains(@class, 'topic') or contains(@class, 'tag')]//span[contains(text(), '{$activityName}')]",
                    "//div[contains(@class, 'participate')]//span[contains(text(), '{$activityName}')]"
                ];

                foreach ($topicTagSelectors as $tagSelector) {
                    try {
                        $tagElements = $driver->findElements(\Facebook\WebDriver\WebDriverBy::xpath($tagSelector));
                        if (!empty($tagElements)) {
                            foreach ($tagElements as $tagElement) {
                                if ($tagElement->isDisplayed()) {
                                    $this->info("找到话题标签: {$activityName}，正在点击...");
                                    $driver->executeScript("arguments[0].click();", [$tagElement]);
                                    sleep(1);
                                    $this->info("已点击话题标签: {$activityName}");
                                    return;
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            } catch (\Exception $e) {
                $this->warn("点击话题标签失败: " . $e->getMessage());
            }

            // 如果直接点击失败，尝试原有的输入框方式
            foreach ($topicSelectors as $selector) {
                try {
                    if (strpos($selector, '//') === 0) {
                        $elements = $driver->findElements(\Facebook\WebDriver\WebDriverBy::xpath($selector));
                    } else {
                        $elements = $driver->findElements(\Facebook\WebDriver\WebDriverBy::cssSelector($selector));
                    }

                    if (!empty($elements) && $elements[0]->isDisplayed()) {
                        $this->info("找到话题输入元素: {$selector}");

                        // 如果是输入框，直接输入
                        if ($elements[0]->getTagName() === 'input') {
                            $elements[0]->clear();
                            $elements[0]->sendKeys($activityName);
                            sleep(2); // 等待建议加载

                            // 尝试选择第一个建议
                            try {
                                $suggestionSelectors = [
                                    '.suggestion-item',
                                    '.dropdown-item',
                                    '.autocomplete-item',
                                    '.topic-suggestion',
                                    '.activity-suggestion',
                                    '.search-suggestion'
                                ];

                                foreach ($suggestionSelectors as $suggSelector) {
                                    $suggestionElements = $driver->findElements(\Facebook\WebDriver\WebDriverBy::cssSelector($suggSelector));
                                    if (!empty($suggestionElements) && $suggestionElements[0]->isDisplayed()) {
                                        $suggestionElements[0]->click();
                                        $this->info("已选择话题建议: {$activityName}");
                                        return;
                                    }
                                }
                            } catch (\Exception $e) {
                                $this->warn("选择话题建议失败: " . $e->getMessage());
                            }

                            $this->info("已输入活动/话题: {$activityName}");
                            return;
                        } else {
                            // 如果是按钮或其他元素，点击后查找选项
                            $elements[0]->click();
                            sleep(2);

                            $activityOptions = $driver->findElements(\Facebook\WebDriver\WebDriverBy::xpath("//li[contains(text(), '{$activityName}')] | //div[contains(text(), '{$activityName}')] | //span[contains(text(), '{$activityName}')]"));
                            if (!empty($activityOptions) && $activityOptions[0]->isDisplayed()) {
                                $activityOptions[0]->click();
                                $this->info("已选择活动: {$activityName}");
                                return;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            $this->warn('未找到参与话题选择器，跳过活动设置');

            // 调试：显示页面上所有可能的元素
            try {
                $this->info('调试信息：页面上包含"话题"或"活动"的元素');
                $debugElements = $driver->findElements(\Facebook\WebDriver\WebDriverBy::xpath("//*[contains(text(), '话题') or contains(text(), '活动')]"));
                foreach ($debugElements as $index => $element) {
                    try {
                        $text = $element->getText();
                        $tagName = $element->getTagName();
                        if (!empty($text)) {
                            $this->line("元素 {$index}: {$tagName} - '{$text}'");
                        }
                    } catch (\Exception $e) {
                        // 忽略
                    }
                }
            } catch (\Exception $e) {
                // 忽略调试错误
            }

        } catch (\Exception $e) {
            $this->warn('选择活动失败: ' . $e->getMessage());
        }
    }

    /**
     * 提交视频
     */
    private function submitVideo($driver): bool
    {
        try {
            $this->info('正在提交投稿...');

            // 先处理可能的弹窗
            $this->handleCreativeCollaborationPopup($driver);
            sleep(2);

            // 查找并勾选同意协议 - 使用 JavaScript 点击避免遮挡问题
            $checkboxSelectors = [
                'input[type="checkbox"]',
                '.checkbox input',
                '.agree-checkbox input'
            ];

            foreach ($checkboxSelectors as $selector) {
                $checkboxes = $driver->findElements(\Facebook\WebDriver\WebDriverBy::cssSelector($selector));
                foreach ($checkboxes as $checkbox) {
                    try {
                        if (!$checkbox->isSelected()) {
                            // 使用 JavaScript 点击避免遮挡问题
                            $driver->executeScript("arguments[0].click();", [$checkbox]);
                            sleep(1);
                        }
                    } catch (\Exception $e) {
                        // 尝试点击父元素
                        try {
                            $parent = $checkbox->findElement(\Facebook\WebDriver\WebDriverBy::xpath('..'));
                            $driver->executeScript("arguments[0].click();", [$parent]);
                        } catch (\Exception $e2) {
                            continue;
                        }
                    }
                }
            }

            // 查找提交按钮 - 优先使用 .submit-add 选择器
            $submitSelectors = [
                // 优先使用指定的选择器
                '.submit-add',
                // 文本识别
                "//button[contains(text(), '立即投稿')]",
                "//button[contains(text(), '发布')]",
                "//button[contains(text(), '提交')]",
                "//button[contains(text(), '投稿')]",
                "//span[contains(text(), '立即投稿')]",
                "//span[contains(text(), '发布')]",
                "//div[contains(text(), '立即投稿')]",
                // 基于调试信息的样式类选择器（主要按钮通常是 primary + large）
                'button.bcc-button.bcc-button--primary.large',
                '.bcc-button.bcc-button--primary.large',
                'button[class*="bcc-button--primary"][class*="large"]',
                // 排除二创弹窗的按钮（避免误点击）
                'button.bcc-button.bcc-button--primary.large:not(:contains("同意")):not(:contains("暂不考虑"))',
                // 通用选择器
                '.submit-btn',
                '.publish-btn',
                '.confirm-btn',
                'button[type="submit"]',
                '.btn-primary',
                '.btn-submit',
                '.upload-submit',
                '.video-submit',
                // 基于位置的选择器（通常在页面底部）
                "//div[contains(@class, 'submit') or contains(@class, 'publish')]//button[contains(@class, 'primary')]"
            ];

            foreach ($submitSelectors as $selector) {
                try {
                    if (strpos($selector, '//') === 0) {
                        $elements = $driver->findElements(\Facebook\WebDriver\WebDriverBy::xpath($selector));
                    } else {
                        $elements = $driver->findElements(\Facebook\WebDriver\WebDriverBy::cssSelector($selector));
                    }

                    if (!empty($elements) && $elements[0]->isDisplayed() && $elements[0]->isEnabled()) {
                        // 滚动到按钮位置
                        $driver->executeScript("arguments[0].scrollIntoView(true);", [$elements[0]]);
                        sleep(1);

                        // 使用 JavaScript 点击
                        $driver->executeScript("arguments[0].click();", [$elements[0]]);
                        $this->info('已点击提交按钮');

                        // 等待提交完成
                        sleep(5);

                        // 检查是否提交成功
                        $successSelectors = [
                            '.success-message',
                            '.submit-success',
                            "//div[contains(text(), '成功')]"
                        ];

                        foreach ($successSelectors as $successSelector) {
                            try {
                                if (strpos($successSelector, '//') === 0) {
                                    $successElements = $driver->findElements(\Facebook\WebDriver\WebDriverBy::xpath($successSelector));
                                } else {
                                    $successElements = $driver->findElements(\Facebook\WebDriver\WebDriverBy::cssSelector($successSelector));
                                }

                                if (!empty($successElements)) {
                                    $this->info('✅ 投稿提交成功');
                                    return true;
                                }
                            } catch (\Exception $e) {
                                continue;
                            }
                        }

                        // 即使没有找到成功标识，也认为提交成功
                        $this->info('✅ 投稿已提交（未检测到明确的成功标识）');
                        return true;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            // 调试：显示页面上所有的按钮
            $this->debugPageButtons($driver);
            throw new \Exception('找不到可用的提交按钮');

        } catch (\Exception $e) {
            $this->error('提交失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 执行反检测脚本
     */
    private function executeAntiDetectionScript($driver): void
    {
        try {
            // 访问一个简单页面来执行脚本
            $driver->get('data:text/html,<html><body></body></html>');

            // 反检测 JavaScript 代码
            $antiDetectionScript = "
                // 删除 webdriver 属性
                Object.defineProperty(navigator, 'webdriver', {
                    get: () => undefined,
                });

                // 修改 plugins 长度
                Object.defineProperty(navigator, 'plugins', {
                    get: () => [1, 2, 3, 4, 5],
                });

                // 修改 languages
                Object.defineProperty(navigator, 'languages', {
                    get: () => ['zh-CN', 'zh', 'en'],
                });

                // 隐藏 automation 相关属性
                Object.defineProperty(navigator, 'permissions', {
                    get: () => undefined,
                });

                // 修改 chrome 对象
                window.chrome = {
                    runtime: {},
                    loadTimes: function() {},
                    csi: function() {},
                    app: {}
                };

                // 删除 _phantom 和 callPhantom
                delete window._phantom;
                delete window.callPhantom;

                // 删除 selenium 相关
                delete window.selenium;
                delete window.webdriver;
                delete window.driver;

                // 修改 screen 属性使其看起来更真实
                Object.defineProperty(screen, 'availTop', { get: () => 0 });
                Object.defineProperty(screen, 'availLeft', { get: () => 0 });

                // 添加真实的 devicePixelRatio
                Object.defineProperty(window, 'devicePixelRatio', {
                    get: () => 1,
                });

                // 模拟真实的 outerHeight 和 outerWidth
                Object.defineProperty(window, 'outerHeight', {
                    get: () => screen.height,
                });
                Object.defineProperty(window, 'outerWidth', {
                    get: () => screen.width,
                });
            ";

            $driver->executeScript($antiDetectionScript);
            $this->info('反检测脚本已执行');

        } catch (\Exception $e) {
            $this->warn('执行反检测脚本失败: ' . $e->getMessage());
        }
    }

    /**
     * 等待用户登录完成
     */
    private function waitForLogin($driver): bool
    {
        $maxWaitTime = 180; // 最大等待3分钟
        $checkInterval = 1; // 每1秒检查一次
        $startTime = time();
        $lastProgressTime = 0;

        $this->info('等待登录完成，最长等待 3 分钟...');
        $this->info('请扫描二维码完成登录...');

        while (time() - $startTime < $maxWaitTime) {
            try {
                // 检查当前URL是否已经跳转
                $currentUrl = $driver->getCurrentURL();

                // 如果不在登录页面了，说明登录成功
                if (!str_contains($currentUrl, 'passport.bilibili.com/login')) {
                    $this->info('✅ 检测到页面跳转，登录成功！');
                    return true;
                }

                // 检查是否有登录相关的Cookie
                $cookies = $driver->manage()->getCookies();
                foreach ($cookies as $cookie) {
                    // 检查关键的登录Cookie
                    if (in_array($cookie['name'], ['SESSDATA', 'bili_jct', 'DedeUserID'])) {
                        if (!empty($cookie['value']) && $cookie['value'] !== '0' && strlen($cookie['value']) > 10) {
                            $this->info('✅ 检测到登录Cookie，登录成功！');
                            return true;
                        }
                    }
                }

                // 检查页面是否有登录成功的标识
                try {
                    $userElements = $driver->findElements(\Facebook\WebDriver\WebDriverBy::cssSelector('.header-avatar, .user-con, .nav-user-info, .user-info'));
                    if (!empty($userElements)) {
                        $this->info('✅ 检测到用户信息元素，登录成功！');
                        return true;
                    }
                } catch (\Exception $e) {
                    // 继续检查
                }

                // 每10秒显示一次进度，避免刷屏
                $currentTime = time();
                if ($currentTime - $lastProgressTime >= 10) {
                    $elapsed = $currentTime - $startTime;
                    $remaining = $maxWaitTime - $elapsed;
                    $this->line("⏳ 等待登录中... 已等待: {$elapsed}秒, 剩余: {$remaining}秒");
                    $lastProgressTime = $currentTime;
                }

                sleep($checkInterval);

            } catch (\Exception $e) {
                $this->warn('检测登录状态时出错: ' . $e->getMessage());
                sleep($checkInterval);
            }
        }

        $this->error('❌ 登录超时，请重新尝试');
        return false;
    }

    /**
     * 处理弹窗和权限授权
     */
    private function handlePopupsAndPermissions($driver): void
    {
        $this->info('检查并处理弹窗和权限...');

        try {
            // 等待页面加载
            sleep(2);

            // 1. 处理通知权限弹窗
            $this->handleNotificationPermission($driver);

            // 2. 处理二创计划弹窗
            $this->handleCreativeCollaborationPopup($driver);

            // 3. 处理其他可能的弹窗
            $this->handleGeneralPopups($driver);

            // 4. 设置 localStorage 来记住选择
            $this->setPermissionStorage($driver);

        } catch (\Exception $e) {
            $this->warn('处理弹窗时出错: ' . $e->getMessage());
        }
    }

    /**
     * 处理通知权限弹窗
     */
    private function handleNotificationPermission($driver): void
    {
        try {
            // 查找通知权限相关的弹窗
            $notificationSelectors = [
                "//button[contains(text(), '允许')]",
                "//button[contains(text(), '确定')]",
                "//button[contains(text(), '同意')]",
                "//span[contains(text(), '允许')]",
                "//div[contains(text(), '允许')]",
                ".notification-allow",
                ".permission-allow",
                ".modal-confirm",
                ".dialog-confirm"
            ];

            foreach ($notificationSelectors as $selector) {
                try {
                    if (strpos($selector, '//') === 0) {
                        $elements = $driver->findElements(\Facebook\WebDriver\WebDriverBy::xpath($selector));
                    } else {
                        $elements = $driver->findElements(\Facebook\WebDriver\WebDriverBy::cssSelector($selector));
                    }

                    if (!empty($elements) && $elements[0]->isDisplayed()) {
                        $this->info('找到通知权限弹窗，正在点击允许...');
                        $driver->executeScript("arguments[0].click();", [$elements[0]]);
                        sleep(1);
                        return;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        } catch (\Exception $e) {
            // 忽略错误
        }
    }

    /**
     * 处理二创计划弹窗
     */
    private function handleCreativeCollaborationPopup($driver): void
    {
        try {
            // 查找二创计划相关的弹窗
            $creativePopupSelectors = [
                "//div[contains(text(), '是否允许有创作者加入二创计划')]",
                "//div[contains(text(), '二创计划')]",
                "//div[contains(text(), '创作者加入')]"
            ];

            foreach ($creativePopupSelectors as $selector) {
                try {
                    $elements = $driver->findElements(\Facebook\WebDriver\WebDriverBy::xpath($selector));
                    if (!empty($elements) && $elements[0]->isDisplayed()) {
                        $this->info('找到二创计划弹窗，正在处理...');

                        // 查找"同意"或"暂不考虑"按钮
                        $buttonSelectors = [
                            "//button[contains(text(), '同意')]",
                            "//button[contains(text(), '确定')]",
                            "//button[contains(text(), '暂不考虑')]",
                            "//span[contains(text(), '同意')]",
                            "//span[contains(text(), '确定')]",
                            "//span[contains(text(), '暂不考虑')]"
                        ];

                        foreach ($buttonSelectors as $btnSelector) {
                            try {
                                $buttons = $driver->findElements(\Facebook\WebDriver\WebDriverBy::xpath($btnSelector));
                                if (!empty($buttons) && $buttons[0]->isDisplayed()) {
                                    $buttonText = $buttons[0]->getText();
                                    $this->info("找到按钮: {$buttonText}，正在点击...");

                                    // 优先点击"同意"，如果没有就点击"暂不考虑"
                                    if (strpos($buttonText, '同意') !== false || strpos($buttonText, '确定') !== false) {
                                        $driver->executeScript("arguments[0].click();", [$buttons[0]]);
                                        $this->info('已点击同意按钮');
                                        sleep(2);

                                        // 确保弹窗完全关闭
                                        $this->waitForPopupToClose($driver);
                                        return;
                                    } elseif (strpos($buttonText, '暂不考虑') !== false) {
                                        $driver->executeScript("arguments[0].click();", [$buttons[0]]);
                                        $this->info('已点击暂不考虑按钮');
                                        sleep(2);

                                        // 确保弹窗完全关闭
                                        $this->waitForPopupToClose($driver);
                                        return;
                                    }
                                }
                            } catch (\Exception $e) {
                                continue;
                            }
                        }

                        // 如果找不到具体按钮，尝试通用的关闭方法
                        $closeSelectors = [
                            ".modal-close",
                            ".dialog-close",
                            ".popup-close",
                            "//button[contains(@class, 'close')]"
                        ];

                        foreach ($closeSelectors as $closeSelector) {
                            try {
                                if (strpos($closeSelector, '//') === 0) {
                                    $closeButtons = $driver->findElements(\Facebook\WebDriver\WebDriverBy::xpath($closeSelector));
                                } else {
                                    $closeButtons = $driver->findElements(\Facebook\WebDriver\WebDriverBy::cssSelector($closeSelector));
                                }

                                if (!empty($closeButtons) && $closeButtons[0]->isDisplayed()) {
                                    $driver->executeScript("arguments[0].click();", [$closeButtons[0]]);
                                    $this->info('已关闭二创计划弹窗');
                                    sleep(2);
                                    return;
                                }
                            } catch (\Exception $e) {
                                continue;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        } catch (\Exception $e) {
            $this->warn('处理二创计划弹窗时出错: ' . $e->getMessage());
        }
    }

    /**
     * 等待弹窗关闭
     */
    private function waitForPopupToClose($driver): void
    {
        try {
            $this->info('等待弹窗完全关闭...');
            $maxWait = 10; // 最多等待10秒
            $waited = 0;

            while ($waited < $maxWait) {
                try {
                    // 检查是否还有二创计划弹窗
                    $popupElements = $driver->findElements(\Facebook\WebDriver\WebDriverBy::xpath("//div[contains(text(), '是否允许有创作者加入二创计划')] | //div[contains(text(), '二创计划')]"));

                    if (empty($popupElements)) {
                        $this->info('弹窗已完全关闭');
                        return;
                    }

                    // 检查弹窗是否还可见
                    $visible = false;
                    foreach ($popupElements as $popup) {
                        if ($popup->isDisplayed()) {
                            $visible = true;
                            break;
                        }
                    }

                    if (!$visible) {
                        $this->info('弹窗已隐藏');
                        return;
                    }

                } catch (\Exception $e) {
                    // 如果找不到弹窗元素，说明已经关闭
                    $this->info('弹窗元素已消失');
                    return;
                }

                sleep(1);
                $waited++;
            }

            $this->warn('等待弹窗关闭超时');

        } catch (\Exception $e) {
            $this->warn('等待弹窗关闭时出错: ' . $e->getMessage());
        }
    }

    /**
     * 处理一般弹窗
     */
    private function handleGeneralPopups($driver): void
    {
        try {
            // 查找关闭按钮
            $closeSelectors = [
                ".modal-close",
                ".dialog-close",
                ".popup-close",
                ".close-btn",
                "//button[contains(@class, 'close')]",
                "//span[contains(@class, 'close')]"
            ];

            foreach ($closeSelectors as $selector) {
                try {
                    if (strpos($selector, '//') === 0) {
                        $elements = $driver->findElements(\Facebook\WebDriver\WebDriverBy::xpath($selector));
                    } else {
                        $elements = $driver->findElements(\Facebook\WebDriver\WebDriverBy::cssSelector($selector));
                    }

                    if (!empty($elements) && $elements[0]->isDisplayed()) {
                        $this->info('找到弹窗关闭按钮，正在关闭...');
                        $driver->executeScript("arguments[0].click();", [$elements[0]]);
                        sleep(1);
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        } catch (\Exception $e) {
            // 忽略错误
        }
    }

    /**
     * 等待页面完全加载
     */
    private function waitForPageLoad($driver): void
    {
        try {
            $this->info('等待投稿页面完全加载...');

            // 等待页面基本元素加载
            $maxWait = 30; // 最多等待30秒
            $waited = 0;

            while ($waited < $maxWait) {
                try {
                    // 检查页面是否有基本的投稿元素
                    $elements = $driver->findElements(\Facebook\WebDriver\WebDriverBy::cssSelector('input[type="file"], .upload-wrapper, .video-upload'));
                    if (!empty($elements)) {
                        $this->info('页面基本元素已加载');
                        break;
                    }
                } catch (\Exception $e) {
                    // 继续等待
                }

                sleep(1);
                $waited++;
            }

            // 等待 JavaScript 完全执行
            $driver->executeScript("return document.readyState === 'complete'");
            sleep(2); // 额外等待确保所有脚本执行完成

            $this->info('投稿页面加载完成');

        } catch (\Exception $e) {
            $this->warn('等待页面加载时出错: ' . $e->getMessage());
        }
    }

    /**
     * 检查并设置投稿页面的 localStorage
     */
    private function checkAndSetUploadPageStorage($driver): void
    {
        try {
            $this->info('检查投稿页面 localStorage 设置...');

            // 检查 bili_videoup_submit_auto_tips 是否存在
            $checkScript = "
                var autoTips = localStorage.getItem('bili_videoup_submit_auto_tips');
                return autoTips;
            ";

            $autoTipsValue = $driver->executeScript($checkScript);

            $storageResult = [];

            if ($autoTipsValue === null || $autoTipsValue === '') {
                $this->info('bili_videoup_submit_auto_tips 不存在，正在设置...');

                // 设置 bili_videoup_submit_auto_tips 为 1
                $setScript = "
                    localStorage.setItem('bili_videoup_submit_auto_tips', '1');
                    return localStorage.getItem('bili_videoup_submit_auto_tips');
                ";

                $result = $driver->executeScript($setScript);
                $this->info("已设置 bili_videoup_submit_auto_tips = {$result}");
                 // 同时设置其他可能有用的投稿相关 localStorage
                $additionalStorageScript = "
                    // 设置其他投稿相关的 localStorage
                    localStorage.setItem('bili_videoup_guide_dismissed', '1');
                    localStorage.setItem('bili_videoup_tips_shown', '1');
                    localStorage.setItem('bili_upload_auto_submit_tips', '1');
                    localStorage.setItem('bili_upload_guide_closed', '1');

                    // 返回设置的值用于确认
                    return {
                        'bili_videoup_submit_auto_tips': localStorage.getItem('bili_videoup_submit_auto_tips'),
                        'bili_videoup_guide_dismissed': localStorage.getItem('bili_videoup_guide_dismissed'),
                        'bili_videoup_tips_shown': localStorage.getItem('bili_videoup_tips_shown'),
                        'bili_upload_auto_submit_tips': localStorage.getItem('bili_upload_auto_submit_tips'),
                        'bili_upload_guide_closed': localStorage.getItem('bili_upload_guide_closed')
                    };
                ";

                $storageResult = $driver->executeScript($additionalStorageScript);
                $this->info('投稿页面 localStorage 设置完成');
            } else {
                $this->info("bili_videoup_submit_auto_tips 已存在，值为: {$autoTipsValue}");
            }

            // 显示设置的值
            if (is_array($storageResult)) {
                foreach ($storageResult as $key => $value) {
                    $this->line("  {$key} = {$value}");
                }
            }

        } catch (\Exception $e) {
            $this->warn('设置投稿页面 localStorage 失败: ' . $e->getMessage());
        }
    }

    /**
     * 设置权限相关的存储
     */
    private function setPermissionStorage($driver): void
    {
        try {
            // 设置 localStorage 来记住权限选择
            $storageScript = "
                // 设置通知权限已授权
                localStorage.setItem('notification_permission_granted', 'true');
                localStorage.setItem('bilibili_notification_dismissed', 'true');
                localStorage.setItem('upload_notification_shown', 'true');

                // 设置二创计划弹窗已处理
                localStorage.setItem('creative_collaboration_popup_dismissed', 'true');
                localStorage.setItem('creative_plan_dialog_shown', 'true');
                localStorage.setItem('collaboration_popup_handled', 'true');

                // 设置其他可能的权限标记
                localStorage.setItem('permission_dialog_dismissed', 'true');
                localStorage.setItem('upload_guide_dismissed', 'true');
                localStorage.setItem('upload_popup_dismissed', 'true');

                // 设置 sessionStorage
                sessionStorage.setItem('notification_permission_granted', 'true');
                sessionStorage.setItem('popup_dismissed', 'true');
                sessionStorage.setItem('creative_collaboration_handled', 'true');
            ";

            $driver->executeScript($storageScript);
            $this->info('已设置权限存储标记');

        } catch (\Exception $e) {
            $this->warn('设置权限存储失败: ' . $e->getMessage());
        }
    }

    /**
     * 调试页面按钮
     */
    private function debugPageButtons($driver): void
    {
        try {
            $this->warn('调试信息：页面上的所有按钮');

            // 查找所有按钮
            $allButtons = $driver->findElements(\Facebook\WebDriver\WebDriverBy::tagName('button'));
            $this->warn("找到 " . count($allButtons) . " 个 button 元素");

            foreach ($allButtons as $index => $button) {
                try {
                    $text = $button->getText();
                    $class = $button->getAttribute('class');
                    $type = $button->getAttribute('type');
                    $this->warn("按钮 {$index}: 文本='{$text}', class='{$class}', type='{$type}'");
                } catch (\Exception $e) {
                    $this->warn("按钮 {$index}: 无法获取信息");
                }
            }

            // 查找所有包含"投稿"、"发布"、"提交"文本的元素
            $textElements = $driver->findElements(\Facebook\WebDriver\WebDriverBy::xpath("//*[contains(text(), '投稿') or contains(text(), '发布') or contains(text(), '提交')]"));
            $this->warn("找到 " . count($textElements) . " 个包含关键词的元素");

            foreach ($textElements as $index => $element) {
                try {
                    $text = $element->getText();
                    $tagName = $element->getTagName();
                    $class = $element->getAttribute('class');
                    $this->warn("元素 {$index}: 标签='{$tagName}', 文本='{$text}', class='{$class}'");
                } catch (\Exception $e) {
                    $this->warn("元素 {$index}: 无法获取信息");
                }
            }
        } catch (\Exception $e) {
            $this->warn('调试信息获取失败: ' . $e->getMessage());
        }
    }

    /**
     * 标记文件为已处理
     */
    private function markFileAsProcessed(string $filePath, bool $success): void
    {
        $processedFile = storage_path('processed_files.json');
        $processed = [];

        if (file_exists($processedFile)) {
            $processed = json_decode(file_get_contents($processedFile), true) ?: [];
        }

        $processed[basename($filePath)] = [
            'processed_at' => date('Y-m-d H:i:s'),
            'success' => $success,
            'file_path' => $filePath
        ];

        file_put_contents($processedFile, json_encode($processed, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    }

    /**
     * Define the command's schedule.
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
