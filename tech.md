# 技术

 "bufferutil": "^4.0.7",
    "extract-zip": "^2.0.1",
    "puppeteer": "^19.4.1",
    "puppeteer-core": "^19.8.0",
    "restler": "^3.4.0",
    "typescript": "^5.0.2",
    "utf-8-validate": "^6.0.3"

## 表单

### 输入框

```
page.type('#__BVID__13', '131453000121')
```

### 复选框

```
await page.click('#__BVID__20');
```

### 下拉选项

```
const select = page.waitforselector('#__BVID__41');
select.select('13');
```

### 截屏

```
fullPage
```

### 请求响应监听

```
 page.on('response', async response => {
	// console.log('url:' + response.url());
	// 下载列表里的pdf
    if (response.url().indexOf('downloadZmnPdfFile') > 0){
    	console.log('url:'+response.url());
    	console.log(response.text());
        response.buffer().then(function (value) {
            // orc(value);
            // console.log(value);
        });
    }
}
```

### 获取cookie

```
await page.goto('https://system.reins.jp/main/BK/GBK001210');
const cookies = await page.cookies();
console.log('cookies');
console.log(cookies);
console.log(`${cookies[0].name}=${cookies[0].value}`);
```

## 错误

- Navigation failed because browser has disconnected!

# 进程守护

### 自启目录

shell:startup

C:\ProgramData\Microsoft\Windows\Start Menu\Programs\StartUp

### pm2

#### pm2-install
``` shell
cd pm2 installer
npm run configure
npm run configure-policy
npm run setup
```



```shell
cnpm install pm2@latest -g & cnpm install pm2-logrotate -g & pnpm i pm2-windows-service -g &  pm2-service-install
```

```shell
pm2 start C:\git\cpquery_crew\rize.js --log C:\git\log.log --time --name=crew --watch=C:\git\cpquery_crew --cron="5 0 * * *" & pm2 log 
```

### forever

```shell
cnpm install forever -g
```


### nssm

> 要在storage 目录里建一个 crew.in.log  和crew.out.log

~~~
C:\git\web\cpquery_crew\nssm.exe install crew C:\git\web\cpquery_crew\php.exe
C:\git\web\cpquery_crew\nssm.exe set crew AppParameters "C:\git\web\cpquery_crew\patent crew"
C:\git\web\cpquery_crew\nssm.exe set crew AppDirectory C:\git\web\cpquery_crew
C:\git\web\cpquery_crew\nssm.exe set crew AppExit Default Restart
C:\git\web\cpquery_crew\nssm.exe set crew AppPriority HIGH_PRIORITY_CLASS
C:\git\web\cpquery_crew\nssm.exe set crew AppStdin C:\git\web\cpquery_crew\storage\crew.in.log
C:\git\web\cpquery_crew\nssm.exe set crew AppStdout C:\git\web\cpquery_crew\storage\crew.out.log
C:\git\web\cpquery_crew\nssm.exe set crew AppStderr C:\git\web\cpquery_crew\storage\crew.out.log
C:\git\web\cpquery_crew\nssm.exe set crew AppRotateFiles 1
C:\git\web\cpquery_crew\nssm.exe set crew AppRotateOnline 1
C:\git\web\cpquery_crew\nssm.exe set crew AppRotateBytes 10485760
C:\git\web\cpquery_crew\nssm.exe set crew Description 从国知局采集专利
C:\git\web\cpquery_crew\nssm.exe set crew DisplayName crew
C:\git\web\cpquery_crew\nssm.exe set crew ObjectName .\jay "****"
C:\git\web\cpquery_crew\nssm.exe set crew Start SERVICE_AUTO_START
C:\git\web\cpquery_crew\nssm.exe set crew Type SERVICE_WIN32_OWN_PROCESS
~~~

### 计划任务
#### 项目 php.ini 里配置系统目录

~~~ ini
sys_temp_dir = C:/git/cpquery_crew/storage/tmp
~~~

#### 本地安全策略

本地策略->安全选项-> 域控制器：允许服务器操作者计划任务

#### path里加入 git的目录（smartgit）

#### 启用所有任务历史记录
计划任务右侧有 链接

### composer 禁用ssl
composer config -g -- disable-tls true

火狐离线下载
https://cdn.stubdownloader.services.mozilla.com/builds/firefox-latest-ssl/zh-CN/win64/5b4e34fbf944e0602dc8ce48259c93060a61ab14bd4f7e5f41d83c05dc9770f0/Firefox%20Setup%20133.0.3.exe


Installing shortcut: "C:\\Users\\Administrator\\AppData\\Roaming\\Microsoft\\Windows\\Start Menu\\Programs\\SnoreToast\\0.7.0\\SnoreToast.lnk" "D:\\git\\cpquery_crew\\vendor\\jolicode\\jolinotif\\bin\\snoreToast\\snoretoast-x86.exe" Snore.DesktopToasts.0.7.0


### 组策略修复
~~~ gpedit-repaire.bat
@echo off

pushd "%~dp0"

dir /b C:\Windows\servicing\Packages\Microsoft-Windows-GroupPolicy-ClientExtensions-Package~3*.mum >List.txt

dir /b C:\Windows\servicing\Packages\Microsoft-Windows-GroupPolicy-ClientTools-Package~3*.mum >>List.txt

for /f %%i in ('findstr /i . List.txt 2^>nul') do dism /online /norestart /add-package:"C:\Windows\servicing\Packages\%%i"

pause
~~~
