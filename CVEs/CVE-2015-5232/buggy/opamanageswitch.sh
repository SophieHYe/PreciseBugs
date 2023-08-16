#!/bin/bash
# BEGIN_ICS_COPYRIGHT8 ****************************************
# 
# Copyright (c) 2015, Intel Corporation
# 
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are met:
# 
#     * Redistributions of source code must retain the above copyright notice,
#       this list of conditions and the following disclaimer.
#     * Redistributions in binary form must reproduce the above copyright
#       notice, this list of conditions and the following disclaimer in the
#       documentation and/or other materials provided with the distribution.
#     * Neither the name of Intel Corporation nor the names of its contributors
#       may be used to endorse or promote products derived from this software
#       without specific prior written permission.
# 
# THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
# AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
# IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
# DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE
# FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
# DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
# SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
# CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
# OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
# OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
# 
# END_ICS_COPYRIGHT8   ****************************************

# [ICS VERSION STRING: unknown]

cmdName=opamanageswitch

captureCapable=n

resultsFile=$PWD/opamanageswitch.res

# Functions

# checkCapture

checkCapture()
{
	if [ -x $bindir/opaswcapture ]
	then
		captureCapable=y
	fi
}

# Usage

Usage()
{
    if [ $captureCapable = y ]
	then
	echo "Usage: $cmdName -t target-guid [-H] [-v] [-h hfi] [-p port] [ -x] [ -f fileName] [-r] [-C configOption] [-i integer-value] [-s string-value] [-c captureFile] operation"
#	echo "Usage: $cmdName -t target-guid [-H] [-v] [-h hfi] [-p port] [ -x] [-S] [ -f fileName] [-r] [-C configOption] [-i integer-value] [-s string-value] [ -F ] [-c captureFile] operation"
	else
	echo "Usage: $cmdName -t target-guid [-H] [-v] [-h hfi] [-p port] [ -x] [ -f fileName] [-r] [-C configOption] [-i integer-value] [-s string-value] operation"
#	echo "Usage: $cmdName -t target-guid [-H] [-v] [-h hfi] [-p port] [ -x] [-S] [ -f fileName] [-r] [-C configOption] [-i integer-value] [-s string-value] [ -F ] operation"
	fi
	echo "         -H - help (this message)"
	echo "         -v - verbose - additional output"
	echo "         -t - guid of target switch in hex format, e.g. 0x00066a00e3001234"
	echo "         -h - hfi, numbered 1..n, 0= -p port will be a system wide port num"
	echo "         -p - port, numbered 1..n, 0=1st active (default is 1st active)"
	echo "         -x - clobber previous results file"
	echo "         -f - fileName of the emfw file to be used in fwUpdate operation - must be a valid emfw file with .emfw suffix"
	echo "         -r - reset switch after fwUpdate (only valid with fwUpdate operation)"
	echo "         -C - configuration option for setConfigValue operation"
	echo "              mtucap - mtu capability - use -i for integer value (4-2048, 5-4096)"
	echo "              vlcap - vl capability - use -i for integer value (1-1VL, 2-2VLs, 3-4VLs, 4-8VLs, 5-15VLs)"
	echo "              linkwidth - link width supported - use -i for integer value (1-1X, 2-4X, 3-1X/4X, 4-8X, 5-1X/8X, 6-4X/8X, 7-1X/4X/8X)"
	echo "              vlcreditdist - VL credit distribution - use -i for integer value (0, 1, 2, 3, or 4)"
	echo "              linkspeed - link speed supported - use -i for integer value (1-SDR, 2-DDR, 3-SDR/DDR, 4-QDR, 7-SDR/DDR/QDR)"
	echo "         -i - integer value"
	echo "         -s - string value"
    if [ $captureCapable = y ]
	then
	echo "         -c - filename of capture output file"
	fi
#	echo "         -F - perform fwUpdate operation on secondary (failsafe) EEPROM"
	echo "         operation:"
	echo "            fwUpdate - perform firmware update using fileName parameter, must be an emfw file"
	echo "            fwVerify - perform firmware validation - validate firmware in primary/secondary EEPROMs, report which was booted"
	echo "            ping - test for switch presence"
	echo "            reboot - reboot switch"
	echo "            setConfigValue - update configuration value - use -C for config option and -i for integer value"
	echo "            setOPANodeDesc - set the OPA node description - use -s for string value of node desc"
	echo "            setPassword - set the vendor key (prompts for password to be used for subsequent switch access)"
	echo "            showConfig - report user-configurable settings"
	echo "            showFwVersion - report firmware version running on switch"
	echo "            showPowerCooling - report status of power supplies and fans"
    if [ $captureCapable = y ]
	then
	echo "            capture - perform capture of switch"
	fi
	echo "            showVPD - report VPD information of switch"
#	echo "            compareEEPROM - compare EEPROMs"
	echo ""
	echo "         Examples:"
	echo "            opamanageswitch -t 0x00066a00e3001234 -f Intel_12000_V1_firmware.7.2.0.0.32.emfw fwUpdate"
	echo "            opamanageswitch -t 0x00066a00e3001234 reboot"
	echo "            opamanageswitch -t 0x00066a00e3001234 showFwVersion"
	echo "            opamanageswitch -t 0x00066a00e3001234 -s i12k1234 setOPANodeDesc"
	echo "            opamanageswitch -t 0x00066a00e3001234 -C mtucap -i 4 setConfigValue"
	echo "            opamanageswitch -H"
	echo ""
	echo "         Results are recorded in opamanageswitch.res file in the current directory - use -x option to clobber and create anew"

	exit 1
}

