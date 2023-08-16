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
# perform installation verification on hosts in a cluster

# optional override of defaults
if [ -f /etc/sysconfig/opa/opafastfabric.conf ]
then
	. /etc/sysconfig/opa/opafastfabric.conf
fi

. /opt/opa/tools/opafastfabric.conf.def

TOOLSDIR=${TOOLSDIR:-/opt/opa/tools}
BINDIR=${BINDIR:-/usr/sbin}

. $TOOLSDIR/ff_funcs

if [ x"$FF_IPOIB_SUFFIX" = xNONE ]
then
	export FF_IPOIB_SUFFIX=""
fi

temp=/tmp/opatest$$
trap "rm -f $temp" 1 2 3 9 15

# identify how we are being run, affects valid options and usage
mode=opatest
cmd=`basename $0`
case $cmd in
opahostadmin|opaswitchadmin|opachassisadmin) mode=$cmd;;
esac

Usage_opatest_full()
{
	echo "Usage: opatest [-cCn] [-i ipoib_suffix] [-f hostfile] [-F chassisfile]" >&2
	echo "              [-h 'hosts'] [-H 'chassis'] [-N 'nodes'] [-L nodefile]" >&2
	echo "              [-r release] [-I install_options] [-U upgrade_options] [-d dir]" >&2
	echo "              [-T product] [-P packages] [-m netmask] [-a action] [-S] [-O]" >&2
	echo "              [-t portsfile] [-p ports] operation ..." >&2
	echo "              or" >&2
	echo "       opatest --help" >&2
	echo "  --help - produce full help text" >&2
	echo "  -c - clobber result files from any previous run before starting this run" >&2
	echo "  -C - perform operation against chassis, default is hosts" >&2
	echo "  -n - perform operation against IB node, default is hosts" >&2
	echo "  -i ipoib_suffix - suffix to apply to host names to create ipoib host names" >&2
	echo "         default is '$FF_IPOIB_SUFFIX'" >&2
	echo "  -f hostfile - file with hosts in cluster, default is $CONFIG_DIR/opa/hosts" >&2
	echo "  -F chassisfile - file with chassis in cluster" >&2
	echo "           default is $CONFIG_DIR/opa/chassis" >&2
	echo "  -h hosts - list of hosts to execute operation against" >&2
	echo "  -H chassis - list of chassis to execute operation against" >&2
	echo "  -N nodes - list of IB nodes to execute operation against" >&2
	echo "  -L nodefile - file with IB nodes in cluster" >&2
	echo "           default is $CONFIG_DIR/opa/switches" >&2
	echo "  -r release - IntelOPA release to load/upgrade to, default is $FF_PRODUCT_VERSION" >&2
	echo "  -d dir - directory to get product.release.tgz from for load/upgrade" >&2
	echo "           to upload FM config files to for fmgetconfig (default is uploads)" >&2
	echo "           to upload capture files to for capture (default is uploads)" >&2
	echo "  -I install_options - IntelOPA install options" >&2
	echo "              or for Chassis fmconfig and fmcontrol:" >&2
	echo "                 disable - disable FM start at chassis boot" >&2
	echo "                 enable - enable FM start on master at chassis boot" >&2
	echo "                 enableall - enable FM start on all MM at chassis boot" >&2
	echo "  -U upgrade_options - IntelOPA upgrade options" >&2
	echo "  -t portsfile - file with list of local HFI ports used to access" >&2
	echo "           fabric(s) for switch access, default is /etc/sysconfig/opa/ports" >&2
	echo "  -p ports - list of local HFI ports used to access fabric(s) for switch access" >&2
	echo "           default is 1st active port" >&2
	echo "           This is specified as hfi:port" >&2
	echo "              0:0 = 1st active port in system" >&2
	echo "              0:y = port y within system" >&2
	echo "              x:0 = 1st active port on HFI x" >&2
	echo "              x:y = HFI x, port y" >&2
	echo "              The first HFI in the system is 1.  The first port on an HFI is 1." >&2
	echo "  -T product - IntelOPA product type to install, default is $FF_PRODUCT" >&2
	echo "           Other options include: IntelOPA-Basic, InfiniServBasic, InfiniServPerf, InfiniServMgmt, InfiniServTools, etc" >&2
	echo "  -P packages - IntelOPA packages to install, default is 'iba ipoib mpi'" >&2
	echo -n "                Host allows:" >&2
	/sbin/opaconfig -C >&2
	echo "                or for Chassis upgrade, filenames/directories of firmware" >&2
	echo "                   images to install.  For directories specified, all" >&2
	echo "                   .pkg files in directory tree will be used." >&2
	echo "                   shell wildcards may also be used within quotes." >&2
	echo "                or for Chassis fmconfig, filename of FM config file to use" >&2
	echo "                or for Chassis fmgetconfig, filename to upload to (default" >&2
	echo "                   opafm.xml)" >&2
	echo "                or for Switch upgrade, filename/directory of firmware" >&2
	echo "                   image to install.  For directory specified," >&2
	echo "                   .emfw file in directory tree will be used." >&2
	echo "                   shell wildcards may also be used within quotes." >&2
	echo "                or for Switch capture, filename to upload to (default" >&2
	echo "                   switchcapture)" >&2
	echo "  -m netmask - IPoIB netmask to use for configipoib" >&2
	echo "  -a action - action for supplied file" >&2
	echo "              For Chassis/Switch upgrade:" >&2
	echo "                 push   - ensure firmware is in primary or alternate" >&2
    echo "                          (alternate only applicable to chassis)" >&2
	echo "                 select - ensure firmware is in primary" >&2
	echo "                 run    - ensure firmware is in primary and running" >&2
	echo "                 default is push for chassis, select for switch" >&2
	echo "              For Chassis fmconfig:" >&2
	echo "                 push   - ensure config file is in chassis" >&2
	echo "                 run    - after push, restart FM on master, stop on slave" >&2
	echo "                 runall - after push, restart FM on all MM" >&2
	echo "                 default is push" >&2
	echo "              For Chassis fmcontrol:" >&2
	echo "                 stop   - stop FM on all MM" >&2
	echo "                 run    - make sure FM running on master, stopped on slave" >&2
	echo "                 runall - make sure FM running on all MM" >&2
	echo "                 restart- restart FM on master, stop on slave" >&2
	echo "                 restartall- restart FM on all MM" >&2
	echo "  -S - securely prompt for password for user on remote system/chassis" >&2
	echo "  -O - override: for Switch upgrade, bypass version checks and force update unconditionally" >&2
	echo "  operation - operation to perform." >&2
	echo "    Host Operation can be one or more of:" >&2
	echo "     load - initial install of all hosts" >&2
	echo "     upgrade - upgrade install of all hosts" >&2
	echo "     configipoib - create ifcfg-ib1 using host IP addr from /etc/hosts" >&2
	echo "     reboot - reboot hosts, ensure they go down and come back" >&2
	echo "     sacache - confirm sacache has all hosts in it" >&2
	echo "     ipoibping - verify this host can ping each host via IPoIB" >&2
	echo "     mpiperf - verify latency and bandwidth for each host" >&2
	echo "     mpiperfdeviation - check for latency and bandwidth tolerance deviation between hosts" >&2
	echo "    Chassis Operation can be one or more of:" >&2
	echo "     reboot - reboot chassis, ensure they go down and come back" >&2
	echo "     configure - run wizard to set up chassis configuration" >&2
	echo "     upgrade - upgrade install of all chassis" >&2
	echo "     getconfig - gets basic configuration of chassis" >&2
	echo "     fmconfig - FM config operation on all chassis" >&2
	echo "     fmgetconfig - Fetch FM config from all chassis" >&2
	echo "     fmcontrol - Control FM on all chassis" >&2
    echo "    IB Node Operation can be one or more of:" >&2
    echo "     reboot - reboot IB node, ensure they go down and come back" >&2
	echo "     configure - run wizard to set up IB node configuration" >&2
    echo "     upgrade - upgrade install of all IB nodes" >&2
    echo "     info - report f/w & h/w version, part number, and data rate capability of all IB nodes" >&2
    echo "     hwvpd - complete hardware VPD report of all IB nodes" >&2
    echo "     ping - ping all IB nodes - test for presence" >&2
    echo "     fwverify - report integrity of failsafe firmware of all IB nodes" >&2
	echo "     capture - get switch hardware and firmware state capture of all IB nodes" >&2
	echo "     getconfig - get port configurations of a externally managed switch" >&2
	echo " Environment:" >&2
	echo "   HOSTS - list of hosts, used if -h option not supplied" >&2
	echo "   CHASSIS - list of chassis, used if -C used and -H and -F option not supplied" >&2
	echo "   OPASWITCHES - list of nodes, used if -n used and -N and -L option not supplied" >&2
	echo "   HOSTS_FILE - file containing list of hosts, used in absence of -f and -h" >&2
	echo "   CHASSIS_FILE - file containing list of chassis, used in absence of -F and -H" >&2
	echo "   OPASWITCHES_FILE - file containing list of nodes, used in absence of -N and -L" >&2
	echo "   FF_MAX_PARALLEL - maximum concurrent operations" >&2
	echo "   FF_SERIALIZE_OUTPUT - serialize output of parallel operations (yes or no)" >&2
	echo "   UPLOADS_DIR - directory to upload to, used in absence of -d" >&2
	echo "for example:" >&2
	echo "   opatest -c reboot" >&2
	echo "   opatest upgrade" >&2
	echo "   opatest -h 'elrond arwen' reboot" >&2
	echo "   HOSTS='elrond arwen' opatest reboot" >&2
	echo "   opatest -C -a run -P '*.pkg' upgrade" >&2
	echo "During run the following files are produced:" >&2
	echo "  test.res - appended with summary results of run" >&2
	echo "  test.log - appended with detailed results of run" >&2
	echo "  save_tmp/ - contains a directory per failed operation with detailed logs" >&2
	echo "  test_tmp*/ - intermediate result files while operation is running" >&2
	echo "-c option will remove all of the above" >&2
   # remove temporary work directory
   rm -rf $temp
	exit 0
}
Usage_opahostadmin_full()
{
	echo "Usage: opahostadmin [-c] [-i ipoib_suffix] [-f hostfile] [-h 'hosts'] " >&2
	echo "              [-r release] [-I install_options] [-U upgrade_options] [-d dir]" >&2
	echo "              [-T product] [-P packages] [-m netmask] [-S] operation ..." >&2
	echo "              or" >&2
	echo "       opahostadmin --help" >&2
	echo "  --help - produce full help text" >&2
	echo "  -c - clobber result files from any previous run before starting this run" >&2
	echo "  -i ipoib_suffix - suffix to apply to host names to create ipoib host names" >&2
	echo "         default is '$FF_IPOIB_SUFFIX'" >&2
	echo "  -f hostfile - file with hosts in cluster, default is $CONFIG_DIR/opa/hosts" >&2
	echo "  -h hosts - list of hosts to execute operation against" >&2
	echo "  -r release - IntelOPA release to load/upgrade to, default is $FF_PRODUCT_VERSION" >&2
	echo "  -d dir - directory to get product.release.tgz from for load/upgrade" >&2
	echo "  -I install_options - IntelOPA install options" >&2
	echo "  -U upgrade_options - IntelOPA upgrade options" >&2
	echo "  -T product - IntelOPA product type to install, default is $FF_PRODUCT" >&2
	echo "           Other options include: IntelOPA-Basic, InfiniServBasic, InfiniServPerf, InfiniServMgmt, InfiniServTools, etc" >&2
	echo "  -P packages - IntelOPA packages to install, default is 'iba ipoib mpi'" >&2
	echo -n "                Host allows:" >&2
	/sbin/opaconfig -C >&2
	echo "  -m netmask - IPoIB netmask to use for configipoib" >&2
	echo "  -S - securely prompt for password for user on remote system" >&2
	echo "  operation - operation to perform. operation can be one or more of:" >&2
	echo "     load - initial install of all hosts" >&2
	echo "     upgrade - upgrade install of all hosts" >&2
	echo "     configipoib - create ifcfg-ib1 using host IP addr from /etc/hosts" >&2
	echo "     reboot - reboot hosts, ensure they go down and come back" >&2
	echo "     sacache - confirm sacache has all hosts in it" >&2
	echo "     ipoibping - verify this host can ping each host via IPoIB" >&2
	echo "     mpiperf - verify latency and bandwitch for each host" >&2
	echo "     mpiperfdeviation - check for latency and bandwidth tolerance deviation between hosts" >&2
	echo " Environment:" >&2
	echo "   HOSTS - list of hosts, used if -h option not supplied" >&2
	echo "   HOSTS_FILE - file containing list of hosts, used in absence of -f and -h" >&2
	echo "   FF_MAX_PARALLEL - maximum concurrent operations" >&2
	echo "   FF_SERIALIZE_OUTPUT - serialize output of parallel operations (yes or no)" >&2
	echo "for example:" >&2
	echo "   opahostadmin -c reboot" >&2
	echo "   opahostadmin upgrade" >&2
	echo "   opahostadmin -h 'elrond arwen' reboot" >&2
	echo "   HOSTS='elrond arwen' opahostadmin reboot" >&2
	echo "During run the following files are produced:" >&2
	echo "  test.res - appended with summary results of run" >&2
	echo "  test.log - appended with detailed results of run" >&2
	echo "  save_tmp/ - contains a directory per failed operation with detailed logs" >&2
	echo "  test_tmp*/ - intermediate result files while operation is running" >&2
	echo "-c option will remove all of the above" >&2
   # remove temporary work directory
   rm -rf $temp
	exit 0
}
Usage_opachassisadmin_full()
{
	echo "Usage: opachassisadmin [-c] [-F chassisfile] [-H 'chassis'] " >&2
	echo "              [-P packages] [-a action] [-I fm_bootstate]" >&2
    echo "              [-S] [-d upload_dir] operation ..." >&2
	echo "              or" >&2
	echo "       opachassisadmin --help" >&2
	echo "  --help - produce full help text" >&2
	echo "  -c - clobber result files from any previous run before starting this run" >&2
	echo "  -F chassisfile - file with chassis in cluster" >&2
	echo "           default is $CONFIG_DIR/opa/chassis" >&2
	echo "  -H chassis - list of chassis to execute operation against" >&2
	echo "  -P packages - filenames/directories of firmware" >&2
	echo "                   images to install.  For directories specified, all" >&2
	echo "                   .pkg files in directory tree will be used." >&2
	echo "                   shell wildcards may also be used within quotes." >&2
	echo "                or for fmconfig, filename of FM config file to use" >&2
	echo "                or for fmgetconfig, filename to upload to (default" >&2
	echo "                   opafm.xml)" >&2
	echo "  -a action - action for supplied file" >&2
	echo "              For Chassis upgrade:" >&2
	echo "                 push   - ensure firmware is in primary or alternate" >&2
	echo "                 select - ensure firmware is in primary" >&2
	echo "                 run    - ensure firmware is in primary and running" >&2
	echo "                 default is push" >&2
	echo "              For Chassis fmconfig:" >&2
	echo "                 push   - ensure config file is in chassis" >&2
	echo "                 run    - after push, restart FM on master, stop on slave" >&2
	echo "                 runall - after push, restart FM on all MM" >&2
	echo "              For Chassis fmcontrol:" >&2
	echo "                 stop   - stop FM on all MM" >&2
	echo "                 run    - make sure FM running on master, stopped on slave" >&2
	echo "                 runall - make sure FM running on all MM" >&2
	echo "                 restart- restart FM on master, stop on slave" >&2
	echo "                 restartall- restart FM on all MM" >&2
	echo "  -I fm_bootstate fmconfig and fmcontrol install options" >&2
	echo "                 disable - disable FM start at chassis boot" >&2
	echo "                 enable - enable FM start on master at chassis boot" >&2
	echo "                 enableall - enable FM start on all MM at chassis boot" >&2
	echo "  -d upload_dir - directory to upload FM config files to, default is uploads" >&2
	echo "  -S - securely prompt for password for admin on chassis" >&2
	echo "  operation - operation to perform. operation can be one or more of:" >&2
	echo "     reboot - reboot chassis, ensure they go down and come back" >&2
	echo "     configure - run wizard to set up chassis configuration" >&2
	echo "     upgrade - upgrade install of all chassis" >&2
	echo "     getconfig - get basic configuration of chassis" >&2
	echo "     fmconfig - FM config operation on all chassis" >&2
	echo "     fmgetconfig - Fetch FM config from all chassis" >&2
	echo "     fmcontrol - Control FM on all chassis" >&2
	echo " Environment:" >&2
	echo "   CHASSIS - list of chassis, used if -H and -F option not supplied" >&2
	echo "   CHASSIS_FILE - file containing list of chassis, used in absence of -F and -H" >&2
	echo "   FF_MAX_PARALLEL - maximum concurrent operations" >&2
	echo "   FF_SERIALIZE_OUTPUT - serialize output of parallel operations (yes or no)" >&2
	echo "   UPLOADS_DIR - directory to upload to, used in absence of -d" >&2
	echo "for example:" >&2
	echo "   opachassisadmin -c reboot" >&2
	echo "   opachassisadmin -P /root/ChassisFw4.2.0.0.1 upgrade" >&2
	echo "   opachassisadmin -H 'chassis1 chassis2' reboot" >&2
	echo "   CHASSIS='chassis1 chassis2' opachassisadmin reboot" >&2
	echo "   opachassisadmin -a run -P '*.pkg' upgrade" >&2
	echo "During run the following files are produced:" >&2
	echo "  test.res - appended with summary results of run" >&2
	echo "  test.log - appended with detailed results of run" >&2
	echo "  save_tmp/ - contains a directory per failed operation with detailed logs" >&2
	echo "  test_tmp*/ - intermediate result files while operation is running" >&2
	echo "-c option will remove all of the above" >&2
   # remove temporary work directory
   rm -rf $temp
	exit 0
}
Usage_opaswitchadmin_full()
{
	echo "Usage: opaswitchadmin [-c] [-N 'nodes'] [-L nodefile] [-d upload_dir]" >&2
	echo "              [-O] [-P packages] [-a action]" >&2
	echo "              [-t portsfile] [-p ports] operation ..." >&2
	echo "              or" >&2
	echo "       opaswitchadmin --help" >&2
	echo "  --help - produce full help text" >&2
	echo "  -c - clobber result files from any previous run before starting this run" >&2
	echo "  -N nodes - list of IB nodes to execute operation against" >&2
	echo "  -L nodefile - file with IB nodes in cluster" >&2
	echo "           default is $CONFIG_DIR/opa/switches" >&2
	echo "  -d upload_dir - directory to upload capture files to for capture" >&2
	echo "                  (default is uploads)" >&2
	echo "  -P packages - for upgrade, filename/directory of firmware" >&2
	echo "                   image to install.  For directory specified," >&2
	echo "                   .emfw file in directory tree will be used." >&2
	echo "                   shell wildcards may also be used within quotes." >&2
	echo "                or for capture, filename to upload to (default switchcapture)" >&2
	echo "  -t portsfile - file with list of local HFI ports used to access" >&2
	echo "           fabric(s) for switch access, default is /etc/sysconfig/opa/ports" >&2
	echo "  -p ports - list of local HFI ports used to access fabric(s) for switch access" >&2
	echo "           default is 1st active port" >&2
	echo "           This is specified as hfi:port" >&2
	echo "              0:0 = 1st active port in system" >&2
	echo "              0:y = port y within system" >&2
	echo "              x:0 = 1st active port on HFI x" >&2
	echo "              x:y = HFI x, port y" >&2
	echo "              The first HFI in the system is 1.  The first port on an HFI is 1." >&2
	echo "  -a action - action for firmware file for Switch upgrade" >&2
	echo "              select - ensure firmware is in primary" >&2
	echo "              run    - ensure firmware is in primary and running" >&2
	echo "              default is select" >&2
	echo "  -O - override: for firmware upgrade, bypass version checks and force update unconditionally" >&2
	echo "  operation - operation to perform. operation can be one or more of:" >&2
   echo "     reboot - reboot switches, ensure they go down and come back" >&2
   echo "     configure - run wizard to set up switch configuration" >&2
   echo "     upgrade - upgrade install of all switches" >&2
   echo "     info - report f/w & h/w version, part number, and data rate capability of all IB nodes" >&2
   echo "     hwvpd - complete hardware VPD report of all IB nodes" >&2
   echo "     ping - ping all IB nodes - test for presence" >&2
   echo "     fwverify - report integrity of failsafe firmware of all IB nodes" >&2
   echo "     capture - get switch hardware and firmware state capture of all IB nodes" >&2
   echo "     getconfig - get port configurations of a externally managed switch" >&2
	echo " Environment:" >&2
	echo "   OPASWITCHES - list of nodes, used if -N and -L option not supplied" >&2
	echo "   OPASWITCHES_FILE - file containing list of nodes, used in absence of -N and -L" >&2
	echo "   FF_MAX_PARALLEL - maximum concurrent operations" >&2
	echo "   FF_SERIALIZE_OUTPUT - serialize output of parallel operations (yes or no)" >&2
	echo "for example:" >&2
	echo "   opaswitchadmin -c reboot" >&2
	echo "   opaswitchadmin -P /root/ChassisFw4.2.0.0.1 upgrade" >&2
	echo "   opaswitchadmin -a run -P '*.emfw' upgrade" >&2
	echo "During run the following files are produced:" >&2
	echo "  test.res - appended with summary results of run" >&2
	echo "  test.log - appended with detailed results of run" >&2
	echo "  save_tmp/ - contains a directory per failed operation with detailed logs" >&2
	echo "  test_tmp*/ - intermediate result files while operation is running" >&2
	echo "-c option will remove all of the above" >&2
   # remove temporary work directory
   rm -rf $temp
	exit 0
}
Usage_full()
{
	case $mode in
	opatest) Usage_opatest_full;;
	opahostadmin) Usage_opahostadmin_full;;
	opachassisadmin) Usage_opachassisadmin_full;;
	opaswitchadmin) Usage_opaswitchadmin_full;;
	esac
}
Usage_opatest()
{
	echo "Usage: opatest [-cCn] [-f hostfile] [-F chassisfile] [-L nodefile]" >&2
	echo "              [-r release] [-d dir]" >&2
	echo "              [-T product] [-P packages] [-a action] [-S] [-O] [-d upload_dir] operation ..." >&2
	echo "              or" >&2
	echo "       opatest --help" >&2
	echo "  --help - produce full help text" >&2
	echo "  -c - clobber result files from any previous run before starting this run" >&2
	echo "  -C - perform operation against chassis, default is hosts" >&2
	echo "  -n - perform operation against IB node, default is hosts" >&2
	echo "  -f hostfile - file with hosts in cluster, default is $CONFIG_DIR/opa/hosts" >&2
	echo "  -F chassisfile - file with chassis in cluster" >&2
	echo "           default is $CONFIG_DIR/opa/chassis" >&2
	echo "  -L nodefile - file with IB nodes in cluster" >&2
	echo "           default is $CONFIG_DIR/opa/switches" >&2
	echo "  -r release - IntelOPA release to load/upgrade to, default is $FF_PRODUCT_VERSION" >&2
	echo "  -d dir - directory to get product.release.tgz from for load/upgrade" >&2
	echo "           to upload FM config files to for fmgetconfig (default is uploads)" >&2
	echo "           to upload capture files to for capture (default is uploads)" >&2
	echo "  -T product - IntelOPA product type to install, default is $FF_PRODUCT" >&2
	echo "           Other options include: IntelOPA-Basic, InfiniServBasic, InfiniServPerf, InfiniServMgmt, InfiniServTools, etc" >&2
	echo "  -P packages - IntelOPA packages to install, default is 'iba ipoib mpi'" >&2
	echo -n "                Host allows:" >&2
	/sbin/opaconfig -C >&2
	echo "                or for Chassis upgrade, filenames/directories of firmware" >&2
	echo "                   images to install.  For directories specified, all" >&2
	echo "                   .pkg files in directory tree will be used." >&2
	echo "                   shell wildcards may also be used within quotes." >&2
	echo "                or for Chassis fmconfig, filename of FM config file to use" >&2
	echo "                or for Chassis fmgetconfig, filename to upload to (default" >&2
	echo "                   opafm.xml)" >&2
	echo "                or for Switch upgrade, filename/directory of firmware" >&2
	echo "                   image to install.  For directory specified," >&2
	echo "                   .emfw file in directory tree will be used." >&2
	echo "                   shell wildcards may also be used within quotes." >&2
	echo "                or for Switch capture, filename to upload to (default" >&2
	echo "                   switchcapture)" >&2
	echo "  -a action - action for supplied file" >&2
	echo "              For Chassis/Switch upgrade:" >&2
	echo "                 push   - ensure firmware is in primary or alternate" >&2
    echo "                          (alternate only applicable to chassis)" >&2
	echo "                 select - ensure firmware is in primary" >&2
	echo "                 run    - ensure firmware is in primary and running" >&2
	echo "                 default is push for chassis, select for switch" >&2
	echo "              For Chassis fmconfig:" >&2
	echo "                 push   - ensure config file is in chassis" >&2
	echo "                 run    - after push, restart FM on master, stop on slave" >&2
	echo "                 runall - after push, restart FM on all MM" >&2
	echo "              For Chassis fmcontrol:" >&2
	echo "                 stop   - stop FM on all MM" >&2
	echo "                 run    - make sure FM running on master, stopped on slave" >&2
	echo "                 runall - make sure FM running on all MM" >&2
	echo "                 restart- restart FM on master, stop on slave" >&2
	echo "                 restartall- restart FM on all MM" >&2
	echo "  -S - securely prompt for password for user on remote system/chassis" >&2
	echo "  -O - override: for Switch upgrade, bypass version checks and force update unconditionally" >&2
	echo "  operation - operation to perform." >&2
	echo "    Host Operation can be one or more of:" >&2
	echo "     load - initial install of all hosts" >&2
	echo "     upgrade - upgrade install of all hosts" >&2
	echo "     configipoib - create ifcfg-ib1 using host IP addr from /etc/hosts" >&2
	echo "     reboot - reboot hosts, ensure they go down and come back" >&2
	echo "     sacache - confirm sacache has all hosts in it" >&2
	echo "     ipoibping - verify this host can ping each host via IPoIB" >&2
	echo "     mpiperf - verify latency and bandwitch for each host" >&2
	echo "     mpiperfdeviation - check for latency and bandwidth tolerance deviation between hosts" >&2
	echo "    Chassis Operation can be one or more of:" >&2
	echo "     reboot - reboot chassis, ensure they go down and come back" >&2
	echo "     configure - run wizard to set up chassis configuration" >&2
	echo "     upgrade - upgrade install of all chassis" >&2
	echo "     getconfig - get basic configuration of chassis" >&2
	echo "     fmconfig - FM config operation on all chassis" >&2
	echo "     fmgetconfig - Fetch FM config from all chassis" >&2
	echo "     fmcontrol - Control FM on all chassis" >&2
   echo "    IB Node Operation can be one or more of:" >&2
   echo "     reboot - reboot IB nodes, ensure they go down and come back" >&2
   echo "     configure - run wizard to set up IB node configuration" >&2
   echo "     upgrade - upgrade install of all IB nodes" >&2
   echo "     info - report f/w & h/w version, part number, and data rate capability of all IB nodes" >&2
   echo "     hwvpd - complete hardware VPD report of all IB nodes" >&2
   echo "     ping - ping all IB nodes - test for presence" >&2
   echo "     fwverify - report integrity of failsafe firmware of all IB nodes" >&2
   echo "     capture - get switch hardware and firmware state capture of all IB nodes" >&2
   echo "     getconfig - get port configurations of a externally managed switch" >&2
	echo "for example:" >&2
	echo "   opatest -c reboot" >&2
	echo "   opatest upgrade" >&2
	echo "   opatest -C -a run -P '*.pkg' upgrade" >&2
	echo "During run the following files are produced:" >&2
	echo "  test.res - appended with summary results of run" >&2
	echo "  test.log - appended with detailed results of run" >&2
	echo "  save_tmp/ - contains a directory per failed test with detailed logs" >&2
	echo "  test_tmp*/ - intermediate result files while test is running" >&2
	echo "-c option will remove all of the above" >&2
   # remove temporary work directory
   rm -rf $temp
	exit 2
}
Usage_opahostadmin()
{
	echo "Usage: opahostadmin [-c] [-f hostfile] [-r release] [-d dir]" >&2
	echo "              [-T product] [-P packages] [-S] operation ..." >&2
	echo "              or" >&2
	echo "       opahostadmin --help" >&2
	echo "  --help - produce full help text" >&2
	echo "  -c - clobber result files from any previous run before starting this run" >&2
	echo "  -f hostfile - file with hosts in cluster, default is $CONFIG_DIR/opa/hosts" >&2
	echo "  -r release - IntelOPA release to load/upgrade to, default is $FF_PRODUCT_VERSION" >&2
	echo "  -d dir - directory to get product.release.tgz from for load/upgrade" >&2
	echo "  -T product - IntelOPA product type to install, default is $FF_PRODUCT" >&2
	echo "           Other options include: IntelOPA-Basic, InfiniServBasic, InfiniServPerf, InfiniServMgmt, InfiniServTools, etc" >&2
	echo "  -P packages - IntelOPA packages to install, default is 'iba ipoib mpi'" >&2
	echo -n "                Host allows:" >&2
	/sbin/opaconfig -C >&2
	echo "  -S - securely prompt for password for user on remote system" >&2
	echo "  operation - operation to perform. operation can be one or more of:" >&2
	echo "     load - initial install of all hosts" >&2
	echo "     upgrade - upgrade install of all hosts" >&2
	echo "     configipoib - create ifcfg-ib1 using host IP addr from /etc/hosts" >&2
	echo "     reboot - reboot hosts, ensure they go down and come back" >&2
	echo "     sacache - confirm sacache has all hosts in it" >&2
	echo "     ipoibping - verify this host can ping each host via IPoIB" >&2
	echo "     mpiperf - verify latency and bandwitch for each host" >&2
	echo "     mpiperfdeviation - check for latency and bandwidth tolerance deviation between hosts" >&2
	echo "for example:" >&2
	echo "   opahostadmin  -c reboot" >&2
	echo "   opahostadmin  upgrade" >&2
	echo "During run the following files are produced:" >&2
	echo "  test.res - appended with summary results of run" >&2
	echo "  test.log - appended with detailed results of run" >&2
	echo "  save_tmp/ - contains a directory per failed test with detailed logs" >&2
	echo "  test_tmp*/ - intermediate result files while test is running" >&2
	echo "-c option will remove all of the above" >&2
   # remove temporary work directory
   rm -rf $temp
	exit 2
}
Usage_opachassisadmin()
{
	echo "Usage: opachassisadmin [-c] [-F chassisfile] " >&2
	echo "              [-P packages] [-I fm_bootstate] [-a action]" >&2
    echo "              [-S] [-d upload_dir] operation ..." >&2
	echo "              or" >&2
	echo "       opachassisadmin --help" >&2
	echo "  --help - produce full help text" >&2
	echo "  -c - clobber result files from any previous run before starting this run" >&2
	echo "  -F chassisfile - file with chassis in cluster" >&2
	echo "           default is $CONFIG_DIR/opa/chassis" >&2
	echo "  -P packages - filenames/directories of firmware" >&2
	echo "                   images to install.  For directories specified, all" >&2
	echo "                   .pkg files in directory tree will be used." >&2
	echo "                   shell wildcards may also be used within quotes." >&2
	echo "                or for fmconfig, filename of FM config file to use" >&2
	echo "                or for fmgetconfig, filename to upload to (default" >&2
	echo "                   opafm.xml)" >&2
	echo "  -a action - action for supplied file" >&2
	echo "              For Chassis upgrade:" >&2
	echo "                 push   - ensure firmware is in primary or alternate" >&2
	echo "                 select - ensure firmware is in primary" >&2
	echo "                 run    - ensure firmware is in primary and running" >&2
	echo "                 default is push" >&2
	echo "              For Chassis fmconfig:" >&2
	echo "                 push   - ensure config file is in chassis" >&2
	echo "                 run    - after push, restart FM on master, stop on slave" >&2
	echo "                 runall - after push, restart FM on all MM" >&2
	echo "              For Chassis fmcontrol:" >&2
	echo "                 stop   - stop FM on all MM" >&2
	echo "                 run    - make sure FM running on master, stopped on slave" >&2
	echo "                 runall - make sure FM running on all MM" >&2
	echo "                 restart- restart FM on master, stop on slave" >&2
	echo "                 restartall- restart FM on all MM" >&2
	echo "  -I fm_bootstate fmconfig and fmcontrol install options" >&2
	echo "                 disable - disable FM start at chassis boot" >&2
	echo "                 enable - enable FM start on master at chassis boot" >&2
	echo "                 enableall - enable FM start on all MM at chassis boot" >&2
	echo "  -d upload_dir - directory to upload FM config files to, default is uploads" >&2
	echo "  -S - securely prompt for password for admin on chassis" >&2
	echo "  operation - operation to perform. operation can be one or more of:" >&2
	echo "     reboot - reboot chassis, ensure they go down and come back" >&2
	echo "     configure - run wizard to set up chassis configuration" >&2
	echo "     upgrade - upgrade install of all chassis" >&2
	echo "     getconfig - get basic configuration of chassis" >&2
	echo "     fmconfig - FM config operation on all chassis" >&2
	echo "     fmgetconfig - Fetch FM config from all chassis" >&2
	echo "     fmcontrol - Control FM on all chassis" >&2
	echo "for example:" >&2
	echo "   opachassisadmin -c reboot" >&2
	echo "   opachassisadmin -P /root/ChassisFw4.2.0.0.1 upgrade" >&2
	echo "   opachassisadmin -a run -P '*.pkg' upgrade" >&2
	echo "During run the following files are produced:" >&2
	echo "  test.res - appended with summary results of run" >&2
	echo "  test.log - appended with detailed results of run" >&2
	echo "  save_tmp/ - contains a directory per failed operation with detailed logs" >&2
	echo "  test_tmp*/ - intermediate result files while operation is running" >&2
	echo "-c option will remove all of the above" >&2
   # remove temporary work directory
   rm -rf $temp
	exit 2
}
Usage_opaswitchadmin()
{
	echo "Usage: opaswitchadmin [-c] [-L nodefile] [-d upload_dir] [-O] [-P packages]" >&2
	echo "                        [-a action] operation ..." >&2
	echo "              or" >&2
	echo "       opaswitchadmin --help" >&2
	echo "  --help - produce full help text" >&2
	echo "  -c - clobber result files from any previous run before starting this run" >&2
	echo "  -L nodefile - file with IB nodes in cluster" >&2
	echo "           default is $CONFIG_DIR/opa/switches" >&2
	echo "  -d upload_dir - directory to upload capture files to for capture" >&2
	echo "                  (default is uploads)" >&2
	echo "  -P packages - for upgrade, filename/directory of firmware" >&2
	echo "                   image to install.  For directory specified," >&2
	echo "                   .emfw file in directory tree will be used." >&2
	echo "                   shell wildcards may also be used within quotes." >&2
	echo "                or for capture, filename to upload to (default switchcapture)" >&2
	echo "  -a action - action for firmware file for Switch upgrade" >&2
	echo "              select - ensure firmware is in primary" >&2
	echo "              run    - ensure firmware is in primary and running" >&2
	echo "              default is select" >&2
	echo "  -O - override: for firmware upgrade, bypass version checks and force update unconditionally" >&2
	echo "  operation - operation to perform. operation can be one or more of:" >&2
   echo "     reboot - reboot switches, ensure they go down and come back" >&2
   echo "     configure - run wizard to set up switch configuration" >&2
   echo "     upgrade - upgrade install of all switches" >&2
   echo "     info - report f/w & h/w version, part number, and data rate capability of all IB nodes" >&2
   echo "     hwvpd - complete hardware VPD report of all IB nodes" >&2
   echo "     ping - ping all IB nodes - test for presence" >&2
   echo "     fwverify - report integrity of failsafe firmware of all IB nodes" >&2
   echo "     capture - get switch hardware and firmware state capture of all IB nodes" >&2
   echo "     getconfig - get port configurations of a externally managed switch" >&2
	echo "for example:" >&2
	echo "   opaswitchadmin -c reboot" >&2
	echo "   opaswitchadmin -P /root/ChassisFw4.2.0.0.1 upgrade" >&2
	echo "   opaswitchadmin -a run -P '*.emfw' upgrade" >&2
	echo "During run the following files are produced:" >&2
	echo "  test.res - appended with summary results of run" >&2
	echo "  test.log - appended with detailed results of run" >&2
	echo "  save_tmp/ - contains a directory per failed operation with detailed logs" >&2
	echo "  test_tmp*/ - intermediate result files while operation is running" >&2
	echo "-c option will remove all of the above" >&2
   # remove temporary work directory
   rm -rf $temp
	exit 2
}
Usage()
{
	case $mode in
	opatest) Usage_opatest;;
	opahostadmin) Usage_opahostadmin;;
	opachassisadmin) Usage_opachassisadmin;;
	opaswitchadmin) Usage_opaswitchadmin;;
	esac
}

