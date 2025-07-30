# Bilibili 自动投稿工具使用指南

🎬 基于 Laravel Zero 和 Laravel Dusk 开发的 Bilibili 视频自动投稿命令行工具。

## ✨ 功能特性

- 🔐 **智能登录管理** - 支持二维码扫码登录，自动保存和维护 Cookie
- 📁 **批量文件处理** - 自动扫描指定目录下的 MP4 文件，支持递归扫描
- 🎯 **自动化投稿** - 自动上传视频、设置分区、添加标签、选择活动
- 📊 **实时进度显示** - 美观的命令行界面，实时显示上传进度和状态
- 🔄 **智能重试机制** - 自动重试失败的操作，支持指数退避策略
- 📝 **详细日志记录** - 完整的操作日志和错误报告
- 📸 **自动截图保存** - 关键步骤自动截图，便于问题排查
- ⚙️ **灵活配置** - 支持自定义分区、标签、活动等参数

## 🚀 快速开始

### 环境要求

- PHP 8.2+
- Composer
- Chrome 浏览器
- ChromeDriver

### 基本使用

1. **扫描文件**
   ```bash
   php biliup up --scan
   ```

2. **查看统计**
   ```bash
   php biliup up --stats
   ```

3. **开始上传**
   ```bash
   php biliup up
   ```

## 📋 命令参数

| 参数 | 说明 |
|------|------|
| `--scan` | 仅扫描文件，不执行上传 |
| `--stats` | 显示处理统计信息 |
| `--reset` | 重置处理记录 |
| `--test-files=N` | 创建 N 个测试文件 |
| `--cleanup` | 清理测试文件 |
| `--dir=PATH` | 指定扫描目录 |

## ⚙️ 配置说明

### 环境变量

```env
# Dusk 配置
DUSK_START_MAXIMIZED=true
DUSK_HEADLESS_DISABLED=true

# 日志配置
LOG_CHANNEL=daily

# Bilibili 投稿配置
SCAN_DIRECTORY=D:\path\to\your\videos
BILIBILI_COOKIES_PATH=storage/cookies/bilibili_cookies.json
BILIBILI_UPLOAD_LOG=storage/logs/upload.log
BILIBILI_PROCESSED_FILES=storage/processed_files.json

# 投稿固定参数
BILIBILI_CATEGORY=音乐区
BILIBILI_TAGS=必剪创作,歌单
BILIBILI_ACTIVITY=音乐分享关

# 重试配置
BILIBILI_RETRY_ATTEMPTS=3
BILIBILI_RETRY_DELAY=5
BILIBILI_WAIT_BETWEEN_UPLOADS=3

# Cookie 配置
BILIBILI_COOKIE_EXPIRY_DAYS=7
```

## 🔧 使用流程

### 1. 首次使用

1. 配置 `.env` 文件中的扫描目录
2. 运行 `php patent bilibili:upload --scan` 检查文件
3. 运行 `php patent bilibili:upload` 开始上传
4. 首次运行会显示二维码，使用手机 Bilibili 客户端扫码登录

### 2. 日常使用

1. 将视频文件放入配置的扫描目录
2. 运行 `php patent bilibili:upload` 自动上传新文件
3. 查看日志和截图了解详细情况

### 3. 测试功能

```bash
# 创建测试文件
php patent bilibili:upload --test-files=3

# 上传测试文件
php patent bilibili:upload

# 清理测试文件
php patent bilibili:upload --cleanup
```

## 📊 监控和日志

### 日志文件

- **上传日志**: `storage/logs/bilibili_upload_*.log`
- **Laravel 日志**: `storage/logs/laravel.log`
- **错误报告**: `storage/logs/error_report_*.txt`

### 截图文件

- **成功截图**: `tests/Browser/screenshots/upload_success_*.png`
- **失败截图**: `tests/Browser/screenshots/upload_failed_*.png`
- **错误截图**: `tests/Browser/screenshots/error_*.png`

### 状态文件

- **Cookie 文件**: `storage/cookies/bilibili_cookies.json`
- **处理记录**: `storage/processed_files.json`

## 🛠️ 故障排除

### 常见问题

1. **ChromeDriver 版本不匹配**
   - 确保 ChromeDriver 版本与 Chrome 浏览器版本匹配
   - 下载地址: https://chromedriver.chromium.org/

2. **登录失败**
   - 检查网络连接
   - 清除 Cookie 文件重新登录
   - 确认账号状态正常

3. **文件上传失败**
   - 检查文件格式是否为 MP4
   - 确认文件大小符合 Bilibili 要求
   - 查看错误日志了解具体原因

4. **页面元素找不到**
   - Bilibili 页面可能有更新
   - 查看截图确认页面状态
   - 可能需要更新选择器

### 调试模式

```bash
# 启用详细日志
LOG_LEVEL=debug php patent bilibili:upload

# 禁用无头模式（显示浏览器窗口）
DUSK_HEADLESS_DISABLED=true php patent bilibili:upload
```

## ⚠️ 免责声明

本工具仅供学习和研究使用，请遵守 Bilibili 的使用条款和相关法律法规。使用本工具产生的任何后果由使用者自行承担。
