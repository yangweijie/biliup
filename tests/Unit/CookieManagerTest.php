<?php

namespace Tests\Unit;

use App\Services\CookieManager;
use PHPUnit\Framework\TestCase;

class CookieManagerTest extends TestCase
{
    private CookieManager $cookieManager;
    private string $testCookiePath;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 使用临时目录进行测试
        $tempDir = sys_get_temp_dir() . '/biliup_cookie_test_' . uniqid();
        mkdir($tempDir, 0755, true);
        
        $this->testCookiePath = $tempDir . '/bilibili_cookies.json';
        
        // 创建 CookieManager 实例并设置测试路径
        $this->cookieManager = new class($this->testCookiePath) extends CookieManager {
            private string $testPath;
            
            public function __construct(string $testPath)
            {
                $this->testPath = $testPath;
                parent::__construct();
            }
            
            protected function getCookiePath(): string
            {
                return $this->testPath;
            }
        };
    }

    protected function tearDown(): void
    {
        // 清理测试文件
        if (file_exists($this->testCookiePath)) {
            unlink($this->testCookiePath);
        }
        
        $dir = dirname($this->testCookiePath);
        if (is_dir($dir)) {
            rmdir($dir);
        }
        
        parent::tearDown();
    }

    public function test_cookie_file_does_not_exist_initially(): void
    {
        $this->assertFalse($this->cookieManager->cookieFileExists());
    }

    public function test_can_detect_cookie_file_exists(): void
    {
        // 创建测试 Cookie 文件
        $this->createTestCookieFile();
        
        $this->assertTrue($this->cookieManager->cookieFileExists());
    }

    public function test_can_get_cookie_info(): void
    {
        $this->createTestCookieFile();
        
        $info = $this->cookieManager->getCookieInfo();
        
        $this->assertNotNull($info);
        $this->assertArrayHasKey('saved_at', $info);
        $this->assertArrayHasKey('cookie_count', $info);
        $this->assertArrayHasKey('is_expired', $info);
        $this->assertEquals(2, $info['cookie_count']);
        $this->assertFalse($info['is_expired']); // 刚创建的不应该过期
    }

    public function test_can_detect_expired_cookies(): void
    {
        // 创建过期的 Cookie 文件
        $expiredData = [
            'cookies' => [
                ['name' => 'test1', 'value' => 'value1'],
                ['name' => 'test2', 'value' => 'value2']
            ],
            'saved_at' => date('Y-m-d H:i:s', time() - (8 * 24 * 60 * 60)), // 8天前
            'count' => 2
        ];
        
        file_put_contents($this->testCookiePath, json_encode($expiredData));
        
        $this->assertTrue($this->cookieManager->isCookieExpired());
    }

    public function test_can_validate_cookie_file(): void
    {
        $this->createTestCookieFile();
        
        $validation = $this->cookieManager->validateCookieFile();
        
        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['errors']);
    }

    public function test_can_detect_invalid_cookie_file(): void
    {
        // 创建无效的 JSON 文件
        file_put_contents($this->testCookiePath, 'invalid json');
        
        $validation = $this->cookieManager->validateCookieFile();
        
        $this->assertFalse($validation['valid']);
        $this->assertNotEmpty($validation['errors']);
    }

    public function test_can_delete_cookie_file(): void
    {
        $this->createTestCookieFile();
        $this->assertTrue($this->cookieManager->cookieFileExists());
        
        $result = $this->cookieManager->deleteCookieFile();
        
        $this->assertTrue($result);
        $this->assertFalse($this->cookieManager->cookieFileExists());
    }

    public function test_can_backup_cookie_file(): void
    {
        $this->createTestCookieFile();
        
        $backupPath = $this->cookieManager->backupCookieFile();
        
        $this->assertNotNull($backupPath);
        $this->assertTrue(file_exists($backupPath));
        
        // 清理备份文件
        if ($backupPath && file_exists($backupPath)) {
            unlink($backupPath);
        }
    }

    public function test_can_get_cookie_stats(): void
    {
        $this->createTestCookieFile();
        
        $stats = $this->cookieManager->getCookieStats();
        
        $this->assertTrue($stats['file_exists']);
        $this->assertGreaterThan(0, $stats['file_size']);
        $this->assertEquals(2, $stats['cookie_count']);
        $this->assertFalse($stats['is_expired']);
        $this->assertArrayHasKey('validation', $stats);
    }

    public function test_can_set_cookie_expiry_days(): void
    {
        $this->cookieManager->setCookieExpiryDays(14);
        $this->assertEquals(14, $this->cookieManager->getCookieExpiryDays());
    }

    /**
     * 创建测试 Cookie 文件
     */
    private function createTestCookieFile(): void
    {
        $cookieData = [
            'cookies' => [
                ['name' => 'test1', 'value' => 'value1'],
                ['name' => 'test2', 'value' => 'value2']
            ],
            'saved_at' => date('Y-m-d H:i:s'),
            'user_agent' => 'Test User Agent',
            'url' => 'https://www.bilibili.com',
            'count' => 2
        ];
        
        // 确保目录存在
        $dir = dirname($this->testCookiePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($this->testCookiePath, json_encode($cookieData));
    }
}
