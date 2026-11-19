#Requires -Version 7.2
[CmdletBinding()]
param(
    [ValidateSet('Configure', 'Check', 'Start', 'Run', 'Stop', 'InstallStartup', 'RemoveStartup')]
    [string] $Action = 'Start',
    [ValidateSet('Web', 'Queue', 'Scheduler')]
    [string] $Component = 'Web',
    [string] $PhpPath,
    [ValidateRange(1024, 65535)]
    [int] $Port = 8000,
    [switch] $NoBrowser,
    [switch] $ResumeAtLogon
)

$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$runtimePath = Join-Path $projectRoot 'storage/app/private/local-runtime'
$configPath = Join-Path $runtimePath 'config.json'
$hash = [Convert]::ToHexString([Security.Cryptography.SHA256]::HashData([Text.Encoding]::UTF8.GetBytes($projectRoot.ToLowerInvariant()))).Substring(0, 12)
$roles = @('Web', 'Queue', 'Scheduler')
$taskPrefix = "PUP-PRISM-$hash"

function Initialize-PrivateDirectory {
    [IO.Directory]::CreateDirectory($runtimePath) | Out-Null
    $sid = [Security.Principal.WindowsIdentity]::GetCurrent().User.Value
    & icacls.exe $runtimePath /inheritance:r /grant:r "*${sid}:(OI)(CI)F" '*S-1-5-18:(OI)(CI)F' | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Cannot restrict the runtime directory. No services were started.' }
}

function Read-Settings {
    if (!(Test-Path -LiteralPath $configPath)) { throw 'First run Prism.ps1 -Action Configure. See docs/operations/WINDOWS_LOCAL_INSTALLATION.md.' }
    $settings = Get-Content -LiteralPath $configPath -Raw | ConvertFrom-Json
    if ([int]$settings.port -lt 1024 -or [int]$settings.port -gt 65535) {
        throw 'Invalid local configuration. Run Configure again with the correct PHP executable and port.'
    }
    return $settings
}

function Assert-Ready($Settings) {
    if (!(Test-Path -LiteralPath $Settings.php -PathType Leaf)) { throw 'Configured PHP is unavailable. Run Configure with its new path. Stop and RemoveStartup remain available.' }
    Push-Location $projectRoot
    try {
        if (!(Test-Path -LiteralPath 'vendor/autoload.php') -or !(Test-Path -LiteralPath '.env')) {
            throw 'Missing dependencies or .env. A developer must provision this installation; no files will be replaced.'
        }
        $result = & $Settings.php artisan app:local-runtime-check "--url=http://localhost:$($Settings.port)" --json --no-interaction
        if ($LASTEXITCODE -ne 0) {
            try { $failed = (($result | ConvertFrom-Json).checks.PSObject.Properties | Where-Object { $_.Value -eq $false }).Name -join ', ' }
            catch { $failed = 'application bootstrap; review the private Laravel log' }
            throw "Not ready: $failed. Run Check after your developer corrects these items. No database changes were made."
        }
    } finally { Pop-Location }
}

function Test-Runner([string] $Role) {
    $state = Join-Path $runtimePath "$Role.json"
    if (!(Test-Path -LiteralPath $state)) { return $false }
    try {
        $saved = Get-Content -LiteralPath $state -Raw | ConvertFrom-Json
        $process = Get-Process -Id $saved.pid -ErrorAction Stop
        return $process.StartTime.ToUniversalTime().Ticks -eq $saved.started
    } catch { return $false }
}

function Start-Runner([string] $Role) {
    if (Test-Runner $Role) { return }
    $stopFile = Join-Path $runtimePath "$Role.stop"
    if (Test-Path -LiteralPath $stopFile) { Remove-Item -LiteralPath $stopFile }
    $start = [Diagnostics.ProcessStartInfo]::new((Join-Path $PSHOME 'pwsh.exe'))
    $start.UseShellExecute = $true
    $start.WindowStyle = [Diagnostics.ProcessWindowStyle]::Hidden
    $start.WorkingDirectory = $projectRoot
    foreach ($argument in @('-NoProfile', '-File', $PSCommandPath, '-Action', 'Run', '-Component', $Role)) { $start.ArgumentList.Add($argument) }
    [Diagnostics.Process]::Start($start) | Out-Null
}

