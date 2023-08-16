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

TEMP=/tmp/smgen$$
trap "rm -f $TEMP; exit 1" SIGHUP SIGTERM SIGINT

Usage()
{
	echo "Usage: config_generate [-e] dest_file" >&2
	echo "      -e  - generate file for embedded FM, default is to generate for host FM" >&2
	exit 2
}

if [ -f /opt/opa/fm_tools/config_convert -a -f /opt/opa/fm_tools/opafm_src.xml ]
then
	tooldir=/opt/opa/fm_tools
elif [ ! -f /etc/sysconfig/opa/opafm.info || ! -f /etc/sysconfig/opa/qlogic_fm.info ]
then
	echo "config_generate: IFS FM not installed" >&2
	exit 1
else
	if [ -f /etc/sysconfig/opa/qlogic_fm.info ]
        then
                cp -f /etc/sysconfig/opa/qlogic_fm.info /etc/sysconfig/opa/opafm.info
        fi

	. /etc/sysconfig/opa/qlogic_fm.info # get IFS_FM_BASE
	tooldir=$IFS_FM_BASE/etc
fi

esm=n
while getopts e param
do
	case $param in
	e)	esm=y;;
	?)	Usage;;
	esac
done
shift $((OPTIND -1))

if [ $# -lt 1 ]
then
	Usage
fi
dest_file=$1

print_separator()
{
	echo "-------------------------------------------------------------------------------"
}

# global $ans set to 1 for yes or 0 for no
get_yes_no()
{
	local prompt default input
	prompt="$1"
	default="$2"
	while true
	do
		input=
		echo -n "$prompt [$default]:"
		read input
		if [ "x$input" = x ]
		then
			input="$default"
		fi
		case "$input" in
		[Yy]*)	ans=1; break;;
		[Nn]*)	ans=0; break;;
		esac
	done
}

# global $ans set to value input
get_number()
{
	local prompt default input min max
	prompt="$1"
	default="$2"
	min="$3"
	max="$4"
	while true
	do
		input=
		echo -n "$prompt [$default]:"
		read input
		if [ "x$input" = x ]
		then
			input="$default"
		fi
		if [ "$input" -lt $min -o "$input" -gt $max ] 2>/dev/null
		then
			echo "Input must be number $min to $max.  Invalid Input: $input"
		else
			ans=$input
			break
		fi
	done
}

# global $ans set to value input
get_string()
{
	local prompt default input
	prompt="$1"
	default="$2"
	while true
	do
		input=
		echo -n "$prompt [$default]:"
		read input
		if [ "x$input" = x ]
		then
			input="$default"
		fi
		ans=$input
		break
	done
}

# global $ans set to value input
#get_choice()
#{
#	local prompt default input
#	prompt="$1"
#	default="$2"
#	PS3="$prompt ($default recommended): "
#	select input in $choices
#	do
#		case "$input" in
#		gcc|pathscale|pgi|intel) ans=$input; break;;
#		esac
#	done
#}

# global $ans set to value input
mtu[1]="256"
mtu[2]="512"
mtu[3]="1024"
mtu[4]="2048"
mtu[5]="4096"
get_mtu()
{
	local prompt default input
	prompt="$1"
	default="$2"

	# set default in case cntrl-D entered
	case "$default" in
	2048) ans=4;;	# convert to IBTA enum
	4096) ans=5;;	# convert to IBTA enum
	esac

	PS3="$prompt ($default recommended): "
	select input in 2048 4096
	do
		case "$input" in
		2048) ans=4; break;;	# convert to IBTA enum
		4096) ans=5; break;;	# convert to IBTA enum
		esac
	done
}

# global $ans set to value input
# rate 1 is obsolete
rate[2]="2.5g"
rate[3]="10g"
rate[4]="30g"
rate[5]="5g"
rate[6]="20g"
rate[7]="40g"
rate[8]="60g"
rate[9]="80g"
rate[10]="120g"

