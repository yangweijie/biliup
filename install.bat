@echo off
echo ========================================
echo    Bilibili 自动投稿工具安装脚本
echo ========================================
echo.

:: 检查 PHP 是否安装
echo [1/6] 检查 PHP 环境...
php --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ PHP 未安装或未添加到 PATH
    echo 请先安装 PHP 8.2+ 并添加到系统 PATH
    pause
    exit /b 1
)
echo ✅ PHP 环境检查通过

:: 检查 Composer 是否安装
echo.
echo [2/6] 检查 Composer...
composer --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ Composer 未安装或未添加到 PATH
    echo 请先安装 Composer 并添加到系统 PATH
    pause
    exit /b 1
)
echo ✅ Composer 检查通过

:: 安装依赖
echo.
echo [3/6] 安装项目依赖...
composer install --no-dev --optimize-autoloader
if %errorlevel% neq 0 (
    echo ❌ 依赖安装失败
    pause
    exit /b 1
)
echo ✅ 依赖安装完成

:: 复制配置文件
echo.
echo [4/6] 配置环境文件...
if not exist .env (
    copy .env.example .env
    echo ✅ 已创建 .env 配置文件
) else (
    echo ⚠️ .env 文件已存在，跳过创建
)

:: 创建必要目录
echo.
echo [5/6] 创建必要目录...
if not exist storage\cookies mkdir storage\cookies
if not exist storage\logs mkdir storage\logs
if not exist tests\Browser\screenshots mkdir tests\Browser\screenshots
if not exist tests\Browser\console mkdir tests\Browser\console
if not exist tests\Browser\source mkdir tests\Browser\source
echo ✅ 目录创建完成

:: 检查 Chrome 和 ChromeDriver
echo.
echo [6/6] 检查浏览器环境...
echo ⚠️ 请确保已安装以下组件：
echo   - Google Chrome 浏览器
echo   - ChromeDriver (版本需与 Chrome 匹配)
echo   - ChromeDriver 已添加到 PATH 或放在项目目录

:: 显示下一步操作
echo.
echo ========================================
echo           安装完成！
echo ========================================
echo.
echo 📝 下一步操作：
echo.
echo 1. 编辑 .env 文件，配置扫描目录：
echo    SCAN_DIRECTORY=你的视频文件目录
echo.
echo 2. 启动 ChromeDriver：
echo    chromedriver.exe
echo.
echo 3. 扫描文件：
echo    php patent bilibili:upload --scan
echo.
echo 4. 开始上传：
echo    php patent bilibili:upload
echo.
echo 📚 更多信息请查看 BILIBILI_UPLOAD_GUIDE.md
echo.
pause