# UsageWarn

UsageWarn()
{
	echo "Enter 'opamanageswitch -H' for help"
	exit 1
}

# Log results

logResults()
{
	cat $1 >> $resultsFile
}

# Check exit

check_exit()
{
	status=$1
	file=$2
	if [ $status -ne 0 ]
	then
		if [ "$file" != "" ]
		then
			grep -i error $file
			grep mismatch $file
			logResults $file
			rm -f $file
		fi
		exit 1
	fi
}

# Execute command

execCommand()
{
	echo Executing $cmd $args >> $resultsFile
	if [ "$verboseArg" != "-v" -a "$teeOut" != "yes" ]
	then
		$cmd $args > $tmpFile 2>&1
	else
		$cmd $args | grep -v completed | tee $tmpFile 2>&1
	fi
}

# Get board id

getBoardID()
{
	cmd=$bindir/opaswquery
	args="-t $target $hfiPortArgs $verboseArg $quietArg -Q 12"
	tmpFile=.query
	execCommand
	check_exit $? .query
	boardID=`grep BoardID .query | cut -d ' ' -f 2`
	logResults .query
	rm -f .query
	return 0
}


fileNameArg=
hfiArg=
portArg=
failSafeArg=
resetArg=
configOptArg=
integerArg=
stringArg=
captureOutArg=
clobberArg=
helpArg=
verboseArg=
quietArg=
teeOut=

options='C:Ff:Hh:i:p:rs:t:c:vx'
bindir=/opt/opa/tools

checkCapture

while getopts "$options" param
do
	case $param in
	c)
		captureOutArg="$OPTARG";;
	C)
		configOptArg="$OPTARG";;
	F)
		failSafeArg=-F;;
	f)
		fileNameArg="$OPTARG";;
	H)
		helpArg="help";;
	h)
		hfiArg="-h $OPTARG";;
	i)
		integerArg="$OPTARG";;
	p)
		portArg="-o $OPTARG";;
	r)
		resetArg="-S";;
	s)
		stringArg="$OPTARG";;
	t)
		target="$OPTARG";;
	v)
		verboseArg=-v;;
	x)
		clobberArg=1;;
	?)
		Usage;;
	esac
done
shift $((OPTIND -1))

if [ "$helpArg" == "help" ]
then
	Usage
fi

numParams=$#
if [ $numParams -gt 1 ]
then
	echo "Error: only one operation allowed"
	UsageWarn
fi

operation=$1

if [ "$operation" = "" ]
then
	echo "Must provide an operation"
	UsageWarn
fi

if [ "$target" = "" ]
then
	echo "Must provide a target guid"
	UsageWarn
fi

if [ $captureCapable != y -a "$operation" == "capture" ]
then
	echo "Capture operation is not available"
	UsageWarn
