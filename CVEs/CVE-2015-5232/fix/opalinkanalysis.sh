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

# analyzes all the links in the fabric

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

punchlist=$FF_RESULT_DIR/punchlist.csv
del=';'
timestamp=$(date +"%Y/%m/%d %T")

Usage_full()
{
	echo "Usage: opalinkanalysis [-U] [-t portsfile] [-p ports] [-T topology_input]" >&2
	echo "                  -X snapshot_input] [-x snapshot_suffix] [-c file] reports ..." >&2
	echo "              or" >&2
	echo "       opalinkanalysis --help" >&2
	echo "   --help - produce full help text" >&2
	echo "   -U - omit unexpected devices and links in punchlist from verify reports" >&2
	echo "   -t portsfile - file with list of local HFI ports used to access" >&2
	echo "                  fabric(s) for analysis, default is $CONFIG_DIR/opa/ports" >&2
	echo "   -p ports - list of local HFI ports used to access fabric(s) for analysis" >&2
	echo "              default is 1st active port" >&2
	echo "              This is specified as hfi:port" >&2
	echo "                 0:0 = 1st active port in system" >&2
	echo "                 0:y = port y within system" >&2
	echo "                 x:0 = 1st active port on HFI x" >&2
	echo "                 x:y = HFI x, port y" >&2
	echo "              The first HFI in the system is 1.  The first port on an HFI is 1." >&2
	echo "   -T topology_input - name of a topology input file to use." >&2
	echo "              Any %P markers in this filename will be replaced with the" >&2
	echo "              hfi:port being operated on (such as 0:0 or 1:2)" >&2
	echo "              default is $CONFIG_DIR/opa/topology.%P.xml" >&2
	echo "              if NONE is specified, will not use any topology_input files" >&2
	echo "              See opareport for more information on topology_input files" >&2
	echo "   -X snapshot_input - perform analysis using data in snapshot_input" >&2
	echo "              snapshot_input must have been generated via a previous" >&2
	echo "              opareport -o snapshot run." >&2
	echo "              If errors report is specified, snapshot must have been generated" >&2
	echo "              with opareport -s option" >&2
	echo "              When this option is used, only one port may be specified" >&2
	echo "              to select a topology_input file (unless -T specified)">&2
	echo "              When this option is used, clearerrors and clearhwerrors reports" >&2
	echo "              are not permitted" >&2
	echo "   -x snapshot_suffix - create a snapshot file per selected port" >&2
	echo "              The files will be created in FF_RESULT_DIR with names of the form:">&2
	echo "              snapshotSUFFIX.HFI:PORT.xml.">&2
	echo "   -c file - error thresholds config file" >&2
	echo "             default is $CONFIG_DIR/opa/opamon.si.conf" >&2
	echo "    reports - The following reports are supported" >&2
	echo "         errors - link error analysis" >&2
	echo "         slowlinks - links running slower than expected" >&2
	echo "         misconfiglinks - links configured to run slower than supported" >&2
	echo "         misconnlinks - links connected with mismatched speed potential" >&2
	echo "         all - includes all reports above" >&2
	echo "         verifylinks - verify links against topology input" >&2
	echo "         verifyextlinks - verify links against topology input" >&2
	echo "                     limit analysis to links external to systems" >&2
	echo "         verifyfis - verify FIs against topology input" >&2
	echo "         verifysws - verify Switches against topology input" >&2
	echo "         verifyrtrs - verify Routers against topology input" >&2
	echo "         verifynodes - verify FIs, Switches and Routers against topology input" >&2
	echo "         verifysms - verify SMs against topology input" >&2
	echo "         verifyall - verifies links, FIs, Switches, Routers and SMs" >&2
	echo "                     against topology input" >&2
	echo "         clearerrors - clear error counters, uses PM if available" >&2
	echo "         clearhwerrors - clear HW error counters, bypasses PM" >&2
	echo "         clear - includes clearerrors and clearhwerrors" >&2
  	echo >&2
	echo "A punchlist of bad links is also appended to FF_RESULT_DIR/punchlist.csv" >&2
  	echo >&2
	echo " Environment:" >&2
	echo "   PORTS - list of ports, used in absence of -t and -p" >&2
	echo "   PORTS_FILE - file containing list of ports, used in absence of -t and -p" >&2
	echo "   FF_TOPOLOGY_FILE - file containing topology_input, used in absence of -T" >&2
	echo "example:">&2
	echo "   opalinkanalysis errors" >&2
	echo "   opalinkanalysis errors clearerrors" >&2
	echo "   opalinkanalysis -p '1:1 1:2 2:1 2:2'" >&2
	exit 0
}

