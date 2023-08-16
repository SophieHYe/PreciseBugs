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

# [ICS VERSION STRING: @(#) ./fastfabric/opaxlattopology 10_0_0_0_135 [09/08/14 23:46]

# Topology script to translate the CSV form (topology.csv) of a
# standard-format topology spread sheet for a fabric to one or more topology
# XML files (topology.0:0.xml) at the specified levels (top-level, rack group,
# rack, edge switch).  This script operates from the fabric directory and
# populates it.

# The following topology directories and files are generated:
#   FILE_LINKSUM         - Host-to-Edge, Edge-to-Core, Host-to-Core links;
#                           includes cable data
#   FILE_LINKSUM_NOCORE  - No Edge-to-Core or Host-to-Core links;
#                           includes cable data
#   FILE_LINKSUM_NOCABLE - Leaf-to-Spine links, no cable data
#   FILE_NODEFIS         - Host FIs, includes NodeDetails
#   FILE_NODESWITCHES    - Edge, Leaf and Spine switches
#   FILE_NODECHASSIS     - Core switches
#   FILE_NODELEAVES      - Leaf switches
#   FILE_TOPOLOGY_OUT    - Topology: FILE_LINKSUM + FILE_LINKSUM_NOCABLE +
#                           FILE_NODEFIS + FILE_NODESWITCHES
#   FILE_HOSTS           - 'hosts' file
#   FILE_CHASSIS         - 'chassis' file

# User topology CSV format:
#  Lines 1 - x    ignored
#  Line y         header line 1 (ignored)
#  Line n         header line 2
#  Lines n+1 - m  topology CSV
#  Line m+1       blank
#  Lines m+2 - z  core specification(s)

# Links Fields:
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

# Core specifications (same line):
#  Core Name:X
#  Core Size:X (288 or 1152)
#  Core Group:X
#  Core Rack:X
#  Core Full:X (0 or 1)


## Defines:
TOOLSDIR=${TOOLSDIR:-/opt/opa/tools}
BINDIR=${BINDIR:-/usr/sbin}

XML_GENERATE="$BINDIR/opaxmlgenerate"
FILE_TOPOLOGY_LINKS="topology.csv"
FILE_LINKSUM_SWD06="linksum_swd06.csv"
FILE_LINKSUM_SWD24="linksum_swd24.csv"
FILE_LINKSUM="linksum.csv"
FILE_LINKSUM_NOCORE="linksum_nocore.csv"
FILE_LINKSUM_NOCABLE="linksum_nocable.csv"
FILE_NODEFIS="nodefis.csv"
FILE_NODESWITCHES="nodeswitches.csv"
FILE_NODELEAVES="nodeleaves.csv"
FILE_NODECHASSIS="nodechassis.csv"
FILE_CHASSIS="chassis"
FILE_HOSTS="hosts"
FILE_TOPOLOGY_OUT="topology.0:0.xml"
FILE_RESERVE="file_reserve"
FILE_TEMP="file_temp"
FILE_TEMP2="file_temp2"
FILE_DEBUG="file_debug"
FILE_DEBUG2="file_debug2"
# Note: there are no real limits on numbers of groups, racks or switches;
#  these defines simply allow error messages before too much thrashing
#  takes place in cases where FILE_TOPOLOGY_LINKS has bad data
MAX_GROUPS=21
MAX_RACKS=501
MAX_SWITCHES=20001
OUTPUT_SWITCHES=1
OUTPUT_RACKS=2
OUTPUT_GROUPS=4
OUTPUT_EDGE_LEAF_LINKS=8
OUTPUT_SPINE_LEAF_LINKS=16
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
CAT_CHAR_CORE=" "


## Global variables:

# Parsing tokens:
t_00=""
t_01=""
t_02=""
t_03=""
t_04=""
t_05=""
t_06=""
t_07=""
t_08=""
t_09=""
t_10=""
t_11=""
t_12=""
t_13=""
t_14=""

t_srcgroup=""
t_srcrack=""
t_srcname=""
t_srcname2=""
t_srcport=""
t_srctype=""
t_dstgroup=""
t_dstrack=""
t_dstname=""
t_dstname2=""
t_dstport=""
t_dsttype=""
t_cablelabel=""
t_cablelength=""
t_cabledetails=""

# Output CSV values:
rate=""
mtu=""
internal=""
nodedesc1=""
nodedetails1=""
nodetype1=""
nodedesc2=""
nodedetails2=""
nodetype2=""
link=""

# Operating variables:
cts_parse=0
ix=0
n_detail=0
fl_output_edge_leaf=1
fl_output_spine_leaf=1
n_verbose=2
indent=""
cat_char=" "
fl_clean=1
ix_srcgroup=0
ix_srcrack=0
ix_srcswitch=0
ix_dstgroup=0
ix_dstrack=0
ix_dstswitch=0
core_group=""
core_rack=""
core_name=""
core_size=
core_full=
rack=""
switch=""
leaves=""

# Arrays
tb_group[0]=""
tb_rack[0]=""
tb_switch[0]=""

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