fi

if [ "$fileNameArg" != "" -a "$operation" != "fwUpdate" ]
then
	echo "Filename parameter is only valid for fwUpdate operation"
	UsageWarn
fi

if [ "$configOptArg" != "" -a "$operation" != "setConfigValue" ]
then
	echo "configOption parameter is only valid for configure operation"
	UsageWarn
fi

if [ "$captureOutArg" != "" -a "$operation" != "capture" ]
then
	if [ $captureCapable != y ]
	then
		echo "Capture operation is not available"
	else
		echo "Capture outfile parameter is only valid for capture operation"
	fi
	UsageWarn
fi

if [ "$clobberArg" = "1" ]
then
	> $resultsFile
else
	echo "" >> $resultsFile
fi
echo "********************" >> $resultsFile
date >> $resultsFile
echo Beginning operation: $operation >> $resultsFile
echo "********************" >> $resultsFile

if [ "$verboseArg" != "-v" ]
then
	quietArg=-q
else
	quietArg=
fi

case $operation in
capture)
	if [ "$captureOutArg" = "" ]
	then
		echo "Must provide a capture outfile parameter for capture operation"
		UsageWarn
	fi
	quietArg=
	echo "Performing switch capture..."
	cmd=$bindir/opaswcapture
	args="$verboseArg $quietArg $hfiArg $portArg -t $target $captureOutArg"
	tmpFile=.cap
	teeOut=yes
	execCommand
	check_exit $? .cap
	logResults .cap
	rm -f .cap
	;;
fwUpdate)
	# must have a filename parameter
	if [ "$fileNameArg" = "" ]
	then
		echo "Must provide a fileName parameter for fwUpdate operation"
		UsageWarn
	fi

	# verify filename is for V1 silicon
	echo $fileNameArg | grep V1 > /dev/null 2>&1
	if [ $? -ne 0 ]
	then
		echo "$fileNameArg does not appear to be a V1 silicon emfw file"
		UsageWarn
	fi

	# verify filename is emfw
	sfx=`echo $fileNameArg |  awk -F . '{print $NF}'`
	if [ "$sfx" != "emfw" ]
	then
		# maybe prompt then to continue??
		echo "Filename parameter must be an emfw file"
		UsageWarn
	fi

	# verify file format
	tar tzf $fileNameArg > /dev/null 2>&1
	if [ $? -ne 0 ]
	then
		echo "File $fileNameArg has invalid format"
		UsageWarn
	fi

	# get the boardID of the switch
	getBoardID

	# create a directory and untar the emfw
	c=`echo $fileNameArg | cut -b 1`
	if [ "$c" != "/" ]
	then
		thisdir=`pwd`
		fn=$thisdir/$fileNameArg
	else
		fn=$fileNameArg
	fi
	tmpdir=/tmp/12200FW$$
	mkdir $tmpdir
	cd $tmpdir
	tar xzf $fn > /dev/null 2>&1

	# set names
	if [ -f emfwMapFile ]
	then
		binName=`grep $boardID emfwMapFile | cut -d ' ' -f 2`
		inibinName=`grep $boardID emfwMapFile | cut -d ' ' -f 3`
	else
		# no emfwMapFile - use standard f/w and opasw inibin
		binName=s20fwV1_fw.bin
		# TODO andlowe make sure this is correct
		inibinName=iniOpasw.inibin
	fi

	# do fwconfigure and fwupdate
	echo Updating config block...
	cmd=$bindir/opaswfwconfigure
	args="$verboseArg $quietArg $hfiArg $portArg -t $target -f $inibinName $failSafeArg"
	tmpFile=.fw
	execCommand
	check_exit $? .fw
	logResults .fw
	rm -f .fw
	cmd=$bindir/opaswconfigure
	args="$verboseArg $quietArg $hfiArg $portArg -t $target -C 2 -S"
	tmpFile=.pswd
	execCommand
	check_exit $?
	logResults .pswd
	rm -f .pswd
	echo Updating firmware...
	cmd=$bindir/opaswfwupdate
	args="$verboseArg $quietArg $hfiArg $portArg -t $target -f $binName $resetArg $failSafeArg"
	tmpFile=.fw
	execCommand
	check_exit $? .fw
	logResults .fw
	if [ "$verboseArg" != "-v" ]
	then
		grep Verification .fw
		grep Resetting .fw
	fi
	rm -f .fw
	cd $thisdir
	rm -rf $tmpdir
	;;