Usage()
{
	echo "Usage: opalinkanalysis [-U] reports ..." >&2
	echo "              or" >&2
	echo "       opalinkanalysis --help" >&2
	echo "   --help - produce full help text" >&2
  	echo >&2
	echo "   -U - omit unexpected devices and links in punchlist from verify reports" >&2
	echo "    reports - The following reports are supported" >&2
	echo "         errors - link error analysis" >&2
	echo "         slowlinks - links running slower than expected" >&2
	echo "         misconfiglinks - links configured to run slower than supported" >&2
	echo "         misconnlinks - links connected with mismatched speed potential" >&2
	echo "         all - includes all reports above" >&2
	echo "         verifylinks - verify links against topology input" >&2
	echo "         verifyextlinks - verify links against topology input" >&2
	echo "                     limit analysis to links external to systems" >&2
	echo "         verifyfis - verify FIs against topology input" >&2
	echo "         verifysws - verify Switches against topology input" >&2
	echo "         verifyrtrs - verify Routers against topology input" >&2
	echo "         verifynodes - verify FIs, Switches and Routers against topology input" >&2
	echo "         verifysms - verify SMs against topology input" >&2
	echo "         verifyall - verifies links, FIs, Switches, Routers and SMs" >&2
	echo "                     against topology input" >&2
	echo "         clearerrors - clear error counters, uses PM if available" >&2
	echo "         clearhwerrors - clear HW error counters, bypasses PM" >&2
	echo "         clear - includes clearerrors and clearhwerrors" >&2
  	echo >&2
	echo "A punchlist of bad links is also appended to FF_RESULT_DIR/punchlist.csv" >&2
  	echo >&2
	echo "example:">&2
	echo "   opalinkanalysis errors" >&2
	echo "   opalinkanalysis errors clearerrors" >&2
	exit 2
}

if [ x"$1" = "x--help" ]
then
	Usage_full
fi

append_punchlist()
# $1 = device
# $2 = issue
{
	echo "$timestamp$del$1$del$2" >> $punchlist
}

gen_errors_punchlist()
# $@ =  snapshot, port and/or topology selection options for opareport
{
	(
	# TBD - is cable information available?
	export IFS=';'
	port1=
	#opareport -q "$@" -o errors -x | $BINDIR/opaxmlextract -H -d \; -e LinkErrors.Link.Port.NodeGUID -e LinkErrors.Link.Port.PortNum -e LinkErrors.Link.Port.NodeType -e LinkErrors.Link.Port.NodeDesc|while read line
	opareport -q "$@" -o errors -x | $BINDIR/opaxmlextract -H -d \; -e LinkErrors.Link.Port.NodeDesc -e LinkErrors.Link.Port.PortNum|while read desc port
	do
		if [ x"$port1" = x ]
		then
			port1="$desc p$port"
		else
			append_punchlist "$port1 $desc p$port" "Link errors"
			port1=
		fi
	done
	)
}

gen_slowlinks_punchlist()
# $@ =  snapshot, port and/or topology selection options for opareport
{
	(
	# TBD - is cable information available?
	export IFS=';'
	port1=
	#opareport -q "$@" -o slowlinks -x | $BINDIR/opaxmlextract -H -d \; -e LinksExpected.Link.Port.NodeGUID -e LinksExpected.Link.Port.PortNum -e LinksExpected.Link.Port.NodeType -e LinksExpected.Link.Port.NodeDesc|while read line
	opareport -q "$@" -o slowlinks -x | $BINDIR/opaxmlextract -H -d \; -e LinksExpected.Link.Port.NodeDesc -e LinksExpected.Link.Port.PortNum|while read desc port
	do
		if [ x"$port1" = x ]
		then
			port1="$desc p$port"
		else
			append_punchlist "$port1 $desc p$port" "Link speed/width lower than expected"
			port1=
		fi
	done
	)
}