# Output usage information
usage_full()
{
  echo "Usage: opaxlattopology [-d level -v level -i level -c char -K -?] [source [dest]]"
  echo "       -d level  -  output detail level (default 0)"
  echo "                    values are additive"
  echo "                     1 - edge switch topology files"
  echo "                     2 - rack topology files"
  echo "                     4 - rack group topology files"
# TBD - these options are disabled for now
#  echo "                     8 - DO NOT output edge-to-leaf links"
#  echo "                    16 - DO NOT output spine-to-leaf links"
  echo "       -v level  -  verbose level (0-8, default 2)"
  echo "                    0 - no output"
  echo "                    1 - progress output"
  echo "                    2 - reserved"
  echo "                    4 - time stamps"
  echo "                    8 - reserved"
  echo "       -i level  -  output indent level (0-15, default 0)"
  echo "       -c char   -  NodeDesc concatenation char (default SPACE)"
  echo "       -K        -  DO NOT clean temporary files"
  echo "       -?        -  print this output"
  exit $1
}  # End of usage_full()

# Convert general node types to standard node types
# Inputs:
#   $1 = general node type
#
# Outputs:
#   Standard node type
cvt_nodetype()
{
  case $1 in
  $NODETYPE_HFI)
    echo "FI"
    ;;
  $NODETYPE_EDGE)
    echo "SW"
    ;;
  $NODETYPE_LEAF)
    echo "SW"
    ;;
  $NODETYPE_SPINE)
    echo "SW"
    ;;
  *)
    echo ""
    ;;
  esac

}  # End of cvt_nodetype()

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
}  # End of display_progress()

# Generate directory-level FILE_TOPOLOGY_OUT file from directory-level
# CSV files; consolidate directory-level CSV files.
# Inputs:
#   $1 = 1 - Use FILE_LINKSUM
#        0 - Use FILE_LINKSUM_NOCORE
#   $2 = 1 - Use FILE_LINKSUM_NOCABLE
#        0 - Do not use FILE_LINKSUM_NOCABLE
#   FILE_LINKSUM
#   FILE_LINKSUM_NOCORE (optional)
#   FILE_LINKSUM_NOCABLE (optional)
#   FILE_NODEFIS
#   FILE_NODESWITCHES
#   FILE_NODECHASSIS
#
# Outputs:
#   FILE_TOPOLOGY_OUT
#   FILE_HOSTS
#   FILE_CHASSIS
gen_topology()
{
  if [ -f $FILE_LINKSUM ]
    then
    rm -f $FILE_TEMP
    mv $FILE_LINKSUM $FILE_TEMP
    sort -u $FILE_TEMP > $FILE_LINKSUM
  fi
  if [ -f $FILE_LINKSUM_NOCORE ]
    then
    rm -f $FILE_TEMP
    mv $FILE_LINKSUM_NOCORE $FILE_TEMP
    sort -u $FILE_TEMP > $FILE_LINKSUM_NOCORE
  fi
  if [ -f $FILE_LINKSUM_NOCABLE ]
    then
    rm -f $FILE_TEMP
    mv $FILE_LINKSUM_NOCABLE $FILE_TEMP
    sort -u $FILE_TEMP > $FILE_LINKSUM_NOCABLE
  fi

  if [ -f $FILE_NODEFIS ]
    then
    rm -f $FILE_HOSTS
    rm -f $FILE_TEMP
    mv $FILE_NODEFIS $FILE_TEMP
    sort -u $FILE_TEMP > $FILE_NODEFIS
    cut -d ';' -f 1 $FILE_NODEFIS | sed -e "s/$cat_char$HFI_SUFFIX//" > $FILE_HOSTS
  fi

  if [ -f $FILE_NODESWITCHES ]
    then
    rm -f $FILE_TEMP
    mv $FILE_NODESWITCHES $FILE_TEMP
    sort -u $FILE_TEMP > $FILE_NODESWITCHES
  fi
  if [ -f $FILE_NODECHASSIS ]
    then
    rm -f $FILE_CHASSIS
    rm -f $FILE_TEMP
    mv $FILE_NODECHASSIS $FILE_TEMP
    sort -u $FILE_TEMP > $FILE_NODECHASSIS
    cp -p $FILE_NODECHASSIS $FILE_CHASSIS
  fi

  rm -f $FILE_TOPOLOGY_OUT
  echo '<?xml version="1.0" encoding="utf-8" ?>' >> $FILE_TOPOLOGY_OUT
  echo "<Report>" >> $FILE_TOPOLOGY_OUT

  # Generate LinkSummary section
  echo "<LinkSummary>" >> $FILE_TOPOLOGY_OUT
  if [ -s $FILE_LINKSUM -a $1 == 1 ]
    then
    $XML_GENERATE -X $FILE_LINKSUM -d \; -i 2 -h Link -g Rate -g MTU -g Internal -h Cable -g CableLength -g CableLabel -g CableDetails -e Cable -h Port -g PortNum -g NodeType -g NodeDesc -g NodeGUID -e Port -h Port -g PortNum -g NodeType -g NodeDesc -g NodeGUID -e Port -e Link >> $FILE_TOPOLOGY_OUT
  elif [ -s $FILE_LINKSUM_NOCORE -a $1 == 0 ]
    then
    $XML_GENERATE -X $FILE_LINKSUM_NOCORE -d \; -i 2 -h Link -g Rate -g MTU -g Internal -h Cable -g CableLength -g CableLabel -g CableDetails -e Cable -h Port -g PortNum -g NodeType -g NodeDesc -g NodeGUID -e Port -h Port -g PortNum -g NodeType -g NodeDesc -g NodeGUID -e Port -e Link >> $FILE_TOPOLOGY_OUT
  fi

  if [ -s $FILE_LINKSUM_NOCABLE -a $2 == 1 ]
    then
    # Note: <Cable> header not needed because cable data is null
    $XML_GENERATE -X $FILE_LINKSUM_NOCABLE -d \; -i 2 -h Link -g Rate -g MTU -g Internal -g CableLength -g CableLabel -g CableDetails -h Port -g PortNum -g NodeType -g NodeDesc -g NodeGUID -e Port -h Port -g PortNum -g NodeType -g NodeDesc -g NodeGUID -e Port -e Link >> $FILE_TOPOLOGY_OUT
  fi
  echo "</LinkSummary>" >> $FILE_TOPOLOGY_OUT

  # Generate Nodes/FIs section
  echo "<Nodes>" >> $FILE_TOPOLOGY_OUT
  echo "<FIs>" >> $FILE_TOPOLOGY_OUT
  if [ -s $FILE_NODEFIS ]
    then
    $XML_GENERATE -X $FILE_NODEFIS -d \; -i 2 -h Node -g NodeDesc -g NodeGUID -g NodeDetails -e Node >> $FILE_TOPOLOGY_OUT
  fi
  echo "</FIs>" >> $FILE_TOPOLOGY_OUT

  # Generate Nodes/Switches section
  echo "<Switches>" >> $FILE_TOPOLOGY_OUT
  if [ -s $FILE_NODESWITCHES ]
    then
    $XML_GENERATE -X $FILE_NODESWITCHES -d \; -i 2 -h Node -g NodeDesc -g NodeGUID -e Node >> $FILE_TOPOLOGY_OUT
  fi
  echo "</Switches>" >> $FILE_TOPOLOGY_OUT
  echo "</Nodes>" >> $FILE_TOPOLOGY_OUT

  echo "</Report>" >> $FILE_TOPOLOGY_OUT

  # Clean temporary files
  if [ $fl_clean == 1 ]
    then
    rm -f $FILE_TEMP
    rm -f $FILE_TEMP2
    rm -f $FILE_LINKSUM
    rm -f $FILE_LINKSUM_NOCORE
    rm -f $FILE_LINKSUM_NOCABLE
    rm -f $FILE_NODEFIS
    rm -f $FILE_NODESWITCHES
    rm -f $FILE_NODELEAVES
    rm -f $FILE_NODECHASSIS
  fi
}  # End of gen_topology

