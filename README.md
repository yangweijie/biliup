# 🎬 Bilibili 自动投稿工具

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP Version" />
  <img src="https://img.shields.io/badge/Laravel%20Zero-11.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel Zero" />
  <img src="https://img.shields.io/badge/License-MIT-green?style=for-the-badge" alt="License" />
  <img src="https://img.shields.io/badge/Platform-Windows%20%7C%20Linux%20%7C%20macOS-lightgrey?style=for-the-badge" alt="Platform" />
</p>

<p align="center">
  <strong>基于 Laravel Zero 和 Laravel Dusk 开发的 Bilibili 视频自动投稿命令行工具</strong>
</p>

<p align="center">
  一个功能强大、稳定可靠的自动化工具，帮助您批量上传视频到 Bilibili 平台，节省大量重复操作时间。
</p>

---

## ✨ 核心功能

### 🔐 智能登录管理
- **二维码扫码登录** - 支持手机扫码快速登录
- **Cookie 自动管理** - 智能缓存和维护登录状态
- **登录状态检测** - 自动检测过期并重新登录
- **安全存储** - Cookie 安全加密存储

### 📁 批量文件处理
- **自动文件扫描** - 递归扫描指定目录下的 MP4 文件
- **智能过滤** - 自动过滤无效文件（0字节、非MP4格式）
- **重复检测** - 记录已处理文件，避免重复上传
- **文件统计** - 详细的文件信息和处理统计

### 🎯 自动化投稿
- **视频上传** - 自动上传视频文件并监控进度
- **分区设置** - 自动选择合适的视频分区
- **标签添加** - 自动添加预设标签
- **活动选择** - 自动参与相关活动
- **协议确认** - 自动勾选协议并提交投稿

### 📊 实时监控
- **进度显示** - 美观的命令行界面，实时显示上传进度
- **状态更新** - 实时更新文件处理状态
- **彩色输出** - 支持彩色文本和图标显示
- **交互确认** - 重要操作前的交互式确认

### 🔄 智能重试
- **自动重试** - 失败操作自动重试，支持指数退避策略
- **容错处理** - 多种元素定位策略，适应页面变化
- **异常恢复** - 智能异常处理和恢复机制
- **网络容错** - 网络超时和连接异常处理

### 📝 详细日志
- **操作日志** - 完整记录所有操作过程
- **错误追踪** - 详细的错误信息和堆栈跟踪
- **自动截图** - 关键步骤自动截图，便于问题排查
- **日志分级** - 支持不同级别的日志输出

---

## 🚀 快速开始

### 环境要求

- **PHP 8.2+** - 现代 PHP 版本支持
- **Composer** - PHP 依赖管理工具
- **Chrome 浏览器** - 用于自动化操作
- **ChromeDriver** - Chrome 浏览器驱动

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
php patent up --scan

# 2. 查看统计信息
php patent up --stats

# 3. 开始自动上传
php patent up

# 4. 跳过确认直接开始
php patent up --yes
```

---

## 📋 命令参数

| 参数 | 说明 | 示例 |
|------|------|------|
| `--scan` | 仅扫描文件，不执行上传 | `php patent up --scan` |
| `--stats` | 显示处理统计信息 | `php patent up --stats` |
| `--reset` | 重置处理记录 | `php patent up --reset` |
| `--test-files=N` | 创建 N 个测试文件 | `php patent up --test-files=5` |
| `--cleanup` | 清理测试文件 | `php patent up --cleanup` |
| `--dir=PATH` | 指定扫描目录 | `php patent up --dir=/path/to/videos` |
| `--yes` | 跳过确认直接开始上传 | `php patent up --yes` |

---

## ⚙️ 配置说明

### 环境变量配置

在 `.env` 文件中配置以下参数：

```env
# 视频文件扫描目录
SCAN_DIRECTORY=/path/to/your/videos

# 重试配置
MAX_RETRIES=3
RETRY_DELAY=5

# Cookie 配置
COOKIE_EXPIRE_HOURS=24

