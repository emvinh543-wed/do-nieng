@echo off
setlocal
title GLOWDRINKS - TRINH KHOI DONG VA TU DONG SUA LOI HE THONG
color 0B

set "ROOT_DIR=%~dp0"
set "APP_DIR=%~dp0Trang chinh"
set "PORT=8000"

:MAIN_MENU
cls
echo ===============================================================================
echo                HE THONG QUAN LY VA BAN DO UONG GLOWDRINKS
echo                   (Bo cong cu tu dong sua loi he thong)
echo ===============================================================================
echo.
echo   [1] Khoi dong Server va Mo Web (Tu dong sua loi toan bo) [Mac dinh]
echo   [2] Kiem tra va Chuan doan he thong (PHP, MySQL, Database, Extensions)
echo   [3] Khoi phuc Database goc (Reset va Tu dong nap lai toan bo du lieu)
echo   [4] Giai phong cong mang %PORT% (Dong cac server cu bi treo)
echo   [5] Quet loi cu phap ma nguon PHP (Lint Check)
echo   [6] Doi cong Server (Port hien tai: %PORT%)
echo   [0] Thoat
echo.
echo ===============================================================================
echo He thong se tu dong chon [1] sau 5 giay neu ban khong nhap...
echo ===============================================================================

choice /c 1234560 /t 5 /d 1 /n /m "Nhap lua chon cua ban [1-6, 0]: "
set "USER_CHOICE=%ERRORLEVEL%"

if "%USER_CHOICE%"=="1" goto DO_START
if "%USER_CHOICE%"=="2" goto DO_DIAGNOSE
if "%USER_CHOICE%"=="3" goto DO_RESET_DB
if "%USER_CHOICE%"=="4" goto DO_CLEAN_PORT
if "%USER_CHOICE%"=="5" goto DO_LINT
if "%USER_CHOICE%"=="6" goto DO_CHANGE_PORT
if "%USER_CHOICE%"=="7" goto DO_EXIT
goto MAIN_MENU

:DO_START
cls
echo ===============================================================================
echo                   DANG TU DONG KIEM TRA VA KHOI DONG SERVER
echo ===============================================================================
echo.

call :FIND_PHP
if errorlevel 1 goto PAUSE_AND_MENU

echo [*] Dang tien hanh tu dong chuan doan va sua loi...
php "%ROOT_DIR%tools\system_diagnose.php" check %PORT%
if errorlevel 1 (
    echo.
    echo [CANH BAO] He thong phat hien co loi chua the tu dong khac phuc.
    echo Vui long kiem tra lai thong bao ben tren truoc khi tiep tuc!
    echo.
    pause
)

echo.
echo [1/3] Dang khoi dong PHP Local Server tai port %PORT%...
cd /d "%APP_DIR%"
start "GlowDrinks Server (Port %PORT%)" php -S 127.0.0.1:%PORT%

echo [2/3] Dang doi server san sang...
ping 127.0.0.1 -n 3 >nul

echo [3/3] Dang mo website GlowDrinks tren trinh duyet...
start http://127.0.0.1:%PORT%/

echo.
echo ===============================================================================
echo  KHOI DONG THANH CONG!
echo  - Dia chi Website : http://127.0.0.1:%PORT%/
echo  - Tai khoan Admin : admin  /  Mat khau: 123456
echo  - Luu y: Giu nguyen cua so PHP Server dang chay de khong bi ngat ket noi.
echo ===============================================================================
echo.
pause
goto MAIN_MENU

:DO_DIAGNOSE
cls
echo ===============================================================================
echo                     CHUAN DOAN VA KIEM TRA HE THONG
echo ===============================================================================
echo.
call :FIND_PHP
if errorlevel 1 goto PAUSE_AND_MENU

php "%ROOT_DIR%tools\system_diagnose.php" check %PORT%
echo.
pause
goto MAIN_MENU