# Process rack group name; check for non-null name and find in tb_group[].
# If present return tb_group[] index, otherwise make entry and return index.
# Note that tb_group[0] is always null and is the default rack group.
# Inputs:
#   $1 = rack group name (may be null)
#
# Outputs:
#         functout - index of rack group name, or 0
#   tb_rack[index] - name of rack group (written when new group)
proc_group()
{
  local val
  local ix

  val=0

  if [ $((n_detail & OUTPUT_GROUPS)) != 0 ]
    then
    if [ -n "$1" ]
      then
      # Check for group name already in tb_group[]
      for (( ix=1 ; $ix<$MAX_GROUPS ; ix=$((ix+1)) ))
      do
        if [ -n "${tb_group[$ix]}" ]
          then
          if [ "$1/" == "${tb_group[$ix]}" ]
            then
            val=$ix
            break
          fi
        # New group name, save in tb_group[] and make group directory
        else
          tb_group[$ix]="$1/"
          rm -f -r ${tb_group[$ix]}
          mkdir ${tb_group[$ix]}
          val=$ix
          break
        fi
      
      done  # for (( ix=1 ; $ix<$MAX_GROUPS

      if [ $ix == $MAX_GROUPS ]
        then
        echo "Too many rack groups (>= $MAX_GROUPS)" >&2
        usage_full "2"
      fi

    else
      echo "Must have rack group name when outputting rack group (line:$ix_line)" >&2
      usage_full "2"
    fi  # End of if [ -n "$1" ]

  fi  # End of if [ $((n_detail & OUTPUT_GROUPS)) != 0 ]

  functout=$val

}  # End of proc_group()

# Process rack name; check for non-null name and find in tb_rack[].
# If present return tb_rack[] index, otherwise make entry and return index.
# Note that tb_rack[0] is always null and is the default rack.
# Inputs:
#   $1 = rack name (may be null)
#   $2 = rack group name (may be null)
#
# Outputs:
#         functout - index of rack name, or 0
#   tb_rack[index] - name of rack (written when new rack)
proc_rack()
{
  local val
  local ix

  val=0

  if [ $((n_detail & OUTPUT_RACKS)) != 0 ]
    then
    if [ -n "$1" ]
      then
      # Check for rack name already in tb_rack[]
      for (( ix=1 ; $ix<$MAX_RACKS ; ix=$((ix+1)) ))
      do
        if [ -n "${tb_rack[$ix]}" ]
          then
          if [ "$1/" == "${tb_rack[$ix]}" ]
            then
            val=$ix
            break
          fi
        # New rack name, save in tb_rack[] and make rack directory
        else
          tb_rack[$ix]="$1/"
          rm -f -r $2${tb_rack[$ix]}
          mkdir $2${tb_rack[$ix]}
          val=$ix
          break
        fi
      
      done  # for (( ix=1 ; $ix<$MAX_RACKS

      if [ $ix == $MAX_RACKS ]
        then
        echo "Too many racks (>= $MAX_RACKS)" >&2
        usage_full "2"
      fi

    else
      echo "Must have rack name when outputting rack (line:$ix_line)" >&2
      usage_full "2"
    fi  # End of if [ -n "$1" ]

  fi  # End of if [ $((n_detail & OUTPUT_RACKS)) != 0 ]

  functout=$val

}  # End of proc_rack()

