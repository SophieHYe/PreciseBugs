$defaultTentacleDownloadUrl = "https://octopus.com/downloads/latest/OctopusTentacle"
$defaultTentacleDownloadUrl64 = "https://octopus.com/downloads/latest/OctopusTentacle64"

# dot-source the helper file (cannot load as a module due to scope considerations)
. (Join-Path -Path (Split-Path (Split-Path $PSScriptRoot -Parent) -Parent) -ChildPath 'OctopusDSCHelpers.ps1')

function Get-APIResult {
    param (
        [Parameter(Mandatory=$true)]
        [System.String]
        $ServerUrl,

        [Parameter(Mandatory=$true)]
        [System.String]
        $API,

        [Parameter(Mandatory=$true)]
        [System.String]
        $APIKey
    )

    # Check to see if the server url ends with a /
    if (!$ServerUrl.EndsWith("/")) {
        # Add trailing slash
        $ServerUrl = "$ServerUrl/"
    }

    # form the api endpoint
    $apiEndpoint = "{0}api{1}" -f $ServerUrl, $API

    # Call API and capture results
    $results = Invoke-WebRequest -Uri $apiEndpoint -Headers @{"X-Octopus-ApiKey"="$APIKey"} -UseBasicParsing

    # return the result
    return ConvertFrom-Json -InputObject $results
}

function Add-SpaceIfPresent {
    param (
        [string]
        $Space,
        [string[]]
        $argumentList
     )
    if(![String]::IsNullOrEmpty($Space)) {
        $argumentList += @("--space", $Space)
    }

    return $argumentList
}

function Get-MachineFromOctopusServer {
    param (
        [Parameter(Mandatory=$true)]
        [String]
        $ServerUrl,
        [Parameter(Mandatory=$true)]
        [System.String]
        $APIKey,
        [Parameter(Mandatory=$true)]
        [System.String]
        $Instance,
        [Parameter(Mandatory=$true)]
        [AllowNull()]
        [AllowEmptyString()]
        [System.String]
        $SpaceId
    )
    $apiUrl = "/machines/all"
    if (![String]::IsNullOrEmpty($SpaceId)) {
        $apiUrl = "/$SpaceId" + $apiUrl
    }

    $machines = Get-APIResult -ServerUrl $ServerUrl -APIKey $APIKey -API $apiUrl
    $thumbprint = Get-TentacleThumbprint -Instance $Instance
    $machine = $machines | Where-Object {$_.Thumbprint -eq $thumbprint}

    return $machine
}

function Get-WorkerFromOctopusServer
{
    param (
        [Parameter(Mandatory=$true)]
        [String]
        $ServerUrl,
        [Parameter(Mandatory=$true)]
        [System.String]
        $APIKey,
        [Parameter(Mandatory=$true)]
        [System.String]
        $Instance,
        [Parameter(Mandatory=$true)]
        [AllowNull()]
        [AllowEmptyString()]
        [System.String]
        $SpaceId
    )
    $apiUrl = "/workers/all"
    if (![String]::IsNullOrEmpty($SpaceId)) {
        $apiUrl = "/$SpaceId" + $apiUrl
    }

    $workers = Get-APIResult -ServerUrl $ServerUrl -APIKey $APIKey -API $apiUrl
    $thumbprint = Get-TentacleThumbprint -Instance $Instance
    $worker = $workers | Where-Object {$_.Thumbprint -eq $thumbprint}

    return $worker
}

function Get-TentacleThumbprint {
    param (
        [Parameter(Mandatory=$true)]
        [string]
        $Instance
    )

    $thumbprint = Invoke-TentacleCommand @("show-thumbprint", "--instance", $Instance, "--console", "--thumbprint-only")
    return $thumbprint
}

function Get-WorkerPoolMembership {
    [OutputType([object[]])]
    param (
        [Parameter(Mandatory=$true)]
        [System.String]
        $ServerUrl,
        [Parameter(Mandatory=$true)]
        [System.String]
        $Thumbprint,
        [Parameter(Mandatory=$true)]
        [System.String]
        $ApiKey,
        [Parameter(Mandatory=$true)]
        [AllowNull()]
        [AllowEmptyString()]
        [System.String]
        $SpaceId
    )
    $apiUrl = "/workerpools/all"

    if (![String]::IsNullOrEmpty($SpaceId)) {
        $apiUrl = "/$SpaceId" + $apiUrl
    }

    $octoWorkerPools = Get-APIResult -ServerUrl $ServerUrl -ApiKey $ApiKey -API $apiUrl

    $workerPoolMembership = @()
    $workersUrl = "/workers/all"
    if (![String]::IsNullOrEmpty($SpaceId)) {
        $workersUrl = "/$SpaceId" + $workersUrl
    }
    $workersall = Get-APIResult -ServerUrl $ServerUrl -ApiKey $ApiKey -API $workersUrl

    foreach ($octoWorkerPool in $octoWorkerPools) {
        $workers = $workersall | Where-Object { $_.WorkerPoolIds -contains $($octoWorkerPool.Id) }
        # Check to see if the thumbprint is listed
        $workerWithThumbprint = ($workers | Where-Object {$_.Thumbprint -eq $Thumbprint})
        if ($null -ne $workerWithThumbprint) {
            $workerPoolMembership += $octoWorkerPool
        }
    }
    return ,$workerPoolMembership
}

