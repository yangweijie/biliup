<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class CookieManager
{
    private string $cookiesPath;
    private int $cookieExpiryDays;

    public function __construct()
    {
        $this->cookiesPath = $this->getStoragePath('cookies/bilibili_cookies.json');
        $this->cookieExpiryDays = env('BILIBILI_COOKIE_EXPIRY_DAYS', 7);

        // 确保目录存在
        $this->ensureDirectoryExists();
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
     * 确保 Cookie 目录存在
     */
    private function ensureDirectoryExists(): void
    {
        $dir = dirname($this->cookiesPath);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * 检查 Cookie 文件是否存在
     */
    public function cookieFileExists(): bool
    {
        return file_exists($this->cookiesPath);
    }

    /**
     * 获取 Cookie 信息
     */
    public function getCookieInfo(): ?array
    {
        if (!$this->cookieFileExists()) {
            return null;
        }

        $content = file_get_contents($this->cookiesPath);
        $data = json_decode($content, true);

        if (!$data || !isset($data['saved_at'])) {
            return null;
        }

        $savedAt = strtotime($data['saved_at']);
        $expiryTime = $savedAt + ($this->cookieExpiryDays * 24 * 60 * 60);
        $isExpired = time() > $expiryTime;

        return [
            'saved_at' => $data['saved_at'],
            'cookie_count' => $data['count'] ?? 0,
            'user_agent' => $data['user_agent'] ?? '',
            'is_expired' => $isExpired,
            'expires_at' => date('Y-m-d H:i:s', $expiryTime),
            'days_until_expiry' => $isExpired ? 0 : ceil(($expiryTime - time()) / (24 * 60 * 60)),
        ];
    }

    /**
     * 检查 Cookie 是否过期
     */
    public function isCookieExpired(): bool
    {
        $info = $this->getCookieInfo();
        return $info ? $info['is_expired'] : true;
    }

    /**
     * 删除 Cookie 文件
     */
    public function deleteCookieFile(): bool
    {
        if ($this->cookieFileExists()) {
            $result = unlink($this->cookiesPath);
            if ($result) {
                Log::info('Cookie 文件已删除');
            }
            return $result;
        }
        return true;
    }

    /**
     * 备份当前 Cookie 文件
     */
    public function backupCookieFile(): ?string
    {
        if (!$this->cookieFileExists()) {
            return null;
        }

        $backupPath = $this->cookiesPath . '.backup.' . date('Y-m-d_H-i-s');
        
        if (copy($this->cookiesPath, $backupPath)) {
            Log::info('Cookie 文件已备份', ['backup_path' => $backupPath]);
            return $backupPath;
        }

        return null;
    }

    /**
     * 恢复 Cookie 文件
     */
    public function restoreCookieFile(string $backupPath): bool
    {
        if (!file_exists($backupPath)) {
            return false;
        }

        if (copy($backupPath, $this->cookiesPath)) {
            Log::info('Cookie 文件已恢复', ['backup_path' => $backupPath]);
            return true;
        }

        return false;
    }

    /**
     * 清理旧的备份文件
     */
    public function cleanupOldBackups(int $daysToKeep = 30): int
    {
        $cookieDir = dirname($this->cookiesPath);
        $backupFiles = glob($cookieDir . '/bilibili_cookies.json.backup.*');
        $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);
        $deletedCount = 0;

        foreach ($backupFiles as $file) {
            if (filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $deletedCount++;
                }
            }
        }

        if ($deletedCount > 0) {
            Log::info("清理了 {$deletedCount} 个旧的 Cookie 备份文件");
        }

        return $deletedCount;
    }

    /**
     * 验证 Cookie 文件格式
     */
    public function validateCookieFile(): array
    {
        $result = [
            'valid' => false,
            'errors' => [],
            'warnings' => [],
        ];

        if (!$this->cookieFileExists()) {
            $result['errors'][] = 'Cookie 文件不存在';
            return $result;
        }

        $content = file_get_contents($this->cookiesPath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $result['errors'][] = 'Cookie 文件不是有效的 JSON 格式: ' . json_last_error_msg();
            return $result;
        }

        // 检查必要字段
        $requiredFields = ['cookies', 'saved_at'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                $result['errors'][] = "缺少必要字段: {$field}";
            }
        }

        if (!empty($result['errors'])) {
            return $result;
        }

        // 检查 cookies 数组
        if (!is_array($data['cookies'])) {
            $result['errors'][] = 'cookies 字段必须是数组';
            return $result;
        }

        if (empty($data['cookies'])) {
            $result['warnings'][] = 'cookies 数组为空';
        }

        // 检查日期格式
        if (strtotime($data['saved_at']) === false) {
            $result['errors'][] = 'saved_at 字段不是有效的日期格式';
            return $result;
        }

        // 检查是否过期
        if ($this->isCookieExpired()) {
            $result['warnings'][] = 'Cookie 已过期';
        }

        $result['valid'] = empty($result['errors']);
        return $result;
    }

    /**
     * 获取 Cookie 统计信息
     */
    public function getCookieStats(): array
    {
        $stats = [
            'file_exists' => $this->cookieFileExists(),
            'file_size' => 0,
            'cookie_count' => 0,
            'is_expired' => true,
            'validation' => null,
        ];

        if ($stats['file_exists']) {
            $stats['file_size'] = filesize($this->cookiesPath);
            $stats['validation'] = $this->validateCookieFile();
            
            if ($stats['validation']['valid']) {
                $info = $this->getCookieInfo();
                if ($info) {
                    $stats['cookie_count'] = $info['cookie_count'];
                    $stats['is_expired'] = $info['is_expired'];
                    $stats['saved_at'] = $info['saved_at'];
                    $stats['expires_at'] = $info['expires_at'];
                    $stats['days_until_expiry'] = $info['days_until_expiry'];
                }
            }
        }

        return $stats;
    }

    /**
     * 获取 Cookie 文件路径
     */
    public function getCookiePath(): string
    {
        return $this->cookiesPath;
    }

    /**
     * 设置 Cookie 过期天数
     */
    public function setCookieExpiryDays(int $days): void
    {
        $this->cookieExpiryDays = $days;
    }

    /**
     * 获取 Cookie 过期天数
     */
    public function getCookieExpiryDays(): int
    {
        return $this->cookieExpiryDays;
    }
}