# Process switch name; check for non-null name and find in tb_switch[].
# If present return tb_switch[] index, otherwise make entry and return index.
# Note that tb_switch[0] is always null and is the default switch.
# Inputs:
#   $1 = switch name (may be null)
#   $2 = rack group/rack name (may be null)
#
# Outputs:
#           functout - index of switch name, or 0
#   tb_switch[index] - name of switch (written when new switch)
proc_switch()
{
  local val
  local ix

  val=0

  if [ $((n_detail & OUTPUT_SWITCHES)) != 0 ]
    then
    if [ -n "$1" ]
      then
      # Check for switch name already in tb_switch[]
      for (( ix=1 ; $ix<$MAX_SWITCHES ; ix=$((ix + 1)) ))
      do
        if [ -n "${tb_switch[$ix]}" ]
          then
          if [ "$1/" == "${tb_switch[$ix]}" ]
            then
            val=$ix
            break
          fi
        # New switch name, save in tb_switch[] and make switch directory
        else
          tb_switch[$ix]="$1/"
          rm -f -r $2${tb_switch[$ix]}
          mkdir $2${tb_switch[$ix]}
          val=$ix
          break
        fi
      
      done  # for (( ix=1 ; $ix<$MAX_SWITCHES

      if [ $ix == $MAX_SWITCHES ]
        then
        echo "Too many switches (>= $MAX_SWITCHES)" >&2
        usage_full "2"
      fi

    else
      echo "Must have switch name when outputting switch (line:$ix_line)" >&2
      usage_full "2"
    fi  # End of if [ -n "$1" ]

  fi  # End of if [ $((n_detail & OUTPUT_RACKS)) != 0 ]

  functout=$val

}  # End of proc_switch()


## Main function:

rm -f $FILE_DEBUG
rm -f $FILE_DEBUG2

# Get options
while getopts c:d:i:Kv:? option
do
  case $option in
  c)
    cat_char=$OPTARG
    ;;

  d)
    n_detail=$OPTARG
    if [ $((n_detail & OUTPUT_EDGE_LEAF_LINKS)) != 0 ]
      then
      fl_output_edge_leaf=0
    fi
    if [ $((n_detail & OUTPUT_SPINE_LEAF_LINKS)) != 0 ]
      then
      fl_output_spine_leaf=0
    fi
    ;;

  i)
    indent=`echo "                    " | cut -b -$OPTARG`
    ;;

  K)
    fl_clean=0
    ;;

  v)
    n_verbose=$OPTARG
    ;;

  *)
    usage_full "0"
    ;;
  esac
done

shift $((OPTIND -1))

