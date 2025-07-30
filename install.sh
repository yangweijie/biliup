#!/bin/bash

echo "========================================"
echo "   Bilibili 自动投稿工具安装脚本"
echo "========================================"
echo

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 检查 PHP 是否安装
echo "[1/6] 检查 PHP 环境..."
if ! command -v php &> /dev/null; then
    echo -e "${RED}❌ PHP 未安装或未添加到 PATH${NC}"
    echo "请先安装 PHP 8.2+ 并添加到系统 PATH"
    exit 1
fi

# 检查 PHP 版本
PHP_VERSION=$(php -r "echo PHP_VERSION_ID;")
if [ "$PHP_VERSION" -lt 80200 ]; then
    echo -e "${RED}❌ PHP 版本过低，需要 8.2+${NC}"
    exit 1
fi
echo -e "${GREEN}✅ PHP 环境检查通过${NC}"

# 检查 Composer 是否安装
echo
echo "[2/6] 检查 Composer..."
if ! command -v composer &> /dev/null; then
    echo -e "${RED}❌ Composer 未安装或未添加到 PATH${NC}"
    echo "请先安装 Composer 并添加到系统 PATH"
    exit 1
fi
echo -e "${GREEN}✅ Composer 检查通过${NC}"

# 安装依赖
echo
echo "[3/6] 安装项目依赖..."
if ! composer install --no-dev --optimize-autoloader; then
    echo -e "${RED}❌ 依赖安装失败${NC}"
    exit 1
fi
echo -e "${GREEN}✅ 依赖安装完成${NC}"

# 复制配置文件
echo
echo "[4/6] 配置环境文件..."
if [ ! -f .env ]; then
    cp .env.example .env
    echo -e "${GREEN}✅ 已创建 .env 配置文件${NC}"
else
    echo -e "${YELLOW}⚠️ .env 文件已存在，跳过创建${NC}"
fi

# 创建必要目录
echo
echo "[5/6] 创建必要目录..."
mkdir -p storage/cookies
mkdir -p storage/logs
mkdir -p tests/Browser/screenshots
mkdir -p tests/Browser/console
mkdir -p tests/Browser/source

# 设置权限
chmod 755 storage/cookies
chmod 755 storage/logs
chmod 755 tests/Browser/screenshots
chmod 755 tests/Browser/console
chmod 755 tests/Browser/source

echo -e "${GREEN}✅ 目录创建完成${NC}"

# 检查 Chrome 和 ChromeDriver
echo
echo "[6/6] 检查浏览器环境..."
echo -e "${YELLOW}⚠️ 请确保已安装以下组件：${NC}"
echo "  - Google Chrome 浏览器"
echo "  - ChromeDriver (版本需与 Chrome 匹配)"
echo "  - ChromeDriver 已添加到 PATH 或放在项目目录"

# 尝试检查 Chrome
if command -v google-chrome &> /dev/null || command -v chromium-browser &> /dev/null; then
    echo -e "${GREEN}✅ 检测到 Chrome 浏览器${NC}"
else
    echo -e "${YELLOW}⚠️ 未检测到 Chrome 浏览器，请确保已安装${NC}"
fi

# 尝试检查 ChromeDriver
if command -v chromedriver &> /dev/null; then
    echo -e "${GREEN}✅ 检测到 ChromeDriver${NC}"
else
    echo -e "${YELLOW}⚠️ 未检测到 ChromeDriver，请确保已安装并添加到 PATH${NC}"
fi

# 显示下一步操作
echo
echo "========================================"
echo "          安装完成！"
echo "========================================"
echo
echo "📝 下一步操作："
echo
echo "1. 编辑 .env 文件，配置扫描目录："
echo "   SCAN_DIRECTORY=/path/to/your/videos"
echo
echo "2. 启动 ChromeDriver（如果未在后台运行）："
echo "   chromedriver &"
echo
echo "3. 扫描文件："
echo "   php patent bilibili:upload --scan"
echo
echo "4. 开始上传："
echo "   php patent bilibili:upload"
echo
echo "📚 更多信息请查看 BILIBILI_UPLOAD_GUIDE.md"
echo

# 使脚本可执行
chmod +x "$0"
