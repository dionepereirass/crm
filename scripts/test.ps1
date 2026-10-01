param(
    [string]$Filter = ""
)

$phpDir = "C:\Users\dioni\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe"
$env:Path = "$phpDir;" + $env:Path

Write-Host "==================================================" -ForegroundColor Cyan
Write-Host "  BET CRM — Executando Suíte de Testes            " -ForegroundColor Cyan
Write-Host "==================================================" -ForegroundColor Cyan

Set-Location "x:\OPUSS DIGITAL\OPUSS CRM\backend"

if ($Filter) {
    .\vendor\bin\phpunit --filter $Filter
} else {
    .\vendor\bin\phpunit
}
