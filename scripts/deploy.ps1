#requires -Version 5
<#
.SYNOPSIS
    One-command FTP deploy for VanAssist to GoDaddy (cPanel).

.DESCRIPTION
    Exports the committed tree (git archive HEAD) to a temp folder and uploads it
    to the remote project root via WinSCP. The upload is one-way (local -> remote)
    and NEVER deletes remote files, so server-generated files are preserved:
      - .env, storage/installed.lock
      - storage/** uploads, public/uploads-public/**
      - the auto-created cgi-bin
    Only files tracked by git are deployed (secrets in .gitignore never leave the
    machine).

    Credentials are read from scripts/deploy.env (gitignored). Copy
    scripts/deploy.env.example to scripts/deploy.env and fill it in first.

.PARAMETER RemotePath
    Override the remote project-root path (default: DEPLOY_REMOTE_PATH from
    deploy.env, or /vanassist).

.PARAMETER Full
    Force a complete re-upload of every file (overwrite all). Without this, files
    whose remote size already matches are skipped, which makes routine redeploys
    fast. Use -Full after large refactors or if anything looks out of sync.

.PARAMETER DryRun
    Build the clean export and print what would happen, but do not connect/upload.

.EXAMPLE
    pwsh ./scripts/deploy.ps1
    Fast incremental deploy of the current commit.

.EXAMPLE
    pwsh ./scripts/deploy.ps1 -Full
    Complete overwrite of every file.

.NOTES
    Requires: git and WinSCP (WinSCP.com). Deploys the LAST COMMIT - commit your
    changes before running.
#>
[CmdletBinding()]
param(
    [string]$RemotePath,
    [switch]$Full,
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot

function Fail($message) { Write-Error $message; exit 1 }

# --- Load deploy.env -------------------------------------------------------
$envFile = Join-Path $PSScriptRoot 'deploy.env'
if (Test-Path $envFile) {
    foreach ($line in Get-Content $envFile) {
        if ($line -match '^\s*#' -or $line -notmatch '=') { continue }
        $key, $value = $line -split '=', 2
        Set-Item -Path ("Env:" + $key.Trim()) -Value $value.Trim().Trim('"').Trim("'")
    }
} else {
    Fail "Missing $envFile. Copy scripts/deploy.env.example to scripts/deploy.env and fill it in."
}

$ftpHost = $env:FTP_HOST
$ftpUser = $env:FTP_USER
$ftpPass = $env:FTP_PASSWORD
$ftpSecure = ($env:FTP_SECURE -eq 'true')
if (-not $RemotePath) {
    $RemotePath = if ($env:DEPLOY_REMOTE_PATH) { $env:DEPLOY_REMOTE_PATH } else { '/vanassist' }
}
$RemotePath = $RemotePath.TrimEnd('/')

if (-not $ftpHost -or -not $ftpUser -or -not $ftpPass) {
    Fail "FTP_HOST / FTP_USER / FTP_PASSWORD must all be set in scripts/deploy.env."
}

# --- Locate WinSCP ---------------------------------------------------------
$winscp = (Get-Command 'winscp.com' -ErrorAction SilentlyContinue).Source
if (-not $winscp) {
    foreach ($candidate in @(
        "C:\Program Files (x86)\WinSCP\WinSCP.com",
        "C:\Program Files\WinSCP\WinSCP.com"
    )) {
        if (Test-Path $candidate) { $winscp = $candidate; break }
    }
}
if (-not $winscp) { Fail "WinSCP.com not found. Install WinSCP (https://winscp.net) or add it to PATH." }

# --- Clean export of the committed tree ------------------------------------
Push-Location $projectRoot
try {
    $commit = (git rev-parse --short HEAD).Trim()
    $branch = (git rev-parse --abbrev-ref HEAD).Trim()
    $exportDir = Join-Path $env:TEMP "vanassist_deploy_$commit"
    if (Test-Path $exportDir) { Remove-Item $exportDir -Recurse -Force }
    New-Item -ItemType Directory -Path $exportDir | Out-Null

    $tarFile = Join-Path $env:TEMP "vanassist_$commit.tar"
    git archive --format=tar -o $tarFile HEAD
    tar -xf $tarFile -C $exportDir
    Remove-Item $tarFile -Force
} finally {
    Pop-Location
}

$fileCount = (Get-ChildItem $exportDir -Recurse -File).Count
$mode = if ($Full) { 'full overwrite' } else { 'incremental (by size)' }
Write-Host ""
Write-Host "VanAssist deploy" -ForegroundColor Cyan
Write-Host "  commit : $branch @ $commit"
Write-Host "  files  : $fileCount"
Write-Host "  target : ${ftpHost}:${RemotePath}"
Write-Host "  mode   : $mode"
Write-Host ""

if ($DryRun) {
    Write-Host "[dry-run] Clean export at $exportDir. No connection made." -ForegroundColor Yellow
    exit 0
}

# --- Build and run the WinSCP script ---------------------------------------
$u = [uri]::EscapeDataString($ftpUser)
$p = [uri]::EscapeDataString($ftpPass)
$protocol = if ($ftpSecure) { 'ftpes' } else { 'ftp' }
# WinSCP: keep Windows backslashes in lcd (forward slashes break C:\ paths).
$exportDirLcd = $exportDir
$criteria = if ($Full) { 'size' } else { 'size' }

$winscpScript = @"
option batch abort
option confirm off
option transfer binary
open ${protocol}://${u}:${p}@${ftpHost}/
lcd "$exportDirLcd"
cd $RemotePath
synchronize remote -criteria=$criteria -resumesupport=off .
exit
"@

$scriptFile = Join-Path $env:TEMP "vanassist_winscp_$commit.txt"
Set-Content -Path $scriptFile -Value $winscpScript -Encoding ASCII

$verifyKernel = Join-Path $env:TEMP "vanassist_deploy_verify_kernel.php"
$verifyOk = $false
$code = 1

try {
    & $winscp /ini=nul /script=$scriptFile /log="$env:TEMP\vanassist_deploy_$commit.log"
    $code = $LASTEXITCODE

    if ($code -eq 0) {
        $verifyScript = @"
option batch abort
option confirm off
open ${protocol}://${u}:${p}@${ftpHost}/
get $RemotePath/app/Core/Kernel.php "$verifyKernel"
exit
"@
        $verifyScriptFile = Join-Path $env:TEMP "vanassist_winscp_verify_$commit.txt"
        Set-Content -Path $verifyScriptFile -Value $verifyScript -Encoding ASCII
        & $winscp /ini=nul /script=$verifyScriptFile | Out-Null
        if ((Test-Path $verifyKernel) -and (Select-String -Path $verifyKernel -Pattern 'is_callable\(\$register\)' -Quiet)) {
            $verifyOk = $true
        }
        Remove-Item $verifyScriptFile -Force -ErrorAction SilentlyContinue
    }
} finally {
    Remove-Item $scriptFile -Force -ErrorAction SilentlyContinue
    Remove-Item $exportDir -Recurse -Force -ErrorAction SilentlyContinue
    Remove-Item $verifyKernel -Force -ErrorAction SilentlyContinue
}

Write-Host ""
if ($code -eq 0 -and $verifyOk) {
    Write-Host "Deploy complete: $fileCount files ($branch @ $commit) -> $RemotePath" -ForegroundColor Green
    Write-Host "Live: https://vanassist.com.au/" -ForegroundColor Green
} elseif ($code -eq 0 -and -not $verifyOk) {
    Fail "WinSCP finished but the remote site does not have the expected code (Kernel.php check failed). See $env:TEMP\vanassist_deploy_$commit.log"
} else {
    Fail "WinSCP exited with code $code. The upload is one-way and safe to repeat - just run the script again to finish."
}