fwVerify)
	echo -n "Validating firmware in primary EEPROM...     "
	cmd=$bindir/opaswfwverify
	args="$verboseArg $quietArg $hfiArg $portArg -t $target"
	tmpFile=.fwv
	execCommand
	check_exit $? .fwv
	validity=`grep found .fwv | cut -d ' ' -f 2`
	logResults .fwv
	echo "Primary image is $validity"
	echo " "
	echo -n "Validating firmware in secondary EEPROM...   "
	cmd=$bindir/opaswfwverify
	args="$verboseArg $quietArg $hfiArg $portArg -t $target -F"
	tmpFile=.fwv
	execCommand
	check_exit $? .fwv
	validity=`grep found .fwv | cut -d ' ' -f 2`
	logResults .fwv
	echo "Secondary image is $validity"
	echo " "
	echo -n "Checking boot EEPROM ...                     "
	cmd=$bindir/opaswquery
	args="$verboseArg $quietArg $hfiArg $portArg -t $target -Q 2"
	tmpFile=.fwv
	execCommand
	check_exit $? .fwv
	booteeprom=`grep booted .fwv | cut -d ' ' -f 5`
	echo "Switch has booted from $booteeprom EEPROM"
	logResults .fwv
	rm -f .fwv
	;;
ping)
	cmd=$bindir/opaswping
	args="$verboseArg $quietArg $hfiArg $portArg -t $target"
	tmpFile=.png
	execCommand
	grep present .png > /dev/null 2>&1
	if [ $? -eq 0 ]
	then
		grep present .png | grep not > /dev/null 2>&1
		if [ $? -eq 0 ]
		then
			echo "Unit is not present"
		else
			echo "Unit is present"
		fi
	else
		echo "Unit is not present"
	fi
	logResults .png
	rm .png
	;;
reboot)
	echo "Resetting switch..."
	cmd=$bindir/opaswreset
	args="$verboseArg $quietArg $hfiArg $portArg -t $target"
	tmpFile=.res
	execCommand
	check_exit $? .res
	logResults .res
	rm -f .res
	;;
setConfigValue)
	intOpt=
	cfgOpt=
	if [ "$configOptArg" = "" ]
	then
		echo "Must provide a configOption parameter for setConfigValue operation"
		UsageWarn
	fi
	if [ "$integerArg" = "" ]
	then
		echo "Must provide an integer parameter for setConfigValue operation"
		UsageWarn
	fi
	intOpt="-i $integerArg"
	cmd=$bindir/opaswconfigure
	tmpFile=.cfg
	case $configOptArg in
	mtucap)
		cfgOpt="-C 3"
		args="$verboseArg $quietArg $hfiArg $portArg -t $target $cfgOpt $intOpt -S"
		execCommand
		grep Error .cfg > /dev/null 2>&1
		if [ $? -eq 0 ]
		then
			grep Error .cfg | cut -d ':' -f 2- | cut -d ' ' -f 2-
			logResults .cfg
			rm -f .cfg
			exit 1
		fi
		logResults .cfg
		rm -f .cfg
		;;
	vlcap)
		cfgOpt="-C 4"
		args="$verboseArg $quietArg $hfiArg $portArg -t $target $cfgOpt $intOpt -S"
		execCommand
		grep Error .cfg > /dev/null 2>&1
		if [ $? -eq 0 ]
		then
			grep Error .cfg | cut -d ':' -f 2- | cut -d ' ' -f 2-
			logResults .cfg
			rm -f .cfg
			exit 1
		fi
		logResults .cfg
		rm -f .cfg
		;;
	linkwidth)
		cfgOpt="-C 5"
		args="$verboseArg $quietArg $hfiArg $portArg -t $target $cfgOpt $intOpt -S"
		execCommand
		grep Error .cfg > /dev/null 2>&1
		if [ $? -eq 0 ]
		then
			grep Error .cfg | cut -d ':' -f 2- | cut -d ' ' -f 2-
			logResults .cfg
			rm -f .cfg
			exit 1
		fi
		logResults .cfg
		rm -f .cfg
		;;
	vlcreditdist)
		cfgOpt="-C 6"
		args="$verboseArg $quietArg $hfiArg $portArg -t $target $cfgOpt $intOpt -S"
		execCommand
		grep Error .cfg > /dev/null 2>&1
		if [ $? -eq 0 ]
		then
			grep Error .cfg | cut -d ':' -f 2- | cut -d ' ' -f 2-
			logResults .cfg
			rm -f .cfg
			exit 1
		fi
		logResults .cfg
		rm -f .cfg
		;;
	linkspeed)
		cfgOpt="-C 7"
		args="$verboseArg $quietArg $hfiArg $portArg -t $target $cfgOpt $intOpt -S"
		execCommand
		grep Error .cfg > /dev/null 2>&1
		if [ $? -eq 0 ]
		then
			grep Error .cfg | cut -d ':' -f 2- | cut -d ' ' -f 2-
			logResults .cfg
			rm -f .cfg
			exit 1
		fi
		logResults .cfg
		rm -f .cfg
		;;
	*)
		echo "Invalid configOption parameter $configOptArg"
		UsageWarn
		;;
	esac
	intVal=`echo $intOpt | cut -d ' ' -f 2`
	echo Successfully set $configOptArg to $intVal
	;;