get_rate()
{
	local prompt default input
	prompt="$1"
	default="$2"

	# set default in case cntrl-D entered
	case "$default" in
	'10g(4xSDR)') ans=3;;	# convert to IBTA enum
	'20g(4xDDR)') ans=6;;	# convert to IBTA enum
	'30g(12xSDR)') ans=4;;	# convert to IBTA enum
	'60g(12xDDR)') ans=8;;	# convert to IBTA enum
	esac

	PS3="$prompt ($default recommended): "
	#select input in '10g(4xSDR)' '20g(4xDDR)' '30g(12xSDR)' '60g(12xDDR)' '40g(4xQDR)'
	select input in '10g(4xSDR)' '20g(4xDDR)' '40g(4xQDR)'
	do
		case "$input" in
		'10g(4xSDR)') ans=3; break;;	# convert to IBTA enum
		'20g(4xDDR)') ans=6; break;;	# convert to IBTA enum
		'30g(12xSDR)') ans=4; break;;	# convert to IBTA enum
		'40g(4xQDR)') ans=7; break;;	# convert to IBTA enum
		'60g(12xDDR)') ans=8; break;;	# convert to IBTA enum
		esac
	done
}

enable_instance()
{
	local instance
	instance=$1

	fm_enabled[$instance]=1
	echo "SM_${instance}_start=yes" >> $TEMP
	echo "PM_${instance}_start=yes" >> $TEMP
	echo "BM_${instance}_start=yes" >> $TEMP
	echo "FE_${instance}_start=yes" >> $TEMP
}

set_instance_priority()
{
	local instance priority
	instance=$1
	priority=$2

	echo "SM_${instance}_priority=$priority" >> $TEMP
	echo "PM_${instance}_priority=$priority" >> $TEMP
	echo "BM_${instance}_priority=$priority" >> $TEMP
	#echo "FE_${instance}_priority=$priority" >> $TEMP
}

if [ -e $dest_file ]
then
	if [ ! -f $dest_file ]
	then
		echo "config_generate: $dest_file exists but is not a file" >&2
		exit 1
	fi
	get_yes_no "Overwrite existing $dest_file" "n"
	if [ $ans -eq 0 ]
	then
		exit 0
	fi
fi

# FM instance HCAs
fm_hca[0]=1
fm_hca[1]=1
fm_hca[2]=2
fm_hca[3]=2
# FM instance Ports
fm_port[0]=1
fm_port[1]=2
fm_port[2]=1
fm_port[3]=2
# which instances are enabled
fm_enabled[0]=0
fm_enabled[1]=0
fm_enabled[2]=0
fm_enabled[3]=0
# default names for instances
fm_name[0]=fm0
fm_name[1]=fm1
fm_name[2]=fm2
fm_name[3]=fm3
# FM device descriptions
fm_device[0]="HCA ${fm_hca[0]} Port ${fm_port[0]}"
fm_device[1]="HCA ${fm_hca[1]} Port ${fm_port[1]}"
fm_device[2]="HCA ${fm_hca[2]} Port ${fm_port[2]}"
fm_device[3]="HCA ${fm_hca[3]} Port ${fm_port[3]}"

fm_allinstances="all FM instances"
	
# start to build $TEMP file with answers
rm -f $TEMP
print_separator
echo "FM resources and buffering are scaled to match the anticipated maximum size"
echo "of the fabric.  The size is specified in terms of the number of CAs in"
echo "a single fabric."
if [ "$esm" = y ]
then
	echo "For Embedded Fabric Manager, its recommended to use a value of 1000 or less."
	get_number "Anticipated maximum fabric size" 1000 0 1000
else
	echo "For Host Fabric Manager, its recommended to use a value of 2560 or larger."
	get_number "Anticipated maximum fabric size" 2560 0 10000
fi
echo "  Setting SubnetSize to $ans"
echo "SUBNET_SIZE=$ans" >> $TEMP