if [ x"$1" = "x--help" ]
then
	Usage_full
fi

# default to install wrapper version
if [ -e /etc/sysconfig/opa/version_wrapper ]
then
	CFG_RELEASE=`cat /etc/sysconfig/opa/version_wrapper 2>/dev/null`;
fi
if [ x"$CFG_RELEASE" = x ]
then
# if no wrapper, use version of FF itself as filled in at build time
# version string is filled in by prep, special marker format for it to use
CFG_RELEASE="THIS_IS_THE_ICS_VERSION_NUMBER:@(#)000.000.000.000B000"
fi
export CFG_RELEASE=`echo $CFG_RELEASE|sed -e 's/THIS_IS_THE_ICS_VERSION_NUMBER:@(#.//' -e 's/%.*//'`
# THIS_IS_THE_ICS_INTERNAL_VERSION_NUMBER:@(#)000.000.000.000B000
# test automation configuration defaults
export CFG_INIC_SUFFIX=
export CFG_IPOIB_SUFFIX="$FF_IPOIB_SUFFIX"
export CFG_USERNAME="$FF_USERNAME"
export CFG_PASSWORD="$FF_PASSWORD"
export CFG_ROOTPASS="$FF_ROOTPASS"
export CFG_LOGIN_METHOD="$FF_LOGIN_METHOD"
export CFG_CHASSIS_LOGIN_METHOD="$FF_CHASSIS_LOGIN_METHOD"
export CFG_CHASSIS_ADMIN_PASSWORD="$FF_CHASSIS_ADMIN_PASSWORD"
export CFG_FAILOVER="n"
export CFG_FTP_SERVER=""
export CFG_IPOIB="y"
export CFG_IPOIB_MTU="2030"
export CFG_IPOIB_COMBOS=TBD
export CFG_INIC=n
export CFG_SDP=n
export CFG_SRP=n
export CFG_MPI=y
export CFG_UDAPL=n
export TEST_TIMEOUT_MULT="$FF_TIMEOUT_MULT"
export TEST_RESULT_DIR="$FF_RESULT_DIR"
export TEST_MAX_PARALLEL="$FF_MAX_PARALLEL"
export TEST_CONFIG_FILE="/dev/null"
export TL_DIR=/opt/opa/tools
export TEST_IDENTIFY=no
export TEST_SHOW_CONFIG=no
export TEST_SHOW_START=yes
export CFG_PRODUCT="${FF_PRODUCT:-IntelOPA-Basic}"
export CFG_INSTALL_OPTIONS="$FF_INSTALL_OPTIONS"
export CFG_UPGRADE_OPTIONS="$FF_UPGRADE_OPTIONS"
export CFG_IPOIB_NETMASK="$FF_IPOIB_NETMASK"
export CFG_IPOIB_CONNECTED="$FF_IPOIB_CONNECTED"
export CFG_MPI_ENV="$FF_MPI_ENV"
export TEST_SERIALIZE_OUTPUT="$FF_SERIALIZE_OUTPUT"

