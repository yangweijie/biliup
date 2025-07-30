<?php

require_once 'vendor/autoload.php';

use App\Services\FileScanner;
use App\Services\CookieManager;

// 简单的上传脚本，不依赖 Laravel 测试框架

echo "=== Bilibili 自动投稿工具 ===\n";
echo "正在初始化...\n";

try {
    // 初始化服务
    $fileScanner = new FileScanner();
    $cookieManager = new CookieManager();
    $logger = new UploadLogger();
    $progressDisplay = new ProgressDisplay();

    // 扫描文件
    echo "正在扫描文件...\n";
    $allFiles = $fileScanner->scanMp4Files();
    $unprocessedFiles = $fileScanner->getUnprocessedFiles();

    echo "扫描结果:\n";
    echo "- 总文件数: " . count($allFiles) . "\n";
    echo "- 未处理文件数: " . count($unprocessedFiles) . "\n";

    if (empty($unprocessedFiles)) {
        echo "没有找到待上传的文件。\n";
        exit(0);
    }

    // 显示文件列表
    echo "\n待上传文件:\n";
    foreach ($unprocessedFiles as $index => $file) {
        $fileInfo = $fileScanner->getFileInfo($file);
        echo ($index + 1) . ". " . $fileInfo['name'] . " (" . $fileInfo['size_human'] . ")\n";
    }

    // 确认上传
    echo "\n确定要开始上传吗？(y/N): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);

    if (trim(strtolower($line)) !== 'y') {
        echo "用户取消上传。\n";
        exit(0);
    }

    echo "\n注意：由于 ChromeDriver 配置问题，当前无法启动浏览器自动化。\n";
    echo "请按照以下步骤手动完成设置：\n\n";

    echo "1. 下载并安装 ChromeDriver:\n";
    echo "   - 访问: https://chromedriver.chromium.org/\n";
    echo "   - 下载与您的 Chrome 版本匹配的 ChromeDriver\n";
    echo "   - 将 chromedriver.exe 放在 PATH 中或项目目录下\n\n";

    echo "2. 启动 ChromeDriver:\n";
    echo "   chromedriver.exe\n\n";

    echo "3. 重新运行上传命令:\n";
    echo "   php biliup up --yes\n\n";

    // 显示配置信息
    echo "当前配置:\n";
    echo "- 扫描目录: " . $fileScanner->getScanDirectory() . "\n";
    echo "- 分区: " . (getenv('BILIBILI_CATEGORY') ?: '音乐区') . "\n";
    echo "- 标签: " . (getenv('BILIBILI_TAGS') ?: '必剪创作,歌单') . "\n";
    echo "- 活动: " . (getenv('BILIBILI_ACTIVITY') ?: '音乐分享官') . "\n";

    // 显示 Cookie 状态
    $cookieStats = $cookieManager->getCookieStats();
    echo "- Cookie 状态: " . ($cookieStats['file_exists'] ? '已保存' : '未保存') . "\n";
    if ($cookieStats['file_exists']) {
        echo "  - Cookie 数量: " . $cookieStats['cookie_count'] . "\n";
        echo "  - 是否过期: " . ($cookieStats['is_expired'] ? '是' : '否') . "\n";
    }

    echo "\n工具已准备就绪，等待 ChromeDriver 配置完成后即可开始自动上传。\n";

} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "详细信息: " . $e->getTraceAsString() . "\n";
    exit(1);
}
