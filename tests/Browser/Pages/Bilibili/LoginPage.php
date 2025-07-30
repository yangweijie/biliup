<?php

namespace Tests\Browser\Pages\Bilibili;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Page;

class LoginPage extends Page
{
    /**
     * Get the URL for the page.
     */
    public function url(): string
    {
        return 'https://passport.bilibili.com/login';
    }

    /**
     * Assert that the browser is on the page.
     */
    public function assert(Browser $browser): void
    {
        $browser->assertUrlIs($this->url());
    }

    /**
     * Get the element shortcuts for the page.
     */
    public function elements(): array
    {
        return [
            '@qr-login-tab' => '.login-tab-item[data-tab-name="qr"]',
            '@qr-code' => '.qrcode-img img',
            '@qr-status' => '.qrcode-status',
            '@login-success' => '.login-success',
        ];
    }

    /**
     * 检查是否已经登录
     */
    public function isLoggedIn(Browser $browser): bool
    {
        return $this->validateCookies($browser);
    }

    /**
     * 等待用户扫码登录
     */
    public function waitForQrLogin(Browser $browser, int $timeout = 120): bool
    {
        echo "请使用 Bilibili 手机客户端扫描二维码登录...\n";
        
        try {
            // 点击二维码登录标签
            $browser->click('@qr-login-tab');
            $browser->waitFor('@qr-code', 10);
            
            // 等待登录成功
            $browser->waitFor('@login-success', $timeout);
            
            echo "登录成功！\n";
            return true;
        } catch (\Exception $e) {
            echo "登录超时或失败: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * 保存登录 Cookie
     */
    public function saveCookies(Browser $browser): void
    {
        $cookies = $browser->driver->manage()->getCookies();
        $cookiesPath = $this->getStoragePath('cookies/bilibili_cookies.json');

        // 确保目录存在
        if (!file_exists(dirname($cookiesPath))) {
            mkdir(dirname($cookiesPath), 0755, true);
        }

        // 保存 Cookie 和元数据
        $cookieData = [
            'cookies' => $cookies,
            'saved_at' => date('Y-m-d H:i:s'),
            'user_agent' => $browser->driver->executeScript('return navigator.userAgent;'),
            'url' => $browser->driver->getCurrentURL(),
            'count' => count($cookies),
        ];
        dump($cookieData);

        file_put_contents($cookiesPath, json_encode($cookieData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "Cookie 已保存到: $cookiesPath (共 " . count($cookies) . " 个)\n";
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
     * 加载保存的 Cookie
     */
    public function loadCookies(Browser $browser): bool
    {
        $cookiesPath = $this->getStoragePath('cookies/bilibili_cookies.json');

        if (!file_exists($cookiesPath)) {
            echo "Cookie 文件不存在: $cookiesPath\n";
            return false;
        }

        $cookieData = json_decode(file_get_contents($cookiesPath), true);

        if (!$cookieData || !isset($cookieData['cookies']) || !isset($cookieData['saved_at'])) {
            echo "Cookie 文件格式无效\n";
            return false;
        }

        // 检查 Cookie 是否过期（7天）
        $savedAt = strtotime($cookieData['saved_at']);
        $expiryTime = $savedAt + (7 * 24 * 60 * 60); // 7天

        if (time() > $expiryTime) {
            echo "Cookie 已过期，需要重新登录\n";
            return false;
        }

        $cookies = $cookieData['cookies'];

        try {
            // 先访问 bilibili 主页
            $browser->visit('https://www.bilibili.com');

            // 删除现有 cookies
            $browser->driver->manage()->deleteAllCookies();

            // 添加保存的 cookies
            $validCookies = 0;
            foreach ($cookies as $cookie) {
                try {
                    // 确保 cookie 有必要的字段
                    if (isset($cookie['name']) && isset($cookie['value'])) {
                        $browser->driver->manage()->addCookie($cookie);
                        $validCookies++;
                    }
                } catch (\Exception $e) {
                    // 忽略无效的 cookie
                    continue;
                }
            }

            echo "加载了 {$validCookies} 个有效 Cookie\n";

            // 刷新页面使 cookie 生效
            $browser->refresh();
            sleep(2);

            return $validCookies > 0;
        } catch (\Exception $e) {
            echo "加载 Cookie 时发生错误: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * 检查 Cookie 是否有效
     */
    public function validateCookies(Browser $browser): bool
    {
        try {
            // 访问需要登录的页面
            $browser->visit('https://space.bilibili.com');

            // 等待页面加载
            sleep(3);

            // 检查是否有登录用户的标识
            $currentUrl = $browser->driver->getCurrentURL();

            // 如果被重定向到登录页面，说明 Cookie 无效
            if (strpos($currentUrl, 'passport.bilibili.com') !== false) {
                return false;
            }

            // 尝试查找用户头像或用户名
            try {
                $browser->waitFor('.h-avatar, .nav-user-info, .user-info', 5);
                return true;
            } catch (\Exception $e) {
                return false;
            }
        } catch (\Exception $e) {
            echo "验证 Cookie 时发生错误: " . $e->getMessage() . "\n";
            return false;
        }
    }
}
