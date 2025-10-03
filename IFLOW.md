# 项目概览

这是一个基于 Laravel Zero 和 Laravel Dusk 开发的 Bilibili 视频自动投稿命令行工具。它能够自动扫描指定目录中的 MP4 视频文件，并通过浏览器自动化技术将它们上传到 Bilibili 平台。

主要技术栈：
- PHP 8.2+
- Laravel Zero 11.x
- Laravel Dusk (用于浏览器自动化)
- ChromeDriver (浏览器驱动)

## 核心功能

1.  **智能登录管理**：支持二维码扫码登录，自动管理 Cookie，检测登录状态并在过期时重新登录。
2.  **批量文件处理**：自动扫描指定目录下的 MP4 文件，过滤无效文件，记录已处理文件避免重复上传。
3.  **自动化投稿**：自动上传视频文件，设置分区、标签、活动，勾选协议并提交投稿。
4.  **实时监控**：在命令行界面显示上传进度和状态更新。
5.  **智能重试**：对失败操作进行自动重试，支持指数退避策略。
6.  **详细日志**：记录所有操作过程和错误信息，并在关键步骤自动截图。

## 目录结构

```
biliup/
├── app/                    # 核心应用代码
│   ├── Commands/           # 命令行命令
│   └── Services/          # 核心服务类
├── tests/                  # 测试代码
│   ├── Browser/           # 浏览器自动化测试
│   │   ├── Pages/         # 页面对象类
│   │   └── screenshots/   # 自动截图
│   └── Unit/             # 单元测试
├── storage/                # 存储目录
│   ├── cookies/          # Cookie 存储
│   └── logs/            # 日志文件
├── config/                 # 配置文件
├── .env.example            # 环境配置示例
├── install.bat             # Windows 安装脚本
├── install.sh              # Linux/macOS 安装脚本
└── BILIBILI_UPLOAD_GUIDE.md  # 详细使用指南
```

## 构建和运行

### 环境要求

- PHP 8.2+
- Composer
- Chrome 浏览器
- ChromeDriver

### 安装步骤

#### Windows 用户
```bash
# 运行安装脚本
install.bat
```

#### Linux/macOS 用户
```bash
# 添加执行权限并运行安装脚本
chmod +x install.sh && ./install.sh
```

#### 手动安装
```bash
# 1. 安装 PHP 依赖
composer install

# 2. 复制环境配置文件
cp .env.example .env

# 3. 配置扫描目录
# 编辑 .env 文件，设置 SCAN_DIRECTORY 为您的视频目录
```

### 基本使用

```bash
# 1. 扫描文件（查看待处理的视频文件）
php biliup up --scan

# 2. 查看统计信息
php biliup up --stats

# 3. 开始自动上传
php biliup up

# 4. 跳过确认直接开始
php biliup up --yes
```

## 测试

### 运行测试
```bash
# 运行所有测试
php biliup test

# 运行单元测试
php biliup test --testsuite=Unit

# 运行浏览器测试
php biliup test --testsuite=Browser
```

## 配置说明

在 `.env` 文件中配置以下关键参数：

```env
# 视频文件扫描目录
SCAN_DIRECTORY=/path/to/your/videos

# Cookie 和日志文件路径
BILIBILI_COOKIES_PATH=storage/cookies/bilibili_cookies.json
BILIBILI_UPLOAD_LOG=storage/logs/upload.log
BILIBILI_PROCESSED_FILES=storage/processed_files.json

# 投稿固定参数
BILIBILI_CATEGORY=音乐区
BILIBILI_TAGS=必剪创作,歌单
BILIBILI_ACTIVITY=音乐分享关

# 重试和延迟配置
BILIBILI_RETRY_ATTEMPTS=3
BILIBILI_RETRY_DELAY=5
BILIBILI_WAIT_BETWEEN_UPLOADS=3
BILIBILI_LOGIN_TIMEOUT=120
BILIBILI_UPLOAD_TIMEOUT=600

# Cookie 管理
BILIBILI_COOKIE_EXPIRY_DAYS=7

# 截图和调试
BILIBILI_SCREENSHOT_ON_ERROR=true
DUSK_SCREENSHOTS=true
DUSK_SCREENSHOTS_ON_FAILURE=true
DUSK_CONSOLE_LOGS=true
DUSK_SOURCE=true
```

## 开发约定

- **编码风格**：遵循 PSR 标准。
- **命令行接口**：使用 Laravel Zero 的命令行功能。
- **浏览器自动化**：使用 Laravel Dusk 进行浏览器操作。
- **日志记录**：使用 Laravel 的日志系统记录操作和错误。
- **配置管理**：使用 `.env` 文件进行环境配置。