clobber=n
host=0
chassis=0
opaswitch=0
dir=.
packages="notsupplied"
action=default
Sopt=n
sopt=n
bypassSwitchCheck=n
fwOverride=n
case $mode in
opatest) options='a:BcCnd:h:H:f:F:i:r:I:U:P:T:m:p:t:L:N:S';;
opahostadmin) host=1; options='cd:h:f:i:r:I:U:P:T:m:S';;
opachassisadmin) chassis=1; options='a:I:cH:F:P:d:S';;
opaswitchadmin) opaswitch=1; options='a:Bcd:P:p:t:L:N:O';;
esac
while getopts "$options"  param
do
	case $param in
	a)
		action="$OPTARG";;
	B)
		bypassSwitchCheck=y;;
	c)
		clobber=y;;
	C)
		chassis=1;;
	d)
		dir="$OPTARG"
		export UPLOADS_DIR="$dir";;
	h)
		host=1
		HOSTS="$OPTARG";;
	H)
		chassis=1
		CHASSIS="$OPTARG";;
	n)
		opaswitch=1;;
	N)
		opaswitch=1
		OPASWITCHES="$OPTARG";;
	f)
		host=1
		HOSTS_FILE="$OPTARG";;
	F)
		chassis=1
		CHASSIS_FILE="$OPTARG";;
	L)
		opaswitch=1
		OPASWITCHES_FILE="$OPTARG";;
	i)
		export CFG_IPOIB_SUFFIX="$OPTARG"
		export FF_IPOIB_SUFFIX="$OPTARG";;
	r)
		export FF_PRODUCT_VERSION="$OPTARG";;
	I)
		export CFG_INSTALL_OPTIONS="$OPTARG";;
	U)
		export CFG_UPGRADE_OPTIONS="$OPTARG";;
	P)
		packages="$OPTARG";;
	T)
		export CFG_PRODUCT="$OPTARG";;
	m)
		export CFG_IPOIB_NETMASK="$OPTARG";;
	p)
		export PORTS="$OPTARG";;
	t)
		export PORTS_FILE="$OPTARG";;
	S)
		Sopt=y;;
	O)
		fwOverride=y;;
	?)
		Usage;;
	esac
