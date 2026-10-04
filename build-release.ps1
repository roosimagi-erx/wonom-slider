<#
.SYNOPSIS
    Bumps the Wonom Slider version, builds wonom-slider.zip and (optionally) tags + pushes.

.DESCRIPTION
    Updates the version in wonom-slider.php (header + constant), readme.txt (Stable tag)
    and adds a CHANGELOG.md heading if missing. Builds a WordPress-installable zip whose
    root folder is "wonom-slider".

    With -Tag it also commits, creates tag vX.Y.Z and pushes. The GitHub Action
    (.github/workflows/release.yml) then builds the release and attaches the zip,
    so no manual upload is needed.

.EXAMPLE
    .\build-release.ps1 -Version 1.1.0          # only bump + zip
    .\build-release.ps1 -Version 1.1.0 -Tag     # bump + zip + commit + tag + push
#>
param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^\d+\.\d+\.\d+$')]
    [string]$Version,

    [switch]$Tag
)

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$main = Join-Path $root 'wonom-slider.php'
$readme = Join-Path $root 'readme.txt'
$changelog = Join-Path $root 'CHANGELOG.md'
$zip = Join-Path $root 'wonom-slider.zip'
$utf8 = New-Object System.Text.UTF8Encoding($false)

function Update-File([string]$path, [hashtable]$replacements) {
    if (-not (Test-Path $path)) { return }
    $text = [System.IO.File]::ReadAllText($path, [System.Text.Encoding]::UTF8)
    foreach ($pattern in $replacements.Keys) {
        $text = [regex]::Replace($text, $pattern, $replacements[$pattern])
    }
    [System.IO.File]::WriteAllText($path, $text, $utf8)
}

Write-Host "Version -> $Version" -ForegroundColor Cyan

Update-File $main @{
    '(\*\s+Version:\s+)\d+\.\d+\.\d+' = "`${1}$Version"
    "(define\(\s*'WONOM_SLIDER_VERSION',\s*')\d+\.\d+\.\d+" = "`${1}$Version"
}
Update-File $readme @{ '(Stable tag:\s*)\d+\.\d+\.\d+' = "`${1}$Version" }

if (Test-Path $changelog) {
    $cl = [System.IO.File]::ReadAllText($changelog, [System.Text.Encoding]::UTF8)
    if ($cl -notmatch "## \[?$([regex]::Escape($Version))") {
        $date = Get-Date -Format 'yyyy-MM-dd'
        $cl = $cl -replace '(?m)^(# .*\r?\n)', "`$1`r`n## $Version - $date`r`n`r`n- `r`n"
        [System.IO.File]::WriteAllText($changelog, $cl, $utf8)
        Write-Host "Added empty section to CHANGELOG.md - fill it in before tagging." -ForegroundColor Yellow
    }
}

# Build zip (forward slashes inside the archive so Linux servers unpack correctly).
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
if (Test-Path $zip) { Remove-Item $zip -Force }

$exclude = @('.git', '.github', 'build', 'docs', 'node_modules', 'vendor')
$excludeFiles = @('.gitignore', '.distignore', 'build-release.ps1')
$archive = [System.IO.Compression.ZipFile]::Open($zip, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    Get-ChildItem -Recurse -File $root | Where-Object {
        $rel = $_.FullName.Substring($root.Length + 1)
        $top = $rel.Split([IO.Path]::DirectorySeparatorChar)[0]
        -not ($exclude -contains $top) -and -not ($excludeFiles -contains $_.Name) -and $_.Extension -notin @('.zip', '.md')
    } | ForEach-Object {
        $rel = 'wonom-slider/' + $_.FullName.Substring($root.Length + 1).Replace('\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $archive, $_.FullName, $rel, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
} finally {
    $archive.Dispose()
}
$kb = [math]::Round((Get-Item $zip).Length / 1kb, 1)
Write-Host "Built: $zip ($kb KB)" -ForegroundColor Green

if ($Tag) {
    Push-Location $root
    try {
        git add -A
        git commit -m "Release $Version"
        git tag "v$Version"
        git push origin HEAD --tags 2>&1 | ForEach-Object { "$_" }
        Write-Host "Tag v$Version pushed. GitHub Actions will publish the release in ~1 minute." -ForegroundColor Green
    } finally {
        Pop-Location
    }
} else {
    Write-Host ""
    Write-Host "Next: .\build-release.ps1 -Version $Version -Tag   (commit, tag and push)" -ForegroundColor Cyan
}