:DO_RESET_DB
cls
echo ===============================================================================
echo                       KHOI PHUC DATABASE GOC
echo ===============================================================================
echo.
echo CANH BAO: Thao tac nay se xoa toan bo du lieu cu va nap lai database goc
echo (bao gom danh muc, san pham, anh dep va tai khoan mac dinh).
echo.
set /p "CONFIRM=Ban co chac chan muon tiep tuc? (y/n): "
if /i not "%CONFIRM%"=="y" (
    echo Da huy thao tac.
    ping 127.0.0.1 -n 3 >nul
    goto MAIN_MENU
)

call :FIND_PHP
if errorlevel 1 goto PAUSE_AND_MENU

php "%ROOT_DIR%tools\system_diagnose.php" reset-db
echo.
pause
goto MAIN_MENU

:DO_CLEAN_PORT
cls
echo ===============================================================================
echo                     GIAI PHONG CONG MANG %PORT%
echo ===============================================================================
echo.
call :FIND_PHP
if errorlevel 1 goto PAUSE_AND_MENU

php "%ROOT_DIR%tools\system_diagnose.php" free-port %PORT%
echo.
pause
goto MAIN_MENU

:DO_LINT
cls
echo ===============================================================================
echo                 QUET KIEM TRA LOI CU PHAP MA NGUON PHP
echo ===============================================================================
echo.
call :FIND_PHP
if errorlevel 1 goto PAUSE_AND_MENU

php "%ROOT_DIR%tools\system_diagnose.php" lint
echo.
pause
goto MAIN_MENU

:DO_CHANGE_PORT
cls
echo ===============================================================================
echo                         THAY DOI CONG SERVER
echo ===============================================================================
echo Cong hien tai: %PORT%
echo (Goi y cac cong thong dung: 8000, 8080, 8088, 8888, 3000)
echo.
set /p "INPUT_PORT=Nhap so cong moi: "
if not "%INPUT_PORT%"=="" (
    set "PORT=%INPUT_PORT%"
    echo.
    echo [OK] Da doi thanh cong sang cong: %PORT%
) else (
    echo Khong co thay doi.
)
ping 127.0.0.1 -n 3 >nul
goto MAIN_MENU

:FIND_PHP
echo [*] Kiem tra moi truong PHP...
where php >nul 2>&1
if "%ERRORLEVEL%"=="0" (
    echo   [OK] Da tim thay PHP trong bien moi truong PATH.
    exit /b 0
)

echo   [!] PHP chua co trong PATH. Dang tu dong tim kiem tren may...

for /d %%D in ("E:\BE\Warm\bin\php\php*" "C:\wamp64\bin\php\php*" "D:\wamp64\bin\php\php*" "E:\wamp64\bin\php\php*") do (
    if exist "%%D\php.exe" (
        set "PATH=%%D;%PATH%"
        echo   [OK] Da tim thay PHP tai: %%D
        exit /b 0
    )
)

for %%D in ("C:\xampp\php" "D:\xampp\php" "E:\xampp\php") do (
    if exist "%%~D\php.exe" (
        set "PATH=%%~D;%PATH%"
        echo   [OK] Da tim thay PHP tai: %%~D
        exit /b 0
    )
)

for /d %%D in ("C:\laragon\bin\php\php*" "D:\laragon\bin\php\php*") do (
    if exist "%%D\php.exe" (
        set "PATH=%%D;%PATH%"
        echo   [OK] Da tim thay PHP tai: %%D
        exit /b 0
    )
)

echo.
echo [LOI] Khong the tim thay PHP tren may tinh!
echo Vui long kiem tra lai duong dan cai dat XAMPP / WampServer / Laragon.
exit /b 1

:PAUSE_AND_MENU
echo.
pause
goto MAIN_MENU

:DO_EXIT
cls
echo Tam biet!
ping 127.0.0.1 -n 2 >nul
exit /b 0