print_separator
echo "LMC is used to control the number of LIDs per CA."
echo "Multiple LIDs can be used to permit multiple routes between endpoints."
echo "This permits selected applications (such as MPIs using QLogic PSM) to"
echo "optimize performance and/or resiliency by using dispersive routing."
get_number "LMC value to use (there will be 2^LMC LIDs per CA)" 0 0 7
echo "  Setting Lmc to $ans"
echo "  There will be $((2**$ans)) LIDs per CA"
echo "SM_0_lmc=$ans" >> $TEMP	# sets for all instances

print_separator
echo "Adaptive routing permits QLogic QDR switches to dynamically adjust routing"
echo "based on traffic patterns and hence reduce congestion and improve overall"
echo "cluster performance and efficiency."
get_yes_no "Should Adaptive Routing be enabled" "n"
echo "  Setting AdaptiveRouting.Enable to $ans"
echo "SM_0_ar_enable=$ans" >> $TEMP # sets for all instances
if [ "$ans" -eq 1 ]
then
	echo "Adaptive routing will always route around failed or down links."
   	echo "In addition adapting routing around congestion can be enabled."
	get_yes_no "Should Adaptive Routing around congestion be enabled" "n"
	echo "  Setting AdaptiveRouting.LostRouteOnly to $ans"
	echo "SM_0_ar_lost_route_only=$ans" >> $TEMP # sets for all instances

	echo "Adaptive routing will always permit adapting among links between neighbor"
   	echo "switch ASICs. In addition for pure CLOS/Fat Tree topologies, adaptions can be"
   	echo "enabled to occur at all levels in the tree and across all ISLs."
	get_yes_no "Should full CLOS/Fat Tree Adaptive Routing be enabled" "n"
	echo "  Setting AdaptiveRouting.Tier1FatTree to $ans"
	echo "SM_0_ar_tier1_fat_tree=$ans" >> $TEMP # sets for all instances
fi

if [ "$esm" != y ]
then
	print_separator
	echo "The FM logging has two possible modes."
	echo "In Normal mode, logging includes user actionable events and other FM"
	echo "messages such as sweep progress, internal warnings and errors, etc."
	echo "In Quiet mode, only user actionable events are logged."
	get_yes_no "Should Quiet Mode be used" "n"
	echo "  Setting LogMode to $ans"	# 0=Normal, 1=Quiet
else
	ans=0
fi

print_separator
echo "When nodes appear or disappear from the fabric, a message is logged."
echo "A Threshold can be configured to limit the number of such messages per sweep."
echo "This Threshold can help to avoid excessive messages when fabric changes occur."
get_number "Node Appearance Log Message Threshold" 100
echo "  Setting NodeAppearanceMsgThreshold to $ans"
echo "SM_0_node_appearance_msg_thresh=$ans" >> $TEMP # sets for all instances

if [ "$esm" = y ]
then
	enable_instance 0
	instances="0"
	num_instances=1
	fm_device[0]="Switch Port 0"
	fm_allinstances="this FM"
else
	print_separator
	echo "By default a FM node will run a single FM on the 1st Port of the 1st HCA."
	echo "However, in larger configurations, a single FM node may be used to"
	echo "manage multiple fabrics.  Each such fabric would be contected to a different"
	echo "HCA port.  Each HCA port is associated with a different FM instance."
	num_instances=0
	default=y
	for instance in 0 1 2 3
	do
		get_yes_no "Should FM instance $instance (${fm_device[$instance]}) be enabled" $default
		default=n	# only default to instance 0 enabled
		if [ "$ans" -eq 1 ]
		then
			echo "  Enabling Start of FM instance $instance SM, PM, BM and FE"
			enable_instance $instance
			instances="$instances $instance"
			num_instances=$(($num_instances + 1))
		fi
	done
fi

print_separator
if [ "$esm" = y ]
then
	echo "Each FM can have a name."
else
	echo "Each FM instance can have a unique name.  The name will appear as part"
	echo "of all syslog entries generated by the given FM instance."
