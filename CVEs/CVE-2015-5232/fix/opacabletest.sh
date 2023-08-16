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

# start and stop HFI-SW and/or ISL cable Bit Error Rate tests

tempfile=`mktemp`
trap "rm -f $tempfile; exit 1" SIGHUP SIGTERM SIGINT
trap "rm -f $tempfile" EXIT

# optional override of defaults
if [ -f /etc/sysconfig/opa/opafastfabric.conf ]
then
	. /etc/sysconfig/opa/opafastfabric.conf
fi

. /opt/opa/tools/opafastfabric.conf.def

TOOLSDIR=${TOOLSDIR:-/opt/opa/tools}
BINDIR=${BINDIR:-/usr/sbin}

. $TOOLSDIR/ff_funcs

Usage_full()
{
		echo "Usage: opacabletest [-C|-A] [-c file] [-f hostfile] [-h 'hosts'] [-n numprocs]" >&2
	echo "                     [-t portsfile] [-p ports]" >&2
	echo "                     [start|start_fi|start_isl|stop|stop_fi|stop_isl] ..." >&2
	echo "              or" >&2
	echo "       opacabletest --help" >&2
	echo "   --help - produce full help text" >&2
	echo "   -C - clear error counters" >&2
	echo "   -A - force clear of hw error counters" >&2
	echo "        implies -C" >&2
	echo "   -c file - error thresholds config file" >&2
	echo "             default is $CONFIG_DIR/opa/opamon.si.conf" >&2
	echo "             only used if -C or -A specified" >&2
	echo "   -f hostfile - file with hosts to include in HFI-SW test," >&2
	echo "                 default is $CONFIG_DIR/opa/hosts" >&2
	echo "   -h hosts - list of hosts to include in HFI-SW test" >&2
	echo "   -n numprocs - number of processes per host for HFI-SW test" >&2
	echo "   -t portsfile - file with list of local HFI ports used to access fabric(s)" >&2
	echo "                  when clearing counters, default is $CONFIG_DIR/opa/ports" >&2
	echo "   -p ports - list of local HFI ports used to access fabric(s) for counter clear" >&2
	echo "              default is 1st active port" >&2
	echo "              This is specified as hfi:port" >&2
	echo "                 0:0 = 1st active port in system" >&2
	echo "                 0:y = port y within system" >&2
	echo "                 x:0 = 1st active port on HFI x" >&2
	echo "                 x:y = HFI x, port y" >&2
	echo "              The first HFI in the system is 1.  The first port on an HFI is 1." >&2
	echo "   start - start the HFI-SW and ISL tests" >&2
	echo "   start_fi - start the HFI-SW test" >&2
	echo "   start_isl - start the ISL test" >&2
	echo "   stop - stop the HFI-SW and ISL tests" >&2
	echo "   stop_fi - stop the HFI-SW test" >&2
	echo "   stop_isl - stop the ISL test" >&2
	echo >&2
	echo "The HFI-SW cabletest requires that FF_MPI_APPS_DIR be set and contains" >&2
	echo "a prebuilt copy of Intel mpi_apps for an appropriate MPI" >&2
	echo >&2
	echo "The ISL cabletest as started by this tool assumes the master HSM is running" >&2
	echo "on this host.  If using ESM or a different host is master FM, ISL cabletest" >&2
	echo "will have to be controlled by the switch CLI or by FastFabric on the master FM" >&2
	echo "respectively" >&2
	echo >&2
	echo " Environment:" >&2
	echo "   HOSTS - list of hosts, used if -h option not supplied" >&2
	echo "   HOSTS_FILE - file containing list of hosts, used in absence of -f and -h" >&2
	echo "   PORTS - list of ports, used in absence of -t and -p" >&2
	echo "   PORTS_FILE - file containing list of ports, used in absence of -t and -p" >&2
	echo "   FF_MAX_PARALLEL - maximum concurrent operations" >&2
	echo "example:">&2
	echo "   opacabletest -A start" >&2
	echo "   opacabletest -f good -A start" >&2
	echo "   opacabletest -h 'arwen elrond' start_fi" >&2
	echo "   HOSTS='arwen elrond' opacabletest stop" >&2
	echo "   opacabletest -A" >&2
	rm -f $tempfile
	exit 0
}

