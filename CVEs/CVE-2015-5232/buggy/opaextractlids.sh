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

# Run opareport pipe output to opaxmlextract to extract lids to a csv file

TOOLSDIR=${TOOLSDIR:-/opt/opa/tools}
BINDIR=${BINDIR:-/usr/sbin}

tempfile=/tmp/opaextractlids$$
trap "rm -f $tempfile; exit 1" SIGHUP SIGTERM SIGINT

Usage_full()
{
	echo "Usage: opaextractlids [-h hfi] [-p port]" >&2
	echo "              or" >&2
	echo "       opaextractlids --help" >&2
	echo "   --help - produce full help text" >&2
	echo "   -h/--hfi hfi              - hfi to send via, default is 1st hfi" >&2
	echo "   -p/--port port            - port to send via, default is 1st active port" >&2
	echo "for example:" >&2
	echo "   opaextractlids > lids.csv" >&2
	echo "   opaextractlids -h 2 -p 1 > lids.csv'" >&2
	exit 0
}

Usage()
{
	echo "Usage: opaextractlids" >&2
	echo "              or" >&2
	echo "       opaextractlids --help" >&2
	echo "   --help - produce full help text" >&2
	echo "for example:" >&2
	echo "   opaextractlids > lids.csv" >&2
	exit 2
}

if [ x"$1" = "x--help" ]
then
	Usage_full
fi

IFS=';'
$BINDIR/opareport -o lids -x "$@" > $tempfile
if [ -s $tempfile ]
then
	cat $tempfile | $BINDIR/opaxmlextract -H -d \; -e LIDSummary.LIDs.Value.NodeGUID -e LIDSummary.LIDs.Value.PortNum -e LIDSummary.LIDs.Value.NodeType -e LIDSummary.LIDs.Value.NodeDesc -e LIDSummary.LIDs.Value:LID
	res=0
else
	echo "opaextractlids: Unable to get lids report" >&2
	res=1
fi
		
rm -f $tempfile
exit $res
