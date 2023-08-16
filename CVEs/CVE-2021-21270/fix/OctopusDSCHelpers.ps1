# Module contains shared code for OctopusDSC

$octopusServerExePath = "$($env:ProgramFiles)\Octopus Deploy\Octopus\Octopus.Server.exe"
$tentacleExePath = "$($env:ProgramFiles)\Octopus Deploy\Tentacle\Tentacle.exe"

function Get-ODSCParameter($parameters) {
    # unfortunately $PSBoundParameters doesn't contain parameters that weren't supplied (because the default value was okay)
    # credit to https://www.briantist.com/how-to/splatting-psboundparameters-default-values-optional-parameters/
    $params = @{}
    foreach ($h in $parameters.GetEnumerator()) {
        $key = $h.Key
        $var = Get-Variable -Name $key -ErrorAction SilentlyContinue
        if ($null -ne $var) {
            $val = Get-Variable -Name $key -ErrorAction Stop  | Select-Object -ExpandProperty Value -ErrorAction Stop
            $params[$key] = $val
        }
    }
    return $params
}

function Test-GetODSCParameter {
    param(
        $Name,
        $Ensure,
        $DefaultValue = 'default'
    )
   return (Get-ODSCParameter $MyInvocation.MyCommand.Parameters)
}


function Invoke-WebClient($url, $OutFile) {
    $downloader = new-object System.Net.WebClient
    $downloader.DownloadFile($url, $OutFile)
}

