param(
    [switch]$Docker,
    [switch]$Local
)

Write-Host "==================================================" -ForegroundColor Cyan
Write-Host "  BET CRM — Ambiente de Desenvolvimento (FASE 1)  " -ForegroundColor Cyan
Write-Host "==================================================" -ForegroundColor Cyan

if ($Docker) {
    Write-Host "[DOCKER] Iniciando todos os containers com Docker Compose..." -ForegroundColor Green
    docker compose up -d
    Write-Host "[OK] Containers iniciados!" -ForegroundColor Green
    Write-Host "  - Nginx / API / Web: http://localhost" -ForegroundColor Yellow
    Write-Host "  - Frontend:         http://localhost:3000" -ForegroundColor Yellow
    Write-Host "  - Health Check:     http://localhost/health" -ForegroundColor Yellow
    Write-Host "  - PostgreSQL:       localhost:5432" -ForegroundColor Yellow
    Write-Host "  - Redis:            localhost:6379" -ForegroundColor Yellow
    exit 0
}

# Execução Local
$phpDir = "C:\Users\dioni\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe"
$env:Path = "$phpDir;" + $env:Path

Write-Host "[LOCAL] Iniciando Backend API (Laravel 12) na porta 8000..." -ForegroundColor Green
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd 'x:\OPUSS DIGITAL\OPUSS CRM\backend'; `$env:Path = '$phpDir;' + `$env:Path; php artisan serve --port=8000"

Write-Host "[LOCAL] Iniciando Frontend (Next.js 15) na porta 3000..." -ForegroundColor Green
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd 'x:\OPUSS DIGITAL\OPUSS CRM\frontend'; npm run dev"

Write-Host ""
Write-Host "Serviços iniciados em janelas dedicadas:" -ForegroundColor Cyan
Write-Host "  Backend API:  http://localhost:8000" -ForegroundColor White
Write-Host "  Health Check: http://localhost:8000/health" -ForegroundColor White
Write-Host "  Frontend:     http://localhost:3000" -ForegroundColor White