fi
for instance in $instances
do
	get_string "Name for FM instance $instance (${fm_device[$instance]})" "fm$instance"
	echo "  Setting Name of FM instance $instance to $ans"
	echo "SM_${instance}_name=$ans" >> $TEMP
	fm_name[$instance]="$ans"
done

print_separator
echo "The FM configures the rate and MTU used for IPoIB multicast."
echo "The rate selected must be no greater than the rate of the slowest link"
echo "in the fabric(s)."
echo "The MTU selected must be no greater than the MTU of the smallest MTU link"
echo "in the fabric(s)."
echo "When selecting the rate and MTU, CAs which won't run IPoIB can be ignored."
echo "However all Switches must be operating with at least the rate and MTU selected."
echo
if [ $num_instances -eq 1 ]
then
	same_rate=1
	same_mtu=1
else
	get_yes_no "Will the same IPoIB Rate be used for $fm_allinstances" "y"
	same_rate=$ans
	get_yes_no "Will the same IPoIB MTU be used for $fm_allinstances" "y"
	same_mtu=$ans
fi
if [ $same_rate -eq 1 ]
then
	get_rate "IPoIB rate for $fm_allinstances" '20g(4xDDR)'
	rate=$ans
fi
if [ $same_mtu -eq 1 ]
then
	get_mtu "IPoIB MTU for $fm_allinstances" 2048
	mtu=$ans
fi
if [ $same_mtu -eq 1 -a $same_rate -eq 1 ]
then
	echo "  Setting MulticastGroup.MTU to ${mtu[$mtu]}"
	echo "  Setting MulticastGroup.Rate to ${rate[$rate]}"
	echo "SM_0_def_mc_mtu=$mtu" >> $TEMP # sets for all instances
	echo "SM_0_def_mc_rate=$rate" >> $TEMP # sets for all instances
else
	for instance in $instances
	do
		if [ $same_rate -eq 0 ]
		then
			get_rate "IPoIB rate for FM instance $instance (${fm_name[$instance]}) (${fm_device[$instance]})" '20g(4xDDR)'
			rate=$ans
		fi
		if [ $same_mtu -eq 0 ]
		then
			get_mtu "IPoIB MTU for FM instance $instance (${fm_name[$instance]}) (${fm_device[$instance]})" 2048
			mtu=$ans
		fi
		echo "  Setting MulticastGroup.Rate for FM instance $instance to ${rate[$rate]}"
		echo "  Setting MulticastGroup.MTU for FM instance $instance to ${mtu[$mtu]}"
		if [ $instance -eq 0 ]
		then
			echo "SM_0_def_mc_mtu=$mtu" >> $TEMP
			echo "SM_0_def_mc_rate=$rate" >> $TEMP
		else
			echo "SM_${instance}_def_mc_create=0x1" >> $TEMP
			#echo "SM_${instance}_def_mc_pkey=0xffff" >> $TEMP
			echo "SM_${instance}_def_mc_mtu=$mtu" >> $TEMP
			echo "SM_${instance}_def_mc_rate=$rate" >> $TEMP
			#echo "SM_${instance}_def_mc_sl=0x0" >> $TEMP
			echo "SM_${instance}_def_mc_qkey=0x0" >> $TEMP
			echo "SM_${instance}_def_mc_fl=0x0" >> $TEMP
			echo "SM_${instance}_def_mc_tc=0x0" >> $TEMP
		fi
	done
fi

