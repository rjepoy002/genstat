$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

function Assert-Regular($Path) {
    $item = Get-Item -LiteralPath $Path -Force
    if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'unsafe' }
    return $item
}

function Assert-Acl($Path, [bool]$Parent) {
    $acl = Get-Acl -LiteralPath $Path
    if ($trusted -notcontains $acl.GetOwner([Security.Principal.SecurityIdentifier]).Value) { throw 'unsafe' }
    foreach ($rule in $acl.GetAccessRules($true, $true, [Security.Principal.SecurityIdentifier])) {
        if ($rule.AccessControlType -ne [Security.AccessControl.AccessControlType]::Allow -or
            $trusted -contains $rule.IdentityReference.Value -or
            ($rule.PropagationFlags -band [Security.AccessControl.PropagationFlags]::InheritOnly)) { continue }
        if (!$Parent -or ($rule.FileSystemRights -band (0xD0040))) { throw 'unsafe' }
    }
}

try {
    $id = [Console]::In.ReadLine()
    if ($id -notmatch '^[a-f0-9]{24}$') { throw 'unsafe' }
    $sid = [Security.Principal.WindowsIdentity]::GetCurrent().User
    $trusted = @($sid.Value, 'S-1-5-18', 'S-1-5-32-544')
    $parent = [Environment]::GetFolderPath([Environment+SpecialFolder]::CommonApplicationData)
    if (!$parent -or !(Assert-Regular $parent).PSIsContainer) { throw 'unsafe' }
    Assert-Acl $parent $true
    $directory = Join-Path $parent ('PALECO-GENSTAT-COOP-' + $id)
    if (!(Test-Path -LiteralPath $directory)) {
        $security = [Security.AccessControl.DirectorySecurity]::new()
        $security.SetAccessRuleProtection($true, $false)
        foreach ($identity in $trusted | Select-Object -Unique) {
            $rule = [Security.AccessControl.FileSystemAccessRule]::new(
                [Security.Principal.SecurityIdentifier]::new($identity),
                [Security.AccessControl.FileSystemRights]::FullControl,
                [Security.AccessControl.InheritanceFlags]'ContainerInherit, ObjectInherit',
                [Security.AccessControl.PropagationFlags]::None,
                [Security.AccessControl.AccessControlType]::Allow)
            $security.AddAccessRule($rule)
        }
        [void][IO.Directory]::CreateDirectory($directory, $security)
    }
    if (!(Assert-Regular $directory).PSIsContainer) { throw 'unsafe' }
    Assert-Acl $directory $false
    foreach ($name in @('settings.json', 'settings.json.lock', 'key')) {
        $path = Join-Path $directory $name
        if (Test-Path -LiteralPath $path) {
            if ((Assert-Regular $path).PSIsContainer) { throw 'unsafe' }
            Assert-Acl $path $false
        }
    }
    [Console]::Write($directory)
} catch {
    if ($_.Exception.Message -eq 'unsafe') { exit 2 }
    exit 1
}