function Run-Component($Settings) {
    $mutex = [Threading.Mutex]::new($false, "Local\$taskPrefix-$Component")
    $acquired = $false
    $child = $null
    try {
        try { $acquired = $mutex.WaitOne(0) } catch [Threading.AbandonedMutexException] { $acquired = $true }
        if (!$acquired) { return }
        $stopFile = Join-Path $runtimePath "$Component.stop"
        if ($ResumeAtLogon -and (Test-Path -LiteralPath $stopFile)) { Remove-Item -LiteralPath $stopFile }
        Assert-Ready $Settings
        if (Test-Path -LiteralPath $stopFile) { return }
        $process = Get-Process -Id $PID
        @{pid=$PID; started=$process.StartTime.ToUniversalTime().Ticks} | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $runtimePath "$Component.json")
        $arguments = switch ($Component) {
            'Web' { @('artisan', 'serve', '--host=127.0.0.1', "--port=$($Settings.port)", '--tries=1', '--no-reload', '--no-interaction') }
            'Queue' { @('artisan', 'queue:listen', '--queue=security,mail,default,notifications,broadcast', '--sleep=3', '--tries=3', '--timeout=60', '--no-interaction') }
            'Scheduler' { @('artisan', 'schedule:work', '--no-interaction') }
        }
        $failures = 0
        while (!(Test-Path -LiteralPath $stopFile)) {
            if ($Component -eq 'Web') {
                $listener = [Net.Sockets.TcpClient]::new()
                try { $listener.Connect('127.0.0.1', [int]$Settings.port); throw 'Port is already in use. Stop the other server; PRISM will not take over an unknown process.' }
                catch [Net.Sockets.SocketException] { }
                finally { $listener.Dispose() }
            }
            Assert-Ready $Settings
            $child = Start-Process -FilePath $Settings.php -ArgumentList $arguments -WorkingDirectory $projectRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtimePath "$Component.stdout.log") -RedirectStandardError (Join-Path $runtimePath "$Component.stderr.log") -PassThru
            while (!$child.WaitForExit(1000)) {
                if (Test-Path -LiteralPath $stopFile) {
                    # Only terminate the process tree created and still owned by this runner.
                    & taskkill.exe /PID $child.Id /T /F | Out-Null
                    return
                }
            }
            $failures++
            if ($failures -ge 5) { throw "$Component stopped repeatedly. Correct the cause and choose Start again. See private runtime logs." }
            Start-Sleep -Seconds ([Math]::Min(30, 2 * $failures))
        }
    } finally {
        if ($child -and !$child.HasExited) { & taskkill.exe /PID $child.Id /T /F | Out-Null }
        if ($acquired) {
            Remove-Item -LiteralPath (Join-Path $runtimePath "$Component.json") -ErrorAction SilentlyContinue
            $mutex.ReleaseMutex()
        }
        $mutex.Dispose()
    }
}

