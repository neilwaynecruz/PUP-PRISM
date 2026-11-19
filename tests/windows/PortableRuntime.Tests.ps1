#Requires -Version 7.2
[CmdletBinding()]
param([string] $ReuseFixture)
$ErrorActionPreference = 'Stop'
$sourceRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$installation = Join-Path $sourceRoot ("storage/framework/testing/portable installation " + [Guid]::NewGuid().ToString('N'))
if ($ReuseFixture) {
    $resolvedFixture = (Resolve-Path -LiteralPath $ReuseFixture).Path
    $fixtureParent = [IO.Path]::GetFullPath((Join-Path $sourceRoot 'storage/framework/testing'))
    if ([IO.Path]::GetDirectoryName($resolvedFixture) -ne $fixtureParent -or [IO.Path]::GetFileName($resolvedFixture) -notmatch '^portable installation [a-f0-9]{32}$' -or ((Get-Item -LiteralPath $resolvedFixture).Attributes -band [IO.FileAttributes]::ReparsePoint)) {
        throw 'Reuse is restricted to a generated synthetic fixture directly under storage/framework/testing.'
    }
    $installation = $resolvedFixture
}
$php = (Get-Command php -CommandType Application).Source
$pwsh = Join-Path $PSHOME 'pwsh.exe'
$checks = 0

function Assert-True([bool] $Condition, [string] $Message) {
    if (!$Condition) { throw "FAIL: $Message" }
    $script:checks++
    Write-Host "PASS: $Message"
}

function Invoke-Child([string] $Executable, [string[]] $Arguments) {
    $start = [Diagnostics.ProcessStartInfo]::new($Executable)
    $start.WorkingDirectory = $installation
    $start.UseShellExecute = $false
    $start.RedirectStandardOutput = $true
    $start.RedirectStandardError = $true
    foreach ($key in @($start.Environment.Keys)) {
        if ($key -match '^(APP_|DB_|MAIL_|RESEND_|QUEUE_|CACHE_|SESSION_|RETENTION_|BROADCAST_|REVERB_|INERTIA_|TRUSTED_PROXIES)') { $start.Environment.Remove($key) | Out-Null }
    }
    foreach ($argument in $Arguments) { $start.ArgumentList.Add($argument) }
    $process = [Diagnostics.Process]::Start($start)
    $stdout = $process.StandardOutput.ReadToEndAsync()
    $stderr = $process.StandardError.ReadToEndAsync()
    if (!$process.WaitForExit(120000)) { $process.Kill($true); throw 'Test child exceeded its safety deadline.' }
    $result = @{code=$process.ExitCode; output=$stdout.GetAwaiter().GetResult(); error=$stderr.GetAwaiter().GetResult()}
    $process.Dispose()
    return $result
}

function Invoke-Package([string[]] $Arguments) {
    return Invoke-Child $pwsh (@('-NoProfile', '-File', (Join-Path $installation 'resources/windows/Prism.ps1')) + $Arguments)
}

function Wait-Condition([scriptblock] $Condition, [int] $Seconds = 15) {
    $deadline = [DateTime]::UtcNow.AddSeconds($Seconds)
    do {
        if (& $Condition) { return $true }
        Start-Sleep -Milliseconds 200
    } while ([DateTime]::UtcNow -lt $deadline)
    return $false
}

