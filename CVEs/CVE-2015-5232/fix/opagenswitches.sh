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

# query the SM via opareport and generate a switches file listing all the
# Externally Managed SilverStorm switches found in the fabric.

# Enhancements: optionally, do not generate an switches file, but use an existing
# switches file; optionally, update NodeDesc values in an switches file using
# NodeDesc values found in a specified topology.xml file and fabric link
# information obtained via opareport -o links (live fabric or snapshot).


# optional override of defaults
if [ -f /etc/sysconfig/opa/opafastfabric.conf ]
then
	. /etc/sysconfig/opa/opafastfabric.conf
fi

. /opt/opa/tools/opafastfabric.conf.def

TOOLSDIR=${TOOLSDIR:-/opt/opa/tools}
BINDIR=${BINDIR:-/usr/sbin}

. $TOOLSDIR/ff_funcs

## Defines:
OPAEXPAND_FILE="$BINDIR/opaexpandfile"
OPA_REPORT="$BINDIR/opareport"
OPASAQUERY="$BINDIR/opasaquery"
XML_EXTRACT="$BINDIR/opaxmlextract"
GEN_OPASWITCHES_HELPER="$TOOLSDIR/opagenswitcheshelper"
FILE_OPASWITCHES="file_switches"
FILE_OPASWITCHES2="file_switches2"
FILE_LINKSUM_LEAF_EDGE="linksum_leaf_edge.csv"
FILE_LINKSUM_EDGE_HFI="linksum_edge_hfi.csv"
FILE_LINKS_LEAF_EDGE="links_leaf_edge.csv"
FILE_LINKS_LEAF_EDGE2="links_leaf_edge2.csv"
FILE_LINKS_EDGE_HFI="links_edge_hfi.csv"
FILE_LINKS_EDGE_HFI2="links_edge_hfi2.csv"
FILE_RESERVE="file_reserve"
FILE_TEMP="file_temp"
FILE_TEMP2="file_temp2"
FILE_DEBUG="file_debug"
FILE_DEBUG2="file_debug2"


## Global variables:

# Operating variables:
n_verbose=0
fl_clean=1
fl_gen_switches=1
fl_gen_linksum=0
fl_write_switches=0
file_switches="$FILE_OPASWITCHES"
file_output=""
file_topology=""
file_snapshot=""
nodeguid1=
portnum1=
nodetype1=
nodedesc1=
nodesuffix1=
nodeguid1b=
portnum1b=
nodetype1b=
nodedesc1b=
nodesuffix1b=
nodeguid2=
portnum2=
nodetype2=
nodedesc2=
nodesuffix2=
nodeguid2b=
portnum2b=
nodetype2b=
nodedesc2b=
nodesuffix2b=
nodedesc_last=
line=
line1=
line2=
n_hfis=0
hfis=
n_edges=0
n_edges2=0
n_edges2b=0
edges=
n_edges_unique=0
edges_unique=
n_leaves=0
n_leaves2=0
n_leaves2b=0
leaves=
n_leaves_unique=0
leaves_unique=

# Debug variables:
debug_0=
debug_1=
debug_2=
debug_3=
debug_4=
debug_5=
debug_6=
debug_7=
#echo "DEBUG-x.y: 0:$debug_0: 1:$debug_1: 2:$debug_2: 3:$debug_3: 4:$debug_4: 5:$debug_5: 6:$debug_6: 7:$debug_7:"


## Local functions:
functout=

# Clean temporary files
clean_files()
{
	if [ $fl_clean == 1 ]
		then
		rm -f $FILE_TEMP
		rm -f $FILE_TEMP2
		rm -f $FILE_OPASWITCHES
		rm -f $FILE_OPASWITCHES2
		rm -f $FILE_LINKSUM_LEAF_EDGE
		rm -f $FILE_LINKSUM_EDGE_HFI
		rm -f $FILE_LINKS_LEAF_EDGE
		rm -f $FILE_LINKS_LEAF_EDGE2
		rm -f $FILE_LINKS_EDGE_HFI
		rm -f $FILE_LINKS_EDGE_HFI2
	fi
}	# End of clean_files()

