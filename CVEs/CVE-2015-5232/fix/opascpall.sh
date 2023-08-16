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
# copy a file to all hosts

temp="$(mktemp --tmpdir "opascpall.XXXXXX")"
trap "rm -f $temp; exit 1" SIGHUP SIGTERM SIGINT
trap "rm -f $temp" EXIT

# optional override of defaults
if [ -f /etc/sysconfig/opa/opafastfabric.conf ]
then
	. /etc/sysconfig/opa/opafastfabric.conf
fi

. /opt/opa/tools/opafastfabric.conf.def

. /opt/opa/tools/ff_funcs

temp=`mktemp`
trap "rm -f $temp" 1 2 3 9 15

Usage_full()
{
	echo "Usage: opascpall [-p] [-r] [-f hostfile] [-h 'hosts'] [-u user] source_file ... dest_file" >&2
	echo "       opascpall -t [-p] [-f hostfile] [-h 'hosts'] [-u user] [source_dir [dest_dir]]" >&2
	echo "              or" >&2
	echo "       opascpall --help" >&2
	echo "   --help - produce full help text" >&2
	echo "   -p - perform copy in parallel on all hosts" >&2
	echo "   -r - recursive copy of directories" >&2
	echo "   -t - optimized recursive copy of directories using tar" >&2
	echo "        if dest_dir omitted, defaults to current directory name" >&2
	echo "        if source_dir and dest_dir omitted, both default to current directory" >&2
	echo "   -h hosts - list of hosts to copy to" >&2
	echo "   -f hostfile - file with hosts in cluster, default is $CONFIG_DIR/opa/hosts" >&2
	echo "   -u user - user to perform copy to, default is current user code" >&2
	echo "   source_file - list of source files to copy" >&2
	echo "   source_dir - source directory to copy, if omitted . is used" >&2
	echo "   dest_file - destination for copy." >&2
	echo "        If more than 1 source file, this must be a directory" >&2
	echo "   dest_dir - destination for copy.  If omitted current directory name is used" >&2
	echo " Environment:" >&2
	echo "   HOSTS - list of hosts, used if -h option not supplied" >&2
	echo "   HOSTS_FILE - file containing list of hosts, used in absence of -f and -h" >&2
	echo "   FF_MAX_PARALLEL - when -p option is used, maximum concurrent operations" >&2
	echo "example:">&2
	echo "   opascpall MPI-PMB /root/MPI-PMB" >&2
	echo "   opascpall -t -p /opt/opa/src/mpi_apps /opt/opa/src/mpi_apps" >&2
	echo "   opascpall a b c /root/tools/" >&2
	echo "   opascpall -h 'arwen elrond' a b c /root/tools" >&2
	echo "   HOSTS='arwen elrond' opascpall a b c /root/tools" >&2
	echo "user@ syntax cannot be used in filenames specified" >&2
	echo "To copy from hosts in the cluster to this host, use opauploadall" >&2
	exit 0
}

Usage()
{
	echo "Usage: opascpall [-p] [-r] [-f hostfile] source_file ... dest_file" >&2
	echo "       opascpall -t [-p] [-f hostfile] [source_dir [dest_dir]]" >&2
	echo "              or" >&2
	echo "       opascpall --help" >&2
	echo "   --help - produce full help text" >&2
	echo "   -p - perform copy in parallel on all hosts" >&2
	echo "   -r - recursive copy of directories" >&2
	echo "   -t - optimized recursive copy of directories using tar" >&2
	echo "        if dest_dir omitted, defaults to current directory name" >&2
	echo "        if source_dir and dest_dir omitted, both default to current directory" >&2
	echo "   -f hostfile - file with hosts in cluster, default is $CONFIG_DIR/opa/hosts" >&2
	echo "   source_file - list of source files to copy" >&2
	echo "   source_dir - source directory to copy, if omitted . is used" >&2
	echo "   dest_file - destination for copy." >&2
	echo "        If more than 1 source file, this must be a directory" >&2
	echo "   dest_dir - destination for copy.  If omitted current directory name is used" >&2
	echo "example:">&2
	echo "   opascpall MPI-PMB /root/MPI-PMB" >&2
	echo "   opascpall -t -p /opt/opa/src/mpi_apps /opt/opa/src/mpi_apps" >&2
	echo "   opascpall a b c /root/tools/" >&2
	echo "user@ syntax cannot be used in filenames specified" >&2
	echo "To copy from hosts in the cluster to this host, use opauploadall" >&2
	exit 2
}

if [ x"$1" = "x--help" ]
then
	Usage_full
fi

user=`id -u -n`
opts=
topt=n
popt=n
while getopts f:h:u:prt param
do
	case $param in
	h)
		HOSTS="$OPTARG";;
	f)
		HOSTS_FILE="$OPTARG";;
	u)
		user="$OPTARG";;
	p)
		opts="$opts -q"
		popt=y;;
	r)
		opts="$opts -r";;
	t)
		topt=y;;
	?)
		Usage;;
	esac
done
shift $((OPTIND -1))
if [ "$topt" = "n" -a $# -lt 2 ]
then
	Usage
fi
if [ "$topt" = "y" -a $# -gt 2 ]
then
	Usage
fi
check_host_args opascpall

if [ "$topt" = "n" ]
then
	# remove last name from the list
	files=
	dest=
	for file in "$@"
	do
		if [ ! -z "$dest" ]
		then
			files="$files $dest"
		fi
		dest="$file"
	done
		
	running=0
	for hostname in $HOSTS
	do
		if [ "$popt" = "y" ]
		then
			if [ $running -ge $FF_MAX_PARALLEL ]
			then
				wait
				running=0
			fi
			echo "scp $opts $files $user@[$hostname]:$dest"
			scp $opts $files $user@\[$hostname\]:$dest &
			running=$(( $running + 1))
		else
			echo "scp $opts $files $user@[$hostname]:$dest"
			scp $opts $files $user@\[$hostname\]:$dest
		fi
	done
	wait
else
	if [ $# -lt 2 ]
	then
		destdir=$PWD
	else
		destdir=$2
	fi
	if [ $# -lt 1 ]
	then
		srcdir=$PWD
	else
		srcdir=$1
	fi
	if [ ! -d $srcdir ]
	then
		echo "opascpall: $srcdir: No such directory" >&2
		Usage
	fi
	cd $srcdir
	tar cvfz $temp .
	running=0
	for hostname in $HOSTS
	do
		if [ "$popt" = "y" ]
		then
			if [ $running -ge $FF_MAX_PARALLEL ]
			then
				wait
				running=0
			fi
			(
				echo "scp $opts $temp $user@[$hostname]:$temp"
				scp $opts $temp $user@\[$hostname\]:$temp
				echo "$user@$hostname: mkdir -p $destdir; cd $destdir; tar xfz $temp; rm -f $temp"
				ssh $user@$hostname "mkdir -p $destdir; cd $destdir; tar xfz $temp; rm -f $temp"
			) &
			running=$(( $running + 1))
		else
			echo "scp $opts $temp $user@[$hostname]:$temp"
			scp $opts $temp $user@\[$hostname\]:$temp
			echo "$user@$hostname: mkdir -p $destdir; cd $destdir; tar xfz $temp; rm -f $temp"
			ssh $user@$hostname "mkdir -p $destdir; cd $destdir; tar xfz $temp; rm -f $temp"
		fi
	done
	wait
	rm -f $temp
fi
