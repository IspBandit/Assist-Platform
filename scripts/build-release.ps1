#requires -Version 5.1
[CmdletBinding()]
param(
    [string]$OutputDirectory = 'dist'
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$resolvedOutput = if ([System.IO.Path]::IsPathRooted($OutputDirectory)) {
    $OutputDirectory
} else {
    Join-Path $projectRoot $OutputDirectory
}

Push-Location $projectRoot
try {
    if ((git status --porcelain).Length -gt 0) {
        throw 'Release artefacts must be built from a clean working tree.'
    }

    $commit = (git rev-parse HEAD).Trim()
    $shortCommit = (git rev-parse --short HEAD).Trim()
    $createdAt = [DateTimeOffset]::UtcNow.ToString('o')
    $temporaryRoot = Join-Path ([System.IO.Path]::GetTempPath()) "assist-platform-release-$shortCommit"
    $archivePath = Join-Path ([System.IO.Path]::GetTempPath()) "assist-platform-$shortCommit.tar"

    if (Test-Path -LiteralPath $temporaryRoot) {
        Remove-Item -LiteralPath $temporaryRoot -Recurse -Force
    }
    New-Item -ItemType Directory -Path $temporaryRoot | Out-Null

    git archive --format=tar -o $archivePath HEAD
    if ($LASTEXITCODE -ne 0) {
        throw 'git archive failed.'
    }
    tar -xf $archivePath -C $temporaryRoot

    $files = Get-ChildItem -LiteralPath $temporaryRoot -Recurse -File |
        Sort-Object FullName |
        ForEach-Object {
            [ordered]@{
                path = $_.FullName.Substring($temporaryRoot.Length).TrimStart('\', '/').Replace('\', '/')
                bytes = $_.Length
                sha256 = (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash.ToLowerInvariant()
            }
        }

    $manifest = [ordered]@{
        schema = 1
        project = 'assist-platform'
        commit = $commit
        created_at = $createdAt
        files = $files
    }
    $manifestPath = Join-Path $temporaryRoot 'release-manifest.json'
    $manifest | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath $manifestPath -Encoding UTF8

    New-Item -ItemType Directory -Force -Path $resolvedOutput | Out-Null
    $zipPath = Join-Path $resolvedOutput "assist-platform-$shortCommit.zip"
    if (Test-Path -LiteralPath $zipPath) {
        Remove-Item -LiteralPath $zipPath -Force
    }
    Compress-Archive -Path (Join-Path $temporaryRoot '*') -DestinationPath $zipPath -CompressionLevel Optimal

    $zipHash = (Get-FileHash -LiteralPath $zipPath -Algorithm SHA256).Hash.ToLowerInvariant()
    [ordered]@{
        artefact = $zipPath
        commit = $commit
        sha256 = $zipHash
        files = $files.Count
    } | ConvertTo-Json -Depth 3
} finally {
    Pop-Location
    if ($archivePath -and (Test-Path -LiteralPath $archivePath)) {
        Remove-Item -LiteralPath $archivePath -Force
    }
    if ($temporaryRoot -and (Test-Path -LiteralPath $temporaryRoot)) {
        Remove-Item -LiteralPath $temporaryRoot -Recurse -Force
    }
}