gen_misconfiglinks_punchlist()
# $@ =  snapshot, port and/or topology selection options for opareport
{
	(
	# TBD - is cable information available?
	export IFS=';'
	port1=
	#opareport -q "$@" -o misconfiglinks -x | $BINDIR/opaxmlextract -H -d \; -e LinksConfig.Link.Port.NodeGUID -e LinksConfig.Link.Port.PortNum -e LinksConfig.Link.Port.NodeType -e LinksConfig.Link.Port.NodeDesc|while read line
	opareport -q "$@" -o misconfiglinks -x | $BINDIR/opaxmlextract -H -d \; -e LinksConfig.Link.Port.NodeDesc -e LinksConfig.Link.Port.PortNum|while read desc port
	do
		if [ x"$port1" = x ]
		then
			port1="$desc p$port"
		else
			append_punchlist "$port1 $desc p$port" "Link speed/width configured lower than supported"
			port1=
		fi
	done
	)
}

gen_misconnlinks_punchlist()
# $@ =  snapshot, port and/or topology selection options for opareport
{
	(
	# TBD - is cable information available?
	export IFS=';'
	line1=
	#opareport -q "$@" -o misconnlinks -x | $BINDIR/opaxmlextract -H -d \; -e LinksMismatched.Link.Port.NodeGUID -e LinksMismatched.Link.Port.PortNum -e LinksMismatched.Link.Port.NodeType -e LinksMismatched.Link.Port.NodeDesc|while read line
	opareport -q "$@" -o misconnlinks -x | $BINDIR/opaxmlextract -H -d \; -e LinksMismatched.Link.Port.NodeDesc -e LinksMismatched.Link.Port.PortNum|while read desc port
	do
		if [ x"$line1" = x ]
		then
			line1="$desc p$port"
		else
			append_punchlist "$line1 $desc p$port" "Link speed/width mismatch"
			line1=
		fi
	done
	)
}

append_verify_punchlist()
# $1 = device
# $2 = issue
{
	if [ $skip_unexpected = y ]
	then
		case "$2" in
		Unexpected*)	> /dev/null;;
		*) echo "$timestamp$del$1$del$2" >> $punchlist;;
		esac
	else
		echo "$timestamp$del$1$del$2" >> $punchlist
	fi

}

gen_verifylinks_punchlist()
# $@ =  snapshot, port and/or topology selection options for opareport
{
	(
	# TBD - is cable information available?
	export IFS=';'
	port1=
	port2=
	prob=
	#eval opareport -q "$@" -o verifylinks -x | $BINDIR/opaxmlextract -H -d \; -e VerifyLinks.Link.Port.NodeGUID -e VerifyLinks.Link.Port.PortNum -e VerifyLinks.Link.Port.NodeType -e VerifyLinks.Link.Port.NodeDesc|while read line
	eval opareport -q "$@" -o verifylinks -x | $BINDIR/opaxmlextract -H -d \; -e VerifyLinks.Link.Port.NodeDesc -e VerifyLinks.Link.Port.PortNum -e VerifyLinks.Link.Port.Problem -e VerifyLinks.Link.Problem|while read desc port portprob linkprob
	do
		if [ x"$port1" = x ]
		then
			port1="$desc p$port"
			prob="$portprob"
			if [ x"$prob" = x ]
			then
				prob=$linkprob	# unlikely to occur here
			fi
		elif [ x"$port2" = x ]
		then
			port2="$desc p$port"
			if [ x"$prob" = x ]
			then
				prob=$portprob
			fi
			if [ x"$prob" = x ]
			then
				prob=$linkprob	# unlikely to occur here
			fi

			if [ x"$prob" != x ]
			then
				append_verify_punchlist "$port1 $port2" "$prob"
				port1=
				port2=
				prob=
			fi
		else
			# separate record for link problem
			prob=$linkprob
			append_verify_punchlist "$port1 $port2" "$prob"
			port1=
			port2=
			prob=
		fi
	done
	)
}

