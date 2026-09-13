$ErrorActionPreference = 'Stop'

function Resolve-CloudflaredPath {
    $installedCommand = Get-Command cloudflared -ErrorAction SilentlyContinue

    if ($null -ne $installedCommand) {
        return $installedCommand.Source
    }

    $knownPaths = @(
        'C:\Program Files\cloudflared\cloudflared.exe',
        'C:\Program Files (x86)\cloudflared\cloudflared.exe'
    )

    foreach ($knownPath in $knownPaths) {
        if (Test-Path -LiteralPath $knownPath) {
            return $knownPath
        }
    }

    throw 'cloudflared was not found. Install it with: winget install Cloudflare.cloudflared'
}

function Start-QuickTunnel {
    param(
        [Parameter(Mandatory)] [string] $CloudflaredPath,
        [Parameter(Mandatory)] [string] $Name,
        [Parameter(Mandatory)] [string] $Origin,
        [Parameter(Mandatory)] [string] $LogDirectory
    )

    $stdoutPath = Join-Path $LogDirectory "$Name-output.log"
    $stderrPath = Join-Path $LogDirectory "$Name-error.log"
    $process = Start-Process -FilePath $CloudflaredPath `
        -ArgumentList @('tunnel', '--url', $Origin, '--no-autoupdate') `
        -RedirectStandardOutput $stdoutPath `
        -RedirectStandardError $stderrPath `
        -WindowStyle Hidden `
        -PassThru

    for ($attempt = 0; $attempt -lt 120; $attempt++) {
        Start-Sleep -Milliseconds 250
        $process.Refresh()

        $log = if (Test-Path -LiteralPath $stderrPath) {
            Get-Content -LiteralPath $stderrPath -Raw -ErrorAction SilentlyContinue
        } else {
            ''
        }

        $match = [regex]::Match($log, 'https://[a-z0-9-]+\.trycloudflare\.com')

        if ($match.Success) {
            return @{
                Process = $process
                Url = $match.Value
            }
        }

        if ($process.HasExited) {
            throw "$Name tunnel stopped before it received a public URL. Check $stderrPath"
        }
    }

    Stop-Process -Id $process.Id -Force -ErrorAction SilentlyContinue
    throw "Timed out while creating the $Name tunnel. Check $stderrPath"
}

function Set-EnvironmentValue {
    param(
        [Parameter(Mandatory)] [string] $Path,
        [Parameter(Mandatory)] [string] $Name,
        [Parameter(Mandatory)] [string] $Value
    )

    $contents = [System.IO.File]::ReadAllText($Path)
    $line = "$Name=$Value"
    $pattern = '(?m)^' + [regex]::Escape($Name) + '=.*$'

    if ([regex]::IsMatch($contents, $pattern)) {
        $contents = [regex]::Replace($contents, $pattern, $line)
    } else {
        $contents = $contents.TrimEnd() + [Environment]::NewLine + $line + [Environment]::NewLine
    }

    [System.IO.File]::WriteAllText($Path, $contents)
}

$cloudflaredPath = Resolve-CloudflaredPath
$environmentPath = Join-Path $PSScriptRoot '.env'
$logDirectory = Join-Path ([System.IO.Path]::GetTempPath()) 'elearning-cloudflare-tunnels'
$tunnelProcesses = @()

if (-not (Test-Path -LiteralPath $environmentPath)) {
    throw '.env does not exist. Copy .env.example to .env before starting the project.'
}

New-Item -ItemType Directory -Path $logDirectory -Force | Out-Null

try {
    Write-Host 'Creating temporary HTTPS tunnels...' -ForegroundColor Cyan
    $applicationTunnel = Start-QuickTunnel -CloudflaredPath $cloudflaredPath -Name 'application' -Origin 'http://127.0.0.1:8000' -LogDirectory $logDirectory
    $tunnelProcesses += $applicationTunnel.Process
    $reverbTunnel = Start-QuickTunnel -CloudflaredPath $cloudflaredPath -Name 'reverb' -Origin 'http://127.0.0.1:8080' -LogDirectory $logDirectory
    $tunnelProcesses += $reverbTunnel.Process

    $applicationUri = [uri] $applicationTunnel.Url
    $reverbUri = [uri] $reverbTunnel.Url

    Set-EnvironmentValue -Path $environmentPath -Name 'REVERB_BROWSER_PUBLIC_HOST' -Value $reverbUri.Host
    Set-EnvironmentValue -Path $environmentPath -Name 'REVERB_BROWSER_PUBLIC_PORT' -Value '443'
    Set-EnvironmentValue -Path $environmentPath -Name 'REVERB_BROWSER_PUBLIC_SCHEME' -Value 'https'
    Set-EnvironmentValue -Path $environmentPath -Name 'REVERB_ALLOWED_ORIGINS' -Value "127.0.0.1,localhost,$($applicationUri.Host)"

    php artisan config:clear

    if ($LASTEXITCODE -ne 0) {
        throw 'Laravel configuration could not be cleared.'
    }

    npm run build

    if ($LASTEXITCODE -ne 0) {
        throw 'The frontend assets could not be built.'
    }

    $viteHotPath = Join-Path $PSScriptRoot 'public\hot'

    if (Test-Path -LiteralPath $viteHotPath) {
        Remove-Item -LiteralPath $viteHotPath -Force
    }

    Write-Host ''
    Write-Host 'Wi-Fi HTTPS testing is ready.' -ForegroundColor Cyan
    Write-Host "Open this same URL on both devices: $($applicationTunnel.Url)" -ForegroundColor Green
    Write-Host 'Use two different user accounts. Press Ctrl+C to stop everything.' -ForegroundColor DarkGray
    Write-Host ''

    npx concurrently --kill-others-on-fail `
        --names 'server,queue,reverb,schedule' `
        --prefix-colors '#93c5fd,#c4b5fd,#67e8f9,#fca5a5' `
        'php artisan serve --host=127.0.0.1 --port=8000' `
        'php artisan queue:work --tries=1' `
        'php artisan reverb:start --host=127.0.0.1 --port=8080' `
        'php artisan schedule:work'
} finally {
    foreach ($tunnelProcess in $tunnelProcesses) {
        if ($null -ne $tunnelProcess -and -not $tunnelProcess.HasExited) {
            Stop-Process -Id $tunnelProcess.Id -Force -ErrorAction SilentlyContinue
        }
    }
}