trap 'clean_files; exit 1' SIGINT SIGHUP SIGTERM 
trap clean_files EXIT

Usage_full()
{
	echo "Usage: opagenswitches [-t portsfile] [-p ports] [-R]" >&2
	echo "         [-L switches_file] [-o output_file] [-T topology_file] [-X snapshot_file]" >&2
	echo "         [-s] [-v level] [-K]" >&2
	echo "              or" >&2
	echo "       opagenswitches --help" >&2
	echo "   --help - produce full help text" >&2
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
	echo "   -R - do not attempt to get routes for computation of distance" >&2
	echo "   -s - update/resolve switches switch names using topology XML data" >&2
	echo "   -L switches_file - use switches_file as switches input" >&2
	echo "                     (do not generate switches data; must also use -s)" >&2
	echo "   -o output_file - write switches data to output_file (default is stdout)" >&2
	echo "   -T topology_file - use topology_file as topology XML" >&2
	echo "                      (may contain '%P'; must also use -s)" >&2
	echo "   -X snapshot_file - use snapshot_file XML for fabric link information" >&2
	echo "                      (may contain '%P'; must also use -s)" >&2
	echo "   -v level - verbose level (0-8, default 0)" >&2
	echo "              0 - no output" >&2
	echo "              1 - progress output" >&2
	echo "              2 - reserved" >&2
	echo "              4 - time stamps" >&2
	echo "              8 - reserved" >&2
	echo "   -K - do not clean temporary files" >&2
	echo " Environment:" >&2
	echo "   PORTS - list of ports, used in absence of -t and -p" >&2
	echo "   PORTS_FILE - file containing list of ports, used in absence of -t and -p" >&2
	echo "   FF_TOPOLOGY_FILE - file containing topology XML data, used in absence of -T" >&2
	echo "for example:" >&2
	echo "   opagenswitches" >&2
	echo "   opagenswitches -p '1:1 1:2 2:1 2:2'" >&2
	echo "   opagenswitches -o switches" >&2
	echo "   opagenswitches -s -o switches" >&2
	echo "   opagenswitches -L switches -s -o switches" >&2
	echo "   opagenswitches -s -T topology.%P.xml" >&2
	echo "   opagenswitches -L switches -s -T topology.%P.xml -X snapshot.%P.xml" >&2
	exit 0
}	# End of Usage_full()

Usage()
{
	echo "Usage: opagenswitches [-R] [-s] [-T topology_file]" >&2
	echo "              or" >&2
	echo "       opagenswitches --help" >&2
	echo "   -R - do not attempt to get routes for computation of distance" >&2
	echo "   -s - update/resolve switches switch names using topology XML data" >&2
	echo "   -T topology_file - use topology_file as topology XML" >&2
	echo "   --help - produce full help text" >&2
	echo "for example:" >&2
	echo "   opagenswitches" >&2
	echo "   opagenswitches -T topology.0:0.xml" >&2
	exit 2
}	# End of Usage()

# Display progress information (to STDOUT)
# Inputs:
#   $1 - progress string
#
# Outputs:
#   none
display_progress()
{
	if [ $n_verbose -ge 1 ]
		then
		echo "$1" >&2
		if [ $n_verbose -ge 4 ]
		then
			echo "  "`date +"%F %T (%N nSec)"` >&2
		fi
	fi
}	# End of display_progress()