gen_verifyextlinks_punchlist()
# $@ =  snapshot, port and/or topology selection options for opareport
{
	(
	# TBD - is cable information available?
	export IFS=';'
	port1=
	port2=
	prob=
	#eval opareport -q "$@" -o verifyextlinks -x | $BINDIR/opaxmlextract -H -d \; -e VerifyExtLinks.Link.Port.NodeGUID -e VerifyExtLinks.Link.Port.PortNum -e VerifyExtLinks.Link.Port.NodeType -e VerifyExtLinks.Link.Port.NodeDesc|while read line
	eval opareport -q "$@" -o verifyextlinks -x | $BINDIR/opaxmlextract -H -d \; -e VerifyExtLinks.Link.Port.NodeDesc -e VerifyExtLinks.Link.Port.PortNum -e VerifyExtLinks.Link.Port.Problem -e VerifyExtLinks.Link.Problem|while read desc port portprob linkprob
	do
		if [ x"$port1" = x ]
		then
			port1="$desc p$port"
			prob="$portprob"
			if [ x"$prob" = x ]
			then
				prob=$linkprob	# unlikely to occur here
			fi
		elif [ x"$port2" = x ]
		then
			port2="$desc p$port"
			if [ x"$prob" = x ]
			then
				prob=$portprob
			fi
			if [ x"$prob" = x ]
			then
				prob=$linkprob	# unlikely to occur here
			fi

			if [ x"$prob" != x ]
			then
				append_verify_punchlist "$port1 $port2" "$prob"
				port1=
				port2=
				prob=
			fi
		else
			# separate record for link problem
			prob=$linkprob
			append_verify_punchlist "$port1 $port2" "$prob"
			port1=
			port2=
			prob=
		fi
	done
	)
}

gen_verifyfis_punchlist()
# $@ =  snapshot, port and/or topology selection options for opareport
{
	(
	export IFS=';'
	#eval opareport -q "$@" -o verifyfis -x | $BINDIR/opaxmlextract -H -d \; -e VerifyFIs.Node.NodeGUID -e VerifyFIs.Node.Desc -e VerifyFIs.Node.Problem|while read line
	eval opareport -q "$@" -o verifyfis -x | $BINDIR/opaxmlextract -H -d \; -e VerifyFIs.Node.NodeDesc -e VerifyFIs.Node.Problem |while read desc prob
	do
		append_verify_punchlist "$desc" "$prob"
	done
	)
}

gen_verifysws_punchlist()
# $@ =  snapshot, port and/or topology selection options for opareport
{
	(
	export IFS=';'
	#eval opareport -q "$@" -o verifysws -x | $BINDIR/opaxmlextract -H -d \; -e VerifySWs.Node.NodeGUID -e VerifySWs.Node.Desc -e VerifySWs.Node.Problem|while read line
	eval opareport -q "$@" -o verifysws -x | $BINDIR/opaxmlextract -H -d \; -e VerifySWs.Node.NodeDesc -e VerifySWs.Node.Problem |while read desc prob
	do
		append_verify_punchlist "$desc" "$prob"
	done
	)
}

gen_verifyrtrs_punchlist()
# $@ =  snapshot, port and/or topology selection options for opareport
{
	(
	export IFS=';'
	#eval opareport -q "$@" -o verifyrtrs -x | $BINDIR/opaxmlextract -H -d \; -e VerifyRTs.Node.NodeGUID -e VerifyRTs.Node.Desc -e VerifyRTs.Node.Problem|while read line
	eval opareport -q "$@" -o verifyrtrs -x | $BINDIR/opaxmlextract -H -d \; -e VerifyRTs.Node.NodeDesc -e VerifyRTs.Node.Problem |while read desc prob
	do
		append_verify_punchlist "$desc" "$prob"
	done
	)
}