function Request-File {
    [CmdletBinding()]
    param (
        [string]$url,
        [string]$saveAs
    )

    $retry = $true
    $retryCount = 0
    $maxRetries = 5
    $downloadFile = $true

    [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12, [System.Net.SecurityProtocolType]::Tls11, [System.Net.SecurityProtocolType]::Tls

    Write-Verbose "Checking to see if we have an installer at $saveas"
    if(Test-Path $saveAs)
    {
        # check if we already have a matching file on disk
        Write-Verbose "Local file exists"
        $localHash = Get-FileHash $saveAs -Algorithm SHA256 | Select -Expand hash
        Write-Verbose "Local SHA256 hash: $localHash"
        $remoteHash = (Invoke-WebRequest -uri $url -Method Head -UseBasicParsing | select -expand headers).GetEnumerator()  | ? { $_.Key -eq "x-amz-meta-sha256" } | select -expand value
        Write-Verbose "Remote SHA256 hash: $remoteHash"
        $downloadFile = ($localHash -ne $remoteHash)
    }
    else
    {
        Write-Verbose "No local installer found"
    }

    if($downloadFile)
    {
        while ($retry) {
            Write-Verbose "Downloading $url to $saveAs"

            try {
                Invoke-WebClient -Url $url -OutFile $saveAs
                $retry = $false
            }
            catch {
                Write-Verbose "Failed to download $url"

                $ex = $_.Exception
                while($null -ne $ex)
                {
                    Write-Verbose "Got Exception '$($ex.Message)'."
                    $ex = $ex.InnerException
                }

                Write-Verbose "Retrying up to $maxRetries times."

                if ($retryCount -gt $maxRetries) {
                    # rethrow the inner exception if we've retried enough times
                    throw $_.Exception.InnerException
                }
                $retryCount = $retryCount + 1
                Start-Sleep -Seconds 1
            }
        }
    }
    else
    {
        Write-Verbose "Local file and remote file hashes match. We already have the file, skipping download."
    }
}

function Invoke-AndAssert {
    param ($block)
    & $block | Write-Verbose
    if ($LASTEXITCODE -ne 0 -and $null -ne $LASTEXITCODE) {
        throw "Command returned exit code $LASTEXITCODE"
    }
}

function Write-Log {
    param (
        [string] $message
    )

    $timestamp = ([System.DateTime]::UTCNow).ToString("yyyy'-'MM'-'dd'T'HH':'mm':'ss")
    Write-Verbose "[$timestamp] $message"
}

Function Invoke-WithRetries {
    [CmdletBinding()]
    param(
        [scriptblock]$ScriptBlock,
        [int]$MaxRetries = 10,
        [int]$IntervalInMilliseconds = 200
    )

    $backoff = 1
    $retrycount = 0
    $returnvalue = $null
    while ($null -eq $returnvalue -and $retrycount -lt $MaxRetries) {
        try {
            $returnvalue = Invoke-Command $ScriptBlock
            if ($null -ne $LastExitCode -and $LastExitCode -ne 0) {
                throw "Command exited with exit code $LastExitCode"
            }
        }
        catch
        {
            if($error)
            {
                Write-Verbose ($error | Select-Object -first 1)
            }
            else {
                Write-Verbose ("Invoke-WithRetries threw an exception: " + ($_| Out-String))
            }
            Write-Verbose "We have tried $retrycount times, sleeping for $($backoff * $IntervalInMilliseconds) milliseconds and trying again."
            Start-Sleep -MilliSeconds ($backoff * $IntervalInMilliseconds)
            $backoff = $backoff + $backoff
            $retrycount++
        }
    }

    return $returnvalue
}

function Get-MaskedOutput {
    [CmdletBinding()]
    param($arguments)

    $singleAsterixArgs = "--masterkey|--license|--licence|--trust|--password|--remove-trust|--apikey|--pw|--pfx-password|--proxyPassword";
    $connectionStringArgs = "--connectionstring";

    # Scrub sensitive values
    for($x=0; $x -lt $arguments.count; $x++) {
        if($arguments[$x] -match $singleAsterixArgs) {
            $arguments[$x+1] = "**********"
        } elseif($arguments[$x] -match $connectionStringArgs) {
            $arguments[$x+1] = $arguments[$x+1] -replace "(password|pwd)=[^;|`"]*", "`$1=********"
        }
    }
    return $arguments
}

function Write-VerboseWithMaskedCommand ($cmdArgs) {
    $copiedarguments = @() # hack to pass a copy of the array, not a reference
    $copiedarguments += $cmdArgs
    $maskedarguments = Get-MaskedOutput $copiedarguments
    Write-Verbose "Executing command '$octopusServerExePath $($maskedarguments -join ' ')'"
}

function Invoke-OctopusServerCommand ($cmdArgs) {

    Write-VerboseWithMaskedCommand($cmdArgs);

    $LASTEXITCODE = 0
    $output = & $octopusServerExePath $cmdArgs 2>&1

    Write-CommandOutput $output
    if (($null -ne $LASTEXITCODE) -and ($LASTEXITCODE -ne 0)) {
        Write-Error "Command returned exit code $LASTEXITCODE. Aborting."
        throw "Command returned exit code $LASTEXITCODE. Aborting."
    }
    Write-Verbose "done."
}

function Test-TentacleExecutableExists {
    $tentacleDir = "${env:ProgramFiles}\Octopus Deploy\Tentacle"
    return ((test-path $tentacleDir) -and (test-path "$tentacleDir\tentacle.exe"))
}

function Invoke-TentacleCommand ($cmdArgs) {

    Write-VerboseWithMaskedCommand($cmdArgs);

    $LASTEXITCODE = 0
    $output = & $tentacleExePath $cmdArgs 2>&1

    Write-CommandOutput $output
    if (($null -ne $LASTEXITCODE) -and ($LASTEXITCODE -ne 0)) {
        Write-Error "Command returned exit code $LASTEXITCODE. Aborting."
        throw "Command returned exit code $LASTEXITCODE. Aborting."
    }
    Write-Verbose "done."
    return $output
}

function Write-CommandOutput {
    param (
        [string] $output
    )

    if ($output -eq "") { return }

    Write-Verbose ""
    #this isn't quite working
    foreach ($line in $output.Trim().Split("`n")) {
        Write-Verbose $line
    }
    Write-Verbose ""
}

function Get-ServerConfiguration($instanceName) {
    $rawConfig = & $octopusServerExePath show-configuration --format=json-hierarchical --noconsolelogging --console --instance $instanceName

    # handle a specific error where an exception in registry migration finds its way into the json-hierarchical output
    # Refer to Issue #179 (https://github.com/OctopusDeploy/OctopusDSC/issues/179)
    # wrapped in retries to catch transient issues in json output
    $config = Invoke-WithRetries {
        if(Test-ValidJson $rawConfig) {
            return $rawConfig | ConvertFrom-Json
        } else {
            Write-Warning "Invalid json encountered in show-configuration; attempting to clean up."
            $cleanedUpConfig = Get-CleanedJson $rawConfig
            if(Test-ValidJson $cleanedUpConfig ) {
                return $cleanedUpConfig | ConvertFrom-Json
            } else {
                throw "Attempted to cleanup bad JSON and failed."
            }
        }
    } -MaxRetries 3 -IntervalInMilliseconds 100

    $plainTextMasterKey = & $octopusServerExePath show-master-key --noconsolelogging --console --instance $instanceName

    $encryptedMasterKey = New-Object SecureString
    $plainTextMasterKey.ToCharArray() | Foreach-Object { $encryptedMasterKey.AppendChar($_) }
    $config | Add-Member -NotePropertyName "OctopusMasterKey" -NotePropertyValue (New-Object System.Management.Automation.PSCredential ("ignored", $encryptedMasterKey))

    return $config
}

function Get-TentacleConfiguration($instanceName)
{
  $rawConfig = & $tentacleExePath show-configuration --instance $instanceName
  $config = $rawConfig | ConvertFrom-Json
  return $config
}

function Test-ValidJson
{
    param($string)
    try {
        $string | ConvertFrom-Json | Out-Null
        return $true
    }
    catch {
        return $false
    }
}

# Operates on a very specific json output failure. See issue #179  (https://github.com/OctopusDeploy/OctopusDSC/issues/179)
function Get-CleanedJson
{
    [CmdletBinding()]
    param($jsonstring)
    $jsonstart = $jsonstring.IndexOf("{")
    Write-Verbose "Found start of JSON at character $jsonstart"
    $extractedjson = $jsonstring.Substring($jsonstart, $jsonstring.length - $jsonstart)
    $dumpedstring = $jsonstring.substring(0, $jsonstart)
    Write-Warning ("stripped extra content from JSON configuration string`r`n`r`n" + (Get-MaskedOutput $dumpedstring))

    return $extractedjson
}
