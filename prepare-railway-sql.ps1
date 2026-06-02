param(
    [string]$Source = ".\files_attente.sql",
    [string]$Destination = ".\files_attente_railway.sql"
)

$content = Get-Content -LiteralPath $Source -Raw

# Railway provides the target database already, so we remove database creation and USE.
$content = $content -replace '(?m)^\s*CREATE DATABASE IF NOT EXISTS `files_attente`;\s*', ''
$content = $content -replace '(?m)^\s*USE `files_attente`;\s*', ''

# MySQL on Railway may reject DEFINER blocks or SECURITY DEFINER views/procedures.
$content = $content -replace 'DEFINER=`root`@`localhost`\s*', ''
$content = $content -replace 'SQL SECURITY DEFINER', 'SQL SECURITY INVOKER'

# Events can be problematic on managed MySQL. Remove the nightly QR expiry event.
$content = $content -replace '(?s)\s*DROP EVENT IF EXISTS `event_expire_qrcodes`.*?END\$\$\s*', "`r`n"

# Clean extra blank lines introduced by the replacements.
$content = $content -replace "(\r?\n){3,}", "`r`n`r`n"

Set-Content -LiteralPath $Destination -Value $content -Encoding UTF8

Write-Host "Generated $Destination"
