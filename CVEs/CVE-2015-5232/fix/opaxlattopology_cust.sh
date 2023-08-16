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

# Topology script to translate one or more custom (customer) topology CSV
# files for a (single) fabric to a standard-format topology.csv file for
# input to opaxlattopology.sh.

# Primary input topology CSV format:
#  Line 1     header data (ignored)
#  Line 2     blank
#  Line 3     fabric value pairs
#  Line 4     header data (ignored)
#  Lines 5-N  topology CSV

# Primary input Links Fields:
#  Source Name:    01
#  Dest Name:      02
#  Cable Length:   03
#  Cable Details:  04

# Secondary input topology CSV format:
#  Lines 1-N  topology CSV

# Primary input Links Fields:
#  Source Name:    01
#  Dest Name:      02
#  Cable Length:   03
#  Cable Details:  04

# Output topology CSV format:
#  Line 1         header line 1 ("Standard-Format Topology Spread Sheet")
#  Line 2         header line 2 ("Source   Destination   Cable")
#  Line 3         header line 3 ("Rack Group   Rack   Name   Name-2 ...")
#  Lines 4-N      topology CSV
#  Line N+1       blank
#  Line N+2       core specification

# Output Links Fields:
#  Source Rack Group: 01
#  Source Rack:       02
#  Source Name:       03
#  Source Name-2:     04
#  Source Port:       05
#  Source Type:       06
#  Dest Rack Group:   07
#  Dest Rack:         08
#  Dest Name:         09
#  Dest Name-2:       10
#  Dest Port:         11
#  Dest Type:         12
#  Cable Label:       13
#  Cable Length:      14
#  Cable Details:     15

# Output Core specifications (same line):
#  Core Name:X
#  Core Size:X (288 or 1152)
#  Core Group:X
#  Core Rack:X
#  Core Full:X (0 or 1)


## Defines:
PROGRAM_NAME="opaxlattopology_cust"
HOST_NAME_BASE="host"
EDGE_NAME_BASE="opasw"
CORE_NAME_BASE="opacore"
FILE_RESERVE="file_reserve"
FILE_TEMP="file_temp"
FILE_TEMP2="file_temp2"
FILE_DEBUG="file_debug"
FILE_DEBUG2="file_debug2"
OUTPUT_DETAIL=0
NODETYPE_HFI="FI"
NODETYPE_EDGE="SW"
NODETYPE_LEAF="CL"
NODETYPE_SPINE="CS"
DUMMY_CORE_NAME="ZzNAMEqQ"
CORE_GROUP="Core Group:"
CORE_RACK="Core Rack:"
CORE_NAME="Core Name:"
CORE_SIZE="Core Size:"
CORE_FULL="Core Full:"
HFI_SUFFIX="HFI-1"


## Global variables:

# Parsing tokens:
t_00=""
t_01=""
t_02=""
t_03=""
t_filler=""

t_srcname=""
t_dstname=""
t_cablelength=""
t_cabledetails=""

# Output CSV values:
srcgroup=""
srcrack=""
srcname=""
srcname2=""
srcport=""
srctype=""
dstgroup=""
dstrack=""
dstname=""
dstname2=""
dstport=""
dsttype=""
cablelabel=""
cablelength=""
cabledetails=""
core_group=""
core_rack=""
core_name="$CORE_NAME_BASE"
core_size=""
core_full=0

# Operating variables:
cts_parse=0
ix=0
n_verbose=2
indent=""
cat_char=" "
fl_clean=1
max_coreswitch=0
file_topology_in=""
file_topology_in2=""
file_topology_out=""
tb_corename[0]=""

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

function clean_tempfiles() {
	if [ $fl_clean == 1 ]
	then
		rm -f $FILE_TEMP $FILE_TEMP2
	fi
}

trap 'clean_tempfiles; exit 1' SIGINT SIGHUP SIGTERM
trap 'clean_tempfiles' EXIT