done
shift $((OPTIND -1))

if [ $# -lt 1 ] 
then
	Usage
fi
if [[ $(($chassis+$host+$opaswitch)) -gt 1 ]]
then
	echo "$cmd: conflicting arguments, more than one of host, chassis or opaswitches specified" >&2
	Usage
fi
if [[ $(($chassis+$host+$opaswitch)) -eq 0 ]]
then
	host=1
fi
if [ ! -d "$FF_RESULT_DIR" ]
then
	echo "$cmd: Invalid FF_RESULT_DIR: $FF_RESULT_DIR: No such directory" >&2
	exit 1
fi
if [ $chassis -eq 1 ]
then
	check_chassis_args $cmd
	if [ "$action" = "default" ]
	then
		action=push
	fi
	if [ "$CFG_INSTALL_OPTIONS" = "$FF_INSTALL_OPTIONS" ]
	then
		export CFG_INSTALL_OPTIONS=
	fi
elif [ $opaswitch -eq 1 ]
then
	check_ib_transport_args $cmd
	check_ports_args $cmd
	if [ "$action" = "default" ]
	then
		action=select
	fi
else
	check_host_args $cmd

	if [ "$packages" = "notsupplied" ]
	then
		packages="$FF_PACKAGES"
	fi
	if [ "x$packages" != "x" ]
	then
		for p in $packages
		do
			CFG_INSTALL_OPTIONS="$CFG_INSTALL_OPTIONS -i $p"
		done
	fi
	if [ "x$CFG_INSTALL_OPTIONS" = "x" ]
	then
		CFG_INSTALL_OPTIONS='-i iba -i ipoib -i mpi'
	fi
fi

export CFG_HOSTS="$HOSTS"
export CFG_CHASSIS="$CHASSIS"
export CFG_OPASWITCHES="$OPASWITCHES"
export CFG_PORTS="$PORTS"
export CFG_MPI_PROCESSES="$HOSTS"
#export CFG_PERF_PAIRS=TBD
export CFG_SCPFROMDIR="$dir"
if [ x"$FF_PRODUCT_VERSION" != x ]
then
	CFG_RELEASE="$FF_PRODUCT_VERSION"
fi

# use NONE so ff_function's inclusion of defaults works properly
if [ x"$FF_IPOIB_SUFFIX" = x ]
then
	export FF_IPOIB_SUFFIX="NONE"
	export CFG_IPOIB_SUFFIX="NONE"
fi

if [ "$clobber" = "y" ]
then
	( cd $TEST_RESULT_DIR; rm -rf test.res save_tmp test.log test_tmp* *.dmp )
fi

# create an empty test.log file
( cd $TEST_RESULT_DIR; >> test.log )

run_test()
{
	# $1 = test suite name
	TCLLIBPATH="$TL_DIR /usr/lib/tcl" expect -f $TL_DIR/$1.exp | tee -a $TEST_RESULT_DIR/test.res
}

if [ $chassis -eq 1 ]
then
	if [ "$Sopt" = y ]
	then
		echo -n "Password for admin on all chassis: " > /dev/tty
		stty -echo < /dev/tty > /dev/tty
		password=
		read password < /dev/tty
		stty echo < /dev/tty > /dev/tty
		echo > /dev/tty
		export CFG_CHASSIS_ADMIN_PASSWORD="$password"
	fi
	for test_suite in $*
	do
		case $test_suite in
		reboot)
			run_test chassis_$test_suite;;
		configure)
			$TOOLSDIR/chassis_setup $CFG_CHASSIS
			if [ $? = 0 ]
			then
				export SYSLOG_SERVER=`grep "Syslog Server IP_Address" .chassisSetup.out | cut -d : -f 2`
				export SYSLOG_PORT=`grep "Syslog Port" .chassisSetup.out | cut -d : -f 2`
				export SYSLOG_FACILITY=`grep "Syslog Facility" .chassisSetup.out | cut -d : -f 2`
				export NTP_SERVER=`grep "NTP Server" .chassisSetup.out | cut -d : -f 2`
				export TZ_OFFSET=`grep "Timezone offset" .chassisSetup.out | cut -d : -f 2`
				export DST_START=`grep "Start DST" .chassisSetup.out | cut -d : -f 2`
				export DST_END=`grep "End DST" .chassisSetup.out | cut -d : -f 2`
				export LINKWIDTH_SETTING=`grep "Link Width Selection" .chassisSetup.out | cut -d : -f 2`
				export SET_NAME=`grep "Set IB Node Desc" .chassisSetup.out | cut -d : -f 2`
				export LINKCRCMODE=`grep "Link CRC Mode" .chassisSetup.out | cut -d : -f 2`
				run_test chassis_$test_suite
			else
				echo "Chassis setup wizard exited abnormally ... aborting"
		fi;;
		getconfig)
			run_test chassis_$test_suite;;
		upgrade)
			if [ "$packages" = "notsupplied" -o "$packages" = "" ]
			then
				echo "$cmd: -P option required for chassis upgrade" >&2
				Usage
			fi
			if [ "$action" != "push" -a "$action" != "select" -a "$action" != "run" ]
			then
				echo "$cmd: Invalid firmware upgrade action: $action" >&2
				Usage
			fi
			# check fw files exist, expand directories
			CFG_FWFILES=""
			for fwfile in $packages
			do
				# expand directory, also filters files without .pkg suffix
				# this also expands wildcards in "$packages"
				fwfiles=`find $fwfile -type f -name '*.pkg'`
				if [ $? != 0 -o x"$fwfiles" == x ]
				then
					echo "$cmd: $fwfile: No .pkg files found" >&2
					Usage
				fi
				CFG_FWFILES="$CFG_FWFILES $fwfiles"
			done
			export CFG_FWFILES
			export CFG_FWACTION="$action"
			run_test chassis_$test_suite;;
		fmconfig)
			if [ "$packages" = "notsupplied" -o "$packages" = "" ]
			then
				echo "$cmd: -P option required for chassis fmconfig" >&2
				Usage
			fi
			if [ "$action" != "push" -a "$action" != "run" -a "$action" != "runall" ]
			then
				echo "$cmd: Invalid FM config action: $action" >&2
				Usage
			fi
			if [ "$CFG_INSTALL_OPTIONS" != "" -a "$CFG_INSTALL_OPTIONS" != "disable" -a "$CFG_INSTALL_OPTIONS" != "enable" -a "$CFG_INSTALL_OPTIONS" != "enableall" ]
			then
				echo "$cmd: Invalid FM bootstate: $CFG_INSTALL_OPTIONS" >&2
				Usage
			fi
			export CFG_FMFILE="$packages"
			export CFG_FWACTION="$action"
			run_test chassis_$test_suite;;
		fmcontrol)
			if [ "$action" != "stop" -a "$action" != "run" -a "$action" != "runall" -a "$action" != "restart" -a "$action" != "restartall" ]
			then
				echo "$cmd: Invalid FM config action: $action" >&2
				Usage
			fi
			if [ "$CFG_INSTALL_OPTIONS" != "" -a "$CFG_INSTALL_OPTIONS" != "disable" -a "$CFG_INSTALL_OPTIONS" != "enable" -a "$CFG_INSTALL_OPTIONS" != "enableall" ]
			then
				echo "$cmd: Invalid FM bootstate: $CFG_INSTALL_OPTIONS" >&2
			fi
			export CFG_FWACTION="$action"
			run_test chassis_$test_suite;;
		fmgetconfig)
			if [ "$packages" = "notsupplied" -o "$packages" = "" ]
			then
				packages="opafm.xml"
				#echo "$cmd: -P option required for chassis fmgetconfig" >&2
				#Usage
			fi
			export CFG_FMFILE="$packages"
			run_test chassis_$test_suite;;
		*)
			echo "Invalid Operation name: $test_suite" >&2
			Usage;
			;;
		esac
	done
