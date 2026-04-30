@echo off
chcp 65001 > nul

REM --- Корінь проєкту (папка, де лежить run.bat)
set ROOT=%~dp0

REM --- Python (якщо python не в PATH — впиши повний шлях)
set PYTHON=python

REM --- Скрипт генерації
set SCRIPT=%ROOT%generate_report.py

REM --- Шаблон Word
set TEMPLATE=%ROOT%template.docx

REM --- Папка для результатів
set OUTDIR=%ROOT%output

REM --- Перевірка: чи передали файл
if "%~1"=="" (
    echo ❌ Перетягніть JSON-файл на run.bat
    pause
    exit /b 1
)

REM --- Вхідний JSON (перетягнутий файл)
set JSON=%~1

REM --- Створюємо output, якщо немає
if not exist "%OUTDIR%" (
    mkdir "%OUTDIR%"
)

echo ▶ Генерація звіту...
echo JSON: %JSON%
echo TEMPLATE: %TEMPLATE%
echo OUTPUT DIR: %OUTDIR%
echo.

REM --- Запуск Python-скрипта
%PYTHON% "%SCRIPT%" "%JSON%" -t "%TEMPLATE%" --outdir "%OUTDIR%"

echo.
echo ✅ Готово.
pause
