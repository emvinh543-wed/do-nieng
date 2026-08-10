@echo off

echo ==========================================================
echo        KHOI DONG HE THONG GLOWDRINKS
echo ==========================================================
echo.

REM Di chuyen vao thu muc Trang chinh
cd /d "%~dp0Trang chinh"

REM Chay PHP Server o cua so moi
echo [1/3] Dang khoi dong PHP Server tai port 8000...
start "GlowDrinks Local Server" php -S 127.0.0.1:8000

REM Cho 2 giay de server khoi dong
echo [2/3] Dang cho giay lat...
timeout /t 2 /nobreak >nul

REM Mo trang web tren trinh duyet
echo [3/3] Dang mo trang web tren trinh duyet...
start http://127.0.0.1:8000/

echo.
echo ==========================================================
echo Hoan thanh! Vui lau giu nguyen cua so PHP dang chay.
echo ==========================================================
pause
