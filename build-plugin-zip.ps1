param(                         
    [string]$PluginRoot = $PSScriptRoot,
    [string]$OutputDir = 'dist',
    [switch]$KeepBuildFolder
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

if (-not $PluginRoot) {
    throw 'Nao foi possivel determinar a raiz do plugin.'
}

$PluginRoot = (Resolve-Path $PluginRoot).Path
$PluginMainFile = Join-Path $PluginRoot 'judge-ia-plugin.php'

if (-not (Test-Path $PluginMainFile)) {
    throw "Arquivo principal nao encontrado: $PluginMainFile"
}

$versionMatch = Select-String -Path $PluginMainFile -Pattern 'Version:\s*([0-9.]+)'
if (-not $versionMatch -or $versionMatch.Matches.Count -eq 0) {
    throw 'Nao foi possivel extrair a versao do cabecalho do plugin.'
}

$version = $versionMatch.Matches[0].Groups[1].Value
$pluginSlug = 'judge-ia-plugin'

$outputPath = Join-Path $PluginRoot $OutputDir
$buildPath = Join-Path $outputPath '_build'
$stagePath = Join-Path $buildPath $pluginSlug
$zipName = "$pluginSlug-$version-install.zip"
$zipPath = Join-Path $outputPath $zipName

if (-not (Test-Path $outputPath)) {
    New-Item -ItemType Directory -Path $outputPath -Force | Out-Null
}

if (Test-Path $buildPath) {
    Remove-Item $buildPath -Recurse -Force
}

New-Item -ItemType Directory -Path $stagePath -Force | Out-Null

$itemsToCopy = Get-ChildItem -Path $PluginRoot -Force | Where-Object {
    $_.Name -notin @('.git', $OutputDir, 'build-plugin-zip.ps1')
}

foreach ($item in $itemsToCopy) {
    Copy-Item -Path $item.FullName -Destination $stagePath -Recurse -Force
}

if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

Add-Type -AssemblyName System.IO.Compression.FileSystem
$zipFileStream = [System.IO.File]::Open($zipPath, [System.IO.FileMode]::CreateNew)
$zipArchive = New-Object System.IO.Compression.ZipArchive($zipFileStream, [System.IO.Compression.ZipArchiveMode]::Create, $false)
try {
    $filesToZip = Get-ChildItem -Path $stagePath -Recurse -File
    foreach ($file in $filesToZip) {
        $relativePath = $file.FullName.Substring($buildPath.Length + 1).Replace('\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $zipArchive,
            $file.FullName,
            $relativePath,
            [System.IO.Compression.CompressionLevel]::Optimal
        ) | Out-Null
    }
}
finally {
    $zipArchive.Dispose()
    $zipFileStream.Dispose()
}

$zip = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
try {
    $rootPattern = '^{0}/' -f [Regex]::Escape($pluginSlug)
    $invalidEntry = $zip.Entries | Where-Object { $_.FullName -notmatch $rootPattern } | Select-Object -First 1
    if ($null -ne $invalidEntry) {
        throw "ZIP invalido: entrada fora da pasta raiz '$pluginSlug': $($invalidEntry.FullName)"
    }

    $pluginMainEntry = "$pluginSlug/judge-ia-plugin.php"
    $mainFileExists = $zip.Entries | Where-Object { $_.FullName -eq $pluginMainEntry } | Select-Object -First 1
    if ($null -eq $mainFileExists) {
        throw "ZIP invalido: arquivo principal nao encontrado em '$pluginMainEntry'."
    }
}
finally {
    $zip.Dispose()
}

if (-not $KeepBuildFolder) {
    Remove-Item $buildPath -Recurse -Force
}

Write-Host "Pacote gerado com sucesso: $zipPath"
