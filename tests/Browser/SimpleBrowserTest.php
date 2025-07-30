<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class SimpleBrowserTest extends DuskTestCase
{
    /**
     * 简单的浏览器启动测试
     */
    public function testBrowserLaunch(): void
    {
        $this->browse(function (Browser $browser) {
            echo "正在启动浏览器...\n";
            
            // 访问 Bilibili 主页
            $browser->visit('https://www.bilibili.com')
                    ->pause(3000); // 暂停3秒让您看到浏览器
            
            echo "浏览器已启动，正在访问 Bilibili 主页\n";
            echo "当前页面标题: " . $browser->driver->getTitle() . "\n";
            
            // 保存截图
            $browser->screenshot('browser_test');
            echo "截图已保存到 tests/Browser/screenshots/browser_test.png\n";
            
            // 再暂停5秒
            $browser->pause(5000);
            
            echo "测试完成\n";
        });
    }
}
