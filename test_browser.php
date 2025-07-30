<?php

require_once 'vendor/autoload.php';

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Chrome\ChromeOptions;

echo "=== 浏览器启动测试 ===\n";

try {
    // 配置 Chrome 选项
    $options = new ChromeOptions();
    
    // 添加参数
    $arguments = [
        '--start-maximized',
        '--disable-gpu',
        '--no-sandbox',
        '--disable-dev-shm-usage',
        '--disable-web-security',
        '--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
    ];
    
    // 检查是否启用无头模式
    // 先加载 .env 文件
    if (file_exists('.env')) {
        $envContent = file_get_contents('.env');
        $envLines = explode("\n", $envContent);
        foreach ($envLines as $line) {
            if (strpos($line, 'DUSK_HEADLESS_DISABLED=') === 0) {
                $value = trim(substr($line, strlen('DUSK_HEADLESS_DISABLED=')));
                putenv("DUSK_HEADLESS_DISABLED=$value");
                break;
            }
        }
    }

    $headlessDisabled = getenv('DUSK_HEADLESS_DISABLED') === 'true';
    
    if (!$headlessDisabled) {
        $arguments[] = '--headless=new';
        echo "运行在无头模式\n";
    } else {
        echo "运行在可见模式（应该能看到浏览器窗口）\n";
    }
    
    $options->addArguments($arguments);
    
    // 创建 DesiredCapabilities
    $capabilities = DesiredCapabilities::chrome();
    $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);
    
    echo "正在连接 ChromeDriver (http://localhost:9515)...\n";
    
    // 创建 WebDriver 实例
    $driver = RemoteWebDriver::create('http://localhost:9515', $capabilities);
    
    echo "✅ 浏览器启动成功！\n";
    
    // 访问 Bilibili
    echo "正在访问 Bilibili...\n";
    $driver->get('https://www.bilibili.com');
    
    // 等待页面加载
    sleep(3);
    
    $title = $driver->getTitle();
    echo "页面标题: $title\n";
    
    // 保存截图
    $screenshot = $driver->takeScreenshot();
    $screenshotPath = 'tests/Browser/screenshots/browser_test_' . date('Y-m-d_H-i-s') . '.png';
    
    // 确保目录存在
    $dir = dirname($screenshotPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    file_put_contents($screenshotPath, base64_decode($screenshot));
    echo "截图已保存: $screenshotPath\n";
    
    // 保持浏览器打开10秒
    echo "浏览器将保持打开10秒，您应该能看到 Bilibili 页面...\n";
    sleep(10);
    
    // 关闭浏览器
    $driver->quit();
    echo "✅ 测试完成，浏览器已关闭\n";
    
} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
    echo "详细信息:\n";
    echo $e->getTraceAsString() . "\n";
    
    // 常见问题排查
    echo "\n=== 问题排查建议 ===\n";
    echo "1. 确保 ChromeDriver 正在运行:\n";
    echo "   vendor\\laravel\\dusk\\bin\\chromedriver-win.exe --port=9515\n\n";
    echo "2. 检查 Chrome 浏览器是否已安装\n";
    echo "3. 检查防火墙是否阻止了连接\n";
    echo "4. 尝试重启 ChromeDriver\n";
}