elif [ $opaswitch -eq 1 ]
then
	if [ "$bypassSwitchCheck" = y ]
	then
		export CFG_SWITCH_BYPASS_SWITCH_CHECK=y
	else
		export CFG_SWITCH_BYPASS_SWITCH_CHECK=n
	fi
	for test_suite in $*
	do

		case $test_suite in
      reboot)
         run_test switch_$test_suite;;
		info)
			run_test switch_$test_suite;;
		hwvpd)
			run_test switch_$test_suite;;
		ping)
			run_test switch_$test_suite;;
		fwverify)
			run_test switch_$test_suite;;
		capture)
			if [ "$packages" = "notsupplied" -o "$packages" = "" ]
			then
				packages="switchcapture"
				#echo "$cmd: -P option required for switch capture" >&2
				#Usage
			fi
			export CFG_CAPTUREFILE="$packages"
			run_test switch_$test_suite;;
		configure)
			$TOOLSDIR/switch_setup
			if [ $? = 0 ]
			then
				export LINKWIDTH_SETTING=`grep "Link Width Selection" .switchSetup.out | cut -d : -f 2`
				export LINKSPEED_SETTING=`grep "Link Speed Selection" .switchSetup.out | cut -d : -f 2`
				export NODEDESC_SETTING=`grep "Node Description Selection" .switchSetup.out | cut -d : -f 2`
				export FMENABLED_SETTING=`grep "FM Enabled Selection" .switchSetup.out | cut -d : -f 2`
				export LINKCRCMODE_SETTING=`grep "Link CRC Mode Selection" .switchSetup.out | cut -d : -f 2`
				run_test switch_$test_suite
			else
				echo "Ext mgd switch setup wizard exited abnormally ... aborting"
			fi;;
		upgrade)
			if [ "$packages" = "notsupplied" -o "$packages" = "" ]
			then
				echo "$cmd: -P option required for switch upgrade" >&2
				Usage
			fi
			if [ "$action" != "select" -a "$action" != "run" ]
			then
				echo "$cmd: Invalid firmware upgrade action: $action" >&2
				echo "$cmd: 'run' and 'select' are the only supported actions" >&2
				Usage
			fi

			dirnum=1

			# check fw files exist, expand directories
			CFG_FWFILES=""
			CFG_FWBINFILES=""

			for fwfile in $packages
			do

				echo "$cmd: processing package file: $fwfile" >&2
				# expand directory, also filters files without .emfw suffix
				# this also expands wildcards in "$packages"
				fwfiles=`find $fwfile -type f -name '*.emfw'`
				if [ $? != 0 -o x"$fwfiles" == x ]
				then
					echo "$cmd: $fwfile: No .emfw files found" >&2
					Usage
				fi
				CFG_FWFILES="$fwfiles"

				echo "$cmd: found package file: $fwfiles" >&2
				# copy file to temporary directory
			for tarball in $CFG_FWFILES
			do
				# create temporary work directory
				CFG_FWFILE="$tarball"
				CFG_FWTEMPDIR="$temp.$dirnum"
				mkdir $CFG_FWTEMPDIR
				CFG_FWRELEASEFILE="$CFG_FWTEMPDIR/release.emfw.txt"

				cp -f $tarball $CFG_FWTEMPDIR

				# remove previous firmware image .bin files, and extract
				# .bin files from .emfw file.
				rm -rf '$CFG_FWTEMPDIR/*.bin'
				tar --directory $CFG_FWTEMPDIR -zxf $tarball

				# search for text file that contains release related information
            # about the firmware image .bin files
            if [ ! -f "$CFG_FWRELEASEFILE" ]
				then
					echo "$cmd: No release.emfw.txt file found for package file: $tarball" >&2
					Usage
				fi

            fwreleaseinfo=`cat $CFG_FWRELEASEFILE`
            CFG_FWRELINFO="$fwreleaseinfo"
            fwreleaseversioninfo=`cat $CFG_FWRELEASEFILE | grep _ | sed "s/_/./g"`
            CFG_FWRELEASEVERSIONINFO=$fwreleaseversioninfo
            CFG_FWSPEED=`cat $CFG_FWRELEASEFILE | grep DR`
			if [ "$CFG_FWSPEED" = "" ]
			then
				CFG_FWSPEED="QDR"
			fi
            CFG_FWASICVER=`grep "V[0-9]" $CFG_FWRELEASEFILE`
			if [ "$CFG_FWASICVER" = "" ]
			then
				CFG_FWASICVER="V0"
			fi
			CFG_SWITCH_DEVICE=`head -n 1 $CFG_FWRELEASEFILE`

				# expand directory, also filters files without .bin suffix
				fwfiles=`find $CFG_FWTEMPDIR -type f -name '*.bin'`
				if [ $? != 0 -o x"$fwfiles" == x ]
				then
					echo "$cmd: $tarball: No .bin files found" >&2
					Usage
				fi

				CFG_FWBINFILES="$fwfiles"
				CFG_FWOVERRIDE=$fwOverride

				export CFG_FWFILES
				export CFG_FWFILE
				export CFG_FWBINFILES
				export CFG_FWTEMPDIR
				export CFG_FWRELINFO
				export CFG_FWSPEED
				export CFG_FWASICVER
				export CFG_SWITCH_DEVICE
				export CFG_FWRELEASEVERSIONINFO
				export CFG_FWACTION="$action"
				export CFG_FWOVERRIDE

				echo "$cmd: upgrading with switch firmware image: $tarball : version $fwreleaseversioninfo" >&2
				run_test switch_$test_suite

				dirnum=$((dirnum + 1))
			done
			done

			# remove temporary work directory
			rm -rf $temp.*
			;;
		getconfig)
			run_test switch_$test_suite;;
		*)
			echo "Invalid Operation name: $test_suite" >&2
			Usage;
			;;
		esac
	done