function Test-ParameterSet {
    param(
        [Parameter(Mandatory=$true)]
        [System.String]
        $publicHostNameConfiguration,
        [Parameter(Mandatory=$true)]
        [AllowNull()]
        [AllowEmptyString()]
        [System.String]
        $CustomPublicHostName,
        [System.String[]]
        $Environments = "",
        [System.String[]]
        $Roles = "",
        [System.String[]]
        $WorkerPools,
        [System.String[]]
        $Tenants,
        [System.String[]]
        $TenantTags
    )

    if($publicHostNameConfiguration -eq "Custom" -and [String]::IsNullOrWhiteSpace($CustomPublicHostName)) {
        throw "Invalid configuration requested. " + `
            "PublicHostNameConfiguration was set to 'Custom' but an invalid or null CustomPublicHostName was specified."
    } elseif ((Test-Value($Environments)) -and (Test-Value($WorkerPools))) {
        throw "Invalid configuration requested. " + `
            "You have asked for the Tentacle to be registered as a worker, but still provided the 'Environments' configuration argument. " + `
            "Please remove the 'Environments' configuration argument."
    } elseif ((Test-Value($Roles)) -and (Test-Value($WorkerPools))) {
        throw "Invalid configuration requested. " + `
            "You have asked for the Tentacle to be registered as a worker, but still provided the 'Roles' configuration argument. " + `
            "Please remove the 'Roles' configuration argument."
    } elseif ((Test-Value($Tenants)) -and (Test-Value($WorkerPools))) {
        throw "Invalid configuration requested. " + `
            "You have asked for the Tentacle to be registered as a worker, but still provided the 'Tenants' configuration argument. " + `
            "Please remove the 'Tenants' configuration argument."
    } elseif ((Test-Value($TenantTags)) -and (Test-Value($WorkerPools))) {
        throw "Invalid configuration requested. " + `
            "You have asked for the Tentacle to be registered as a worker, but still provided the 'TenantTags' configuration argument. " + `
            "Please remove the 'TenantTags' configuration argument."
    }
}

function Get-Space {
    param(
        [Parameter(Mandatory=$true)]
        [System.String]
        $Space,
        [Parameter(Mandatory=$true)]
        [System.String]
        $ServerUrl,
        [Parameter(Mandatory=$true)]
        [System.String]
        $APIKey
    )
    $spaces = Get-APIResult -ServerUrl $ServerUrl -APIKey $APIKey -API "/spaces/all"
    return ($spaces | Where-Object {$_.Name -eq $Space})
}

function Get-TargetResource {
    [OutputType([Hashtable])]
    param (
        [ValidateSet("Present", "Absent")]
        [string]$Ensure = "Present",
        [Parameter(Mandatory)]
        [ValidateNotNullOrEmpty()]
        [string]$Name,
        [ValidateSet("Started", "Stopped")]
        [string]$State = "Started",
        [ValidateSet("Listen", "Poll")]
        [string]$CommunicationMode = "Listen",
        [string]$ApiKey,
        [string]$DisplayName = "$($env:COMPUTERNAME)_$Name",
        [string]$OctopusServerUrl,
        [string[]]$Environments = "",
        [string[]]$Roles = "",
        [string]$Policy,
        [string[]]$Tenants = "",
        [string[]]$TenantTags = "",
        [string]$DefaultApplicationDirectory,
        [int]$ListenPort = 10933,
        [int]$TentacleCommsPort = 0,
        [int]$ServerPort = 10943,
        [string]$tentacleDownloadUrl = $defaultTentacleDownloadUrl,
        [string]$tentacleDownloadUrl64 = $defaultTentacleDownloadUrl64,
        [ValidateSet("PublicIp", "FQDN", "ComputerName", "Custom")]
        [string]$PublicHostNameConfiguration = "PublicIp",
        [string]$CustomPublicHostName,
        [string]$TentacleHomeDirectory = "$($env:SystemDrive)\Octopus",
        [bool]$RegisterWithServer = $true,
        [string]$OctopusServerThumbprint,
        [PSCredential]$TentacleServiceCredential,
        [string[]]$WorkerPools,
        [ValidateSet("Untenanted","TenantedOrUntenanted","Tenanted")]
        [string]$TenantedDeploymentParticipation,
        [string]$Space
    )

    Test-ParameterSet   -publicHostNameConfiguration $PublicHostNameConfiguration `
                        -customPublicHostName $CustomPublicHostName `
                        -Environments $Environments `
                        -Roles $Roles `
                        -WorkerPools $WorkerPools `
                        -Tenants $Tenants `
                        -TenantTags $TenantTags

    Write-Verbose "Checking if Tentacle is installed"
    $installLocation = (Get-ItemProperty -path "HKLM:\Software\Octopus\Tentacle" -ErrorAction SilentlyContinue).InstallLocation
    $present = ($null -ne $installLocation)
    Write-Verbose "Tentacle present: $present"

    $currentEnsure = if ($present) { "Present" } else { "Absent" }

    $serviceName = (Get-TentacleServiceName $Name)
    Write-Verbose "Checking for Windows Service: $serviceName"
    $serviceInstance = Get-Service -Name $serviceName -ErrorAction SilentlyContinue
    $currentState = "Stopped"
    if ($null -ne $serviceInstance) {
        Write-Verbose "Windows service: $($serviceInstance.Status)"
        if ($serviceInstance.Status -eq "Running") {
            $currentState = "Started"
        }

        if ($currentEnsure -eq "Absent") {
            Write-Verbose "Since the Windows Service is still installed, the service is present"
            $currentEnsure = "Present"
        }
    } else {
        Write-Verbose "Windows service: Not installed"
        $currentEnsure = "Absent"
    }

    $originalDownloadUrl = $null
    if (Test-Path "$($env:SystemDrive)\Octopus\Octopus.DSC.installstate") {
        $originalDownloadUrl = (Get-Content -Raw -Path "$($env:SystemDrive)\Octopus\Octopus.DSC.installstate" | ConvertFrom-Json).TentacleDownloadUrl
    }

    return @{
        Name                = $Name;
        Ensure              = $currentEnsure;
        State               = $currentState;
        TentacleDownloadUrl = $originalDownloadUrl;
    };
}

# test a variable has a value (whether its an array or string)
function Test-Value($value) {
    if ($null -eq $value) { return $false }
    if ($value -eq "") { return $false }
    if ($value.length -eq 0) { return $false }
    if ($value.length -eq 1 -and $value[0].length -eq 0) { return $false }
    return $true
}

function Confirm-RegistrationParameter {
    param (
        [Parameter(Mandatory)]
        [ValidateSet("Present", "Absent")]
        [string]
        $Ensure,
        [bool]$RegisterWithServer,
        [string[]]$Environments,
        [string[]]$Roles,
        [string]$Policy,
        [string[]]$Tenants,
        [string[]]$TenantTags,
        [string]$OctopusServerUrl,
        [string]$ApiKey
    )

    if ($Ensure -eq "Absent") {
        if ((-not (Test-Value($ApiKey))) -and (($RegisterWithServer))) {
            throw "Invalid configuration requested. " + `
                "You have asked for the Tentacle to be de-registered from the server, but not provided the 'ApiKey' configuration argument. " + `
                "Please specify the 'ApiKey' configuration argument or set 'RegisterWithServer = `$False'."
        }
    } elseif ((Test-Value($Roles)) -and (-not ($RegisterWithServer))) {
        throw "Invalid configuration requested. " + `
            "You have asked for the Tentacle not to be registered with the server, but still provided the 'Roles' configuration argument. " + `
            "Please remove the 'Roles' configuration argument or set 'RegisterWithServer = `$True'."
    } elseif ((Test-Value($Environments)) -and (-not ($RegisterWithServer))) {
        throw "Invalid configuration requested. " + `
            "You have asked for the Tentacle not to be registered with the server, but still provided the 'Environments' configuration argument. " + `
            "Please remove the 'Environments' configuration argument or set 'RegisterWithServer = `$True'."
    } elseif ((Test-Value($Tenants)) -and (-not ($RegisterWithServer))) {
        throw "Invalid configuration requested. " + `
            "You have asked for the Tentacle not to be registered with the server, but still provided the 'Tenants' configuration argument. " + `
            "Please remove the 'Tenants' configuration argument or set 'RegisterWithServer = `$True'."
    } elseif ((Test-Value($TenantTags)) -and (-not ($RegisterWithServer))) {
        throw "Invalid configuration requested. " + `
            "You have asked for the Tentacle not to be registered with the server, but still provided the 'TenantTags' configuration argument. " + `
            "Please remove the 'TenantTags' configuration argument or set 'RegisterWithServer = `$True'."
    } elseif ((Test-Value($Policy)) -and (-not ($RegisterWithServer))) {
        throw "Invalid configuration requested. " + `
            "You have asked for the Tentacle not to be registered with the server, but still provided the 'Policy' configuration argument. " + `
            "Please remove the 'Policy' configuration argument or set 'RegisterWithServer = `$True'."
    } elseif ((-not (Test-Value($OctopusServerUrl))) -and (($RegisterWithServer))) {
        throw "Invalid configuration requested. " + `
            "You have asked for the Tentacle to be registered with the server, but not provided the 'OctopusServerUrl' configuration argument. " + `
            "Please specify the 'OctopusServerUrl' configuration argument or set 'RegisterWithServer = `$False'."
    } elseif ((-not (Test-Value($ApiKey))) -and (($RegisterWithServer))) {
        throw "Invalid configuration requested. " + `
            "You have asked for the Tentacle to be registered with the server, but not provided the 'ApiKey' configuration argument. " + `
            "Please specify the 'ApiKey' configuration argument or set 'RegisterWithServer = `$False'."
    }
}

