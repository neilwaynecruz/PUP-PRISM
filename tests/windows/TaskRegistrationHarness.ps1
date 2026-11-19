#Requires -Version 7.2
param([Parameter(Mandatory)][string] $Fixture, [switch] $FailSecond)
$ErrorActionPreference = 'Stop'
$global:portableTasks = @{}
$global:portableRegistered = @()
$global:portableRemoved = @()
$global:portableJournal = Join-Path $Fixture 'storage/app/private/local-runtime/task-registration-test.json'

function Save-Journal {
    @{registered=$global:portableRegistered; removed=$global:portableRemoved; remaining=$global:portableTasks.Count} | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath $global:portableJournal
}

# These function doubles shadow the OS cmdlets. No Windows task is registered.
function Get-ScheduledTask { param($TaskName, $ErrorAction) return $global:portableTasks[$TaskName] }
function New-ScheduledTaskAction {
    param($Execute, $Argument, $WorkingDirectory)
    if ($Argument -notmatch '-ResumeAtLogon' -or $Argument -notlike ('*-File "' + $Fixture + '*')) { throw 'Action must quote the installation path and resume after sign-in.' }
    return [pscustomobject]@{Execute=$Execute; Arguments=$Argument; WorkingDirectory=$WorkingDirectory}
}
function New-ScheduledTaskTrigger {
    param([switch] $AtLogOn, $User)
    if (!$AtLogOn -or !$User) { throw 'Expected current-user sign-in trigger.' }
    return [pscustomobject]@{AtLogOn=$true; User=$User}
}
function New-ScheduledTaskPrincipal {
    param($UserId, $LogonType, $RunLevel)
    if ($LogonType -ne 'Interactive' -or $RunLevel -ne 'Limited') { throw 'Startup must not store a password or require highest privileges.' }
    return [pscustomobject]@{UserId=$UserId; LogonType=$LogonType; RunLevel=$RunLevel}
}
function New-ScheduledTaskSettingsSet {
    param($MultipleInstances, $RestartCount, $RestartInterval, $ExecutionTimeLimit, [switch] $AllowStartIfOnBatteries, [switch] $DontStopIfGoingOnBatteries)
    if ($MultipleInstances -ne 'IgnoreNew' -or $RestartCount -ne 3 -or $ExecutionTimeLimit -ne [TimeSpan]::Zero -or !$AllowStartIfOnBatteries -or !$DontStopIfGoingOnBatteries) { throw 'Unexpected supervision settings.' }
    return [pscustomobject]@{MultipleInstances=$MultipleInstances; RestartCount=$RestartCount}
}
function Register-ScheduledTask {
    param($TaskName, $Action, $Trigger, $Principal, $Settings, $Description)
    if ($FailSecond -and $global:portableRegistered.Count -eq 1) { throw 'Synthetic registration failure.' }
    $global:portableTasks[$TaskName] = [pscustomobject]@{TaskName=$TaskName; Actions=@($Action)}
    $global:portableRegistered += $TaskName
    Save-Journal
}
function Unregister-ScheduledTask {
    param($TaskName, [switch] $Confirm)
    if (!$global:portableTasks.ContainsKey($TaskName)) { throw 'Cannot remove an unowned task.' }
    $global:portableTasks.Remove($TaskName)
    $global:portableRemoved += $TaskName
    Save-Journal
}

$package = Join-Path $Fixture 'resources/windows/Prism.ps1'
& $package -Action InstallStartup
if ($global:portableRegistered.Count -ne 3) { throw 'Expected three startup definitions.' }
& $package -Action RemoveStartup
if ($global:portableTasks.Count -ne 0) { throw 'Startup removal left a registration behind.' }