else
	if [ "$Sopt" = y ]
	then
		echo -n "Password for $CFG_USERNAME on all hosts: " > /dev/tty
		stty -echo < /dev/tty > /dev/tty
		password=
		read password < /dev/tty
		stty echo < /dev/tty > /dev/tty
		echo > /dev/tty
		export CFG_PASSWORD="$password"
		if [ "$CFG_USERNAME" != "root" ]
		then
			echo -n "Password for root on all hosts: " > /dev/tty
			stty -echo < /dev/tty > /dev/tty
			password=
			read password < /dev/tty
			stty echo < /dev/tty > /dev/tty
			echo > /dev/tty
			export CFG_ROOTPASS="$password"
		fi
	fi
	for test_suite in $*
	do
		case $test_suite in
		load|upgrade)
			if [ ! -f "$dir/$CFG_PRODUCT.$CFG_RELEASE.tgz" ]
			then
				echo "$cmd: $dir/$CFG_PRODUCT.$CFG_RELEASE.tgz not found" >&2
				exit 1
			fi
			run_test $test_suite;;
		reboot|sacache|configipoib|ipoibping|mpiperf|mpiperfdeviation)
			run_test $test_suite;;
		*)
			echo "Invalid Operation name: $test_suite" >&2
			Usage;
			;;
		esac
	done
fi
