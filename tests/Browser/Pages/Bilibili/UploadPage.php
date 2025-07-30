<?php

namespace Tests\Browser\Pages\Bilibili;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Page;

class UploadPage extends Page
{
    /**
     * Get the URL for the page.
     */
    public function url(): string
    {
        return 'https://member.bilibili.com/platform/upload/video/frame';
    }

    /**
     * Assert that the browser is on the page.
     */
    public function assert(Browser $browser): void
    {
        $browser->assertUrlContains('member.bilibili.com/platform/upload');
    }

    /**
     * Get the element shortcuts for the page.
     */
    public function elements(): array
    {
        return [
            // 文件上传相关
            '@file-input' => 'input[type="file"]',
            '@upload-area' => '.upload-v2, .upload-area, .upload-zone',
            '@upload-progress' => '.upload-progress, .progress, .upload-status',
            '@upload-success' => '.upload-success, .upload-complete, .success-icon',
            '@upload-error' => '.upload-error, .error-message, .upload-failed',

            // 视频信息编辑
            '@title-input' => 'input[placeholder*="标题"], input[name="title"], .title-input input',
            '@description-textarea' => 'textarea[placeholder*="简介"], textarea[name="desc"], .desc-input textarea',

            // 分区选择
            '@category-select' => '.category-select, .type-select, .partition-select',
            '@category-dropdown' => '.category-dropdown, .type-dropdown, .partition-dropdown',
            '@category-music' => 'li[data-value*="音乐"], .category-item:contains("音乐")',

            // 标签管理
            '@tag-input' => '.tag-input input, .tags-input input, input[placeholder*="标签"]',
            '@tag-add-btn' => '.tag-add-btn, .add-tag, .tag-confirm',
            '@tag-list' => '.tag-list, .tags-list, .selected-tags',

            // 活动选择
            '@activity-select' => '.activity-select, .topic-select, .event-select',
            '@activity-dropdown' => '.activity-dropdown, .topic-dropdown, .event-dropdown',
            '@activity-music' => 'li:contains("音乐分享关"), .activity-item:contains("音乐分享关")',

            // 提交相关
            '@agree-checkbox' => '.agree-checkbox input, .protocol-checkbox input, input[type="checkbox"]',
            '@submit-btn' => '.submit-btn, .publish-btn, .confirm-btn, button:contains("立即投稿")',
            '@submit-success' => '.submit-success, .publish-success, .success-message',
            '@submit-error' => '.submit-error, .publish-error, .error-message',

            // 其他元素
            '@loading' => '.loading, .spinner, .uploading',
            '@modal' => '.modal, .dialog, .popup',
            '@close-modal' => '.modal-close, .dialog-close, .close-btn',
        ];
    }