setIBNodeDesc)
	strOpt=
	if [ "$stringArg" = "" ]
	then
		echo "Must include a string value for setOPANodeDesc operation"
		UsageWarn
	fi
	if [ "$integerArg" != "" ]
	then
		echo "Integer value is not valid with setOPANodeDesc operation"
		UsageWarn
	fi
	strOpt="-s $stringArg"
	cmd=$bindir/opaswconfigure
	args="$verboseArg $quietArg $hfiArg $portArg -t $target -C 1 $strOpt -S"
	tmpFile=.cfg
	execCommand
	check_exit $? .cfg
	grep Error .cfg > /dev/null 2>&1
	if [ $? -eq 0 ]
	then
		grep Error .cfg | cut -d ':' -f 2- | cut -d ' ' -f 2-
		logResults .cfg
		rm -f .cfg
		exit 1
	fi
	logResults .cfg
	rm -f .cfg
	;;
setPassword)
	if [ "$stringArg" != "" -o "$integerArg" != "" ]
	then
		echo "String or integer parameter not valid with setPassword operation"
		UsageWarn
	fi
	cmd=$bindir/opaswconfigure
	args="$verboseArg $quietArg $hfiArg $portArg -t $target -C 2 -S"
	tmpFile=.pswd
	teeOut=yes
	execCommand
	check_exit $?
	logResults .pswd
	rm -f .pswd
	;;
showConfig)
	echo "Reporting user-configurable settings..."
	cmd=$bindir/opaswquery
	args="$verboseArg $quietArg $hfiArg $portArg -t $target -Q 11"
	tmpFile=.query
	teeOut=yes
	execCommand
	check_exit $?
	logResults .query
	rm -f .query
	;;
showFwVersion)
	cmd=$bindir/opaswquery
	args="$verboseArg $quietArg $hfiArg $portArg -t $target -Q 3"
	tmpFile=.fwv
	execCommand
	check_exit $? .fwv
	fwversion=`grep Version .fwv | cut -d ' ' -f 4`
	echo "Firmware version is $fwversion"
	logResults .fwv
	rm -f .fwv
	;;