if [ $# -ge 1 ]; then
	FILE_TOPOLOGY_LINKS=$1
	shift;
fi
if [ $# -ge 1 ]; then 
	FILE_TOPOLOGY_OUT=$1
fi

# Parse FILE_TOPOLOGY_LINKS2
display_progress "Parsing $FILE_TOPOLOGY_LINKS"

rm -f ${FILE_LINKSUM}
rm -f ${FILE_LINKSUM_NOCORE}
rm -f ${FILE_LINKSUM_NOCABLE}
rm -f ${FILE_NODEFIS}
rm -f ${FILE_NODESWITCHES}
rm -f ${FILE_NODELEAVES}
rm -f ${FILE_NODECHASSIS}

# TBD - add support for rate & mtu
rate="100g"
mtu="8192"
ix_line=1

IFS=","
while read t_00 t_01 t_02 t_03 t_04 t_05 t_06 t_07 t_08 t_09 t_10 t_11 t_12 t_13 t_14
do
  case $cts_parse in
  # Syncing to beginning of link data
  0)
    if [ "$t_00" == "Rack Group" ]
      then
      cts_parse=1
    fi
    ;;

  # Process link tokens
  1)
    if [ -n "$t_00" ]
      then
      t_srcgroup=$t_00
    fi
    if [ -n "$t_01" ]
      then
      t_srcrack=$t_01
    fi
    t_srcname=$t_02
    t_srcname2=$t_03
    if [ -n "$t_05" ]
      then
      t_srctype=$t_05
    fi
    if [ -z "$t_04" -a "$t_srctype" == "$NODETYPE_HFI" ]
      then
      t_srcport=1
    else
      t_srcport=$t_04
    fi

    if [ -n "$t_06" ]
      then
      t_dstgroup=$t_06
    fi
    if [ -n "$t_07" ]
      then
      t_dstrack=$t_07
    fi
    t_dstname=$t_08
    t_dstname2=$t_09
    if [ -n "$t_11" ]
      then
      t_dsttype=$t_11
    fi
    if [ -z "$t_10" -a "$t_dsttype" == "$NODETYPE_HFI" ]
      then
      t_dstport=1
    else
      t_dstport=$t_10
    fi

    t_cablelabel=$t_12
    t_cablelength=$t_13
    t_cabledetails=$t_14

    if [ "$t_srctype" == "$NODETYPE_SPINE" ]
      then
      internal="1"
    else
      internal="0"
    fi

    # Process group, rack and switch names
    if [ -n "$t_srcname" ]
      then
      proc_group "$t_srcgroup"
      ix_srcgroup=$functout
      proc_rack "$t_srcrack" "${tb_group[$ix_srcgroup]}"
      ix_srcrack=$functout
      proc_group "$t_dstgroup"
      ix_dstgroup=$functout
      proc_rack "$t_dstrack" "${tb_group[$ix_dstgroup]}"
      ix_dstrack=$functout
      if [ "$t_srctype" == "$NODETYPE_EDGE" ]
        then
        proc_switch "$t_srcname" "${tb_group[$ix_srcgroup]}${tb_rack[$ix_srcrack]}"
        ix_srcswitch=$functout
      else
        ix_srcswitch=0
      fi
      if [ "$t_dsttype" == "$NODETYPE_EDGE" ]
        then
        proc_switch "$t_dstname" "${tb_group[$ix_dstgroup]}${tb_rack[$ix_dstrack]}"
        ix_dstswitch=$functout
      else
        ix_dstswitch=0
      fi

      # Form consolidated output data
      nodedesc1="${t_srcname}"
      if [ "$t_srctype" == "$NODETYPE_HFI" ]
        then
        nodedesc1="${nodedesc1}${cat_char}${HFI_SUFFIX}"
        nodedetails1="${t_srcname2}"
      else
        nodedetails1=""
        if [ "$t_srctype" != "$NODETYPE_EDGE" ]
          then
          nodedesc1="${nodedesc1}${cat_char}${t_srcname2}"
        fi
      fi

      nodedesc2="${t_dstname}"
      if [ "$t_dsttype" != "$NODETYPE_EDGE" ]
        then
        nodedesc2="${nodedesc2}${cat_char}${t_dstname2}"
      fi

      nodetype1=`cvt_nodetype "$t_srctype"`
      nodetype2=`cvt_nodetype "$t_dsttype"`

      nodeguid1=`echo $nodedesc1 | cksum | cut -f1 -d\ `
      nodeguid1=`echo "obase=16; $nodeguid1" | bc`
      nodeguid1=`echo "00000000$nodeguid1" | sed "s/.*\(........$\)/\1/"`
      nodeguid2=`echo $nodedesc2 | cksum | cut -f1 -d\ `
      nodeguid2=`echo "obase=16; $nodeguid2" | bc`
      nodeguid2=`echo "00000000$nodeguid2" | sed "s/.*\(........$\)/\1/"`

      # Output CSV FILE_LINKSUM
      link="${rate};${mtu};${internal};${t_cablelength};${t_cablelabel};${t_cabledetails};${t_srcport};${nodetype1};${nodedesc1};0x00117500${nodeguid1};${t_dstport};${nodetype2};${nodedesc2};0x00117500${nodeguid2}"
      echo "${link}" >> ${FILE_LINKSUM}
      if [ $((n_detail & OUTPUT_GROUPS)) != 0 ]
        then
        echo "${link}" >> ${tb_group[$ix_srcgroup]}${FILE_LINKSUM}
        if [ $ix_dstgroup != $ix_srcgroup ]
          then
          echo "${link}" >> ${tb_group[$ix_dstgroup]}${FILE_LINKSUM}
        fi
      fi

      if [ $((n_detail & OUTPUT_RACKS)) != 0 ]
        then
        echo "${link}" >> ${tb_group[$ix_srcgroup]}${tb_rack[$ix_srcrack]}${FILE_LINKSUM}
        if [ $ix_dstrack != $ix_srcrack ]
          then
          echo "${link}" >> ${tb_group[$ix_dstgroup]}${tb_rack[$ix_dstrack]}${FILE_LINKSUM}
        fi
      fi

      if [ $((n_detail & OUTPUT_SWITCHES)) != 0 ]
        then
        if [ "$t_srctype" == "$NODETYPE_EDGE" ]
          then
          echo "${link}" >> ${tb_group[$ix_srcgroup]}${tb_rack[$ix_srcrack]}${tb_switch[$ix_srcswitch]}${FILE_LINKSUM}
        fi
        if [ "$t_dsttype" == "$NODETYPE_EDGE" ]
          then
          echo "${link}" >> ${tb_group[$ix_dstgroup]}${tb_rack[$ix_dstrack]}${tb_switch[$ix_dstswitch]}${FILE_LINKSUM}
        fi
      fi

      # Output CSV FILE_LINKSUM_NOCORE
      if [ "$t_dsttype" != "$NODETYPE_LEAF" ]
        then
        echo "${link}" >> ${FILE_LINKSUM_NOCORE}
        if [ $((n_detail & OUTPUT_GROUPS)) != 0 ]
          then
          echo "${link}" >> ${tb_group[$ix_srcgroup]}${FILE_LINKSUM_NOCORE}
          if [ $ix_dstgroup != $ix_srcgroup ]
            then
            echo "${link}" >> ${tb_group[$ix_dstgroup]}${FILE_LINKSUM_NOCORE}
          fi
        fi

        if [ $((n_detail & OUTPUT_RACKS)) != 0 ]
          then
          echo "${link}" >> ${tb_group[$ix_srcgroup]}${tb_rack[$ix_srcrack]}${FILE_LINKSUM_NOCORE}
          if [ $ix_dstrack != $ix_srcrack ]
            then
            echo "${link}" >> ${tb_group[$ix_dstgroup]}${tb_rack[$ix_dstrack]}${FILE_LINKSUM_NOCORE}
          fi
        fi

        if [ $((n_detail & OUTPUT_SWITCHES)) != 0 ]
          then
          if [ "$t_srctype" == "$NODETYPE_EDGE" ]
            then
            echo "${link}" >> ${tb_group[$ix_srcgroup]}${tb_rack[$ix_srcrack]}${tb_switch[$ix_srcswitch]}${FILE_LINKSUM_NOCORE}
          fi
          if [ "$t_dsttype" == "$NODETYPE_EDGE" ]
            then
            echo "${link}" >> ${tb_group[$ix_dstgroup]}${tb_rack[$ix_dstrack]}${tb_switch[$ix_dstswitch]}${FILE_LINKSUM_NOCORE}
          fi
        fi
      fi

      # Output CSV FILE_LINKSUM_NOCABLE
      #  Note: output only needed at top-level to bootstrap FILE_LINKSUM_SWD06
      #  and FILE_LINKSUM_SWD24
      if [ "$t_srctype" == "$NODETYPE_SPINE" ]
        then
        echo "${link}" >> ${FILE_LINKSUM_NOCABLE}
      fi

      # Output CSV nodedesc1
      if [ "$t_srctype" == "$NODETYPE_HFI" ]
        then
        echo "${nodedesc1};0x00117500${nodeguid1};${nodedetails1}" >> ${FILE_NODEFIS}
        if [ $((n_detail & OUTPUT_GROUPS)) != 0 ]
          then
          echo "${nodedesc1};0x00117500${nodeguid1};${nodedetails1}" >> ${tb_group[$ix_srcgroup]}${FILE_NODEFIS}
        fi

        if [ $((n_detail & OUTPUT_RACKS)) != 0 ]
          then
          echo "${nodedesc1};0x00117500${nodeguid1};${nodedetails1}" >> ${tb_group[$ix_srcgroup]}${tb_rack[$ix_srcrack]}${FILE_NODEFIS}
        fi

        if [ $((n_detail & OUTPUT_SWITCHES)) != 0 ]
          then
          if [ "$t_dsttype" == "$NODETYPE_EDGE" ]
            then
            if [ "x${tb_group[$ix_dstgroup]}${tb_rack[$ix_dstrack]}" == "x${tb_group[$ix_srcgroup]}${tb_rack[$ix_srcrack]}" ]
              then
              echo "${nodedesc1};0x00117500${nodeguid1};${nodedetails1}" >> ${tb_group[$ix_dstgroup]}${tb_rack[$ix_dstrack]}${tb_switch[$ix_dstswitch]}${FILE_NODEFIS}
            fi
          fi
        fi
      else
        echo "${nodedesc1};0x00117500${nodeguid1}" >> ${FILE_NODESWITCHES}
        if [ $((n_detail & OUTPUT_GROUPS)) != 0 ]
          then
          echo "${nodedesc1};0x00117500${nodeguid1}" >> ${tb_group[$ix_srcgroup]}${FILE_NODESWITCHES}
        fi

        if [ $((n_detail & OUTPUT_RACKS)) != 0 ]
          then
          echo "${nodedesc1};0x00117500${nodeguid1}" >> ${tb_group[$ix_srcgroup]}${tb_rack[$ix_srcrack]}${FILE_NODESWITCHES}
        fi

        if [ $((n_detail & OUTPUT_SWITCHES)) != 0 ]
          then
          echo "${nodedesc1};0x00117500${nodeguid1}" >> ${tb_group[$ix_srcgroup]}${tb_rack[$ix_srcrack]}${tb_switch[$ix_srcswitch]}${FILE_NODESWITCHES}
        fi
      fi

      # Output CSV nodedesc2
      echo "${nodedesc2};0x00117500${nodeguid2}" >> ${FILE_NODESWITCHES}
      if [ "$t_dsttype" == "$NODETYPE_LEAF" ]
        then
        echo "${nodedesc2};0x00117500${nodeguid2}" >> ${FILE_NODELEAVES}
        echo "${t_dstname}" >> ${FILE_NODECHASSIS}
      fi
      if [ $((n_detail & OUTPUT_GROUPS)) != 0 ]
        then
        echo "${nodedesc2};0x00117500${nodeguid2}" >> ${tb_group[$ix_dstgroup]}${FILE_NODESWITCHES}
        if [ "$t_dsttype" == "$NODETYPE_LEAF" ]
          then
          echo "${nodedesc2};0x00117500${nodeguid2}" >> ${tb_group[$ix_dstgroup]}${FILE_NODELEAVES}
          echo "${t_dstname}" >> ${tb_group[$ix_dstgroup]}${FILE_NODECHASSIS}
        fi
      fi

      if [ $((n_detail & OUTPUT_RACKS)) != 0 ]
        then
        echo "${nodedesc2};0x00117500${nodeguid2}" >> ${tb_group[$ix_dstgroup]}${tb_rack[$ix_dstrack]}${FILE_NODESWITCHES}
        if [ "$t_dsttype" == "$NODETYPE_LEAF" ]
          then
          echo "${nodedesc2};0x00117500${nodeguid2}" >> ${tb_group[$ix_dstgroup]}${tb_rack[$ix_dstrack]}${FILE_NODELEAVES}
          echo "${t_dstname}" >> ${tb_group[$ix_dstgroup]}${tb_rack[$ix_dstrack]}${FILE_NODECHASSIS}
        fi
      fi

      if [ $((n_detail & OUTPUT_SWITCHES)) != 0 ]
        then
        if [ "$t_dsttype" == "$NODETYPE_EDGE" ]
          then
          echo "${nodedesc2};0x00117500${nodeguid2}" >> ${tb_group[$ix_dstgroup]}${tb_rack[$ix_dstrack]}${tb_switch[$ix_dstswitch]}${FILE_NODESWITCHES}
        elif [ "$t_dsttype" == "$NODETYPE_LEAF" ]
          then
          echo "${nodedesc2};0x00117500${nodeguid2}" >> ${tb_group[$ix_dstgroup]}${tb_rack[$ix_dstrack]}${tb_switch[$ix_dstswitch]}${FILE_NODELEAVES}
          echo "${t_dstname}" >> ${tb_group[$ix_dstgroup]}${tb_rack[$ix_dstrack]}${tb_switch[$ix_dstswitch]}${FILE_NODECHASSIS}
        fi
      fi

    # End of link information
    else
      cts_parse=2
    fi
    ;;

    # Process core switch data
  2)
    if echo "$t_00" | grep -e "$CORE_NAME" > /dev/null 2>&1
      then
      core_name=`echo $t_00 | cut -d ':' -f 2`
      display_progress "Generating links for Core:$core_name"
      if echo "$t_01" | grep -e "$CORE_GROUP" > /dev/null 2>&1
        then
        core_group=`echo $t_01 | cut -d ':' -f 2`
      elif [ $((n_detail & OUTPUT_GROUPS)) != 0 ]
        then
        echo "Must have rack group name when outputting rack group (line:$ix_line)"
        usage_full "2"
      else
        core_group=""
      fi
      if echo "$t_02" | grep -e "$CORE_RACK" > /dev/null 2>&1
        then
        core_rack=`echo $t_02 | cut -d ':' -f 2`
      elif [ $((n_detail & OUTPUT_RACKS)) != 0 ]
        then
        echo "Must have rack name when outputting rack (line:$ix_line)"
        usage_full "2"
      else
        core_rack=""
      fi
      if echo "$t_03" | grep -e "$CORE_SIZE" > /dev/null 2>&1
        then
        core_size=`echo $t_03 | cut -d ':' -f 2`
        if [ $core_size -ne 288 -a $core_size -ne 1152 ]
          then
          echo "Invalid $CORE_SIZE parameter ($t_03)"
          usage_full "2"
        fi
      else
        echo "No $CORE_SIZE parameter"
        usage_full "2"
      fi
      if echo "$t_04" | grep -e "$CORE_FULL" > /dev/null 2>&1
        then
        core_full=`echo $t_04 | cut -d ':' -f 2`
        if [ $core_full -ne 0 -a $core_full -ne 1 ]
          then
          echo "Invalid $CORE_FULL parameter ($t_04)"
          usage_full "2"
        fi
      else
        core_full=0
      fi

      # Output CSV FILE_LINKSUM_NOCABLE
      if [ $core_full == 1 ]
        then
        leaves=""
      else
        leaves=`cat $FILE_NODELEAVES | tr '\012' '|' | sed -e 's/|$//'`
      fi
      if [ $core_size == 288 ]
        then
        cat $FILE_LINKSUM_SWD06 | sed -e "s/$DUMMY_CORE_NAME/$core_name/g" -e "s/$CAT_CHAR_CORE/$cat_char/g" | grep -E "$leaves" >> ${FILE_LINKSUM_NOCABLE}
      else
        cat $FILE_LINKSUM_SWD24 | sed -e "s/$DUMMY_CORE_NAME/$core_name/g" -e "s/$CAT_CHAR_CORE/$cat_char/g" | grep -E "$leaves" >> ${FILE_LINKSUM_NOCABLE}
      fi
      cat ${FILE_LINKSUM_NOCABLE} | cut -d ';' -f 9-10 | sort -u >> ${FILE_NODESWITCHES}
      cat ${FILE_LINKSUM_NOCABLE} | cut -d ';' -f 13-14 | sort -u >> ${FILE_NODESWITCHES}
      cat ${FILE_LINKSUM_NOCABLE} | cut -d ';' -f 13-14 | cut -d "$cat_char" -f 1 | sort -u >> ${FILE_NODECHASSIS}

      if [ $((n_detail & OUTPUT_GROUPS)) != 0 ]
        then
        if [ $core_full == 1 ]
          then
          leaves=""
        else
          leaves=`cat $core_group/$FILE_NODELEAVES | tr '\012' '|' | sed -e 's/|$//'`
        fi
        if [ $core_size == 288 ]
          then
          cat $FILE_LINKSUM_SWD06 | sed -e "s/$DUMMY_CORE_NAME/$core_name/g" -e "s/$CAT_CHAR_CORE/$cat_char/g" | grep -E "$leaves" >> $core_group/${FILE_LINKSUM_NOCABLE}
        else
          cat $FILE_LINKSUM_SWD24 | sed -e "s/$DUMMY_CORE_NAME/$core_name/g" -e "s/$CAT_CHAR_CORE/$cat_char/g" | grep -E "$leaves" >> $core_group/${FILE_LINKSUM_NOCABLE}
        fi
        cat $core_group/${FILE_LINKSUM_NOCABLE} | cut -d ';' -f 9-10 | sort -u >> $core_group/${FILE_NODESWITCHES}
        cat $core_group/${FILE_LINKSUM_NOCABLE} | cut -d ';' -f 13-14 | sort -u >> $core_group/${FILE_NODESWITCHES}
        cat $core_group/${FILE_LINKSUM_NOCABLE} | cut -d ';' -f 13-14 | cut -d "$cat_char" -f 1 | sort -u >> $core_group/${FILE_NODECHASSIS}
      fi

      if [ $((n_detail & OUTPUT_RACKS)) != 0 ]
        then
        if [ $core_full == 1 ]
          then
          leaves=""
        else
          leaves=`cat $core_group/$core_rack/$FILE_NODELEAVES | tr '\012' '|' | sed -e 's/|$//'`
        fi
        if [ $core_size == 288 ]
          then
          cat $FILE_LINKSUM_SWD06 | sed -e "s/$DUMMY_CORE_NAME/$core_name/g" -e "s/$CAT_CHAR_CORE/$cat_char/g" | grep -E "$leaves" >> $core_group/$core_rack/${FILE_LINKSUM_NOCABLE}
        else
          cat $FILE_LINKSUM_SWD24 | sed -e "s/$DUMMY_CORE_NAME/$core_name/g" -e "s/$CAT_CHAR_CORE/$cat_char/g" | grep -E "$leaves" >> $core_group/$core_rack/${FILE_LINKSUM_NOCABLE}
        fi
        cat $core_group/$core_rack/${FILE_LINKSUM_NOCABLE} | cut -d ';' -f 9-10 | sort -u >> $core_group/$core_rack/${FILE_NODESWITCHES}
        cat $core_group/$core_rack/${FILE_LINKSUM_NOCABLE} | cut -d ';' -f 13-14 | sort -u >> $core_group/$core_rack/${FILE_NODESWITCHES}
        cat $core_group/$core_rack/${FILE_LINKSUM_NOCABLE} | cut -d ';' -f 13-14 | cut -d "$cat_char" -f 1 | sort -u >> $core_group/$core_rack/${FILE_NODECHASSIS}
      fi

    # End of core switch information
    else
      break
    fi
    ;;

  esac  # end of case $cts_parse in

  ix_line=$((ix_line+1))