gen_switches()
{
	local suffix

	# $1 = hfi
	# $2 = port
	if [ "$port" -eq 0 ]
	then
		port_opts="-h $hfi"	# default port to 1st active
	else
		port_opts="-h $hfi -p $port"
	fi

	eval $OPASAQUERY $port_opts -o desc >/dev/null 2>&1
	if [ $? -ne 0 ]
		then
		echo "opagenswitches: Error: Fabric Nodes Not Available at hfi:$hfi port:$port" >&2
		return
	fi
	suffix=":$hfi:$port"
	export IFS=';'
	rm -f $FILE_TEMP
	eval $OPA_REPORT $port_opts -q -o comps -x -F nodetype:SW -d 3| $XML_EXTRACT -H -d \; -e Node.NodeGUID -e Node.SystemImageGUID -e Node.Capability -e Node.NodeDesc -e Node.PortInfo.GUID -s Focus > $FILE_TEMP
	if [ $? -eq 0 ]
		then
		fl_write_switches=1
		cat $FILE_TEMP | while read nodeguid systemguid capability nodedesc portguid
		do
			if echo "$capability"|grep E0 >/dev/null 2>&1
			then
				continue	# EM switches don't have E0 capability
			fi
			[ "$nodeguid" != "$systemguid" ] && continue # EM switches have 1 guid

			distance=
			comma=
			if [ "$get_distance" = y ]
			then
				distance=$(eval opasaquery $port_opts -o trace -g $portguid|grep GID|wc -l)
				if [ ! -z "$distance" ]
				then
					comma=","
				fi
			fi

			# valid names for switches start with a non-numeric and are alpha numeric
			if echo "$nodedesc"|grep '^[a-zA-Z_][a-zA-Z_0-9]*$' >/dev/null 2>&1
			then
				echo "$nodeguid$suffix,$nodedesc$comma$distance" >> $file_switches
			else
				echo "# $nodedesc" >> $file_switches
				echo "$nodeguid$suffix$comma$comma$distance" >> $file_switches
			fi
		done	# End of while read nodeguid systemguid capability nodedesc portguid
	else
		echo "opagenswitches: Error: Unable to Query Fabric Nodes at hfi:$hfi port:$port" >&2
	fi

}	# End of gen_switches()

# Resolve name of switches file
# Inputs:
#   $1 - command name
#   OPASWITCHES_FILE - switches file name to process
#
# Outputs:
#   OPASWITCHES_FILE - resolved switches file name
resolve_switches_file()
{
	if [ "$OPASWITCHES_FILE" = "" ]
	then
		OPASWITCHES_FILE=$CONFIG_DIR/opa/switches
	fi
	OPASWITCHES_FILE=`resolve_file "$1" "$OPASWITCHES_FILE"`
	if [ "$OPASWITCHES_FILE" = "" ]
	then
		Usage
	fi

}	# End of resolve_switches_file()

# Resolve name of snapshot file, including %P
# Inputs:
#   $1 - command name
#   $2 - hfi:port fabric selector (0:0, 1:2, etc)
#   FF_SNAPSHOT_FILE - snapshot file name to process
#
# Outputs:
#   SNAPSHOT_FILE - resolved snapshot file name
resolve_snapshot_file()
{
	if [ "$FF_SNAPSHOT_FILE" = "" -o "$FF_SNAPSHOT_FILE" = "NONE" ]
	then
		SNAPSHOT_FILE=""
		# snapshot check disabled
		return
	fi
	# Expand %P marker
	file=$(echo "$FF_SNAPSHOT_FILE"|sed -e "s/%P/$2/g")
	# allow case where FF_SNAPSHOT_FILE is not found (ignore stderr)
	SNAPSHOT_FILE=`resolve_file "$1" "$file" 2>/dev/null`

}	# End of resolve_snapshot_file()


## Main function:
rm -f $FILE_OPASWITCHES
rm -f $FILE_OPASWITCHES2
rm -f $FILE_DEBUG
rm -f $FILE_DEBUG2

if [ x"$1" = "x--help" ]
then
	Usage_full
fi