showPowerCooling)
	echo "Reporting power and cooling status..."

	# middle digit of part number tells how many PS
	cmd=$bindir/opaswquery
	args="$verboseArg $quietArg $hfiArg $portArg -t $target -Q 4"
	tmpFile=.pwc
	execCommand
	check_exit $? .pwc
	prtno=`grep VPD .pwc | cut -d ',' -f 2`
	psIndicator=`echo $prtno | cut -d '-' -f 2 | awk '{ print substr( $0, 2, 1 ) }'`
	case $psIndicator in
	0)
		numPS=1
		;;
	1)
		numPS=2
		;;
	*)
		numPS=1
		;;
	esac
	logResults .pwc
	getBoardID

	cmd=$bindir/opaswquery
	tmpFile=.pwc
	if [ "$boardID" != "0x13" ]
	then
		args="$verboseArg $quietArg $hfiArg $portArg -t $target -Q 7"
		execCommand
		check_exit $? .pwc
		fan1_0stat=`grep FAN .pwc |cut -d ':' -f 2 | cut -d ' ' -f 1`
		fan1_1stat=`grep FAN .pwc |cut -d ':' -f 3`
		logResults .pwc
	else
		fan1_0stat="N/A   "
		fan1_1stat="N/A"
	fi
	args="$bindir/opaswquery $verboseArg $quietArg $hfiArg $portArg -t $target -Q 7"
	execCommand
	check_exit $? .pwc
	logResults .pwc
	fan2_0stat=`grep FAN .pwc |cut -d ':' -f 2 | cut -d ' ' -f 1`
	fan2_1stat=`grep FAN .pwc |cut -d ':' -f 3`
	if [ $numPS -eq 2 ]
	then
		args="$bindir/opaswquery $verboseArg $quietArg $hfiArg $portArg -t $target -Q 8 -i 1"
		execCommand
		check_exit $? .pwc
		ps1stat=`grep PS .pwc | cut -d ' ' -f 3`
		logResults .pwc
	else
		ps1stat="N/A"
	fi
	args="$bindir/opaswquery $verboseArg $quietArg $hfiArg $portArg -t $target -Q 8 -i 2"
	execCommand
	check_exit $? .pwc
	ps2stat=`grep PS .pwc | cut -d ' ' -f 3`
	echo "Fan status: Fan 1/0: $fan1_0stat    Fan 1/1: $fan1_1stat"
	echo "Fan status: Fan 2/0: $fan2_0stat    Fan 2/1: $fan2_1stat"
	echo "PS status : PS1    : $ps1stat       PS2    : $ps2stat"
	logResults .pwc
	rm -f .pwc
	;;
showVPD)
	echo "Reporting switch VPD info..."
	cmd=$bindir/opaswquery
	args="$bindir/opaswquery $verboseArg $quietArg $hfiArg $portArg -t $target -Q 4"
	tmpFile=.vpd
	execCommand
	check_exit $? .vpd
	serno=`grep VPD .vpd | cut -d ' ' -f 2 | cut -d ',' -f 1`
	prtno=`grep VPD .vpd | cut -d ',' -f 2`
	model=`grep VPD .vpd | cut -d ',' -f 3`
	hwver=`grep VPD .vpd | cut -d ',' -f 4`
	mfgnm=`grep VPD .vpd | cut -d ',' -f 5`
	prdnm=`grep VPD .vpd | cut -d ',' -f 6`
	mfgid=`grep VPD .vpd | cut -d ',' -f 7`
	mfgdt=`grep VPD .vpd | cut -d ',' -f 8`
	mfgtm=`grep VPD .vpd | cut -d ',' -f 9`
	echo "   Serial Number :    $serno"
	echo "   Part Number   :    $prtno"
	echo "   Model         :    $model"
	echo "   H/W Version   :    $hwver"
	echo "   Mfg Name      :    $mfgnm"
	echo "   Product Name  :    $prdnm"
	echo "   Mfg ID        :    $mfgid"
	echo "   Mfg Date      :    $mfgdt"
	echo "   Mfg Time      :    $mfgtm"
	logResults .vpd
	rm -f .vpd
	;;
compareEEPROM)
	echo "Comparing switch EEPROMs..."
	cmd=$bindir/opaswfwverify
	args="$verboseArg $quietArg $hfiArg $portArg -t $target -C"
	tmpFile=.fwv
	execCommand
	check_exit $? .fwv
	match=`grep CRC .fwv | cut -d ' ' -f 3`
	if [ "$match" = "match" ]
	then
		echo "Switch EEPROMs match"
	else
		echo "Switch EEPROMs do not match"
	fi
	logResults .fwv
	rm -f .fwv
	;;
*)
	echo "$operation is not a valid operation"
	UsageWarn
esac

exit 0
