<#
.SYNOPSIS
    Builds the translation files from languages/<domain>-<locale>.l10n.php (the hand-edited source).

.DESCRIPTION
    For every languages/wonom-slider-<locale>.l10n.php it writes, next to it:
      wonom-slider-<locale>.po                             readable copy for translators / Poedit
      wonom-slider-<locale>.mo                             PHP translations (WordPress before 6.5)
      wonom-slider-<locale>-wonom-slider-admin-i18n.json   admin JS strings (wp.i18n, Jed format)
    WordPress 6.5+ loads the .l10n.php itself for PHP strings. Run by build-release.ps1 on every build.
    Keep this file ASCII-only: Windows PowerShell 5.1 reads BOM-less scripts in the ANSI code page.
#>
param([string]$Root = (Split-Path -Parent $PSScriptRoot))

$ErrorActionPreference = 'Stop'
$domain = 'wonom-slider'
$jsHandle = 'wonom-slider-admin-i18n'
$utf8 = New-Object System.Text.UTF8Encoding($false)
$langDir = Join-Path $Root 'languages'

function Unescape-PhpSingle([string]$s) { return $s.Replace("\\", "\").Replace("\'", "'") }
function Unescape-PhpDouble([string]$s) { return $s.Replace('\4', [string][char]4).Replace('\"', '"').Replace('\\', '\') }
function Po-Escape([string]$s) { return $s.Replace('\', '\\').Replace('"', '\"').Replace("`n", '\n').Replace("`t", '\t') }
function Json-Escape([string]$s) {
    $sb = New-Object System.Text.StringBuilder
    foreach ($ch in $s.ToCharArray()) {
        $c = [int]$ch
        if ($ch -eq '"') { [void]$sb.Append('\"') }
        elseif ($ch -eq '\') { [void]$sb.Append('\\') }
        elseif ($c -lt 32) { [void]$sb.Append(('\u{0:x4}' -f $c)) }
        else { [void]$sb.Append($ch) }
    }
    return $sb.ToString()
}

$files = Get-ChildItem -Path $langDir -Filter "$domain-*.l10n.php" -ErrorAction SilentlyContinue
foreach ($f in $files) {
    $locale = $f.Name.Substring($domain.Length + 1).Replace('.l10n.php', '')
    $text = [IO.File]::ReadAllText($f.FullName, [Text.Encoding]::UTF8)

    $plural = 'nplurals=2; plural=(n != 1);'
    $pm = [regex]::Match($text, "'plural-forms'\s*=>\s*'([^']*)'")
    if ($pm.Success) { $plural = $pm.Groups[1].Value }

    # Only the "messages" array holds translations.
    $mi = $text.IndexOf("'messages'")
    if ($mi -lt 0) { throw "$($f.Name): no 'messages' array" }
    $body = $text.Substring($mi)
    $rx = [regex]"(?m)^\s*(?:'((?:[^'\\]|\\.)*)'|""((?:[^""\\]|\\.)*)"")\s*=>\s*'((?:[^'\\]|\\.)*)'\s*,"
    $pairs = New-Object 'System.Collections.Generic.SortedDictionary[string,string]' ([StringComparer]::Ordinal)
    foreach ($m in $rx.Matches($body)) {
        $key = if ($m.Groups[1].Success) { Unescape-PhpSingle $m.Groups[1].Value } else { Unescape-PhpDouble $m.Groups[2].Value }
        $val = Unescape-PhpSingle $m.Groups[3].Value
        if ($key -eq '' -or $val -eq '') { continue }
        $pairs[$key] = $val
    }
    if ($pairs.Count -eq 0) { throw "$($f.Name): no translations found" }

    $base = Join-Path $langDir "$domain-$locale"
    $header = "Content-Type: text/plain; charset=UTF-8`nLanguage: $locale`nPlural-Forms: $plural`nX-Generator: tools/build-translations.ps1`n"

    # ---- .po
    $po = New-Object System.Text.StringBuilder
    [void]$po.AppendLine('# Generated from ' + $f.Name + ' - edit that file, not this one.')
    [void]$po.AppendLine('msgid ""')
    [void]$po.AppendLine('msgstr ""')
    foreach ($h in ($header.TrimEnd("`n") -split "`n")) { [void]$po.AppendLine('"' + (Po-Escape $h) + '\n"') }
    [void]$po.AppendLine('')
    foreach ($k in $pairs.Keys) {
        $ctx = $null; $id = $k
        $p = $k.IndexOf([char]4)
        if ($p -ge 0) { $ctx = $k.Substring(0, $p); $id = $k.Substring($p + 1) }
        if ($ctx) { [void]$po.AppendLine('msgctxt "' + (Po-Escape $ctx) + '"') }
        [void]$po.AppendLine('msgid "' + (Po-Escape $id) + '"')
        [void]$po.AppendLine('msgstr "' + (Po-Escape $pairs[$k]) + '"')
        [void]$po.AppendLine('')
    }
    [IO.File]::WriteAllText("$base.po", $po.ToString().Replace("`r`n", "`n"), $utf8)

    # ---- .mo (GNU gettext binary: header, original table, translation table, strings; keys sorted)
    $origs = New-Object System.Collections.Generic.List[byte[]]
    $trans = New-Object System.Collections.Generic.List[byte[]]
    $origs.Add([byte[]]@()); $trans.Add($utf8.GetBytes($header))   # "" = header entry, sorts first
    foreach ($k in $pairs.Keys) { $origs.Add($utf8.GetBytes($k)); $trans.Add($utf8.GetBytes($pairs[$k])) }
    $n = $origs.Count
    $oTab = 28; $tTab = $oTab + 8 * $n; $strStart = $tTab + 8 * $n
    $ms = New-Object System.IO.MemoryStream
    $bw = New-Object System.IO.BinaryWriter($ms)
    $bw.Write([uint32]2500072158)   # 0x950412de
    $bw.Write([uint32]0); $bw.Write([uint32]$n); $bw.Write([uint32]$oTab); $bw.Write([uint32]$tTab)
    $bw.Write([uint32]0); $bw.Write([uint32]$strStart)
    $offset = $strStart
    $oEntries = @(); foreach ($b in $origs) { $oEntries += , @($b.Length, $offset); $offset += $b.Length + 1 }
    $tEntries = @(); foreach ($b in $trans) { $tEntries += , @($b.Length, $offset); $offset += $b.Length + 1 }
    foreach ($e in $oEntries) { $bw.Write([uint32]$e[0]); $bw.Write([uint32]$e[1]) }
    foreach ($e in $tEntries) { $bw.Write([uint32]$e[0]); $bw.Write([uint32]$e[1]) }
    foreach ($b in $origs) { $bw.Write($b); $bw.Write([byte]0) }
    foreach ($b in $trans) { $bw.Write($b); $bw.Write([byte]0) }
    $bw.Flush()
    [IO.File]::WriteAllBytes("$base.mo", $ms.ToArray())
    $bw.Dispose()

    # ---- JSON for the admin script (Jed 1.x, as WordPress' load_script_textdomain() expects)
    $js = New-Object System.Text.StringBuilder
    [void]$js.Append('{"translation-revision-date":"' + (Get-Date -Format 'yyyy-MM-dd HH:mmzzz') + '","generator":"tools/build-translations.ps1","domain":"messages","locale_data":{"messages":{"":{"domain":"messages","lang":"' + $locale + '","plural-forms":"' + (Json-Escape $plural) + '"}')
    foreach ($k in $pairs.Keys) { [void]$js.Append(',"' + (Json-Escape $k) + '":["' + (Json-Escape $pairs[$k]) + '"]') }
    [void]$js.Append('}}}')
    [IO.File]::WriteAllText("$base-$jsHandle.json", $js.ToString(), $utf8)

    Write-Host ("Translations {0}: {1} strings -> .po, .mo, {2}.json" -f $locale, $pairs.Count, $jsHandle) -ForegroundColor Green
}
exit 0