get_distance=y
while getopts KL:o:p:Rst:T:v:X:? param
do
	case $param in
	K)	fl_clean=0;;
	L)	fl_gen_switches=0
		fl_write_switches=1
		file_switches="$OPTARG"
		if [ ! -f "$file_switches" ]
			then
			echo "opagenswitches: Error: switches file $file_switches does not exist" >&2
			Usage
		fi
		;;
	o)	file_output="$OPTARG";;
	p)	export PORTS="$OPTARG";;
	R)	get_distance=n;;
	s)  fl_gen_linksum=1;;
	t)	export PORTS_FILE="$OPTARG";;
	T)  file_topology="$OPTARG";;
	v)	n_verbose=$OPTARG;;
	X)  file_snapshot="$OPTARG";;
	?)	Usage;;
	esac
done

shift $((OPTIND -1))
if [ $# -ge 1 ]
then
	Usage
fi

check_ports_args opagenswitches
if [ $fl_gen_switches == 0 -o "x$file_topology" != "x" -o "x$file_snapshot" != "x" ]
	then
	if [ $fl_gen_linksum == 0 ]
		then
		echo "opagenswitches: Error: -L, -T and -X must also use -s" >&2
		Usage
	fi
fi

# Generate file_switches
if [ $fl_gen_switches == 1 ]
	then
	echo -n "" > $file_switches

	IFS=$' \t\n'
	for hfi_port in $PORTS
	do
		hfi=$(expr $hfi_port : '\([0-9]*\):[0-9]*')
		port=$(expr $hfi_port : '[0-9]*:\([0-9]*\)')
		$BINDIR/oparesolvehfiport $hfi $port >/dev/null
		if [ $? -ne 0 -o "$hfi" = "" -o "$port" = "" ]
		then
			echo "opagenswitches: Error: Invalid port specification: $hfi_port" >&2
			continue
		fi

		display_progress "Generating switches hfi:$hfi port:$port"
		gen_switches "$hfi" "$port"
	done
else
	OPASWITCHES_FILE=$file_switches
	resolve_switches_file "opagenswitches"
	cp -p $OPASWITCHES_FILE $FILE_OPASWITCHES

	# Check FILE_OPASWITCHES for include lines
	if cat $FILE_OPASWITCHES | grep include >/dev/null 2>&1
		then
		echo "opagenswitches: Warning: $OPASWITCHES_FILE contains non-processed include statement(s)" >&2
	fi
fi	# End of if [ $fl_gen_switches == 1 ]

# Process $file_topology
if [ $fl_write_switches == 1 -a $fl_gen_linksum == 1 ]
	then
	IFS=$' \t\n'
	for hfi_port in $PORTS
	do
		hfi=$(expr $hfi_port : '\([0-9]*\):[0-9]*')
		port=$(expr $hfi_port : '[0-9]*:\([0-9]*\)')
		$BINDIR/oparesolvehfiport $hfi $port >/dev/null
		if [ $? -ne 0 -o "$hfi" = "" -o "$port" = "" ]
			then
			echo "opagenswitches: Error: Invalid port specification: $hfi_port" >&2
			continue
		fi

		if [ -n "$file_topology" ]
			then
			FF_TOPOLOGY_FILE=$file_topology
		fi
		resolve_topology_file "opagenswitches" "$hfi:$port"
		if [ -f "$TOPOLOGY_FILE" ]
			then
			IFS=";"

			# Generate FILE_LINKSUM(s) from TOPOLOGY_FILE
			display_progress "Reading $TOPOLOGY_FILE and Generating FILE_LINKSUM Components"
			rm -f $FILE_LINKSUM_LEAF_EDGE
			rm -f $FILE_LINKSUM_EDGE_HFI
			ix=0
			rm -f $FILE_TEMP
			cat $TOPOLOGY_FILE | $XML_EXTRACT -H -d \; -e PortNum -e NodeType -e Port.NodeDesc > $FILE_TEMP
			$GEN_OPASWITCHES_HELPER proc_linksum $FILE_TEMP $FILE_LINKSUM_EDGE_HFI $FILE_LINKSUM_LEAF_EDGE

			if [ -a $FILE_LINKSUM_LEAF_EDGE ]
				then
				display_progress "Processing $FILE_LINKSUM_LEAF_EDGE"
				rm -f $FILE_TEMP
				mv $FILE_LINKSUM_LEAF_EDGE $FILE_TEMP
				cat $FILE_TEMP | sort -t \; -k3,3 -k1g,1 -k4g,4 > $FILE_LINKSUM_LEAF_EDGE
			fi

			if [ -a $FILE_LINKSUM_EDGE_HFI ]
				then
				display_progress "Processing $FILE_LINKSUM_EDGE_HFI"
				rm -f $FILE_TEMP
				mv $FILE_LINKSUM_EDGE_HFI $FILE_TEMP
				cat $FILE_TEMP | sort -t \; -k3,3 -k1g,1 -k4g,4 > $FILE_LINKSUM_EDGE_HFI
			fi

			# Generate GUID/NodeDesc links files
			display_progress "Generating links report and FILE_LINKS Components"
			rm -f $FILE_LINKS_LEAF_EDGE
			rm -f $FILE_LINKS_EDGE_HFI
			ix=0

			if [ -z "$file_snapshot" ]
				then
				if [ "$port" -eq 0 ]
					then
					port_opts="-h $hfi"	# default port to 1st active
				else
					port_opts="-h $hfi -p $port"
				fi
				eval $OPASAQUERY $port_opts -o desc >/dev/null 2>&1
				if [ $? -ne 0 ]
					then
					echo "opagenswitches: Error: Fabric Links Not Available at hfi:$hfi port:$port" >&2
					continue
				fi
			else
				FF_SNAPSHOT_FILE=$file_snapshot
				resolve_snapshot_file "opagenswitches" "$hfi:$port"
				if [ ! -f "$SNAPSHOT_FILE" ]
					then
					echo "opagenswitches: Error: snapshot file $SNAPSHOT_FILE does not exist" >&2
					continue
				fi
				port_opts="-X $SNAPSHOT_FILE"
			fi

			rm -f $FILE_TEMP
			eval $OPA_REPORT $port_opts -q -x -o links | $XML_EXTRACT -H -d \; -e NodeGUID -e PortNum -e NodeType -e NodeDesc > $FILE_TEMP
			if [ $? -eq 0 ]
				then
				$GEN_OPASWITCHES_HELPER proc_links $FILE_TEMP $FILE_LINKS_EDGE_HFI $FILE_LINKS_LEAF_EDGE

				if [ -a $FILE_LINKS_LEAF_EDGE ]
					then
					display_progress "Processing $FILE_LINKS_LEAF_EDGE"
					rm -f $FILE_TEMP
					mv $FILE_LINKS_LEAF_EDGE $FILE_TEMP
					cat $FILE_TEMP | sort -t \; -k4,4 -k2g,2 -k5g,5 > $FILE_LINKS_LEAF_EDGE
				fi	# End of if [ -a $FILE_LINKS_LEAF_EDGE ]

				if [ -a $FILE_LINKS_EDGE_HFI ]
					then
					display_progress "Processing $FILE_LINKS_EDGE_HFI"
					rm -f $FILE_TEMP
					mv $FILE_LINKS_EDGE_HFI $FILE_TEMP
					cat $FILE_TEMP | sort -t \; -k4,4 -k2g,2 -k5g,5 > $FILE_LINKS_EDGE_HFI
				fi	# End of if [ -a $FILE_LINKS_EDGE_HFI ]

				# Process NodeDesc in links files
				rm -f $FILE_LINKS_LEAF_EDGE2
				rm -f $FILE_LINKS_EDGE_HFI2

				if [ -a $FILE_LINKS_LEAF_EDGE ]
					then
					cp -p $FILE_LINKS_LEAF_EDGE $FILE_LINKS_LEAF_EDGE2
				fi
				if [ -a $FILE_LINKS_EDGE_HFI ]
					then
					cp -p $FILE_LINKS_EDGE_HFI $FILE_LINKS_EDGE_HFI2
				fi

				# Process edge NodeDesc
				if [ -a $FILE_LINKS_EDGE_HFI2 ]
					then
					display_progress "Processing edge NodeDesc"

					ix_line=1
					nodedesc_last="ZzQQQzZ"

					while read portnum1 nodetype1 nodedesc1 portnum1b nodetype1b nodedesc1b
					do
						if [ "$nodedesc1" != "$nodedesc_last" ]
							then
							n_hfis=`cat $FILE_LINKSUM_EDGE_HFI | grep "$nodedesc1;" | wc -l`
							hfis=`cat $FILE_LINKSUM_EDGE_HFI | grep "$nodedesc1;" | cut -d \; -f6 | tr '\012' '|' | sed -e 's/|/$|/g' -e 's/|$//'`
							n_edges=`cat $FILE_LINKS_EDGE_HFI2 | grep -E "$hfis" | wc -l`
							n_edges_unique=`cat $FILE_LINKS_EDGE_HFI2 | grep -E "$hfis" | cut -d \; -f4 | sort -u | wc -l`
# ToDo: may be able to eliminate $edges
							edges=`cat $FILE_LINKS_EDGE_HFI2 | grep -E "$hfis" | cut -d \; -f4`
							edges_unique=`cat $FILE_LINKS_EDGE_HFI2 | grep -E "$hfis" | cut -d \; -f4 | sort -u`
							if [ $n_edges_unique == 1 ]
								then
								nodedesc2=$(expr "$edges_unique" : '\([0-9a-zA-Z =_-]*\)')
								if [ "$nodedesc2" != "$nodedesc1" ]
									then
									rm -f $FILE_TEMP
									mv $FILE_LINKS_EDGE_HFI2 $FILE_TEMP
									cat $FILE_TEMP | sed -e "s/$nodedesc2;/$nodedesc1;/" > $FILE_LINKS_EDGE_HFI2
									if [ -a $FILE_LINKS_LEAF_EDGE2 ]
										then
										rm -f $FILE_TEMP
										mv $FILE_LINKS_LEAF_EDGE2 $FILE_TEMP
										cat $FILE_TEMP | sed -e "s/$nodedesc2$/$nodedesc1/" > $FILE_LINKS_LEAF_EDGE2
									fi
								fi
								nodedesc_last=$nodedesc1
							elif [ $n_edges_unique == 2 ]
								then
								nodedesc2=`echo $edges_unique | head -n1`
								nodedesc2b=`echo $edges_unique  | tail -n1`
								n_edges2=`echo $edges | grep -c "$nodedesc2"`
								n_edges2b=`echo $edges | grep -c "$nodedesc2b"`
								if [ $n_edges2 -gt $n_edges2b ]
									then
									if [ "$nodedesc2" != "$nodedesc1" ]
										then
										rm -f $FILE_TEMP
										mv $FILE_LINKS_EDGE_HFI2 $FILE_TEMP
										cat $FILE_TEMP | sed -e "s/$nodedesc2;/$nodedesc1;/" > $FILE_LINKS_EDGE_HFI2
										if [ -a $FILE_LINKS_LEAF_EDGE2 ]
											then
											rm -f $FILE_TEMP
											mv $FILE_LINKS_LEAF_EDGE2 $FILE_TEMP
											cat $FILE_TEMP | sed -e "s/$nodedesc2$/$nodedesc1/" > $FILE_LINKS_LEAF_EDGE2
										fi
									fi
								elif [ $n_edges2b -gt $n_edges2 ]
									then
									if [ "$nodedesc2b" != "$nodedesc1" ]
										then
										rm -f $FILE_TEMP
										mv $FILE_LINKS_EDGE_HFI2 $FILE_TEMP
										cat $FILE_TEMP | sed -e "s/$nodedesc2b;/$nodedesc1;/" > $FILE_LINKS_EDGE_HFI2
										if [ -a $FILE_LINKS_LEAF_EDGE2 ]
											then
											rm -f $FILE_TEMP
											mv $FILE_LINKS_LEAF_EDGE2 $FILE_TEMP
											cat $FILE_TEMP | sed -e "s/$nodedesc2b$/$nodedesc1/" > $FILE_LINKS_LEAF_EDGE2
										fi
									fi
								fi
								nodedesc_last=$nodedesc1
							elif [ $n_edges_unique -ge 3 ]
								then
# ToDo: add processing for 3 edges
								filler=1
							elif [ $n_edges_unique == 0 ]
								then
# ToDo: consider processing here
							  filler=1
							fi	# End of if [ $n_edges_unique == 1 ]

						fi	# End of if [ "$nodedesc1" != "$nodedesc_last" ]

						ix_line=$((ix_line+1))

					done < <( cat $FILE_LINKSUM_EDGE_HFI )	# End of while read ... do

				fi	# End of if [ -a $FILE_LINKS_EDGE_HFI2 ]

				# Process leaf NodeDesc
				if [ -a $FILE_LINKS_LEAF_EDGE2 ]
					then
					display_progress "Processing leaf NodeDesc"

					ix_line=1
					nodedesc_last="ZzQQQzZ"

					while read portnum1 nodetype1 nodedesc1 portnum1b nodetype1b nodedesc1b
					do
						if [ "$nodedesc1" != "$nodedesc_last" ]
							then
							if [ -a $FILE_LINKSUM_LEAF_EDGE ]
								then
								n_edges=`cat $FILE_LINKSUM_LEAF_EDGE | grep "$nodedesc1;" | wc -l`
								edges=`cat $FILE_LINKSUM_LEAF_EDGE | grep "$nodedesc1;" | cut -d \; -f4-6 | tr '\012' '|' | sed -e 's/|/$|/g' -e 's/|$//'`
							else
								n_edges=0
								edges="ZzQQQzZ"
							fi
							n_leaves=`cat $FILE_LINKS_LEAF_EDGE2 | grep -E "$edges" | wc -l`
							n_leaves_unique=`cat $FILE_LINKS_LEAF_EDGE2 | grep -E "$edges" | cut -d \; -f4 | sort -u | wc -l`
# ToDo: may be able to eliminate $leaves
							leaves=`cat $FILE_LINKS_LEAF_EDGE2 | grep -E "$edges" | cut -d \; -f4`
							leaves_unique=`cat $FILE_LINKS_LEAF_EDGE2 | grep -E "$edges" | cut -d \; -f4 | sort -u`
							if [ $n_leaves_unique == 1 ]
								then
								nodedesc2=$(expr "$leaves_unique" : '\([0-9a-zA-Z =_-]*\)')
								if [ "$nodedesc2" != "$nodedesc1" ]
									then
									rm -f $FILE_TEMP
									mv $FILE_LINKS_LEAF_EDGE2 $FILE_TEMP
									cat $FILE_TEMP | sed -e "s/$nodedesc2;/$nodedesc1;/" > $FILE_LINKS_LEAF_EDGE2
								fi
								nodedesc_last=$nodedesc1
							elif [ $n_leaves_unique == 2 ]
								then
								nodedesc2=`echo $leaves_unique | head -n1`
								nodedesc2b=`echo $leaves_unique  | tail -n1`
								n_leaves2=`echo $leaves | grep -c "$nodedesc2"`
								n_leaves2b=`echo $leaves | grep -c "$nodedesc2b"`
								if [ $n_leaves2 -gt $n_leaves2b ]
									then
									if [ "$nodedesc2" != "$nodedesc1" ]
										then
										rm -f $FILE_TEMP
										mv $FILE_LINKS_LEAF_EDGE2 $FILE_TEMP
										cat $FILE_TEMP | sed -e "s/$nodedesc2;/$nodedesc1;/" > $FILE_LINKS_LEAF_EDGE2
									fi
								elif [ $n_leaves2b -gt $n_leaves2 ]
									then
									if [ "$nodedesc2b" != "$nodedesc1" ]
										then
										rm -f $FILE_TEMP
										mv $FILE_LINKS_LEAF_EDGE2 $FILE_TEMP
										cat $FILE_TEMP | sed -e "s/$nodedesc2b;/$nodedesc1;/" > $FILE_LINKS_LEAF_EDGE2
									fi
								fi
								nodedesc_last=$nodedesc1
							elif [ $n_leaves_unique -ge 3 ]
								then
# ToDo: add processing for 3 leaves
								filler=1
							elif [ $n_leaves_unique == 0 ]
								then
# ToDo: consider processing here
								filler=1
							fi	# End of if [ $n_leaves_unique == 1 ]

						fi	# End of if [ "$nodedesc1" != "$nodedesc_last" ]

						ix_line=$((ix_line+1))

					done  < <( cat $FILE_LINKSUM_LEAF_EDGE )	# End of while read ... do

				fi	# End of if [ -a $FILE_LINKS_LEAF_EDGE2 ]

				# Process file_switches
				display_progress "Processing $file_switches"

				rm -f $FILE_TEMP
				rm -f $FILE_TEMP2
				if [ -a $FILE_LINKS_LEAF_EDGE2 ]
					then
					cat $FILE_LINKS_LEAF_EDGE2 | cut  -d \; -f1,4 | sort -u >> $FILE_TEMP
					cat $FILE_LINKS_LEAF_EDGE2 | cut  -d \; -f5,8 | sort -u >> $FILE_TEMP
				fi
				if [ -a $FILE_LINKS_EDGE_HFI2 ]
					then
					cat $FILE_LINKS_EDGE_HFI2 | cut  -d \; -f1,4 | sort -u >> $FILE_TEMP
				fi
				if [ -a $FILE_TEMP ]
					then
					cat $FILE_TEMP | grep -v -e ';[0-9a-zA-Z_]*[ =-]' | sed -r -e 's/([0-9a-zA-Z_]+);([0-9a-zA-Z_]+)/s\/\(\1:'"$hfi:$port"'\),[0-9a-zA-Z =_-]*\/\\1,\2\//' | sort -u > $FILE_TEMP2
					cat $FILE_OPASWITCHES | sed -r -f $FILE_TEMP2 > $FILE_OPASWITCHES2
					rm -f $FILE_OPASWITCHES
					mv $FILE_OPASWITCHES2 $FILE_OPASWITCHES
				fi

			fi	# End of if [ $? -eq 0 ]

		else
			echo "opagenswitches: Error: topology file $TOPOLOGY_FILE does not exist" >&2

		fi	# End of if [ -f "$TOPOLOGY_FILE" ]

	done	# End of for hfi_port in $PORTS

fi	# End of if [ $fl_write_switches == 1 -a $fl_gen_linksum == 1 ]

if [ $fl_write_switches == 1 ]
	then
	if [ -n "$file_output" ]
		then
		if [ -f $file_output ]
			then
			if [ `$OPAEXPAND_FILE $file_output | wc -l` -ne 0 ]
				then
				rm -f ${file_output}.bak
				mv $file_output ${file_output}.bak
			fi
		fi
		rm -f $file_output
		cp -p $FILE_OPASWITCHES $file_output
	else
		cat $FILE_OPASWITCHES
	fi
	clean_files
	display_progress "Done"
	exit 0
else
	clean_files
	display_progress "Done (error)"
	exit 1
fi

