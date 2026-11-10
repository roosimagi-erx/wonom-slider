<#
.SYNOPSIS
    Bumps the Wonom Slider version, builds wonom-slider.zip and (optionally) publishes a GitHub release.

.DESCRIPTION
    Updates the version in wonom-slider.php (header + constant) and readme.txt (Stable tag), adds a
    CHANGELOG.md heading if missing and builds a WordPress-installable zip whose root folder is
    "wonom-slider".

    -Tag      commits, tags vX.Y.Z and pushes. The GitHub Action then builds and publishes the release.
    -Release  the full automatic flow (same as Wonom KKK): commits and pushes, then creates the GitHub
              release with gh CLI and attaches the zip built here. The release notes are the matching
              CHANGELOG.md section (or -Notes). Shops see the update right after this, within 6 hours
              or immediately via "Check for updates now". The GitHub Action still runs php -l on the
              tag and turns the release into a draft if the check fails, so a broken build never
              reaches the shops.

    Safety checks: the script refuses to run outside the wonom-slider folder, refuses a version that is
    not higher than the current one (use -Force to rebuild the same version), refuses to release with an
    empty changelog section and refuses to overwrite an existing tag.

.EXAMPLE
    .\build-release.ps1 -Version 1.8.0                 # bump + zip only
    .\build-release.ps1 -Version 1.8.0 -Release        # bump + zip + commit + push + GitHub release
    .\build-release.ps1 -Version 1.8.0 -Release -Notes "Fix: ..."   # notes instead of CHANGELOG
    .\build-release.ps1 -Version 1.8.0 -Tag            # tag only, the Action publishes the release
#>
param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^\d+\.\d+\.\d+$')]
    [string]$Version,

    [switch]$Tag,

    [switch]$Release,

    [string]$Notes = '',

    [switch]$Force
)

$ErrorActionPreference = 'Stop'
$slug      = 'wonom-slider'
$repo      = 'roosimagi-erx/wonom-slider'
$root      = $PSScriptRoot
$main      = Join-Path $root "$slug.php"
$readme    = Join-Path $root 'readme.txt'
$changelog = Join-Path $root 'CHANGELOG.md'
$zip       = Join-Path $root "$slug.zip"
$utf8      = New-Object System.Text.UTF8Encoding($false)

# ---------------------------------------------------------------- safety checks

# Right folder? (Kampaaniariba once ran another plugin's script and its version dropped 1.18.0 -> 1.0.13.)
if (-not (Test-Path $main)) {
    throw "This script belongs to $slug. $main not found - wrong folder?"
}
$mainText = [System.IO.File]::ReadAllText($main, [System.Text.Encoding]::UTF8)
if ($mainText -notmatch "define\(\s*'WONOM_SLIDER_VERSION'") {
    throw "$main does not look like the Wonom Slider main file."
}
$current = [regex]::Match($mainText, '\*\s+Version:\s+(\d+\.\d+\.\d+)').Groups[1].Value
if (-not $current) { throw "Could not read the current version from $main." }

$cmp = ([version]$Version).CompareTo([version]$current)
if ($cmp -lt 0) {
    throw "Version $Version is lower than the current $current. WordPress never updates to a lower number."
}
if ($cmp -eq 0 -and -not $Force) {
    throw "Version $Version is already the current version. Use a higher number, or -Force to rebuild the same version."
}

if ($Release) { $Tag = $true }

# Native tools (git, gh) write progress to stderr; in Windows PowerShell 5.1 with 'Stop' that would
# abort the script. Run them with 'Continue', echo their output and return the exit code.
function Invoke-Native([string]$exe, [string[]]$argv, [switch]$Quiet) {
    $prev = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $out = & $exe @argv 2>&1 | ForEach-Object { "$_" }
    } finally {
        $ErrorActionPreference = $prev
    }
    if (-not $Quiet -and $out) { $out | ForEach-Object { Write-Host $_ } }
    return [pscustomobject]@{ Code = $LASTEXITCODE; Out = (@($out) -join "`n").Trim() }
}

function Find-Gh {
    if (Get-Command gh -ErrorAction SilentlyContinue) { return 'gh' }
    $exe = Join-Path $env:ProgramFiles 'GitHub CLI\gh.exe'
    if (Test-Path $exe) { return $exe }
    throw "gh CLI is missing. Install: winget install --id GitHub.cli ; then: gh auth login"
}

if ($Tag) {
    Push-Location $root
    try {
        if ((Invoke-Native git @('tag', '--list', "v$Version") -Quiet).Out) { throw "Tag v$Version already exists locally. Use a higher version." }
        $branch = (Invoke-Native git @('rev-parse', '--abbrev-ref', 'HEAD') -Quiet).Out
        if ($branch -ne 'main') { throw "You are on branch '$branch'. Releases are made from main." }
    } finally {
        Pop-Location
    }
}
# Release notes: the CHANGELOG.md section for this version (checked before any file is touched).
function Get-ChangelogSection([string]$text, [string]$ver) {
    $m = [regex]::Match($text, "(?ms)^## \[?$([regex]::Escape($ver))\]?[^\r\n]*\r?\n(.*?)(?=^## |\z)")
    if ($m.Success) { return $m.Groups[1].Value.Trim() }
    return $null
}
$clText  = if (Test-Path $changelog) { [System.IO.File]::ReadAllText($changelog, [System.Text.Encoding]::UTF8) } else { '' }
$section = Get-ChangelogSection $clText $Version
if ($Notes -eq '' -and $section) { $Notes = $section }
if ($Release -and ($Notes -replace '[-\s]', '') -eq '') {
    throw "The CHANGELOG.md section for $Version is missing or empty. Write what changed (for the shop owner) and run again, or pass -Notes."
}