done < <( cat $FILE_TOPOLOGY_LINKS | tr -d '\015' )  # End of while read ... do

# Generate topology file(s)
display_progress "Generating $FILE_TOPOLOGY_OUT file(s)"
# Generate top-level topology file
gen_topology "$fl_output_edge_leaf" "$fl_output_spine_leaf"

# Output rack groups
if [ $((n_detail & OUTPUT_GROUPS)) != 0 ]
  then
  # Loop through rack groups
  for (( ix=1 ; $ix<$MAX_GROUPS ; ix=$((ix+1)) ))
  do
    if [ -n "${tb_group[$ix]}" ]
      then
      cd ${tb_group[$ix]}
      gen_topology "$fl_output_edge_leaf" "$fl_output_spine_leaf"

      if [ $((n_detail & OUTPUT_RACKS)) != 0 ]
        then
        # Loop through racks
        for rack in *
        do
          if [ -d $rack ]
            then
            cd $rack
            gen_topology "$fl_output_edge_leaf" "$fl_output_spine_leaf"

            if [ $((n_detail & OUTPUT_SWITCHES)) != 0 ]
              then
              # Loop through switches
              for switch in *
              do
                if [ -d $switch ]
                  then
                  cd $switch
                  gen_topology "$fl_output_edge_leaf" "$fl_output_spine_leaf"
                  cd ..
                fi  # End of if [ -d $switch ]

              done  # End of for switch in *

            fi  # End of if [ $((n_detail & OUTPUT_SWITCHES)) != 0 ]

            cd ..

          fi  # End of if [ -d $rack ]

        done  # End of for rack in *

      fi  # End of if [ $((n_detail & OUTPUT_RACKS)) != 0 ]

      cd ..

    elif [ $ix == 1 ]
      then
      echo "Must specify Rack Group names when outputting Rack Groups"
      usage_full "2"
    fi  # End of if [ -n "${tb_group[$ix]}" ]

  done  # End of for (( ix=1 ; $ix<$MAX_GROUPS ; ix=$((ix+1)) ))

