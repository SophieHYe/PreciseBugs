#
# Copyright (C) 2006-2010 Red Hat, Inc.
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
#

import gettext
translation=gettext.translation('setroubleshoot-plugins', fallback=True)
_=translation.gettext

from setroubleshoot.util import *
from setroubleshoot.Plugin import Plugin

import subprocess
import sys

def is_execstack(path):
    if path[0] != "/":
        return False

    x = subprocess.check_output(["execstack",  "-q", path], universal_newlines=True).split()
    return ( x[0] == "X" )

def find_execstack(exe, pid):
    execstacklist = []
    for path in subprocess.check_output(["ldd", exe], universal_newlines=True).split():
        if is_execstack(path) and path not in execstacklist:
                execstacklist.append(path)
    try:
        fd = open("/proc/%s/maps" % pid , "r")
        for rec in fd.readlines():
            for path in rec.split():
                if is_execstack(path) and path not in execstacklist:
                    execstacklist.append(path)
    except IOError:
        pass

    return execstacklist

class plugin(Plugin):
    summary =_('''
    SELinux is preventing $SOURCE_PATH from making the program stack executable.
    ''')

    problem_description = _('''
    The $SOURCE application attempted to make its stack
    executable.  This is a potential security problem.  This should
    never ever be necessary. Stack memory is not executable on most
    OSes these days and this will not change. Executable stack memory
    is one of the biggest security problems. An execstack error might
    in fact be most likely raised by malicious code. Applications are
    sometimes coded incorrectly and request this permission.  The
    <a href="http://people.redhat.com/drepper/selinux-mem.html">SELinux Memory Protection Tests</a>
    web page explains how to remove this requirement.  If $SOURCE does not
    work and you need it to work, you can configure SELinux
    temporarily to allow this access until the application is fixed. Please
file a bug report.
    ''')

    fix_description = _('''
    Sometimes a library is accidentally marked with the execstack flag,
    if you find a library with this flag you can clear it with the
    execstack -c LIBRARY_PATH.  Then retry your application.  If the
    app continues to not work, you can turn the flag back on with
    execstack -s LIBRARY_PATH.
    ''')

    fix_cmd = ""

    if_text = _("you do not think $SOURCE_PATH should need to map stack memory that is both writable and executable.")
    then_text = _("you need to report a bug. \nThis is a potentially dangerous access.")
    do_text = _("Contact your security administrator and report this issue.")

    def get_if_text(self, avc, args):
        try:
            path = args[0]
            if not path:
                return self.if_text

            return _("you believe that \n%s\nshould not require execstack") % path
        except:
            return self.if_text

    def get_then_text(self, avc, args):
        try:
            path = args[0]
            if not path:
                return self.then_text
            return _("you should clear the execstack flag and see if $SOURCE_PATH works correctly.\nReport this as a bug on %s.\nYou can clear the exestack flag by executing:") % path
        except:
            return self.then_text

    def get_do_text(self, avc, args):
        try:
            path = args[0]
            if not path:
                return self.do_text

            return _("execstack -c %s") % path
        except:
            return self.do_text

    def __init__(self):
        Plugin.__init__(self,__name__)

    def analyze(self, avc):
        if (avc.matches_source_types(['unconfined_t', 'staff_t', 'user_t', 'guest_t', 'xguest_t']) and
           avc.has_any_access_in(['execstack'])):
            reports = []
            for i in find_execstack(avc.spath, avc.pid):
                reports.append(self.report((i,avc)))

            if len(reports) > 0:
                return reports

            return self.report((None,None))
        else:
            return None