# Output usage information
usage_full()
{
	echo "Usage: $PROGRAM_NAME -t topology_prime [-s topology_second] -T topology_out"
	echo "                     [-v level] [-i level] [-c char] [-K] [-?]"
	echo "  -t topology_prime  - primary topology CSV input file"
	echo "  -s topology_second - secondary topology CSV input file"
	echo "  -T topology_out    - topology CSV output file"
	echo "  -v level  -  verbose level (0-8, default 2)"
	echo "               0 - no output"
	echo "               1 - progress output"
	echo "               2 - reserved"
	echo "               4 - time stamps"
	echo "               8 - reserved"
	echo "  -i level  -  screen output indent level (0-15, default 0)"
	echo "  -c char   -  concatenation char (default SPACE)"
	echo "  -K        -  DO NOT clean temporary files"
	echo "  -?        -  print this output"
	exit $1
}	# End of usage_full()

# Convert node name from custom encoded forms to standard CSV.  The following
# conversions are performed:
#   CnLxxxpM  -> <CORE_NAME_BASE>N,Lxxx,M,<NODETYPE_LEAF>
#   opaNpM     -> <EDGE_NAME_BASE>N,,M,<NODETYPE_EDGE>
#   abc[-]N   -> abc[-]N,,1,<NODETYPE_HFI>

# Inputs:
#   $1 - encoded node name
#
# Outputs:
#   Converted node name in CSV string
cvt_nodename()
{
	local val

	val=`echo $1 | sed -r \
		-e "s/^[cC]([0-9]+)[lL]([0-9]+)[pP]([0-9]+)/$CORE_NAME_BASE;\1,L\2,\3,$NODETYPE_LEAF/" \
		-e "s/^opa([0-9]+)[pP]([0-9]+)/$EDGE_NAME_BASE;\1,,\2,$NODETYPE_EDGE/" \
		-e "s/^([a-zA-Z-]+)([0-9]+)/\1;\2,,1,$NODETYPE_HFI/"`
	echo "$val" | sed -e 's/;//g'

}	# End of cvt_nodename

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
		echo "$indent$1"
		if [ $n_verbose -ge 4 ]
			then
			echo "$indent  "`date`
		fi
	fi
}	# End of display_progress()

# Process core switch
# Save core switch name in tb_corename, indexed by switch number

# Inputs:
#   $1 - core switch name
proc_coreswitch()
{
	local corenum

	corenum=`echo "$1" | sed -r -e "s/$CORE_NAME_BASE([0-9]+)/\1/"`
	tb_corename[$corenum]="$1"
	if [ $corenum -gt $max_coreswitch ]
		then
		max_coreswitch=$corenum
	fi

}	# End of proc_corename


## Main function:

# Get options
while getopts c:i:Ks:t:T:v:? option
do
	case $option in
	c)
		cat_char=$OPTARG
		;;

	i)
		indent=`echo "                    " | cut -b -$OPTARG`
		;;

	K)
		fl_clean=0
		;;

	s)
		file_topology_in2="$OPTARG"
		if [ ! -f $file_topology_in2 ]
			then
			echo "$PROGRAM_NAME: File $file_topology_in2 does not exist"
			Usage
		fi
		;;
	t)
		file_topology_in="$OPTARG"
		if [ ! -f $file_topology_in ]
			then
			echo "$PROGRAM_NAME: File $file_topology_in does not exist"
			Usage
		fi
		;;
	T)
		file_topology_out="$OPTARG"
		;;

	v)
		n_verbose=$OPTARG
		;;

	*)
		usage_full "0"
		;;
	esac
done

if [ -z "$file_topology_in" ]
	then
	echo "$PROGRAM_NAME: must specify primary topology input file"
	usage_full "1"
fi
if [ ! -f "$file_topology_in" ]
	then
	echo "$PROGRAM_NAME: $file_topology_in does not exist"
	usage_full "1"
fi
if [ -n "$file_topology_in2" ]
	then
	if [ ! -f "$file_topology_in2" ]
		then
		echo "$PROGRAM_NAME: $file_topology_in2 does not exist"
		usage_full "1"
	fi
fi
if [ -z "$file_topology_out" ]
	then
	echo "$PROGRAM_NAME: must specify topology output file"
	usage_full "1"
fi

# Parse topology input file(s)
display_progress "Reading $file_topology_in (and $file_topology_in2)"
if [ -z $file_topology_in2 ]
	then
	cat $file_topology_in > $FILE_TEMP
