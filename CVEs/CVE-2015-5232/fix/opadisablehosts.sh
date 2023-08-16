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

# disable the specified set of hosts

tempfile=`mktemp`
trap "rm -f $tempfile; exit 1" SIGHUP SIGTERM SIGINT
trap "rm -f $tempfile" EXIT

Usage_full()
{
	echo "Usage: opadisablehosts [-h hfi] [-p port] reason host ..." >&2
	echo "              or" >&2
	echo "       opadisablehosts --help" >&2
	echo "   --help - produce full help text" >&2
	echo "   -h/--hfi hfi              - hfi to send via, default is 1st hfi" >&2
	echo "   -p/--port port            - port to send via, default is 1st active port" >&2
	echo "   reason - text description of reason hosts are being diasabled," >&2
	echo "            will be saved at end of any new lines in disabled file." >&2
	echo "            For ports already in disabled file, this is ignored." >&2  
	echo  >&2
	echo "for example:" >&2
	echo "   opadisablehosts 'bad DRAM' compute001 compute045" >&2
	echo "   opadisablehosts -h 1 -p 2 'crashed' compute001 compute045" >&2
	exit 0
}

Usage()
{
	echo "Usage: opadisablehosts reason host ..." >&2
	echo "              or" >&2
	echo "       opadisablehosts --help" >&2
	echo "   --help - produce full help text" >&2
	echo  >&2
	echo "   reason - text description of reason hosts are being diasabled," >&2
	echo "            will be saved at end of any new lines in disabled file." >&2
	echo "            For ports already in disabled file, this is ignored." >&2  
	echo >&2
	echo "for example:" >&2
	echo "   opadisablehosts 'bad DRAM' compute001 compute045" >&2
	exit 2
}

if [ x"$1" = "x--help" ]
then
	Usage_full
fi

reason=
hfi=0
port=0
while getopts h:p: param
do
	case $param in
	h)	hfi="$OPTARG";;
	p)	port="$OPTARG";;
	?)	Usage;;
	esac
done
shift $((OPTIND -1))
if [ $# -lt 1 ]
then
	Usage
fi
reason="$1"
shift

if [ $# -lt 1 ]
then
	Usage
fi

if [ "$port" -eq 0 ]
then
	port_opts="-h $hfi" # default port to 1st active
else
	port_opts="-h $hfi -p $port"
fi

# loop for each host
# this could be more efficient, but for a small list of hosts its ok
res=0
for i in "$@"
do
	echo "============================================================================="
	echo "Disabling host: $i..."
	/sbin/opaextractsellinks $port_opts -F "nodepat:$i HFI*" > $tempfile
	if [ ! -s $tempfile ]
	then
		echo "opadisablehosts: Unable to find host: $i" >&2
		res=1
	else
		/sbin/opadisableports -p "$hfi:$port" "$reason" < $tempfile
	fi
	rm -f $tempfile
done
exit $res