gen_verifysms_punchlist()
# $@ =  snapshot, port and/or topology selection options for opareport
{
	(
	export IFS=';'
	#eval opareport -q "$@" -o verifysms -x | $BINDIR/opaxmlextract -H -d \; -e VerifySMs.SM.NodeGUID -e VerifySMs.SM.Desc -e VerifySMs.SM.Problem|while read line
	eval opareport -q "$@" -o verifysms -x | $BINDIR/opaxmlextract -H -d \; -e VerifySMs.SM.NodeDesc -e VerifySMs.SM.PortNum -e VerifySMs.SM.Problem |while read desc port prob
	do
		# port number is optional in topology_input, so for missing SMs
		# it might not be reported
		if [ x"$port" != x ]
		then
			append_verify_punchlist "$desc p$port" "$prob"
		else
			append_verify_punchlist "$desc" "$prob"
		fi
	done
	)
}

report_opts=""
verify_opts=""
errors=n
clearerrors=n
clearhwerrors=n
slowlinks=n
misconfiglinks=n
misconnlinks=n
verifylinks=n
verifyextlinks=n
verifyfis=n
verifysws=n
verifyrtrs=n
verifysms=n
reports=""
read_snapshot=n
snapshot_input=
save_snapshot=n
snapshot_suffix=
skip_unexpected=n
config_file="$CONFIG_DIR/opa/opamon.si.conf"
while getopts Ut:p:T:X:x:c: param
do
	case $param in
	U)	skip_unexpected=y;;
	p)	export PORTS="$OPTARG";;
	t)	export PORTS_FILE="$OPTARG";;
	T)	export FF_TOPOLOGY_FILE="$OPTARG";;
	X)	read_snapshot=y; export snapshot_input="$OPTARG";;
	x)	save_snapshot=y; export snapshot_suffix="$OPTARG";;
	c)	config_file="$OPTARG";;
	?)
		Usage;;
	esac
done
shift $((OPTIND -1))
if [ $# -le 0 ]
then
	echo "opalinkanalysis: Error: must specify at least 1 report" >&2
	Usage
fi
while [ $# -gt 0 ]
do
	case "$1" in
	errors) errors=y;;
	slowlinks) slowlinks=y;;
	misconfiglinks) misconfiglinks=y;;
	misconnlinks) misconnlinks=y;;
	all) errors=y; slowlinks=y; misconfiglinks=y; misconnlinks=y;;
	verifylinks) verifylinks=y;;
	verifyextlinks) verifyextlinks=y;;
	verifyfis) verifyfis=y;;
	verifysws) verifysws=y;;
	verifyrtrs) verifyrtrs=y;;
	verifynodes)  verifyfis=y; verifysws=y; verifyrtrs=y;;
	verifysms) verifysms=y;;
	verifyall) verifylinks=y; verifyfis=y; verifysws=y; verifyrtrs=y; verifysms=y;;
	clearerrors) clearerrors=y;;
	clearhwerrors) clearhwerrors=y;;
	clear) clearerrors=y; clearhwerrors=y;;
	*)
		echo "opalinkanalysis: Invalid report: $1" >&2
		Usage;;
	esac
	shift
done

for report in errors slowlinks misconfiglinks misconnlinks verifylinks verifyextlinks verifyfis verifysws verifyrtrs verifysms
do
	yes=$(eval echo \$$report)
	if [ $yes = y ]
	then
		case $report in
		verify*)
			verify_opts="$verify_opts -o $report"
			reports="$reports $report";;
		*)
			report_opts="$report_opts -o $report"
			reports="$reports $report";;
		esac
	fi
done

snapshopt_opts=
if [ $errors = y ]
then
	snapshopt_opts="-s"
	report_opts="$report_opts -c '$config_file'"
fi

if [ $read_snapshot = y ]
then
	if [ $clearerrors = y -o $clearhwerrors = y ]
	then
		echo "opalinkanalysis: errors and clearhwerrors reports not available with -X" >&2
		Usage
	fi
	if [ $save_snapshot = y ]
	then
		echo "opalinkanalysis: -X and -x options are mutually exclusive" >&2
	fi
fi

check_ports_args opalinkanalysis