$originalEnvHash = (Get-FileHash (Join-Path $sourceRoot '.env')).Hash
[IO.Directory]::CreateDirectory($installation) | Out-Null
foreach ($folder in @('app', 'bootstrap', 'config', 'database', 'routes', 'vendor', 'resources/windows', 'resources/views', 'public/build', 'tests/Fixtures')) {
    $destination = Join-Path $installation $folder
    [IO.Directory]::CreateDirectory($destination) | Out-Null
    & robocopy.exe (Join-Path $sourceRoot $folder) $destination /E /XJ /NFL /NDL /NJH /NJS /NP /XF '*.sqlite' '*.sqlite-*' '*.dump' /R:1 /W:1 | Out-Null
    if ($LASTEXITCODE -ge 8) { throw "Copy failed: $folder" }
}
$cachedConfig = Join-Path $installation 'bootstrap/cache/config.php'
if (Test-Path -LiteralPath $cachedConfig) { Remove-Item -LiteralPath $cachedConfig }
# The source .env, database files, storage and uploaded files are never copied.
foreach ($file in @('artisan', 'composer.json', 'composer.lock')) { Copy-Item -LiteralPath (Join-Path $sourceRoot $file) -Destination $installation }
Get-ChildItem -LiteralPath (Join-Path $sourceRoot 'public') -File | Where-Object Name -ne 'hot' | Copy-Item -Destination (Join-Path $installation 'public')
foreach ($folder in @('storage/app/private', 'storage/logs', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views')) {
    [IO.Directory]::CreateDirectory((Join-Path $installation $folder)) | Out-Null
}
$socket = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, 0)
$socket.Start()
$port = $socket.LocalEndpoint.Port
$socket.Stop()
$database = Join-Path $installation 'database/portable-test.sqlite'
[IO.File]::WriteAllBytes($database, [byte[]]@())
$key = [Convert]::ToBase64String([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
$environment = @"
APP_NAME="PUP-PRISM SYNTHETIC INSTALLATION TEST"
APP_ENV=testing
APP_KEY=base64:$key
APP_DEBUG=false
APP_URL=http://localhost:$port
DB_CONNECTION=sqlite
DB_DATABASE="$($database.Replace('\', '/'))"
DB_FOREIGN_KEYS=true
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=90
CACHE_STORE=file
SESSION_DRIVER=file
MAIL_MAILER=array
BROADCAST_CONNECTION=null
INERTIA_SSR_ENABLED=false
RETENTION_SCHEDULING_ENABLED=false
"@
[IO.File]::WriteAllText((Join-Path $installation '.env'), $environment)
Assert-True ($database.StartsWith($installation + [IO.Path]::DirectorySeparatorChar)) 'database is inside the new isolated installation'
$migrate = Invoke-Child $php @('artisan', 'migrate', '--force', '--no-interaction')
Assert-True ($migrate.code -eq 0) ("isolated migrations succeed: " + $(if ($migrate.code -ne 0) {$migrate.error} else {'OK'}))
$envHash = (Get-FileHash (Join-Path $installation '.env')).Hash
$command = Join-Path $installation 'resources/windows/Prism.ps1'
$runtime = Join-Path $installation 'storage/app/private/local-runtime'

try {
    $result = Invoke-Package @('-Action', 'Configure', '-PhpPath', $php, '-Port', "$port")
    Assert-True ($result.code -eq 0) ("Configure handles a different installation path containing spaces: " + $result.error)
    $result = Invoke-Package @('-Action', 'Check')
    Assert-True ($result.code -eq 0) ("Check accepts isolated configuration: " + $result.error)
    $harness = Join-Path $PSScriptRoot 'TaskRegistrationHarness.ps1'
    $registration = Invoke-Child $pwsh @('-NoProfile', '-File', $harness, '-Fixture', $installation)
    Assert-True ($registration.code -eq 0) ("simulated startup registration and ownership-checked removal succeed: " + $registration.error)
    $registration = Invoke-Child $pwsh @('-NoProfile', '-File', $harness, '-Fixture', $installation, '-FailSecond')
    $journal = Get-Content (Join-Path $runtime 'task-registration-test.json') -Raw | ConvertFrom-Json
    Assert-True ($registration.code -ne 0 -and $journal.registered.Count -eq 1 -and $journal.removed.Count -eq 1 -and $journal.remaining -eq 0) 'partial startup registration failure rolls back only newly created tasks'
    Remove-Item -LiteralPath (Join-Path $runtime 'task-registration-test.json')
    $result = Invoke-Package @('-Action', 'Start', '-NoBrowser')
    Assert-True ($result.code -eq 0) ("Start supervises all three roles: " + $result.error)
    $login = Invoke-WebRequest "http://127.0.0.1:$port/login" -NoProxy -TimeoutSec 10
    Assert-True ($login.StatusCode -eq 200 -and $login.Content -match 'data-page=' -and $login.Content -match 'SYNTHETIC INSTALLATION TEST') 'real login renders from the isolated installation'
    $probeFile = Join-Path $installation 'storage/app/private/portable-runtime-probe.txt'
    if (Test-Path -LiteralPath $probeFile) { Remove-Item -LiteralPath $probeFile }
    $dispatch = Invoke-Child $php @('-r', 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); dispatch(new Tests\Fixtures\PortableRuntimeProbe);')
    Assert-True ($dispatch.code -eq 0) 'synthetic probe is dispatched to the isolated database queue'
    Assert-True (Wait-Condition { return Test-Path -LiteralPath (Join-Path $installation 'storage/app/private/portable-runtime-probe.txt') }) 'supervised listener actually processes a queued job'
    $states = @{}
    foreach ($role in @('Web', 'Queue', 'Scheduler')) { $states[$role] = (Get-Content (Join-Path $runtime "$role.json") -Raw | ConvertFrom-Json).pid }
    $result = Invoke-Package @('-Action', 'Start', '-NoBrowser')
    Assert-True ($result.code -eq 0) 'repeated Start succeeds'
    foreach ($role in $states.Keys) {
        Assert-True ((Get-Content (Join-Path $runtime "$role.json") -Raw | ConvertFrom-Json).pid -eq $states[$role]) "$role runner is not duplicated"
    }
    $webChild = Get-CimInstance Win32_Process | Where-Object { $_.ParentProcessId -eq $states['Web'] -and $_.Name -eq 'php.exe' } | Select-Object -First 1
    Assert-True ($null -ne $webChild) 'test identifies only its own web child'
    & taskkill.exe /PID $webChild.ProcessId /T /F | Out-Null
    Assert-True (Wait-Condition {
        try { $health = Invoke-WebRequest "http://127.0.0.1:$port/up" -NoProxy -TimeoutSec 1; return $health.StatusCode -eq 200 -and $health.Content -match 'Application up' } catch { return $false }
    } 20) 'supervisor recovers from a terminated web child'
    $schedule = Invoke-Child $php @('artisan', 'schedule:list', '--no-ansi')
    Assert-True ($schedule.code -eq 0 -and $schedule.output -notmatch 'trash:cleanup|prune-operational-data') 'destructive cleanup is absent from the isolated schedule'
    Assert-True ((Get-FileHash (Join-Path $installation '.env')).Hash -eq $envHash) 'startup preserves installation settings and application key'
    Assert-True ((Get-FileHash (Join-Path $sourceRoot '.env')).Hash -eq $originalEnvHash) 'original environment remains unchanged'
} finally {
    if (Test-Path -LiteralPath (Join-Path $runtime 'config.json')) {
        $null = Invoke-Package @('-Action', 'Stop')
        $stopped = Wait-Condition { return !(Get-ChildItem -LiteralPath $runtime -Filter '*.json' | Where-Object Name -ne 'config.json') } 20
        Assert-True $stopped 'isolated runners stop without terminating unrelated processes'
    }
}
$result = Invoke-Package @('-Action', 'Check')
Assert-True ($result.code -eq 0) 'Check is usable after Stop'
$localConfig = Join-Path $runtime 'config.json'
$savedConfig = Get-Content -LiteralPath $localConfig -Raw
try {
    $missingPhp = $savedConfig | ConvertFrom-Json
    $missingPhp.php = Join-Path $installation 'missing-php.exe'
    $missingPhp | ConvertTo-Json | Set-Content -LiteralPath $localConfig
    $result = Invoke-Package @('-Action', 'Check')
    Assert-True ($result.code -ne 0) 'missing PHP fails readiness clearly'
    $result = Invoke-Package @('-Action', 'Stop')
    Assert-True ($result.code -eq 0) 'Stop remains available after the PHP path changes'
} finally { [IO.File]::WriteAllText($localConfig, $savedConfig) }
$resume = [Diagnostics.ProcessStartInfo]::new($pwsh)
$resume.UseShellExecute = $true
$resume.WindowStyle = [Diagnostics.ProcessWindowStyle]::Hidden
foreach ($argument in @('-NoProfile', '-File', $command, '-Action', 'Run', '-Component', 'Web', '-ResumeAtLogon')) { $resume.ArgumentList.Add($argument) }
$resumed = [Diagnostics.Process]::Start($resume)
try {
    Assert-True (Wait-Condition { return Test-Path -LiteralPath (Join-Path $runtime 'Web.json') }) 'sign-in resume clears a previous manual stop marker'
} finally {
    $null = Invoke-Package @('-Action', 'Stop')
    Assert-True ($resumed.WaitForExit(20000)) 'resumed runner stops cleanly'
    $resumed.Dispose()
}
$occupier = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, $port)
$occupier.Start()
try {
    $result = Invoke-Package @('-Action', 'Start', '-NoBrowser')
    Assert-True ($result.code -ne 0 -and $result.error -match 'port is occupied') 'an occupied port is rejected before new workers start'
    Assert-True (!(Get-ChildItem -LiteralPath $runtime -Filter '*.json' | Where-Object Name -ne 'config.json')) 'failed startup leaves no new runners'
} finally { $occupier.Stop() }
Write-Host "$checks checks passed. Fixture retained for inspection: $installation"