    /**
     * 上传视频文件
     */
    public function uploadVideo(Browser $browser, string $filePath): bool
    {
        if (!file_exists($filePath)) {
            echo "文件不存在: $filePath\n";
            return false;
        }

        $fileName = basename($filePath);
        $fileSize = filesize($filePath);
        echo "开始上传文件: {$fileName} (大小: " . $this->formatFileSize($fileSize) . ")\n";

        try {
            // 等待页面完全加载
            $browser->waitUntilMissing('@loading', 10);

            // 尝试多种方式找到上传区域
            $uploadFound = false;
            $selectors = ['@upload-area', '@file-input', '.upload-btn', '.select-file'];

            foreach ($selectors as $selector) {
                try {
                    $browser->waitFor($selector, 5);
                    $uploadFound = true;
                    break;
                } catch (\Exception $e) {
                    continue;
                }
            }

            if (!$uploadFound) {
                throw new \Exception("找不到上传区域");
            }

            // 上传文件
            echo "正在上传文件...\n";
            $browser->attach('@file-input', $filePath);

            // 等待上传开始
            sleep(2);

            // 等待上传完成
            $this->waitForUploadComplete($browser);

            echo "文件上传完成\n";
            return true;
        } catch (\Exception $e) {
            echo "上传失败: " . $e->getMessage() . "\n";
            return false;
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
     * 等待上传完成
     */
    private function waitForUploadComplete(Browser $browser, int $timeout = 600): void
    {
        $startTime = time();
        $lastProgress = '';
        $stuckCount = 0;

        echo "等待上传完成（超时: {$timeout}秒）...\n";

        while (time() - $startTime < $timeout) {
            try {
                // 检查是否有上传错误
                if ($browser->element('@upload-error')) {
                    $errorText = $browser->element('@upload-error')->getText();
                    throw new \Exception("上传失败: {$errorText}");
                }

                // 检查是否有上传成功标识
                $successSelectors = ['@upload-success', '.upload-complete', '.success-status', '.upload-done'];
                foreach ($successSelectors as $selector) {
                    try {
                        if ($browser->element($selector)) {
                            echo "检测到上传成功标识\n";
                            return;
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }

                // 检查上传进度
                $currentProgress = $this->getUploadProgress($browser);
                if ($currentProgress) {
                    if ($currentProgress !== $lastProgress) {
                        echo "上传进度: {$currentProgress}\n";
                        $lastProgress = $currentProgress;
                        $stuckCount = 0;
                    } else {
                        $stuckCount++;
                        if ($stuckCount > 30) { // 60秒没有进度变化
                            echo "上传进度停滞，可能出现问题\n";
                        }
                    }
                }

                // 检查是否可以进行下一步（标题输入可见）
                try {
                    if ($browser->element('@title-input')) {
                        echo "检测到标题输入框，上传可能已完成\n";
                        return;
                    }
                } catch (\Exception $e) {
                    // 继续等待
                }

                sleep(2);
            } catch (\Exception $e) {
                if (strpos($e->getMessage(), '上传失败') !== false) {
                    throw $e;
                }
                // 其他异常继续等待
                sleep(2);
            }
        }

        throw new \Exception("上传超时（{$timeout}秒）");
    }

    /**
     * 获取上传进度
     */
    private function getUploadProgress(Browser $browser): ?string
    {
        $progressSelectors = [
            '@upload-progress',
            '.progress-text',
            '.upload-percent',
            '.progress-bar',
            '.upload-status'
        ];

        foreach ($progressSelectors as $selector) {
            try {
                $element = $browser->element($selector);
                if ($element) {
                    $text = $element->getText();
                    if (!empty($text)) {
                        return $text;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * 设置视频标题
     */
    public function setTitle(Browser $browser, string $title): void
    {
        $browser->waitFor('@title-input', 10)
                ->clear('@title-input')
                ->type('@title-input', $title);
        
        echo "设置标题: $title\n";
    }

    /**
     * 选择分区
     */
    public function selectCategory(Browser $browser, string $category = '音乐区'): void
    {
        echo "正在选择分区: {$category}\n";

        try {
            // 等待分区选择器出现
            $categorySelectors = ['@category-select', '.type-select', '.partition-select', '.category-btn'];
            $selectorFound = false;

            foreach ($categorySelectors as $selector) {
                try {
                    $browser->waitFor($selector, 5);
                    $browser->click($selector);
                    $selectorFound = true;
                    echo "点击分区选择器成功\n";
                    break;
                } catch (\Exception $e) {
                    continue;
                }
            }

            if (!$selectorFound) {
                throw new \Exception("找不到分区选择器");
            }

            // 等待下拉菜单出现
            sleep(1);

            // 尝试多种方式选择音乐分区
            $categoryOptions = [
                "li:contains('{$category}')",
                ".category-item:contains('{$category}')",
                ".option:contains('{$category}')",
                "[data-value*='音乐']",
                "[data-category*='音乐']"
            ];

            $categorySelected = false;
            foreach ($categoryOptions as $option) {
                try {
                    $browser->waitFor($option, 3)->click($option);
                    $categorySelected = true;
                    echo "选择分区成功: {$category}\n";
                    break;
                } catch (\Exception $e) {
                    continue;
                }
            }

            if (!$categorySelected) {
                // 尝试通过文本查找
                try {
                    $browser->clickLink($category);
                    echo "通过链接文本选择分区成功: {$category}\n";
                } catch (\Exception $e) {
                    throw new \Exception("无法找到分区选项: {$category}");
                }
            }

            // 等待选择生效
            sleep(1);

        } catch (\Exception $e) {
            echo "选择分区失败: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    /**
     * 添加标签
     */
    public function addTags(Browser $browser, array $tags): void
    {
        echo "正在添加标签: " . implode(', ', $tags) . "\n";

        foreach ($tags as $index => $tag) {
            $tag = trim($tag);
            if (empty($tag)) {
                continue;
            }

            try {
                echo "添加标签 " . ($index + 1) . "/{count($tags)}: {$tag}\n";

                // 查找标签输入框
                $tagInputSelectors = ['@tag-input', '.tag-input input', '.tags-input input', 'input[placeholder*="标签"]'];
                $inputFound = false;

                foreach ($tagInputSelectors as $selector) {
                    try {
                        $browser->waitFor($selector, 3);
                        $browser->clear($selector)->type($selector, $tag);
                        $inputFound = true;
                        break;
                    } catch (\Exception $e) {
                        continue;
                    }
                }

                if (!$inputFound) {
                    throw new \Exception("找不到标签输入框");
                }

                // 等待输入完成
                sleep(1);

                // 查找添加按钮或按回车
                $addButtonSelectors = ['@tag-add-btn', '.add-tag', '.tag-confirm', '.tag-add'];
                $buttonFound = false;

                foreach ($addButtonSelectors as $selector) {
                    try {
                        $browser->click($selector);
                        $buttonFound = true;
                        echo "通过按钮添加标签: {$tag}\n";
                        break;
                    } catch (\Exception $e) {
                        continue;
                    }
                }

                if (!$buttonFound) {
                    // 尝试按回车键
                    try {
                        $browser->keys('@tag-input', '{enter}');
                        echo "通过回车键添加标签: {$tag}\n";
                    } catch (\Exception $e) {
                        throw new \Exception("无法添加标签，找不到添加按钮或回车键无效");
                    }
                }

                // 等待标签添加完成
                sleep(1);

                // 验证标签是否添加成功
                try {
                    $browser->waitForText($tag, 3);
                    echo "✓ 标签添加成功: {$tag}\n";
                } catch (\Exception $e) {
                    echo "⚠ 无法验证标签是否添加成功: {$tag}\n";
                }

            } catch (\Exception $e) {
                echo "✗ 添加标签失败 ({$tag}): " . $e->getMessage() . "\n";
                // 继续添加下一个标签
                continue;
            }
        }

        echo "标签添加完成\n";
    }

    /**
     * 选择活动
     */
    public function selectActivity(Browser $browser, string $activity = '音乐分享关'): void
    {
        echo "正在选择活动: {$activity}\n";

        try {
            // 查找活动选择器
            $activitySelectors = ['@activity-select', '.topic-select', '.event-select', '.activity-btn'];
            $selectorFound = false;

            foreach ($activitySelectors as $selector) {
                try {
                    $browser->waitFor($selector, 5);
                    $browser->click($selector);
                    $selectorFound = true;
                    echo "点击活动选择器成功\n";
                    break;
                } catch (\Exception $e) {
                    continue;
                }
            }

            if (!$selectorFound) {
                echo "⚠ 未找到活动选择器，可能此页面不支持活动选择\n";
                return;
            }

            // 等待下拉菜单出现
            sleep(1);

            // 尝试多种方式选择活动
            $activityOptions = [
                "li:contains('{$activity}')",
                ".activity-item:contains('{$activity}')",
                ".topic-item:contains('{$activity}')",
                ".option:contains('{$activity}')",
                "[data-value*='音乐分享关']",
                "[data-activity*='音乐分享关']"
            ];

            $activitySelected = false;
            foreach ($activityOptions as $option) {
                try {
                    $browser->waitFor($option, 3)->click($option);
                    $activitySelected = true;
                    echo "选择活动成功: {$activity}\n";
                    break;
                } catch (\Exception $e) {
                    continue;
                }
            }

            if (!$activitySelected) {
                // 尝试通过文本查找
                try {
                    $browser->clickLink($activity);
                    echo "通过链接文本选择活动成功: {$activity}\n";
                } catch (\Exception $e) {
                    echo "⚠ 无法找到活动选项: {$activity}，跳过活动选择\n";
                    return;
                }
            }

            // 等待选择生效
            sleep(1);

        } catch (\Exception $e) {
            echo "选择活动失败: " . $e->getMessage() . "\n";
            // 活动选择失败不应该阻止整个流程
        }
    }

    /**
     * 同意协议并提交
     */
    public function submitVideo(Browser $browser): bool
    {
        echo "正在提交投稿...\n";

        try {
            // 查找并勾选同意协议复选框
            $checkboxSelectors = ['@agree-checkbox', '.protocol-checkbox input', '.agree-protocol input', 'input[type="checkbox"]'];
            $checkboxFound = false;

            foreach ($checkboxSelectors as $selector) {
                try {
                    $browser->waitFor($selector, 3);
                    if (!$browser->element($selector)->isSelected()) {
                        $browser->check($selector);
                        echo "已勾选同意协议\n";
                    } else {
                        echo "协议复选框已勾选\n";
                    }
                    $checkboxFound = true;
                    break;
                } catch (\Exception $e) {
                    continue;
                }
            }

            if (!$checkboxFound) {
                echo "⚠ 未找到协议复选框，继续提交流程\n";
            }

            // 等待一下确保页面状态稳定
            sleep(2);

            // 查找并点击提交按钮
            $submitSelectors = ['@submit-btn', '.publish-btn', '.confirm-btn', 'button:contains("立即投稿")', 'button:contains("发布")', 'button:contains("提交")'];
            $submitFound = false;

            foreach ($submitSelectors as $selector) {
                try {
                    $element = $browser->element($selector);
                    if ($element && $element->isEnabled()) {
                        $browser->click($selector);
                        $submitFound = true;
                        echo "点击提交按钮成功\n";
                        break;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            if (!$submitFound) {
                throw new \Exception("找不到可用的提交按钮");
            }

            // 等待提交处理
            echo "等待提交处理...\n";
            sleep(3);

            // 检查提交结果
            $submitTimeout = 60; // 60秒超时
            $startTime = time();

            while (time() - $startTime < $submitTimeout) {
                try {
                    // 检查是否有错误信息
                    $errorSelectors = ['@submit-error', '.error-message', '.publish-error', '.submit-failed'];
                    foreach ($errorSelectors as $selector) {
                        try {
                            $errorElement = $browser->element($selector);
                            if ($errorElement) {
                                $errorText = $errorElement->getText();
                                throw new \Exception("提交失败: {$errorText}");
                            }
                        } catch (\Exception $e) {
                            if (strpos($e->getMessage(), '提交失败') !== false) {
                                throw $e;
                            }
                            continue;
                        }
                    }

                    // 检查是否有成功信息
                    $successSelectors = ['@submit-success', '.publish-success', '.success-message', '.submit-complete'];
                    foreach ($successSelectors as $selector) {
                        try {
                            if ($browser->element($selector)) {
                                echo "投稿提交成功\n";
                                return true;
                            }
                        } catch (\Exception $e) {
                            continue;
                        }
                    }

                    // 检查是否跳转到成功页面
                    $currentUrl = $browser->driver->getCurrentURL();
                    if (strpos($currentUrl, 'success') !== false || strpos($currentUrl, 'complete') !== false) {
                        echo "检测到成功页面跳转\n";
                        return true;
                    }

                    sleep(2);
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), '提交失败') !== false) {
                        throw $e;
                    }
                    sleep(2);
                }
            }

            // 超时但没有明确的错误，可能成功了
            echo "⚠ 提交超时，但未检测到错误信息，可能已成功\n";
            return true;

        } catch (\Exception $e) {
            echo "提交失败: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * 完整的投稿流程
     */
    public function completeUpload(Browser $browser, string $filePath, array $config = []): bool
    {
        // 默认配置
        $defaultConfig = [
            'title' => basename($filePath, '.mp4'),
            'category' => env('BILIBILI_CATEGORY', '音乐区'),
            'tags' => explode(',', env('BILIBILI_TAGS', '必剪创作,歌单')),
            'activity' => env('BILIBILI_ACTIVITY', '音乐分享关'),
        ];
        
        $config = array_merge($defaultConfig, $config);
        
        // 1. 上传视频
        if (!$this->uploadVideo($browser, $filePath)) {
            return false;
        }
        
        // 2. 设置标题
        $this->setTitle($browser, $config['title']);
        
        // 3. 选择分区
        $this->selectCategory($browser, $config['category']);
        
        // 4. 添加标签
        $this->addTags($browser, $config['tags']);
        
        // 5. 选择活动
        $this->selectActivity($browser, $config['activity']);
        
        // 6. 提交投稿
        return $this->submitVideo($browser);
    }
}