function Confirm-RequestedState() {
    param (
        [Parameter(Mandatory)]
        [Hashtable]$parameters
    )
    if ($parameters['Ensure'] -eq "Absent" -and $parameters['State'] -eq "Started") {
        throw "Invalid configuration requested. " + `
            "You have asked for the service to not exist, but also be running at the same time. " + `
            "You probably want 'State = `"Stopped`"'."
    }
}

function Set-TargetResource {
    param (
        [ValidateSet("Present", "Absent")]
        [string]$Ensure = "Present",
        [Parameter(Mandatory)]
        [ValidateNotNullOrEmpty()]
        [string]$Name,
        [ValidateSet("Started", "Stopped")]
        [string]$State = "Started",
        [ValidateSet("Listen", "Poll")]
        [string]$CommunicationMode = "Listen",
        [string]$ApiKey,
        [string]$OctopusServerUrl,
        [string]$DisplayName = "$($env:COMPUTERNAME)_$Name",
        [string[]]$Environments = "",
        [string[]]$Roles = "",
        [string]$Policy,
        [string[]]$Tenants = "",
        [string[]]$TenantTags = "",
        [string]$DefaultApplicationDirectory = "$($env:SystemDrive)\Applications",
        [int]$ListenPort = 10933,
        [int]$TentacleCommsPort = 0,
        [int]$ServerPort = 10943,
        [string]$tentacleDownloadUrl = $defaultTentacleDownloadUrl,
        [string]$tentacleDownloadUrl64 = $defaultTentacleDownloadUrl64,
        [ValidateSet("PublicIp", "FQDN", "ComputerName", "Custom")]
        [string]$PublicHostNameConfiguration = "PublicIp",
        [string]$CustomPublicHostName,
        [string]$TentacleHomeDirectory = "$($env:SystemDrive)\Octopus",
        [bool]$RegisterWithServer = $true,
        [string]$OctopusServerThumbprint,
        [PSCredential]$TentacleServiceCredential,
        [string[]]$WorkerPools,
        [ValidateSet("Untenanted","TenantedOrUntenanted","Tenanted")]
        [string]$TenantedDeploymentParticipation,
        [string]$Space
    )
    Confirm-RequestedState $PSBoundParameters
    Confirm-RegistrationParameter `
        -Ensure $Ensure `
        -RegisterWithServer $RegisterWithServer `
        -Environments $Environments `
        -Roles $Roles `
        -Policy $Policy `
        -Tenants $Tenants `
        -TenantTags $TenantTags `
        -OctopusServerUrl $OctopusServerUrl `
        -ApiKey $ApiKey

    $currentResource = (Get-TargetResource -Name $Name -Ensure $Ensure)

    Write-Verbose "Configuring Tentacle..."
    if ($State -eq "Stopped" -and $currentResource["State"] -eq "Started") {
        $serviceName = (Get-TentacleServiceName $Name)
        Write-Verbose "Stopping $serviceName"
        Stop-Service -Name $serviceName -Force
    }

    if ($Ensure -eq "Absent" -and $currentResource["Ensure"] -eq "Present") {
        if ($RegisterWithServer) {
            Remove-TentacleRegistration -name $Name -apiKey $ApiKey -octopusServerUrl $OctopusServerUrl -Space $Space
        }

        $serviceName = (Get-TentacleServiceName $Name)
        Write-Verbose "Deleting service $serviceName..."
        Invoke-AndAssert { & sc.exe delete $serviceName }

        $otherServices = @(Get-CimInstance win32_service | Where-Object {$_.PathName -like "`"$($env:ProgramFiles)\Octopus Deploy\Tentacle\Tentacle.exe*"})

        if ($otherServices.length -eq 0) {
            # Uninstall msi
            Invoke-MsiUninstall
        }
        else {
            Write-Verbose "Skipping uninstall, as other tentacles still exist:"
            foreach ($otherService in $otherServices) {
                Write-Verbose " - $($otherService.Name)"
            }
        }
    }
    elseif ($Ensure -eq "Present" -and $currentResource["Ensure"] -eq "Absent") {
        Write-Verbose "Installing Tentacle..."
        New-Tentacle -name $Name `
            -apiKey $ApiKey `
            -octopusServerUrl $OctopusServerUrl `
            -listenPort $ListenPort `
            -tentacleCommsPort $TentacleCommsPort `
            -displayName $DisplayName `
            -environments $Environments `
            -roles $Roles `
            -policy $Policy `
            -tenants $Tenants `
            -tenantTags $TenantTags `
            -defaultApplicationDirectory $DefaultApplicationDirectory `
            -tentacleDownloadUrl $tentacleDownloadUrl `
            -tentacleDownloadUrl64 $tentacleDownloadUrl64 `
            -communicationMode $CommunicationMode `
            -serverPort $ServerPort `
            -publicHostNameConfiguration $PublicHostNameConfiguration `
            -customPublicHostName $CustomPublicHostName `
            -tentacleHomeDirectory $TentacleHomeDirectory `
            -registerWithServer $RegisterWithServer `
            -octopusServerThumbprint $OctopusServerThumbprint `
            -TentacleServiceCredential $TentacleServiceCredential `
            -WorkerPools $WorkerPools `
            -TenantedDeploymentParticipation $TenantedDeploymentParticipation  `
            -Space $Space

        Write-Verbose "Tentacle installed!"
    }
    elseif ($Ensure -eq "Present" -and $currentResource["TentacleDownloadUrl"] -ne (Get-TentacleDownloadUrl $tentacleDownloadUrl $tentacleDownloadUrl64)) {
        Write-Verbose "Upgrading Tentacle..."
        $serviceName = (Get-TentacleServiceName $Name)
        Stop-Service -Name $serviceName
        Install-Tentacle -name $name `
                         -tentacleDownloadUrl $tentacleDownloadUrl `
                         -tentacleDownloadUrl64 $tentacleDownloadUrl64 `
                         -tentacleHomeDirectory $TentacleHomeDirectory
        if ($State -eq "Started") {
            Start-Service $serviceName
        }
        Write-Verbose "Tentacle upgraded!"
    }
    elseif ($Ensure -eq "Present" -and $currentResource["Ensure"] -eq "Present") {
        Write-Verbose "Upgrading/modifying Tentacle..."
        if (($null -ne $WorkerPools) -and ($WorkerPools.Count -gt 0)) {
            Write-Verbose "Registering $Name as a worker in worker pools $($workerPools -join ", ")."
            Add-TentacleToWorkerPool -name $name `
                -octopusServerUrl $octopusServerUrl `
                -apiKey $apiKey `
                -workerPools $workerPools `
                -communicationMode $communicationMode `
                -displayName $displayName `
                -publicHostNameConfiguration $publicHostNameConfiguration `
                -customPublicHostName $customPublicHostName `
                -listenPort $listenPort `
                -tentacleCommsPort $tentacleCommsPort `
                -space $space
        } elseif (![string]::IsNullOrEmpty($Environments) -and ![string]::IsNullOrEmpty($Roles)) {
             Register-Tentacle -name $Name `
                 -apiKey $ApiKey `
                 -octopusServerUrl $OctopusServerUrl `
                 -environments $Environments `
                 -roles $Roles `
                 -tenants $Tenants `
                 -tenantTags $TenantTags `
                 -policy $Policy `
                 -communicationMode $CommunicationMode `
                 -displayName $DisplayName `
                 -publicHostNameConfiguration $PublicHostNameConfiguration `
                 -customPublicHostName $CustomPublicHostName `
                 -listenPort $ListenPort `
                 -serverPort $ServerPort `
                 -tentacleCommsPort $TentacleCommsPort `
                 -TenantedDeploymentParticipation $TenantedDeploymentParticipation `
                 -Space $Space
         }
    }

    if ($State -eq "Started" -and $currentResource["State"] -eq "Stopped") {
        $serviceName = (Get-TentacleServiceName $Name)
        Write-Verbose "Starting $serviceName"
        Start-Service -Name $serviceName
    }

    Write-Verbose "Finished"
}

function Test-TargetResource {
    param (
        [ValidateSet("Present", "Absent")]
        [string]$Ensure = "Present",
        [Parameter(Mandatory)]
        [ValidateNotNullOrEmpty()]
        [string]$Name,
        [ValidateSet("Started", "Stopped")]
        [string]$State = "Started",
        [ValidateSet("Listen", "Poll")]
        [string]$CommunicationMode = "Listen",
        [string]$ApiKey,
        [string]$OctopusServerUrl,
        [string]$DisplayName = "$($env:COMPUTERNAME)_$Name",
        [string[]]$Environments = "",
        [string[]]$Roles = "",
        [string]$Policy,
        [string[]]$Tenants = "",
        [string[]]$TenantTags = "",
        [string]$DefaultApplicationDirectory,
        [int]$ListenPort = 10933,
        [int]$TentacleCommsPort = 0,
        [int]$ServerPort = 10943,
        [string]$tentacleDownloadUrl = $defaultTentacleDownloadUrl,
        [string]$tentacleDownloadUrl64 = $defaultTentacleDownloadUrl64,
        [ValidateSet("PublicIp", "FQDN", "ComputerName", "Custom")]
        [string]$PublicHostNameConfiguration = "PublicIp",
        [string]$CustomPublicHostName,
        [string]$TentacleHomeDirectory = "$($env:SystemDrive)\Octopus",
        [bool]$RegisterWithServer = $true,
        [string]$OctopusServerThumbprint,
        [PSCredential]$TentacleServiceCredential,
        [string[]]$WorkerPools,
        [ValidateSet("Untenanted","TenantedOrUntenanted","Tenanted")]
        [string]$TenantedDeploymentParticipation,
        [string]$Space
    )

    $currentResource = (Get-TargetResource -Name $Name)

    $ensureMatch = $currentResource["Ensure"] -eq $Ensure
    Write-Verbose "Ensure: $($currentResource["Ensure"]) vs. $Ensure = $ensureMatch"
    if (!$ensureMatch) {
        return $false
    }

    $stateMatch = $currentResource["State"] -eq $State
    Write-Verbose "State: $($currentResource["State"]) vs. $State = $stateMatch"
    if (!$stateMatch) {
        return $false
    }

    if ($null -ne $currentResource["TentacleDownloadUrl"]) {
        $requestedDownloadUrl = Get-TentacleDownloadUrl $tentacleDownloadUrl $tentacleDownloadUrl64
        $downloadUrlsMatch = $requestedDownloadUrl -eq $currentResource["TentacleDownloadUrl"]
        Write-Verbose "Download Url: $($currentResource["TentacleDownloadUrl"]) vs. $requestedDownloadUrl = $downloadUrlsMatch"
        if (!$downloadUrlsMatch) {
            return $false
        }
    }

    if ($Ensure -eq "Present" -and ![string]::IsNullOrEmpty($OctopusServerUrl)) {
        if (![string]::IsNullOrEmpty($Space)) {
            $spaceRef = Get-Space -Space $Space -ServerUrl $OctopusServerUrl -APIKey $ApiKey

            if ($null -eq $spaceRef) {
                throw "Unable to find a space by the name of '$Space'"
            }
        }

        if ($null -ne $WorkerPools -and $WorkerPools.Length -gt 0) {
            $worker = Get-WorkerFromOctopusServer -ServerUrl $OctopusServerUrl -APIKey $ApiKey -Instance $Name -SpaceId $spaceRef.Id

            if ($null -ne $worker) {
                $workerPoolMembership = Get-WorkerPoolMembership -ServerUrl $OctopusServerUrl -ApiKey $ApiKey -Thumbprint $worker.Endpoint.Thumbprint -SpaceId $spaceRef.Id

                if ($WorkerPools.Count -ne $workerPoolMembership.Count) {
                    Write-Verbose "Worker pools [$WorkerPools] vs [$workerPoolMembership] = $false"
                    return $false
                } else {
                    foreach ($workerPool in $workerPoolMembership) {
                        if ($WorkerPools -notcontains $workerPool.Name) {
                            Write-Verbose "Worker pools: [$WorkerPools] vs [$workerPoolMembership] = $false"
                            return $false
                        }
                    }
                }
            } else {
                if ([string]::IsNullOrEmpty($Space)) {
                    Write-Verbose "Worker '$Name' is not registered in Space '$Space'"
                } else {
                    Write-Verbose "Worker '$Name' is not registered"
                }
                return $false
            }
        } else {
            $machine = Get-MachineFromOctopusServer -ServerUrl $OctopusServerUrl -APIKey $ApiKey -Instance $Name -SpaceId $spaceRef.Id

            if ($null -ne $machine) {
                if ($Environments.Count -ne $machine.EnvironmentIds.Count) {
                    Write-Verbose "Environments: [$Environments] vs [$machine.EnvironmentIds]: $false"
                    return $false
                } else {
                    foreach ($environmentId in $machine.EnvironmentIds) {
                        $environmentUrl = "/environments/$environmentId"
                        if ($null -ne $spaceRef) {
                            $environmentUrl = "/$($spaceRef.Id)" + $environmentUrl
                        }
                        $environment = Get-APIResult -ServerUrl $OctopusServerUrl -ApiKey $ApiKey -API $environmentUrl
                        if ($Environments -notcontains $environment.Name) {
                            Write-Verbose "Environments: Machine currently has environment $($environment.Name), which is not listed in the passed in list [$Environments].  Machine is not in desired state."
                            return $false
                        }
                    }
                }

                if ($Roles.Count -ne $machine.Roles.Count) {
                    Write-Verbose "Roles: [$Roles] vs [$($machine.Roles)]: $false"
                    return $false
                } else {
                    $differences = Compare-Object -ReferenceObject $Roles -DifferenceObject $machine.Roles
                    if ($null -ne $differences) {
                        Write-Verbose "Roles: [$Roles] vs [$($machine.Roles)]: $false"
                        return $false
                    }
                }
            } else {
                if ([string]::IsNullOrEmpty($Space)) {
                    Write-Verbose "Machine '$Name' is not registered"
                } else {
                    Write-Verbose "Machine '$Name' is not registered in Space '$Space'"
                }
                return $false
            }
        }
    }

    Write-Verbose "Everything looks to be in working order"
    return $true
}

function Get-TentacleServiceName {
    param ( [string]$instanceName )

    if ($instanceName -eq "Tentacle") {
        return "OctopusDeploy Tentacle"
    }
    else {
        return "OctopusDeploy Tentacle: $instanceName"
    }
}

# After the Tentacle is registered with Octopus, Tentacle listens on a TCP port, and Octopus connects to it. The Octopus server
# needs to know the public IP address to use to connect to this Tentacle instance.
function Get-MyPublicIPAddress {
    [CmdletBinding()]
    [Diagnostics.CodeAnalysis.SuppressMessageAttribute("PSUseDeclaredVarsMoreThanAssignments", "")]
    param()
    Write-Verbose "Getting public IP address"

    [Net.ServicePointManager]::SecurityProtocol = @(
        [Net.SecurityProtocolType]::Tls12,
        [Net.SecurityProtocolType]::Tls11
    )

    $publicIPServices = @('https://api.ipify.org/', 'https://canhazip.com/', 'https://ipv4bot.whatismyipaddress.com/')
    $ip = $null
    $x = 0
    while($null -eq $ip -and $x -lt $publicIPServices.Length) {
        try {
            $target = $publicIpServices[$x++]
            $ip = Invoke-RestMethod -Uri $target
        }
        catch {
            Write-Verbose "Failed to find a public IP via $target. Reason: $_ "
        }
    }

    if($null -eq $ip) {
        throw "Unable to determine your Public IP address. Please supply a hostname or IP address via the PublicHostName parameter."
    }

    try {
        [Ipaddress]$ip | Out-Null
    } catch {
        throw "Detected Public IP address '$ip', but we we couldn't parse it as an IPv4 address."
    }

    return $ip
}


function Install-Tentacle {
    param (
        [string]$name,
        [string]$tentacleDownloadUrl,
        [string]$tentacleDownloadUrl64,
        [string]$tentacleHomeDirectory
    )
    Write-Verbose "Beginning Tentacle installation"

    $actualTentacleDownloadUrl = Get-TentacleDownloadUrl $tentacleDownloadUrl $tentacleDownloadUrl64

    if (-not (Test-Path $tentacleHomeDirectory)) {
        Write-Verbose "Tentacle Home directory does not exist. Creating..."
        New-Item -Path "$tentacleHomeDirectory" -ItemType Directory | Out-Null
    }
    $tentaclePath = "$tentacleHomeDirectory\Tentacle.msi"
    if ((Test-Path $tentaclePath) -eq $true) {
        Remove-Item $tentaclePath -force
    }
    Write-Verbose "Downloading Octopus Tentacle MSI from $actualTentacleDownloadUrl to $tentaclePath"
    Request-File $actualTentacleDownloadUrl $tentaclePath

    if (-not (Test-Path $env:TEMP)) {
        Write-Verbose "Configured temp folder does not currently exist, creating..."
        New-Item $env:TEMP -ItemType Directory -force | Out-Null # an edge case when the env var exists but the folder does not
    }

    $logDirectory = Get-LogDirectory
    Invoke-MsiExec -name $name -logDirectory $logDirectory -msiPath $tentaclePath

    if (-not (Test-Path "$($env:SystemDrive)\Octopus")) {
        Write-Verbose "$($env:SystemDrive)\Octopus not found. Creating..."
        New-Item -type Directory "$($env:SystemDrive)\Octopus" -Force | Out-Null
    }
    Update-InstallState "TentacleDownloadUrl" $actualTentacleDownloadUrl -global
}

function Get-LogDirectory {
    $logDirectory = "$($env:SystemDrive)\Octopus\logs"
    if (-not (Test-Path $logDirectory)) { New-Item -type Directory $logDirectory | out-null }
    return $logDirectory
}

function Update-InstallState {
    param (
        [string]$key,
        [string]$value,
        [switch]$global = $false
    )

    if ((Test-Path "$($env:SystemDrive)\Octopus\Octopus.DSC.installstate") -or $global) { # do we already have a legacy installstate file, or are we writing global settings?
        $installStateFile = "$($env:SystemDrive)\Octopus\Octopus.DSC.installstate"
    } else {
        $installStateFile = "$($env:SystemDrive)\Octopus\Octopus.DSC.$script:instancecontext.installstate"
    }

    $currentInstallState = @{}
    if (Test-Path $installStateFile) {
        $fileContent = (Get-Content -Raw -Path $installStateFile | ConvertFrom-Json)
        $fileContent.psobject.properties | ForEach-Object { $currentInstallState[$_.Name] = $_.Value }
    }

    $currentInstallState.Set_Item($key, $value)

    $currentInstallState | ConvertTo-Json | set-content $installStateFile
}

function Invoke-MsiExec ($name, $logDirectory, $msiPath) {
    Write-Verbose "Installing MSI..."
    $msiLog = "$logDirectory\Tentacle.$name.msi.log"
    write-verbose "Executing 'msiexec.exe /i $msiPath /quiet /l*v $msiLog'"
    $msiExitCode = (Start-Process -FilePath "msiexec.exe" -ArgumentList @("/i", "`"$msiPath`"", "/quiet", "/l*v", $msiLog) -Wait -Passthru).ExitCode
    Write-Verbose "MSI installer returned exit code $msiExitCode"
    if ($msiExitCode -ne 0) {
        throw "Installation of the MSI failed; MSIEXEC exited with code: $msiExitCode. View the log at $msiLog"
    }
}

function Invoke-MsiUninstall
{
    Write-Verbose "Uninstalling Tentacle..."
    if (-not (Test-Path "$TentacleHomeDirectory\logs")) {
        Write-Verbose "Log directory does not exist. Creating..."
        New-Item -type Directory "$TentacleHomeDirectory\logs" | Out-Null
    }
    $tentaclePath = "$TentacleHomeDirectory\Tentacle.msi"
    $msiLog = "$TentacleHomeDirectory\logs\Tentacle.msi.uninstall.log"
    if (test-path $tentaclePath) {
        $msiExitCode = (Start-Process -FilePath "msiexec.exe" -ArgumentList "/x `"$tentaclePath`" /quiet /l*v `"$msiLog`"" -Wait -Passthru).ExitCode
        Write-Verbose "Tentacle MSI installer returned exit code $msiExitCode"
        if ($msiExitCode -ne 0) {
            throw "Removal of Tentacle failed, MSIEXEC exited with code: $msiExitCode. View the log at $msiLog"
        }
    } else {
        throw "Tentacle cannot be removed, because the MSI could not be found."
    }
}

function New-Tentacle {
    param (
        [Parameter(Mandatory = $True)]
        [string]$name,
        [string]$apiKey,
        [string]$octopusServerUrl,
        [Parameter(Mandatory = $False)]
        [string[]]$environments = "",
        [Parameter(Mandatory = $False)]
        [string[]]$roles = "",
        [Parameter(Mandatory = $False)]
        [string[]]$tenants = "",
        [Parameter(Mandatory = $False)]
        [string[]]$tenantTags = "",
        [Parameter(Mandatory = $False)]
        [string]$policy,
        [int]$listenPort = 10933,
        [int]$tentacleCommsPort = 0,
        [string]$displayName,
        [string]$defaultApplicationDirectory,
        [string]$tentacleDownloadUrl,
        [string]$tentacleDownloadUrl64,
        [ValidateSet("Listen", "Poll")]
        [string]$communicationMode = "Listen",
        [int]$serverPort = 10943,
        [ValidateSet("PublicIp", "FQDN", "ComputerName", "Custom")]
        [string]$publicHostNameConfiguration = "PublicIp",
        [string]$customPublicHostName,
        [string]$tentacleHomeDirectory = "$($env:SystemDrive)\Octopus",
        [bool]$registerWithServer = $true,
        [Parameter(Mandatory = $False)]
        [string]$octopusServerThumbprint,
        [PSCredential]$TentacleServiceCredential,
        [string[]] $workerPools,
        [string]$TenantedDeploymentParticipation,
        [string]$Space
    )

    Install-Tentacle -Name $name `
                     -tentacleDownloadUrl $tentacleDownloadUrl `
                     -tentacleDownloadUrl64 $tentacleDownloadUrl64 `
                     -tentacleHomeDirectory $tentacleHomeDirectory
    if ($communicationMode -eq "Listen") {
        $windowsFirewall = Get-Service -Name MpsSvc
        if ($windowsFirewall.Status -eq "Running") {

            # Check to see if the firewall rule already exists
            $rules = Invoke-Command {& netsh.exe advfirewall firewall show rule name="Octopus Tentacle: $Name"} | Write-Output

            if ($rules -eq "No rules match the specified criteria.") {
                Write-Verbose "Open port $listenPort on Windows Firewall"
                Invoke-AndAssert { & netsh.exe advfirewall firewall add rule protocol=TCP dir=in localport=$listenPort action=allow name="Octopus Tentacle: $Name" }
            } else {
                Write-Verbose "Tentacle firewall rule already exists, skipping firewall rule addition"
            }
        } else {
            Write-Verbose "Windows Firewall Service is not running... skipping firewall rule addition"
        }
    }
    Write-Verbose "Configuring and registering Tentacle"

    $tentacleAppDirectory = $DefaultApplicationDirectory
    $tentacleConfigFile = "$tentacleHomeDirectory\$Name\Tentacle.config"
    Write-Verbose "Tentacle configuration set as $tentacleConfigFile"
    Invoke-TentacleCommand @("create-instance", "--instance", "$name", "--config", "$tentacleConfigFile", "--console")
    Invoke-TentacleCommand @("new-certificate", "--instance", "$name", "--console")

    $configureArgs = @(
        'configure',
        '--instance', $name,
        '--home', $tentacleHomeDirectory,
        '--app', $tentacleAppDirectory,
        '--console')
    if (($null -ne $octopusServerThumbprint) -and ($octopusServerThumbprint -ne "")) {
        $configureArgs += @('--trust', $octopusServerThumbprint)
    }

    if ($CommunicationMode -eq "Listen") {
        $configureArgs += @('--port', $listenPort)
    } else {
        $configureArgs += @('--noListen', 'True')
    }
    Invoke-TentacleCommand $configureArgs

    $serviceArgs = @(
        'service',
        '--install',
        '--instance', $name,
        '--console',
        '--reconfigure'
    )

    if ($TentacleServiceCredential) {
        Write-Verbose "Adding Service identity to installation command"

        $serviceArgs += @(
            '--username', $TentacleServiceCredential.UserName
            '--password', $TentacleServiceCredential.GetNetworkCredential().Password
        )
    }
    Invoke-TentacleCommand $serviceArgs

    Pop-Location

    if ($registerWithServer) {
        if (($null -ne $workerPools) -and ($workerPools.Count -gt 0)) {
            Write-Verbose "Adding Tentacle to worker pool"
            Add-TentacleToWorkerPool -name $name `
                -octopusServerUrl $octopusServerUrl `
                -apiKey $apiKey `
                -workerPools $workerPools `
                -communicationMode $communicationMode `
                -displayName $displayName `
                -customPublicHostName $customPublicHostName `
                -publicHostNameConfiguration $publicHostNameConfiguration `
                -listenPort $listenPort `
                -tentacleCommsPort $tentacleCommsPort `
                -space $space
        } elseif (![string]::IsNullOrEmpty($Environments) -and ![string]::IsNullOrEmpty($Roles)) {
            Write-Verbose "Registering Tentacle"
            Register-Tentacle -name $name `
                -apiKey $apiKey `
                -octopusServerUrl $octopusServerUrl `
                -environments $environments `
                -roles $roles `
                -tenants $tenants `
                -tenantTags $tenantTags `
                -policy $policy `
                -communicationMode $communicationMode `
                -displayName $displayName `
                -publicHostNameConfiguration $publicHostNameConfiguration `
                -customPublicHostName $customPublicHostName `
                -serverPort $serverPort `
                -listenPort $listenPort `
                -tentacleCommsPort $tentacleCommsPort `
                -TenantedDeploymentParticipation $TenantedDeploymentParticipation `
                -space $space
        }
    } else {
        Write-Verbose "Skipping registration with server as 'RegisterWithServer' is set to '$registerWithServer'"
    }

    Write-Verbose "Tentacle commands complete"
}

function Get-PublicHostName {
    param (
        [ValidateSet("PublicIp", "FQDN", "ComputerName", "Custom")]
        [string]$publicHostNameConfiguration = "PublicIp",
        [string]$customPublicHostName
    )
    if ($publicHostNameConfiguration -eq "Custom") {
        $publicHostName = $customPublicHostName
    } elseif ($publicHostNameConfiguration -eq "FQDN") {
        $publicHostName = [System.Net.Dns]::GetHostByName($env:computerName).HostName
    } elseif ($publicHostNameConfiguration -eq "ComputerName") {
        $publicHostName = $env:COMPUTERNAME
    } else {
        $publicHostName = Get-MyPublicIPAddress
    }
    $publicHostName = $publicHostName.Trim()
    return $publicHostName
}

function Get-TentacleDownloadUrl {
    param (
        [string]$tentacleDownloadUrl,
        [string]$tentacleDownloadUrl64
    )

    if ([IntPtr]::Size -eq 4) {
        return $tentacleDownloadUrl
    }
    return $tentacleDownloadUrl64
}

function Remove-TentacleRegistration {
    param (
        [Parameter(Mandatory = $True)]
        [string]$name,
        [Parameter(Mandatory = $True)]
        [string]$apiKey,
        [Parameter(Mandatory = $True)]
        [string]$octopusServerUrl,
        [Parameter(Mandatory = $True)]
        [AllowNull()]
        [AllowEmptyString()]
        [string]$Space
    )

    if (Test-TentacleExecutableExists) {
        Write-Verbose "Beginning Tentacle deregistration"
        $argumentList = @("deregister-from", "--instance", "$name", "--server", $octopusServerUrl, "--apiKey", $apiKey, "--console")
        $argumentList = Add-SpaceIfPresent -argumentList $argumentList -space $space
        Invoke-TentacleCommand $argumentList
    } else {
        Write-Verbose "Could not find Tentacle.exe"
    }
}

function Remove-WorkerPoolRegistration {
    param(
        [Parameter(Mandatory = $true)]
        [string]
        $octopusServerUrl,
        [Parameter(Mandatory = $true)]
        [string]
        $apiKey,
        [Parameter(Mandatory = $true)]
        [PSCredential]
        $TentacleServiceCredential,
        [Parameter(Mandatory = $true)]
        [string]
        $name,
        [Parameter(Mandatory = $true)]
        [string]
        $Space
    )
    if (Test-TentacleExecutableExists) {
        Write-Verbose "Deregistering $($env:ComputerName) from worker pools"
        $argumentList = @(
            "deregister-worker",
            "--instance", $name,
            "--server", $octopusServerUrl,
            "--console"
        )
        $argumentList = Add-SpaceIfPresent -Space $Space -ArgumentList $argumentList
        if (![string]::IsNullOrEmpty($apiKey)) {
            $argumentList += @(
                "--apiKey", $apiKey
            )
        } elseif (![string]::IsNullOrEmpty($TentacleServiceCredential)) {
            $argumentList += @(
                "--username", $TentacleServiceCredential.UserName,
                "--password", $TentacleServiceCredential.GetNetworkCredential().Password
            )
        } else {
            throw "Both APIKey and TentacleServiceCredential are null!"
        }
        Invoke-TentacleCommand $argumentList
    } else {
        throw "Could not find Tentacle.exe"
    }
}

function Add-TentacleToWorkerPool {
    param(
        [Parameter(Mandatory = $true)]
        [String]
        $name,
        [Parameter(Mandatory = $true)]
        [String]
        $octopusServerUrl,
        [Parameter(Mandatory = $true)]
        [string]
        $apiKey,
        [Parameter(Mandatory = $false)]
        [PSCredential]
        $TentacleServiceCredential,
        [Parameter(Mandatory = $true)]
        [String[]]
        $workerPools,
        [Parameter(Mandatory = $true)]
        [String]
        [ValidateSet("Listen", "Poll")]
        $communicationMode,
        [string]$displayName,
        [string]$publicHostNameConfiguration = "PublicIp",
        [string]$customPublicHostName,
        [int]$tentacleCommsPort = 0,
        [int]$listenPort = 0,
        [Parameter(Mandatory = $true)]
        [AllowNull()]
        [AllowEmptyString()]
        [String]
        $Space
    )
    if ($listenPort -eq 0) {
        $listenPort = 10933
    }
    if ($tentacleCommsPort -eq 0) {
        $tentacleCommsPort = $listenPort
    }

    if (Test-TentacleExecutableExists) {
        Write-Verbose "Adding $($env:COMPUTERNAME) to pool(s) $($workerPools -join ', ')"
        $argumentList = @(
            "register-worker",
            "--instance", $name,
            "--server", $octopusServerUrl,
            "--name", $displayName,
            "--force"
        )
        $argumentList = Add-SpaceIfPresent -Space $Space -ArgumentList $argumentList
        if (![string]::IsNullOrEmpty($apiKey)) {
            $argumentList += @(
                "--apiKey", $apiKey
            )
        } elseif (![string]::IsNullOrEmpty($TentacleServiceCredential)) {
            $argumentList += @(
                "--username", $TentacleServiceCredential.UserName,
                "--password", $TentacleServiceCredential.GetNetworkCredential().Password
            )
        } else {
            throw "Both APIKey and TentacleServiceCredential are null!"
        }
        if ($CommunicationMode -eq "Listen") {
            $publicHostName = Get-PublicHostName $publicHostNameConfiguration $customPublicHostName
            Write-Verbose "Public host name: $publicHostName"
            $argumentList += @(
                "--comms-style", "TentaclePassive",
                "--publicHostName", $publicHostName
            )
            if ($tentacleCommsPort -ne $listenPort) {
                $argumentList += @("--tentacle-comms-port", $tentacleCommsPort)
            }
        } else {
            $argumentList += @(
                "--comms-style", "TentacleActive",
                "--server-comms-port", $serverPort
            )
        }

        foreach ($workerPool in $workerPools) {
            $argumentList += @(
                "--workerpool", $workerPool
            )
        }

        Invoke-TentacleCommand $argumentList
    }
}

function Register-Tentacle {
    param (
        [string]$name,
        [string]$apiKey,
        [string]$octopusServerUrl,
        [Parameter(Mandatory = $False)]
        [string[]]$environments = "",
        [Parameter(Mandatory = $False)]
        [string[]]$roles = "",
        [Parameter(Mandatory = $False)]
        [string[]]$tenants = "",
        [Parameter(Mandatory = $False)]
        [string[]]$tenantTags = "",
        [Parameter(Mandatory = $False)]
        [string]$policy,
        [ValidateSet("Listen", "Poll")]
        [string]$communicationMode = "Listen",
        [string]$displayName,
        [string]$publicHostNameConfiguration = "PublicIp",
        [string]$customPublicHostName,
        [int]$serverPort = 10943,
        [int]$listenPort = 10933,
        [int]$tentacleCommsPort = 0,
        [string]$TenantedDeploymentParticipation,
        [string]$Space
    )
    if ($listenPort -eq 0) {
        $listenPort = 10933
    }
    if ($tentacleCommsPort -eq 0) {
        $tentacleCommsPort = $listenPort
    }

    $registerArguments = @(
        "register-with",
        "--instance", $name,
        "--server", $octopusServerUrl,
        "--name", $displayName,
        "--apiKey", $apiKey,
        "--force",
        "--console"
    )

    $registerArguments = Add-SpaceIfPresent -Space $Space -ArgumentList $registerArguments

    if (($null -ne $policy) -and ($policy -ne "")) {
        $registerArguments += @("--policy", $policy)
    }
    if ($CommunicationMode -eq "Listen") {
        $publicHostName = Get-PublicHostName $publicHostNameConfiguration $customPublicHostName
        Write-Verbose "Public host name: $publicHostName"
        $registerArguments += @(
            "--comms-style", "TentaclePassive",
            "--publicHostName", $publicHostName
        )
        if ($tentacleCommsPort -ne $listenPort) {
            $registerArguments += @("--tentacle-comms-port", $tentacleCommsPort)
        }
    } else {
        $registerArguments += @(
            "--comms-style", "TentacleActive",
            "--server-comms-port", $serverPort
        )
    }
    if ($environments -ne "" -and $environments.Count -gt 0) {
        foreach ($environment in $environments) {
            foreach ($e2 in $environment.Split(',')) {
                $registerArguments += "--environment"
                $registerArguments += $e2.Trim()
            }
        }
    }

    if ($roles -ne "" -and $roles.Count -gt 0) {
        foreach ($role in $roles) {
            foreach ($r2 in $role.Split(',')) {
                $registerArguments += "--role"
                $registerArguments += $r2.Trim()
            }
        }
    }

    if ($tenants -ne "" -and $tenants.Count -gt 0) {
        foreach ($tenant in $tenants) {
            foreach ($t2 in $tenant.Split(',')) {
                $registerArguments += "--tenant"
                $registerArguments += $t2.Trim()
            }
        }
    }

    if ($tenantTags -ne "" -and $tenantTags.Count -gt 0) {
        foreach ($tenantTag in $tenantTags) {
            foreach ($tt2 in $tenantTag.Split(',')) {
                $registerArguments += "--tenanttag"
                $registerArguments += $tt2.Trim()
            }
        }
    }

    if ($TenantedDeploymentParticipation -ne "") {
        $registerArguments += @("--tenanted-deployment-participation", $TenantedDeploymentParticipation)
    }

    Invoke-TentacleCommand $registerArguments
}