print_separator
echo "The FM supports failover.  The FM to be preferred as the primary can be"
echo "selected per FM instance."
echo "If no preferred primary is selected, FMs will negotiate based on CA GUIDs."
get_yes_no "Do you want to configure a preferred primary or secondary FM" "n"
if [ $ans -eq 1 ]
then
	if [ $num_instances -eq 1 ]
	then
		ans=1
	else
		get_yes_no "Will the same FM Failover Priority be used for $fm_allinstances" "y"
	fi
	if [ $ans -eq 1 ]
	then
		get_yes_no "Will this FM be the preferrred primary" "y"
		if [ $ans -eq 1 ]
		then
			set_instance_priority 0 8 # sets for all instances
			echo "  Setting Priority of SM, PM, BM and FE to 8"
		else
			set_instance_priority 0 1 # sets for all instances
			echo "  Setting Priority of SM, PM, BM and FE to 1"
		fi
	else
		for instance in $instances
		do
			get_yes_no "Will FM instance $instance (${fm_name[$instance]}) (${fm_device[$instance]}) be the preferred primary" "y"
			if [ $ans -eq 1 ]
			then
				echo "  Setting Priority of FM instance $instance SM, PM, BM and FE to 8"
				set_instance_priority $instance 8 # sets for all instances
			else
				echo "  Setting Priority of FM instance $instance SM, PM, BM and FE to 1"
				set_instance_priority $instance 1 # sets for all instances
			fi
		done
	fi
fi

print_separator
echo "The FM supports sticky failover.  When enabled Sticky failover will"
echo "prevent a master FM from relinquishing control even if the preferred"
echo "primary FM comes online.  This can prevent situations where a bouncing"
echo "preferred primary repeatedly takes over then fails."
get_yes_no "Should Sticky Failover be enabled" "n"
if [ $ans -eq 1 ]
then
	echo "SM_0_elevated_priority=14" >> $TEMP # sets for all instances
	echo "PM_0_elevated_priority=14" >> $TEMP # sets for all instances
	echo "BM_0_elevated_priority=14" >> $TEMP # sets for all instances
	#echo "FE_0_elevated_priority=14" >> $TEMP # sets for all instances
	echo "  Setting ElevatedPriority of SM, PM, and BM to 14"
fi

print_separator
echo "Each fabric in a cluster must have a unique 64 bit subnet prefix."
echo "The subnet prefix must be consistently configured on all FMs which"
echo "manage the given fabric (eg. on the primary and secondaries)."
echo "To simplify input, you will be prompted for the upper bits for the cluster"
echo "then you will be prompted for the lower bits for each instance."
echo "The two values will be ORed together to form the subnet prefix for each fabric."
get_string "Subnet Prefix upper bits for cluster" "0xfe80000000000000"
upper=$ans
for instance in $instances
do
	if [ $num_instances -eq 1 ]
	then
		default="0x0"
	else
		default="0x100$instance"
	fi
	get_string "Subnet Prefix lower bits for FM instance $instance (${fm_name[$instance]}) (${fm_device[$instance]})" "$default"
	prefix=$(perl -e "use bignum; printf '0x%016Lx', $upper | $ans;")
	echo "  Setting SubnetPrefix of FM instance $instance to $prefix"
	echo "SM_${instance}_gidprefix=$prefix" >> $TEMP
done

print_separator
echo "The Fabric Manager includes a Performance Manager (PM) which can"
echo "monitor the data movement and error counters in all devices."
echo "The PM monitors the counters periodically and computes the delta for counters."
echo "If the PM Sweep Interval is set to 0, no automatic sweeps occur."
echo "The PM SweepInterval must be >0 when using tools such as opatop."
get_number "PM Sweep Interval in seconds" 10
echo "  Setting Pm.SweepInterval to $ans"
echo "PM_0_SweepInterval=$ans" >> $TEMP # sets for all instances