else
	cat $file_topology_in $file_topology_in2 > $FILE_TEMP
fi

# Output header lines
rm -f $file_topology_out
echo "Translated Customer Topology from:$file_topology_in (and $file_topology_in2),,,,,,,,,,,,,," >> ${file_topology_out}
echo "Source,,,,,,Destination,,,,,,Cable,," >> ${file_topology_out}
echo "Rack Group,Rack,Name,Name-2,Port,Type,Rack Group,Rack,Name,Name-2,Port,Type,Label,Length,Details" >> ${file_topology_out}

ix_line=1

IFS=","
while read t_00 t_01 t_02 t_03 t_filler
do
	case $cts_parse in
	# Process core switch data
	0)
		if echo "$t_00" | grep -e "Core Switch" > /dev/null 2>&1
			then
			core_size=`echo $t_00 | cut -d ':' -f 2 | sed -r -e 's/^([0-9]+)[fF]*/\1/'`
			if [ $core_size -ne 288 -a $core_size -ne 1152 ]
				then
				echo "$PROGRAM_NAME: Invalid Core Switch parameter ($t_00)"
				usage_full "2"
			fi
			if echo "$t_00" | cut -d ':' -f 2 | grep -i -e '[fF]' > /dev/null 2>&1
				then
				core_full=1
			fi

			cts_parse=1
		fi
		;;

	# Process link header line
	1)
		if echo "$t_00" | grep -e "Source" > /dev/null 2>&1
			then
			cts_parse=2
		fi
		;;

	# Process link lines
	2)
		if [ -n "$t_00" ]
			then
			t_srcname=$t_00
			t_dstname=$t_01
			t_cablelength=$t_02
			t_cabledetails=$t_03

			nodename=`cvt_nodename "$t_srcname"`
			srcgroup=""
			srcrack=""
			srcname=`echo "$nodename" | cut -d ',' -f 1`
			srcname2=`echo "$nodename" | cut -d ',' -f 2`
			srcport=`echo "$nodename" | cut -d ',' -f 3`
			srctype=`echo "$nodename" | cut -d ',' -f 4`
			if [ "$srctype" == "$NODETYPE_LEAF" ]
				then
				proc_coreswitch "$srcname"
			fi

			nodename=`cvt_nodename "$t_dstname"`
			dstgroup=""
			dstrack=""
			dstname=`echo "$nodename" | cut -d ',' -f 1`
			dstname2=`echo "$nodename" | cut -d ',' -f 2`
			dstport=`echo "$nodename" | cut -d ',' -f 3`
			dsttype=`echo "$nodename" | cut -d ',' -f 4`
			if [ "$dsttype" == "$NODETYPE_LEAF" ]
				then
				proc_coreswitch "$dstname"
			fi

			cablelabel="$t_srcname$cat_char$t_dstname"
			cablelength="$t_cablelength"
			cabledetails="$t_cabledetails"

			# Output link line
			echo "${srcgroup},${srcrack},${srcname},${srcname2},${srcport},${srctype},${dstgroup},${dstrack},${dstname},${dstname2},${dstport},${dsttype},${cablelabel},${cablelength},${cabledetails}" >> ${file_topology_out}
		else
			cts_parse=3
		fi
		;;

	esac	# end of case $cts_parse in

  ix_line=$((ix_line+1))

done < <( cat $FILE_TEMP | tr -d '\015' )	# End of while read ... do

# Generate core line
display_progress "Ending $file_topology_out with core line(s)"
echo ",,,,,,,,,,,,,," >> ${file_topology_out}
for (( ix=0 ; $ix <= $max_coreswitch ; ix=$[ix+1] ))
do
	if [ -n "${tb_corename[$ix]}" ]
		then
		echo "${CORE_NAME}${tb_corename[$ix]},${CORE_GROUP}${core_group},${CORE_RACK}${core_rack},${CORE_SIZE}${core_size},${CORE_FULL}${core_full},,,,,,,,,," >> ${file_topology_out}
	fi
done	# End of for (( ix=0 ; $ix <= $max_coreswitch ; ix=$[ix+1] ))
echo ",,,,,,,,,,,,,," >> ${file_topology_out}

# Clean temporary files
clean_tempfiles

display_progress "Done"
exit 0