if ($Release) {
    $gh = Find-Gh
    if ((Invoke-Native $gh @('auth', 'status') -Quiet).Code -ne 0) { throw "gh is not logged in. Run: gh auth login (account roosimagi-erx)." }
    if ((Invoke-Native $gh @('release', 'view', "v$Version", '--repo', $repo) -Quiet).Code -eq 0) {
        throw "Release v$Version already exists on GitHub. Use a higher version."
    }
}

# ---------------------------------------------------------------- version bump

function Update-File([string]$path, [hashtable]$replacements) {
    if (-not (Test-Path $path)) { return }
    $text = [System.IO.File]::ReadAllText($path, [System.Text.Encoding]::UTF8)
    foreach ($pattern in $replacements.Keys) {
        $text = [regex]::Replace($text, $pattern, $replacements[$pattern])
    }
    [System.IO.File]::WriteAllText($path, $text, $utf8)
}

Write-Host "Version $current -> $Version" -ForegroundColor Cyan

Update-File $main @{
    '(\*\s+Version:\s+)\d+\.\d+\.\d+'                       = "`${1}$Version"
    "(define\(\s*'WONOM_SLIDER_VERSION',\s*')\d+\.\d+\.\d+" = "`${1}$Version"
}
Update-File $readme @{ '(Stable tag:\s*)\d+\.\d+\.\d+' = "`${1}$Version" }

# No CHANGELOG section yet (bump-only run): add an empty one to fill in.
if ((Test-Path $changelog) -and $null -eq $section) {
    $date = Get-Date -Format 'yyyy-MM-dd'
    $clText = $clText -replace '(?m)^(# .*\r?\n)', "`$1`r`n## $Version - $date`r`n`r`n- `r`n"
    [System.IO.File]::WriteAllText($changelog, $clText, $utf8)
    Write-Host "Added an empty section to CHANGELOG.md - fill it in before releasing." -ForegroundColor Yellow
}

# ---------------------------------------------------------------- zip

# Forward slashes inside the archive so Linux servers unpack correctly.
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
if (Test-Path $zip) { Remove-Item $zip -Force }

$exclude      = @('.git', '.github', '.claude', 'build', 'docs', 'node_modules', 'vendor')
$excludeFiles = @('.gitignore', '.distignore', '.gitattributes', 'build-release.ps1', 'desktop.ini', 'Thumbs.db', '.DS_Store')
$archive = [System.IO.Compression.ZipFile]::Open($zip, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    Get-ChildItem -Recurse -File -Force $root | Where-Object {
        $rel = $_.FullName.Substring($root.Length + 1)
        $top = $rel.Split([IO.Path]::DirectorySeparatorChar)[0]
        -not ($exclude -contains $top) -and -not ($excludeFiles -contains $_.Name) -and $_.Extension -notin @('.zip', '.md')
    } | ForEach-Object {
        $rel = "$slug/" + $_.FullName.Substring($root.Length + 1).Replace('\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $archive, $_.FullName, $rel, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
} finally {
    $archive.Dispose()
}
$kb = [math]::Round((Get-Item $zip).Length / 1kb, 1)
Write-Host "Built: $zip ($kb KB)" -ForegroundColor Green

if (-not $Tag) {
    Write-Host ""
    Write-Host "Next: .\build-release.ps1 -Version $Version -Release   (commit, push and publish)" -ForegroundColor Cyan
    return
}

# ---------------------------------------------------------------- commit, tag, release

Push-Location $root
try {
    Invoke-Native git @('add', '-A') | Out-Null
    if ((Invoke-Native git @('commit', '-m', "Release $Version")).Code -ne 0) {
        Write-Host "Nothing new to commit - continuing." -ForegroundColor Yellow
    }

    if (-not $Release) {
        Invoke-Native git @('tag', "v$Version") | Out-Null
        if ((Invoke-Native git @('push', 'origin', 'main', '--tags')).Code -ne 0) { throw "git push failed." }
        Write-Host "Tag v$Version pushed. The GitHub Action publishes the release in about a minute." -ForegroundColor Green
        return
    }

    if ((Invoke-Native git @('push', 'origin', 'main')).Code -ne 0) { throw "git push failed." }
    $sha = (Invoke-Native git @('rev-parse', 'HEAD') -Quiet).Out

    # gh creates the tag on GitHub and the release in one go, with the zip built above.
    # The release exists before the tag event reaches the Action, so the Action only runs its checks.
    $notesFile = Join-Path ([IO.Path]::GetTempPath()) "$slug-$Version-notes.md"
    [System.IO.File]::WriteAllText($notesFile, $Notes, $utf8)
    try {
        $r = Invoke-Native $gh @('release', 'create', "v$Version", $zip, '--repo', $repo, '--target', $sha, '--title', $Version, '--notes-file', $notesFile)
        if ($r.Code -ne 0) { throw "gh release create failed." }
    } finally {
        Remove-Item $notesFile -Force -ErrorAction SilentlyContinue
    }
    Invoke-Native git @('fetch', 'origin', '--tags', '--quiet') -Quiet | Out-Null

    # Verify the asset the plugin's updater downloads.
    $asset = (Invoke-Native $gh @('release', 'view', "v$Version", '--repo', $repo, '--json', 'assets', '--jq', ".assets[] | select(.name==`"$slug.zip`") | .size") -Quiet).Out
    if (-not $asset) { throw "Release v$Version was created but $slug.zip is not attached." }

    Write-Host ""
    Write-Host "Release v$Version published: https://github.com/$repo/releases/tag/v$Version" -ForegroundColor Green
    Write-Host "In WordPress: Wonom Slider -> Settings -> 'Check for updates now' -> 'Update now'." -ForegroundColor Green
} finally {
    Pop-Location
}