try {
    if (!$IsWindows) { throw 'This package supports Windows with PowerShell 7.2 or newer.' }
    if ($Action -eq 'Configure') {
        if ($roles | Where-Object { Test-Runner $_ }) { throw 'Stop this installation before reconfiguring it.' }
        if (!$PhpPath) { $PhpPath = (Get-Command php -CommandType Application).Source }
        $PhpPath = (Resolve-Path -LiteralPath $PhpPath).Path
        if ([IO.Path]::GetFileName($PhpPath) -ne 'php.exe') { throw 'Select the PHP CLI executable (php.exe).' }
        $versionId = & $PhpPath -r 'echo PHP_VERSION_ID;'
        if ($LASTEXITCODE -ne 0 -or [int]$versionId -lt 80400) { throw 'PHP 8.4 or newer is required.' }
        Initialize-PrivateDirectory
        @{php=$PhpPath; port=$Port} | ConvertTo-Json | Set-Content -LiteralPath $configPath
        Write-Host 'Local paths saved. No .env, key, database, network, task, or service was changed. Run Check next.'
        exit 0
    }
    $settings = Read-Settings
    if ($Action -eq 'Check') {
        Assert-Ready $settings
        Write-Host 'Configuration and database checks passed. Email inbox receipt and startup-after-reboot are not proven by this check.'
        foreach ($role in $roles) { Write-Host "$role runner: $(Test-Runner $role)" }
    } elseif ($Action -eq 'Run') {
        Start-Transcript -LiteralPath (Join-Path $runtimePath "$Component.runner.log") -Append | Out-Null
        try { Run-Component $settings } finally { Stop-Transcript | Out-Null }
    } elseif ($Action -eq 'Stop') {
        foreach ($role in $roles) { [IO.File]::WriteAllText((Join-Path $runtimePath "$role.stop"), 'stop') }
        Write-Host 'Stop requested. This can interrupt work: only use after users finish and queues are drained.'
    } elseif ($Action -eq 'Start') {
        Assert-Ready $settings
        if (!(Test-Runner 'Web')) {
            $probe = [Net.Sockets.TcpClient]::new()
            try { $probe.Connect('127.0.0.1', [int]$settings.port); throw 'The selected port is occupied. No new workers were started; stop the other server or configure a different stable port.' }
            catch [Net.Sockets.SocketException] { }
            finally { $probe.Dispose() }
        }
        $newRoles = @($roles | Where-Object { !(Test-Runner $_) })
        foreach ($role in $roles) { Start-Runner $role }
        $ready = $false
        $deadline = [DateTime]::UtcNow.AddSeconds(30)
        do {
            try {
                $response = Invoke-WebRequest "http://127.0.0.1:$($settings.port)/up" -NoProxy -TimeoutSec 2
                $ready = $response.StatusCode -eq 200 -and $response.Content -match 'Application up' -and !($roles | Where-Object { !(Test-Runner $_) })
            } catch { }
            if (!$ready) { Start-Sleep -Milliseconds 500 }
        } while (!$ready -and [DateTime]::UtcNow -lt $deadline)
        if (!$ready) {
            foreach ($role in $newRoles) { [IO.File]::WriteAllText((Join-Path $runtimePath "$role.stop"), 'stop') }
            throw 'Not ready. Newly launched components were asked to stop. Run Check and review private runtime logs.'
        }
        Write-Host 'PUP-PRISM is ready on this laptop.'
        if (!$NoBrowser) { Start-Process "http://localhost:$($settings.port)" }
    } elseif ($Action -eq 'InstallStartup') {
        Assert-Ready $settings
        $user = [Security.Principal.WindowsIdentity]::GetCurrent().Name
        foreach ($role in $roles) {
            $name = "$taskPrefix-$role"
            if (Get-ScheduledTask -TaskName $name -ErrorAction SilentlyContinue) { throw "Task $name already exists. RemoveStartup first; existing tasks will not be overwritten." }
        }
        $registered = @()
        try {
            foreach ($role in $roles) {
                $taskAction = New-ScheduledTaskAction -Execute (Join-Path $PSHOME 'pwsh.exe') -Argument "-NoProfile -WindowStyle Hidden -File `"$PSCommandPath`" -Action Run -Component $role -ResumeAtLogon" -WorkingDirectory $projectRoot
                $trigger = New-ScheduledTaskTrigger -AtLogOn -User $user
                $principal = New-ScheduledTaskPrincipal -UserId $user -LogonType Interactive -RunLevel Limited
                $taskSettings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1) -ExecutionTimeLimit ([TimeSpan]::Zero) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
                Register-ScheduledTask -TaskName "$taskPrefix-$role" -Action $taskAction -Trigger $trigger -Principal $principal -Settings $taskSettings -Description "PUP-PRISM $role at $projectRoot; no stored credentials" | Out-Null
                $registered += "$taskPrefix-$role"
            }
        } catch {
            $failure = $_
            foreach ($name in $registered) { Unregister-ScheduledTask -TaskName $name -Confirm:$false }
            throw $failure
        }
        Write-Host 'Startup registered for the current Windows user at sign-in, not before sign-in. No tasks were started.'
    } elseif ($Action -eq 'RemoveStartup') {
        foreach ($role in $roles) {
            $task = Get-ScheduledTask -TaskName "$taskPrefix-$role" -ErrorAction SilentlyContinue
            if ($task) {
                if ($task.Actions.Arguments -notcontains "-NoProfile -WindowStyle Hidden -File `"$PSCommandPath`" -Action Run -Component $role -ResumeAtLogon") { throw 'Task ownership mismatch; no task was removed.' }
                Unregister-ScheduledTask -TaskName $task.TaskName -Confirm:$false
            }
        }
        Write-Host 'Matching startup registrations removed. Use Stop separately after draining work.'
    }
} catch {
    Write-Error $_.Exception.Message
    exit 1
}