if [ $ans -ne 0 ]
then
	print_separator
	echo "When a port exceeds the threshold for Integrity, Security or Routing errors"
	echo "a message is logged."
	echo "A Threshold can be configured to limit the number of such messages per sweep."
	echo "This Threshold can help to avoid excessive messages."
	get_number "PM Error Threshold Exceeded Log Message Limit" 10
	echo "  Setting ThresholdsExceededMsgLimit.Integrity to $ans"
	echo "PM_0_ThresholdsExceededMsgLimit_Integrity=$ans" >> $TEMP # sets for all instances
	echo "  Setting ThresholdsExceededMsgLimit.Security to $ans"
	echo "PM_0_ThresholdsExceededMsgLimit_Security=$ans" >> $TEMP # sets for all instances
	echo "  Setting ThresholdsExceededMsgLimit.Routing to $ans"
	echo "PM_0_ThresholdsExceededMsgLimit_Routing=$ans" >> $TEMP # sets for all instances

	print_separator
	echo "The PM can retain some recent history in memory."
  	echo "This history can then be viewed in tools such as opatop."
	echo "For each historical sweep both the topology and performance data is retained."
	echo "Each such dataset is referred to as an Image"
	echo "The values will be adjusted based on the number of concurrent PA clients expected."
	if [ "$esm" = y ]
	then
		get_number "How many concurrent clients are expected?" 3 3 4
	else
		get_number "How many concurrent clients are expected?" 3 3 20
	fi
	echo "  Setting Pm.MaxClients to $ans"
	echo "PM_0_MaxClients=$ans" >> $TEMP # sets for all instances

	# FF must be >= MaxClients*2 -1
	# Total must be >= FF+2
	min_ff=$(($ans * 2 -1))
	min_tot=$((min_ff + 2))
	def_tot=10
	[ $min_tot -gt $def_tot ] && def_tot=$min_tot
	if [ "$esm" = y ]
	then
		get_number "How many images should be retained?" $def_tot $min_tot 10
	else
		get_number "How many images should be retained?" $def_tot $min_tot 100000
	fi

	echo "  Setting Pm.TotalImages to $ans"
	echo "PM_0_TotalImages=$ans" >> $TEMP # sets for all instances

	# pick a reasonable number of freeze frame images
	if [ "$ans" -le 10 ]
	then
		ff=$min_ff	# base on MaxClients
	elif [ "$ans" -lt 50 ]
	then
		ff=$(($ans / 2)) # have a reasonably large supply
		[ $ff -lt 8 ] && ff=8   # make sure 11-16 have as much as 10 images
	else
		ff=25
	fi
	[ $ff -lt $min_ff ] && ff=$min_ff
	ans=$ff

	# pick a reasonable number of freeze frame images
	#if [ "$ans" -le 10 ]
	#then
	#	ff=$(($ans -2))	# allocate max allowed
	#elif [ "$ans" -lt 50 ]
	#then
	#	ff=$(($ans / 2)) # have a reasonably large supply
	#	[ $ff -lt 8 ] && ff=8	# make sure 11-16 have as much as 10 images
	#else
	#	ff=25
	#fi
	#ans=$ff

	#print_separator
	#echo "Tools such as opatop freeze historical images when viewed and can"
	#echo "also bookmark images for later viewing during a session".
	#echo "Multiple concurrent opatop sessions could each have unique frozen images."
	#if [ "$esm" = y ]
	#then
	#	get_number "How many frozen/bookmarked images should be allowed" 5 2 8
	#else
	#	get_number "How many frozen/bookmarked images should be allowed" 5 2 99998
	#fi
	echo "  Setting Pm.FreezeFrameImages to $ans"
	echo "PM_0_FreezeFrameImages=$ans" >> $TEMP # sets for all instances
fi

print_separator
$tooldir/config_convert $TEMP $tooldir/opafm_src.xml > $dest_file
echo "Generated $dest_file"
if [ "$esm" = y ]
then
	echo "To activate this configuration, $dest_file must be transfered to"
	echo "the chassis and the FM must be restarted."
	echo "The fastfabric TUI provides an easy way to do this."
elif [ "$dest_file" != "/etc/sysconfig/opafm.xml" ]
then
	echo "To activate this configuration, $dest_file must be copied to"
	echo "/etc/sysconfig/opafm.xml and the FM must be restarted."
else
	echo "To activate this configuration, the FM must be restarted."
fi
rm -f $TEMP
