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

# reenable the specified set of ports

tempfile=/tmp/opaenableports$$
trap "rm -f $tempfile; exit 1" SIGHUP SIGTERM SIGINT

# optional override of defaults
if [ -f /etc/sysconfig/opa/opafastfabric.conf ]
then
	. /etc/sysconfig/opa/opafastfabric.conf
fi

. /opt/opa/tools/opafastfabric.conf.def

. /opt/opa/tools/ff_funcs

Usage_full()
{
	echo "Usage: opaenableports [-t portsfile] [-p ports] < disabled.csv" >&2
	echo "              or" >&2
	echo "       opaenableports --help" >&2
	echo "   --help - produce full help text" >&2
	echo "   -t portsfile - file with list of local HFI ports used to access" >&2
	echo "                  fabric(s) for operation, default is $CONFIG_DIR/opa/ports" >&2
	echo "   -p ports - list of local HFI ports used to access fabric(s) for operation" >&2
	echo "              default is 1st active port" >&2
	echo "              This is specified as hfi:port" >&2
	echo "                 0:0 = 1st active port in system" >&2
	echo "                 0:y = port y within system" >&2
	echo "                 x:0 = 1st active port on HFI x" >&2
	echo "                 x:y = HFI x, port y" >&2
	echo "              The first HFI in the system is 1.  The first port on an HFI is 1." >&2
	echo  >&2
	echo "disabled.csv is a file listing the ports to enable." >&2
	echo "It is of the form:" >&2
	echo "   NodeGUID;PortNum;NodeDesc" >&2
	echo "A input file such as this is generated in $CONFIG_DIR/opa/disabled*" >&2
	echo "by opadisableports." >&2
	echo " Environment:" >&2
	echo "   PORTS - list of ports, used in absence of -t and -p" >&2
	echo "   PORTS_FILE - file containing list of ports, used in absence of -t and -p" >&2
	echo "for example:" >&2
	echo "   opaenableports < disabled.csv" >&2
	echo "   opaenableports -p '1:1 1:2 2:1 2:2' < disabled.csv" >&2
	exit 0
}

Usage()
{
	echo "Usage: opaenableports < disabled.csv" >&2
	echo "              or" >&2
	echo "       opaenableports --help" >&2
	echo "   --help - produce full help text" >&2
	echo  >&2
	echo "disabled.csv is a file listing the ports to enable." >&2
	echo "It is of the form:" >&2
	echo "   NodeGUID;PortNum;NodeDesc" >&2
	echo "A input file such as this is generated in $CONFIG_DIR/opa/disabled*" >&2
	echo "by opadisableports." >&2
	echo "for example:" >&2
	echo "   opaenableports < disabled.csv" >&2
	exit 2
}

if [ x"$1" = "x--help" ]
then
	Usage_full
fi

res=0
while getopts p:t: param
do
	case $param in
	p)	export PORTS="$OPTARG";;
	t)	export PORTS_FILE="$OPTARG";;
	?)	Usage;;
	esac
done
shift $((OPTIND -1))
if [ $# -ge 1 ]
then
	Usage
fi

check_ports_args opaenableports

lookup_lid()
{
	local nodeguid="$1"
	local portnum="$2"
	local guid port type desc lid

	grep "^$nodeguid;$portnum;" < $lidmap|while read guid port type desc lid
	do
		echo -n $lid
	done
}
	

enable_ports()
{
	enabled=0
	failed=0
	skipped=0
	if [ "$port" -eq 0 ]
	then
		port_opts="-h $hfi"	# default port to 1st active
	else
		port_opts="-h $hfi -p $port"
	fi
	suffix=":$hfi:$port"
	lidmap=$CONFIG_DIR/lidmap$suffix.csv

	# generate lidmap
	/sbin/opaextractlids $port_opts > $lidmap
	if [ ! -s $lidmap ]
	then
		echo "opaenableports: Unable to determine fabric lids" >&2
		rm -f $lidmap
		return 1
	fi

	IFS=';'
	while read nodeguid port type desc rest
	do
		lid=$(lookup_lid $nodeguid 0)
		if [ x"$lid" = x ]
		then
			echo "Skipping port: $desc:$port"
			skipped=$(( $skipped + 1))
		else
			echo "Enabling port: $desc:$port"
			/sbin/opaportconfig $port_opts -l $lid -m $port enable

			if [ $? = 0 ]
			then
				logger -p user.err "Enabled port: $desc:$port"
				if [ -e $CONFIG_DIR/opa/disabled$suffix.csv ]
				then
					# remove from disabled ports
					grep -v "^$nodeguid;$port;" < $CONFIG_DIR/opa/disabled$suffix.csv > $tempfile
					mv $tempfile $CONFIG_DIR/opa/disabled$suffix.csv
				fi
				enabled=$(( $enabled + 1))
			else
				failed=$(( $failed + 1))
			fi
		fi
	done
	if [ $failed -eq 0 ]
	then
		echo "Enabled: $enabled; Skipped: $skipped"
		return 0
	else
		echo "Enabled: $enabled; Skipped: $skipped; Failed: $failed"
		return 1
	fi
}


for hfi_port in $PORTS
do
	hfi=$(expr $hfi_port : '\([0-9]*\):[0-9]*')
	port=$(expr $hfi_port : '[0-9]*:\([0-9]*\)')
	/usr/sbin/oparesolvehfiport $hfi $port >/dev/null
	if [ $? -ne 0 -o "$hfi" = "" -o "$port" = "" ]
	then
		echo "opaenableports: Error: Invalid port specification: $hfi_port" >&2
		res=1
		continue
	fi

	echo "Processing fabric: $hfi:$port..."
	echo "--------------------------------------------------------"
	enable_ports "$hfi" "$port"
	if [ $? -ne 0 ]
	then
		res=1
	fi
done

exit $res