# Output racks without rack groups
elif [ $((n_detail & OUTPUT_RACKS)) != 0 ]
  then
  # Loop through racks
  for (( ix=1 ; $ix<$MAX_RACKS ; ix=$((ix+1)) ))
  do
    if [ -n "${tb_rack[$ix]}" ]
      then
      cd ${tb_rack[$ix]}
      gen_topology "$fl_output_edge_leaf" "$fl_output_spine_leaf"

      if [ $((n_detail & OUTPUT_SWITCHES)) != 0 ]
        then
        # Loop through switches
        for switch in *
        do
          if [ -d $switch ]
            then
            cd $switch
            gen_topology "$fl_output_edge_leaf" "$fl_output_spine_leaf"
            cd ..
          fi  # End of if [ -d $switch ]

        done  # End of for switch in *

      fi  # End of if [ $((n_detail & OUTPUT_SWITCHES)) != 0 ]

      cd ..

    elif [ $ix == 1 ]
      then
      echo "Must specify Rack names when outputting Racks"
      usage_full "2"
    fi  # End of if [ -n "${tb_rack[$ix]}" ]

  done  # End of for (( ix=1 ; $ix<$MAX_RACKS ; ix=$((ix+1)) ))

# Output switches without racks or rack groups
elif [ $((n_detail & OUTPUT_SWITCHES)) != 0 ]
  then
  # Loop through switches
  for (( ix=1 ; $ix<$MAX_SWITCHES ; ix=$((ix+1)) ))
  do
    if [ -n "${tb_switch[$ix]}" ]
      then
      cd ${tb_switch[$ix]}
      gen_topology "$fl_output_edge_leaf" "$fl_output_spine_leaf"
      cd ..

    elif [ $ix == 1 ]
      then
      echo "Must specify Switch names when outputting Switches"
      usage_full "2"
    fi  # End of if [ -n "${tb_switch[$ix]}" ]

  done  # End of for (( ix=1 ; $ix<$MAX_SWITCHES ; ix=$((ix+1)) ))

fi  # End of if [ $((n_detail & OUTPUT_GROUPS)) != 0 ]

display_progress "Done"
exit 0