ports=0
for hfi_port in $PORTS
do
    ports=$(($ports + 1))
done
if [ $ports -lt 1 ]
then
    # should not happen, but be safe
    ports=1
    PORTS="0:0"
fi
if [ $read_snapshot = y -a $ports -gt 1 ]
then
	echo "opalinkanalysis: -X option cannot be used with more than 1 port" >&2
	Usage
fi


status=0
for hfi_port in $PORTS
do
	# TBD - make some ff_funcs to handle these conversions and checks
	hfi=$(expr $hfi_port : '\([0-9]*\):[0-9]*')
	port=$(expr $hfi_port : '[0-9]*:\([0-9]*\)')
	if [ "$hfi" = "" -o "$port" = "" ]
	then
		echo "opalinkanalysis: Error: Invalid port specification: $hfi_port" >&2
		status=1
		continue
	fi
	if [ "$port" -eq 0 ]
	then
		port_opts="-h $hfi" # default port to 1st active
	else
		port_opts="-h $hfi -p $port"
	fi
	resolve_topology_file opalinkanalysis "$hfi:$port"
	topt=""
	if [ "$TOPOLOGY_FILE" != "" ]
	then
		topt="-T '$TOPOLOGY_FILE'"
	fi

	if [ "$read_snapshot" = n ]
	then
		if [ $ports -gt 1 ]
		then
			echo "Fabric $hfi:$port Analysis:"
		fi
		if [ "$save_snapshot" = y ]
		then
			snapshot_input=$FF_RESULT_DIR/snapshot$snapshot_suffix.$hfi:$port.xml
		else
			snapshot_input=$tempfile
		fi
		# generate a snapshot per fabric then analyze
		opareport $port_opts $snapshopt_opts -o snapshot > $snapshot_input
	fi

	# generate human readable reports
	if [ x"$report_opts" != x ]
	then
		eval opareport -X $snapshot_input $topt $report_opts 
	fi

	if [ x"$verify_opts" != x ]
	then
		if [ "$TOPOLOGY_FILE" != "" ]
		then
			eval opareport -X $snapshot_input $topt $verify_opts
		else
			echo "Unable to verify topology for $hfi:$port, no topology file found" >&2
			status=1
		fi
	fi

	# note: if snapshot=y, these reports are not permitted
	if [ "$clearerrors" = y ]
	then
		opareport $port_opts -C -c "$config_file" -o none
	fi
	if [ "$clearhwerrors" = y ]
	then
		opareport $port_opts -M -C -c "$config_file" -o none
	fi

	# now generate punchlist
	for report in $reports
	do
		case "$report" in
		errors) gen_errors_punchlist -X $snapshot_input -c "$config_file";;
		slowlinks) gen_slowlinks_punchlist -X $snapshot_input;;
		misconfiglinks) gen_misconfiglinks_punchlist -X $snapshot_input;;
		misconnlinks) gen_misconnlinks_punchlist -X $snapshot_input;;
		verifylinks) [ "$TOPOLOGY_FILE" != "" ] && gen_verifylinks_punchlist -X $snapshot_input $topt;;
		verifyextlinks) [ "$TOPOLOGY_FILE" != "" ] && gen_verifyextlinks_punchlist -X $snapshot_input $topt;;
		verifyfis) [ "$TOPOLOGY_FILE" != "" ] && gen_verifyfis_punchlist -X $snapshot_input $topt;;
		verifysws) [ "$TOPOLOGY_FILE" != "" ] && gen_verifysws_punchlist -X $snapshot_input $topt;;
		verifyrtrs) [ "$TOPOLOGY_FILE" != "" ] && gen_verifyrtrs_punchlist -X $snapshot_input $topt;;
		verifysms) [ "$TOPOLOGY_FILE" != "" ] && gen_verifysms_punchlist -X $snapshot_input $topt;;
		*) continue;;	# should not happen
		esac
	done

	if [ $read_snapshot = y ]
	then
		break
	fi

	if [ $ports -gt 1 ]
	then
		echo "-------------------------------------------------------------------------------"
	fi

done

rm -f $tempfile
exit $status