Usage()
{
	echo "Usage: opacabletest [-C|-A] [-n numprocs] [-f hostfile]" >&2
	echo "                 [start|start_fi|start_isl|stop|stop_fi|stop_isl] ..." >&2
	echo "              or" >&2
	echo "       opacabletest --help" >&2
	echo "   --help - produce full help text" >&2
	echo "   -C - clear error counters" >&2
	echo "   -A - force clear of hw error counters" >&2
	echo "        implies -C" >&2
	echo "   -f hostfile - file with hosts to include in HFI-SW test," >&2
	echo "                 default is $CONFIG_DIR/opa/hosts" >&2
	echo "   -n numprocs - number of processes per host for HFI-SW test" >&2
	echo >&2
	echo "   start - start the HFI-SW and ISL tests" >&2
	echo "   start_fi - start the HFI-SW test" >&2
	echo "   start_isl - start the ISL test" >&2
	echo "   stop - stop the HFI-SW and ISL tests" >&2
	echo "   stop_fi - stop the HFI-SW test" >&2
	echo "   stop_isl - stop the ISL test" >&2
	echo >&2
	echo "The HFI-SW cabletest requires that FF_MPI_APPS_DIR be set and contains" >&2
	echo "a prebuilt copy of Intel mpi_apps for an appropriate MPI" >&2
	echo >&2
	echo "The ISL cabletest as started by this tool assumes the master HSM is running" >&2
	echo "on this host.  If using ESM or a different host is master FM, ISL cabletest" >&2
	echo "will have to be controlled by the switch CLI or by FastFabric on the master FM" >&2
	echo "respectively" >&2
	echo >&2
	echo " Environment:" >&2
	echo "   FF_MAX_PARALLEL - maximum concurrent operations" >&2
	echo "example:">&2
	echo "   opacabletest -f good -A start" >&2
	echo "   opacabletest stop" >&2
	echo "   opacabletest -A" >&2
	rm -f $tempfile
	exit 2
}

if [ x"$1" = "x--help" ]
then
	Usage_full
fi

clear=n
clearhw=n
numprocs=3
config_file="$CONFIG_DIR/opa/opamon.si.conf"
while getopts CAf:h:n:t:p:c: param
do
	case $param in
	C)
		clear=y;;
	A)
		clear=y; clearhw=y;;
	h)
		HOSTS="$OPTARG";;
	f)
		HOSTS_FILE="$OPTARG";;
	n)
		numprocs="$OPTARG";;
	t)
		PORTS_FILE="$OPTARG";;
	p)
		PORTS="$OPTARG";;
	c)
		config_file="$OPTARG";;
	?)
		Usage;;
	esac
done
shift $((OPTIND -1))

check_host_args opacabletest
# HOSTS now lists all the hosts, pass it along to the commands below via env
export HOSTS
unset HOSTS_FILE

if [ $clear = y ]
then
	check_ports_args opacabletest
	# PORTS now lists all the ports, pass it along to the commands below via env
	export PORTS
	unset PORTS_FILE
fi

if [ $clear = y ]
then
	opareports -C -c "$config_file" -o none
	if [ $clearhw = y ]
	then
		opareports -M -C -c "$config_file" -o none
	fi
fi

get_fmconfig()
{
	FM_CONFIG_DIR=/etc/sysconfig
	FM_CONFIG_FILE=$CONFIG_DIR/opafm.xml
	IFS_FM_BASE=/opt/opafm # default
	if [ -s $FM_CONFIG_DIR/opa/opafm.info ]
	then
	    # get IFS_FM_BASE
	    . $FM_CONFIG_DIR/opa/opafm.info
	else
	    echo "opacabletest: Warning: $FM_CONFIG_DIR/opa/opafm.info not found: using $IFS_FM_BASE" >&2
	fi
}

start_fi()
{
	if [ ! -e $FF_MPI_APPS_DIR/run_batch_cabletest ]
	then
		echo "opacabletest: Invalid FF_MPI_APPS_DIR: $FF_MPI_APPS_DIR" >&2
		exit 1
	fi
	if [ ! -x $FF_MPI_APPS_DIR/groupstress/mpi_groupstress ]
	then
		echo "opacabletest: FF_MPI_APPS_DIR ($FF_MPI_APPS_DIR) not compiled" >&2
		rm -f $tempfile
		exit 1
	fi
	ff_var_to_stdout "$HOSTS" > $tempfile
	cd $FF_MPI_APPS_DIR
	MPI_HOSTS=$tempfile ./run_batch_cabletest -n $numprocs infinite
}

start_isl()
{
	(
		get_fmconfig
		if [ ! -x $IFS_FM_BASE/bin/fm_opacmdall ]
		then
			echo "opacabletest: Error: $IFS_FM_BASE/bin/fm_opacmdall not found" >&2
			rm -f $tempfile
			exit 1
		fi
		$IFS_FM_BASE/bin/fm_opacmdall smLooptestFastModeStart
	)
	res=$?
	[ $res -ne 0 ] && exit $res
}

start()
{
	start_fi
	start_isl
}

stop_fi()
{
	# we use patterns so the pkill doesn't kill this script or opacmdall itself
	# use an echo at end so exit status is good
	$BINDIR/opacmdall -p -T 60 "pkill -9 -f '[m]pi_groupstress'; echo -n"
}

stop_isl()
{
	(
		get_fmconfig
		if [ ! -x $IFS_FM_BASE/bin/fm_opacmdall ]
		then
			echo "opacabletest: Error: $IFS_FM_BASE/bin/fm_opacmdall not found" >&2
			rm -f $tempfile
			exit 1
		fi
		$IFS_FM_BASE/bin/fm_opacmdall smLooptestStop
	)
	res=$?
	[ $res -ne 0 ] && exit $res
}

stop()
{
	stop_fi
	stop_isl
}

while [ $# -ne 0 ]
do
	case "$1" in
	start) start;;
	start_fi) start_fi;;
	start_isl) start_isl;;
	stop) stop;;
	stop_fi) stop_fi;;
	stop_isl) stop_isl;;
	*)	Usage;;
	esac
	shift
done

rm -f $tempfile