# 上传配置
UPLOAD_INTERVAL=30
```

### 投稿参数配置

默认投稿参数（可在代码中自定义）：
- **分区**: 音乐区
- **标签**: 必剪创作, 歌单
- **活动**: 音乐分享关

---

## 🏗️ 项目架构

### 核心服务类
- **FileScanner** - 文件扫描和管理
- **CookieManager** - Cookie 管理和验证
- **UploadLogger** - 日志记录和会话管理
- **RetryManager** - 重试机制和错误恢复
- **ExceptionHandler** - 异常处理和分类
- **ProgressDisplay** - 进度显示和用户界面

### 页面对象类
- **LoginPage** - 登录页面操作封装
- **UploadPage** - 投稿页面操作封装

### 命令类
- **BilibiliUploadCommand** - 主命令行接口

---

## 🧪 测试

### 运行测试
```bash
# 运行所有测试
php patent test

# 运行单元测试
php patent test --testsuite=Unit

# 运行浏览器测试
php patent test --testsuite=Browser
```

### 测试覆盖
- ✅ 文件扫描器单元测试
- ✅ Cookie 管理器单元测试
- ✅ Bilibili 投稿流程集成测试

---

## 📁 目录结构

```
biliup/
├── app/
│   ├── Commands/           # 命令行命令
│   └── Services/          # 核心服务类
├── tests/
│   ├── Browser/           # 浏览器自动化测试
│   │   ├── Pages/         # 页面对象类
│   │   └── screenshots/   # 自动截图
│   └── Unit/             # 单元测试
├── storage/
│   ├── cookies/          # Cookie 存储
│   └── logs/            # 日志文件
├── config/              # 配置文件
├── .env.example         # 环境配置示例
├── install.bat          # Windows 安装脚本
├── install.sh           # Linux/macOS 安装脚本
└── BILIBILI_UPLOAD_GUIDE.md  # 详细使用指南
```

---

## 🛡️ 安全和稳定性

- **Cookie 安全存储** - 加密存储用户登录信息
- **异常捕获恢复** - 完善的异常处理机制
- **文件完整性验证** - 确保文件完整性
- **网络超时处理** - 处理网络不稳定情况
- **浏览器崩溃恢复** - 自动恢复浏览器异常

---

## 📈 性能优化

- **批量文件处理** - 高效的批量处理机制
- **智能等待策略** - 优化页面加载等待时间
- **内存使用优化** - 合理的内存管理
- **并发控制** - 避免过度并发导致的问题
- **缓存机制** - 智能缓存提升性能

---

## ⚠️ 注意事项

1. **合规使用** - 请遵守 Bilibili 的使用条款和社区规范
2. **测试验证** - 建议在测试环境先验证功能正常性
3. **数据备份** - 定期备份重要的配置文件和日志
4. **网络环境** - 确保网络稳定性和足够的带宽
5. **版本同步** - 保持 Chrome 和 ChromeDriver 版本同步
6. **文件格式** - 目前仅支持 MP4 格式视频文件

---

## 📞 技术支持

### 文档资源
- 📖 [详细使用指南](BILIBILI_UPLOAD_GUIDE.md) - 完整的使用说明
- 📋 [项目总结](PROJECT_SUMMARY.md) - 项目功能和架构概览

### 问题排查
- 📝 检查 `storage/logs/` 目录下的日志文件
- 📸 查看 `tests/Browser/screenshots/` 目录下的截图
- 🧪 运行单元测试验证功能正常性

### 常见问题
- **登录失败** - 检查网络连接和 Cookie 文件
- **上传失败** - 查看日志文件中的详细错误信息
- **文件扫描问题** - 确认目录路径和文件权限
- **浏览器问题** - 更新 Chrome 和 ChromeDriver 版本

---

## 📄 许可证

本项目基于 [MIT 许可证](LICENSE) 开源，您可以自由使用、修改和分发。

---

## 🤝 贡献

欢迎提交 Issue 和 Pull Request 来改进这个项目！

---

<p align="center">
  <strong>让视频投稿变得简单高效 🚀</strong>
</p>
