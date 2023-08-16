from trac.core import *
from trac.util.datefmt import from_utimestamp, pretty_timedelta
from trac.versioncontrol import RepositoryManager
from trac.util.translation import _
from datetime import datetime
from time import time
import math
import re

class PortRepository(object):
    def __init__(self, env, id):
        self.env = env
        self.clear()
        self.id = id

    def clear(self):
        self.id = None
        self.name = None
        self.type = None
        self.url = None
        self.browseurl = None
        self.username = None

    def delete(self):
        db = self.env.get_db_cnx()
        cursor = db.cursor()

        cursor.execute("SELECT count(*) FROM buildqueue WHERE repository = %s", ( self.id, ))
        row = cursor.fetchone()
        if not row:
            raise TracError('SQL Error')
        if row[0] > 0:
            raise TracError('There are still buildqueue entries that need this repository')

        cursor.execute("DELETE FROM portrepositories WHERE id = %s", ( self.id, ))
        db.commit()

    def add(self):
        if self.id:
            raise TracError('Already existing portrepository object cannot be added again')

        db = self.env.get_db_cnx()
        cursor = db.cursor()
        cursor.execute("INSERT INTO portrepositories (name, type, url, browseurl, username) VALUES(%s, %s, %s, %s, %s)", ( self.name, self.type, self.url, self.browseurl, self.username ) )
        db.commit()



class Backend(object):
    def __init__(self, env, id=None):
        self.env = env
        self.clear()
        self.id = id

    def clear(self):
        self.id = None
        self.protocol = None
        self.host = None
        self.uri = None
        self.credentials = None
        self.maxparallel = None
        self.type = None
        self.status = None

    def getURL(self):
        return self.protocol + '://' + self.host + self.uri

    def setStatus(self, status):
        self.status = int(status)

        if self.status == 0:
            self.disabled = True
            self.statusname = 'fail'
        elif self.status == 1:
            self.enabled = True
            self.statusname = 'success'
        elif self.status == 2:
            self.disabled = True
            self.failed = True
            self.statusname = 'fail'
        else:
            raise TracError('Invalid backend status')

    def updateStatus(self, status):
        self.setStatus(status)
        db = self.env.get_db_cnx()
        cursor = db.cursor()
        cursor.execute("UPDATE backends SET status = %s WHERE id = %s", ( self.status, self.id ) )
        db.commit()

    def delete(self):
        db = self.env.get_db_cnx()
        cursor = db.cursor()

        cursor.execute("SELECT count(*) FROM backendbuilds WHERE backendid = %s", ( self.id, ))
        row = cursor.fetchone()
        if not row:
            raise TracError('SQL Error')
        if row[0] > 0:
            raise TracError('There are still backendbuilds for this backend')

        cursor.execute("DELETE FROM backends WHERE id = %s", ( self.id, ))
        db.commit()

    def add(self):
        if self.id:
            raise TracError('Already existing backend object cannot be added again')

        if self.uri[0] != '/':
            raise TracError('')

        db = self.env.get_db_cnx()
        cursor = db.cursor()
        cursor.execute("INSERT INTO backends (host, protocol, uri, credentials, maxparallel, status, type) VALUES(%s, %s, %s, %s, %s, %s, %s)", ( self.host, self.protocol, self.uri, self.credentials, self.maxparallel, self.status, self.type ) )
        db.commit()


class Backendbuild(object):
    def __init__(self, env, id=None):
        self.env = env
        self.clear()
        self.id = id

    def clear(self):
        self.id = None
        self.buildgroup = None
        self.backendid = None
        self.priority = None
        self.status = None
        self.buildname = None

    def setStatus(self, status):
        self.status = int(status)

        if self.status == 0:
            self.disabled = True
            self.statusname = 'fail'
        elif self.status == 1:
            self.enabled = True
            self.statusname = 'success'
        elif self.status == 2:
            self.disabled = True
            self.failed = True
            self.statusname = 'fail'
        elif self.status == 3:
            self.disabled = False
            self.failed = False
            self.statusname = 'dud'
        elif self.status == 4:
            self.disabled = False
            self.failed = False
            self.statusname = 'dud'
        else:
            raise TracError('Invalid backendbuild status')

    def updateStatus(self, status):
        self.setStatus(status)
        db = self.env.get_db_cnx()
        cursor = db.cursor()
        cursor.execute("UPDATE backendbuilds SET status = %s WHERE id = %s", ( self.status, self.id ) )
        db.commit()

    def delete(self):
        db = self.env.get_db_cnx()
        cursor = db.cursor()

        cursor.execute("SELECT buildgroup, backendid FROM backendbuilds WHERE id = %s", ( self.id, ))
        row = cursor.fetchone()

        cursor.execute("SELECT count(*) FROM builds WHERE buildgroup = %s AND backendid = %s AND status < 90", ( row[0], row[1] ))
        row = cursor.fetchone()
        if not row:
            raise TracError('SQL Error')
        if row[0] > 0:
            raise TracError('There are running builds for this Backendbuild')

        cursor.execute("DELETE FROM backendbuilds WHERE id = %s", ( self.id, ))
        db.commit()


    def add(self):
        if self.id:
            raise TracError('Already existing backendbuild object cannot be added again')

        db = self.env.get_db_cnx()
        cursor = db.cursor()
        cursor.execute("INSERT INTO backendbuilds (buildgroup, backendid, priority, status, buildname) VALUES(%s, %s, %s, %s, %s)", ( self.buildgroup, self.backendid, self.priority, self.status, self.buildname) )
        db.commit()


class Build(object):
    def __init__(self, env, queueid=None):
        self.env = env
        self.clear()
        self.queueid = queueid

    def clear(self):
        self.queueid = None
        self.repository = None
        self.revision = None
        self.description = None
        self.runtime = None
        self.startdate = None
        self.enddate = None
        self.owner = None
        self.priority = None
        self.ports = list()
        self.status = None

    def setStatus(self, status):
        self.status = status

    def addBuild(self, groups, ports, req):
        db = self.env.get_db_cnx()
        cursor = db.cursor()

        self.status = 20

        if not self.queueid:
            self.queueid = datetime.now().strftime('%Y%m%d%H%M%S-%f')[0:20]

        if not self.revision:
            self.revision = None

        if not self.priority:
            self.priority = 5
        self.priority = int(self.priority)

        if not self.description:
            self.description = 'Web rebuild'

        if not ports:
            raise TracError('Portname needs to be set')

        if self.priority < 3:
            cursor.execute("SELECT count(*) FROM buildqueue WHERE owner = %s AND priority < 3 AND status < 90", ( req.authname, ))
            row = cursor.fetchone()
            if not row:
                raise TracError('SQL Error')
            if row[0] > 0:
                self.priority = 5

        try:
            if self.revision:
                self.revision = int(self.revision)
        except ValueError:
            raise TracError('Revision needs to be numeric')

        cursor.execute("SELECT id, type, replace(url, '%OWNER%', %s) FROM portrepositories WHERE id = %s AND ( username = %s OR username IS NULL )", (
 req.authname, self.repository, self.owner ))
        if cursor.rowcount != 1:
            raise TracError('SQL Error')
        row = cursor.fetchone()

        if row[1] == 'svn':
             reponame, repo, fullrepopath = RepositoryManager(self.env).get_repository_by_path(row[2])
             if not self.revision:
                 self.revision = repo.get_youngest_rev()
             if self.revision < 0 or self.revision > repo.get_youngest_rev():
                 raise TracError('Invalid Revision number')
             if not repo.has_node(fullrepopath[len(repo.get_path_url('/', self.revision)):], self.revision):
                 raise TracError('No permissions to schedule builds for this repository')
             
        if isinstance(groups, basestring):
            grouplist = list()
            grouplist.append(groups)
            groups = grouplist

        if isinstance(ports, basestring):
            ports = ports.split()
            ports.sort()

            for portname in ports:
                if not re.match('^([a-zA-Z0-9_+.-]+)/([a-zA-Z0-9_+.-]+)$', portname):
                    raise TracError(_('Invalid portname %(port)s', port=portname))
                if len(portname) >= 100:
                    raise TracError(_('Portname %(port)s too long', port=portname))

        if groups[0] == 'automatic':
            cursor.execute("SELECT buildgroup FROM automaticbuildgroups WHERE username = %s ORDER BY priority", (self.owner,) )
            if cursor.rowcount < 1:
                raise TracError('Cannot schedule automatic builds. You need to join a buildgroup first.')
            groups = cursor.fetchall()
        else:
            for group in groups:
                cursor.execute("SELECT name FROM buildgroups WHERE name = %s", (group,) )
                if cursor.rowcount != 1:
                    raise TracError('Invalid Buildqueue')

        if len(ports) > 2 and self.priority < 3:
            self.priority = 5

        cursor.execute("INSERT INTO buildqueue (id, owner, repository, revision, status, priority, startdate, enddate, description) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)", ( self.queueid, self.owner, self.repository, self.revision, self.status, self.priority, long(time()*1000000), 0, self.description ))

        for portname in ports:
            for group in groups:
                cursor.execute("INSERT INTO builds (queueid, backendkey, buildgroup, portname, pkgversion, status, buildstatus, buildreason, buildlog, wrkdir, backendid, startdate, enddate, checkdate) VALUES (%s, SUBSTRING(MD5(RANDOM()::text), 1, 25), %s, %s, null, %s, null, null, null, null, 0, 0, 0, 0)", ( self.queueid, group, portname, self.status ))
        db.commit()
        return True

    def delete(self, req):
        db = self.env.get_db_cnx()
        cursor = db.cursor()

        cursor.execute("SELECT count(*) FROM buildqueue WHERE buildqueue.id = %s AND buildqueue.owner = %s", ( self.queueid, req.authname ))
        row = cursor.fetchone()
        if not row:
            raise TracError('SQL Error')
        if row[0] != 1:
            raise TracError('Invalid QueueID')

        cursor.execute("SELECT id FROM builds WHERE queueid = %s FOR UPDATE", ( self.queueid, ) )

        error = None
        for id in cursor:
            try:
                port = Port(self.env, id)
                port.delete(req)
            except TracError as e:
                error = e

        db.commit()
        if error:
            raise error


class Port(object):
    def __init__(self, env, id=None):
        self.env = env
        self.clear()
        self.id = id

    def clear(self):
        self.id = None
        self.group = None
        self.portname = None
        self.pkgversion = None
        self.status = None
        self.buildstatus = 'unknown'
        self.statusname = None
        self.reason = None
        self.buildlog = None
        self.wrkdir = None
        self.runtime = None
        self.startdate = None
        self.enddate = None
        self.deletable = None

    def setStatus(self, status, statusname=None):
        self.status = status

        if not self.status:
            self.status = 20

        if math.floor(self.status / 10) == 2:
            self.statusname = 'waiting'
            self.deletable = True
        elif math.floor(self.status / 10) == 3:
            self.statusname = 'starting'
        elif math.floor(self.status / 10) == 5:
            self.statusname = 'building'
        elif math.floor(self.status / 10) == 7:
            self.statusname = 'transferring'
        elif math.floor(self.status / 10) == 8:
            self.statusname = 'cleaning'
        elif math.floor(self.status / 10) == 9:
            self.statusname = 'finished'
            self.deletable = True
        else:
            self.statusname = 'unknown'

        if statusname:
            self.statusname = statusname.lower()

    def setPriority(self, priority):
        self.priority = int(priority)

        if self.priority == 1 or self.priority == 2:
            self.priorityname = 'highest'
        elif self.priority == 3 or self.priority == 4:
            self.priorityname = 'high'
        elif self.priority == 5:
            self.priorityname = 'standard'
        elif self.priority == 6 or self.priority == 7:
            self.priorityname = 'low'
        elif self.priority == 8 or self.priority == 9:
            self.priorityname = 'lowest'
        else:
            raise TracError('Invalid Priority')

    def buildFinished(self, key):
        db = self.env.get_db_cnx()
        cursor = db.cursor()
        cursor.execute("UPDATE builds SET status = 51 WHERE backendkey = %s AND status = 50", ( key, ))
        db.commit()

    def delete(self, req=None):
        db = self.env.get_db_cnx()
        cursor = db.cursor()

        if req:
            username = req.authname
        else:
            username = 'anonymous'

        cursor.execute("SELECT count(*) FROM buildqueue, builds WHERE buildqueue.id = builds.queueid AND builds.id = %s AND (buildqueue.owner = %s OR %s = 'anonymous')", ( self.id, username, username ))
        row = cursor.fetchone()
        if not row:
            raise TracError('SQL Error')
        if row[0] != 1:
            raise TracError('Invalid ID')

        cursor.execute("SELECT buildqueue.id, builds.status FROM buildqueue, builds WHERE buildqueue.id = builds.queueid AND builds.id = %s", ( self.id, ))
        row = cursor.fetchone()
        if not row:
            raise TracError('SQL Error')
        if cursor.rowcount != 1:
            raise TracError('Invalid ID')

        queueid = row[0]
        status = row[1]

        if status >= 30 and status < 90:
            raise TracError('Cannot delete running build')

        cursor.execute("UPDATE builds SET status = 95 WHERE id = %s", (self.id,) )

        cursor.execute("SELECT count(*) FROM builds WHERE queueid = %s AND status <= 90", (queueid,) )
        row = cursor.fetchone()
        if not row:
            raise TracError('SQL Error')
        if row[0] == 0:
            cursor.execute("UPDATE buildqueue SET status = 95, enddate = %s WHERE id = %s", (long(time()*1000000), queueid ))

        db.commit()


def GlobalBuildqueueIterator(env, req):
    cursor = env.get_db_cnx().cursor()

    if req.args.get('group'):
        group = req.args.get('group')
    else:
        group = ''

    cursor.execute("SELECT builds.id, builds.buildgroup, builds.portname, builds.pkgversion, builds.status, builds.buildstatus, builds.buildreason, builds.buildlog, builds.wrkdir, builds.startdate, CASE WHEN builds.enddate < builds.startdate THEN extract(epoch from now())*1000000 ELSE builds.enddate END, buildqueue.id, buildqueue.priority, buildqueue.owner FROM builds, buildqueue WHERE buildqueue.id = builds.queueid AND builds.status < 90 AND (builds.buildgroup = %s OR %s = '') ORDER BY builds.status DESC, buildqueue.priority, builds.id DESC LIMIT 50", (group, group))

    lastport = None
    for id, group, portname, pkgversion, status, buildstatus, buildreason, buildlog, wrkdir, startdate, enddate, queueid, priority, owner in cursor:
        port = Port(env)
        port.id = id
        port.group = group
        port.portname = portname
        port.pkgversion = pkgversion
        port.buildstatus = buildstatus
        port.buildlog = buildlog
        port.wrkdir = wrkdir
        port.runtime = pretty_timedelta( from_utimestamp(startdate), from_utimestamp(enddate) )
        port.startdate = startdate
        port.enddate = enddate
        port.directory = '/~%s/%s-%s' % ( owner, queueid, id )
        port.queueid = queueid
        port.owner = owner
        port.setPriority(priority)

        if buildstatus:
            port.buildstatus = buildstatus.lower()
        if buildstatus and not buildreason:
            buildreason = buildstatus.lower()

        if owner == req.authname or status != 20:
            port.highlight = True

        port.setStatus(status, buildreason)

        if lastport != portname:
            port.head = True
            lastport = portname

        yield port


def BuildqueueIterator(env, req):
    cursor = env.get_db_cnx().cursor()
    cursor2 = env.get_db_cnx().cursor()

    cursor.execute("SELECT buildqueue.id, owner, replace(replace(browseurl, '%OWNER%', owner), '%REVISION%', revision::text), revision, status, startdate, enddate, description FROM buildqueue, portrepositories WHERE buildqueue.repository = portrepositories.id AND owner = %s AND buildqueue.status < 95 ORDER BY buildqueue.id DESC", (req.authname,) )

    for queueid, owner, repository, revision, status, startdate, enddate, description in cursor:
        build = Build(env)
        build.queueid = queueid
        build.owner = owner
        build.repository = repository
        build.revision = revision
        build.setStatus(status)
        build.runtime = pretty_timedelta( from_utimestamp(startdate), from_utimestamp(enddate) )
        build.startdate = startdate
        build.enddate = enddate
        build.description = description

        cursor2.execute("SELECT id, buildgroup, portname, pkgversion, status, buildstatus, buildreason, buildlog, wrkdir, startdate, CASE WHEN enddate < startdate THEN extract(epoch from now())*1000000 ELSE enddate END FROM builds WHERE queueid = %s AND status <= 90 ORDER BY id", (queueid,) )

        lastport = None
        for id, group, portname, pkgversion, status, buildstatus, buildreason, buildlog, wrkdir, startdate, enddate in cursor2:
            port = Port(env)
            port.id = id
            port.group = group
            port.portname = portname
            port.pkgversion = pkgversion
            port.buildstatus = buildstatus
            port.buildlog = buildlog
            port.wrkdir = wrkdir
            port.runtime = pretty_timedelta( from_utimestamp(startdate), from_utimestamp(enddate) )
            port.startdate = startdate
            port.enddate = enddate
            port.directory = '/~%s/%s-%s' % ( owner, queueid, id )

            if buildstatus:
                port.buildstatus = buildstatus.lower()
            if buildstatus and not buildreason:
                buildreason = buildstatus.lower()

            port.setStatus(status, buildreason)

            if lastport != portname:
                port.head = True
                lastport = portname

            build.ports.append(port)

        yield build


class Buildgroup(object):
   def __init__(self, env, name):
        self.env = env
        self.clear()
        self.name = name

   def clear(self):
        self.name = None
        self.version = None
        self.arch = None
        self.type = None
        self.description = None
        self.available = None
        self.status = 'fail'
        self.priority = None
        self.priorityname = None
        self.joined = None
        self.queued = None

   def setPriority(self, priority):
        self.priority = 0
        self.priorityname = 'unknown'

        if int(priority) > 0 and int(priority) < 10:
            self.priority = int(priority)
        else:
            self.priority = 5

        if self.priority == 1:
            self.priorityname = 'highest'
        if self.priority == 3:
            self.priorityname = 'high'
        if self.priority == 5:
            self.priorityname = 'normal'
        if self.priority == 7:
            self.priorityname = 'low'
        if self.priority == 9:
            self.priorityname = 'lowest'

   def leave(self, req):
        db = self.env.get_db_cnx()
        cursor = db.cursor()
        cursor.execute("DELETE FROM automaticbuildgroups WHERE buildgroup = %s AND username = %s", ( self.name, req.authname ) )
        db.commit()

   def join(self, req):
        self.leave(req)
        db = self.env.get_db_cnx()
        cursor = db.cursor()
        cursor.execute("INSERT INTO automaticbuildgroups (username, buildgroup, priority) VALUES(%s, %s, %s)", ( req.authname, self.name, self.priority ) )
        db.commit()

   def delete(self):
        db = self.env.get_db_cnx()
        cursor = db.cursor()
        
        cursor.execute("SELECT count(*) FROM backendbuilds WHERE buildgroup = %s", ( self.name, ))
        row = cursor.fetchone()
        if not row:
            raise TracError('SQL Error')
        if row[0] > 0:
            raise TracError('Backendbuilds for this buildgroup still exist')

        cursor.execute("DELETE FROM automaticbuildgroups WHERE buildgroup = %s", (self.name,) )
        cursor.execute("DELETE FROM buildgroups WHERE name = %s", (self.name,) )
        db.commit()

   def add(self):
        db = self.env.get_db_cnx()
        cursor = db.cursor()
        cursor.execute("INSERT INTO buildgroups (name, version, arch, type, description) VALUES(%s, %s, %s, %s, %s)", ( self.name, self.version, self.arch, self.type, self.description) )
        db.commit()

   def deleteAllJobs(self):
        db = self.env.get_db_cnx()
        cursor = db.cursor()
        
        cursor.execute("SELECT id FROM builds WHERE status < 30 AND buildgroup = %s", ( self.name, ))
        for id in cursor:
            port = Port(self.env, id)
            port.delete()
        db.commit()

def BuildgroupsIterator(env, req):
   cursor = env.get_db_cnx().cursor()
   cursor2 = env.get_db_cnx().cursor()
   cursor.execute("SELECT name, version, arch, type, description, (SELECT count(*) FROM backendbuilds, backends WHERE buildgroup = name AND backendbuilds.status IN(1, 3) AND backendbuilds.backendid = backends.id AND backends.status = 1) FROM buildgroups WHERE 1=1 ORDER BY version DESC, name")

   for name, version, arch, type, description, available in cursor:
        buildgroup = Buildgroup(env, name)
        buildgroup.version = version
        buildgroup.arch = arch
        buildgroup.type = type
        buildgroup.description = description
        buildgroup.available = available

        if available > 0:
            buildgroup.status = 'success'
        if req.authname and req.authname != 'anonymous':
            cursor2.execute("SELECT priority FROM automaticbuildgroups WHERE username = %s AND buildgroup = %s", ( req.authname, name ) )

            if cursor2.rowcount > 0:
                buildgroup.joined = 'true'
                buildgroup.setPriority(cursor2.fetchall()[0][0])

        cursor2.execute("SELECT count(*) FROM builds WHERE buildgroup = %s AND status < 90", (name,) )
        if cursor2.rowcount > 0:
            buildgroup.queued = cursor2.fetchall()[0][0]
        
        yield buildgroup


def AvailableBuildgroupsIterator(env, req):
    cursor = env.get_db_cnx().cursor()
    cursor.execute("SELECT name FROM buildgroups WHERE version != '000' AND name NOT IN (SELECT buildgroup FROM automaticbuildgroups WHERE username = %s) ORDER BY version DESC, name", (req.authname,) )

    for name in cursor:
        buildgroup = Buildgroup(env, name)

        yield buildgroup


def UserBuildgroupsIterator(env):
    cursor = env.get_db_cnx().cursor()
    cursor.execute("SELECT name, version, arch, type, description FROM buildgroups WHERE version != '000' ORDER BY version DESC, name")

    for name, version, arch, type, description in cursor:
        buildgroup = Buildgroup(env, name)
        buildgroup.version = version
        buildgroup.arch = arch
        buildgroup.type = type
        buildgroup.description = description

        yield buildgroup


def AllBuildgroupsIterator(env):
    cursor = env.get_db_cnx().cursor()
    cursor.execute("SELECT name, version, arch, type, description FROM buildgroups ORDER BY version DESC, name")

    for name, version, arch, type, description in cursor:
        buildgroup = Buildgroup(env, name)
        buildgroup.version = version
        buildgroup.arch = arch
        buildgroup.type = type
        buildgroup.description = description

        yield buildgroup


def RepositoryIterator(env, req):
    cursor = env.get_db_cnx().cursor()
    cursor.execute("SELECT id, replace(name, '%OWNER%', %s), type, url, replace(browseurl, '%OWNER%', %s), username FROM portrepositories WHERE username IS NULL OR username = %s ORDER BY id", (req.authname, req.authname, req.authname))

    for id, name, type, url, browseurl, username in cursor:
        repository = PortRepository(env, id)
        repository.name = name
        repository.type = type
        repository.url = url
        repository.browseurl = browseurl
        repository.username = username

        yield repository


def PortRepositoryIterator(env):
    cursor = env.get_db_cnx().cursor()
    cursor.execute("SELECT id, name, type, url, browseurl, username FROM portrepositories ORDER BY id")

    for id, name, type, url, browseurl, username in cursor:
        repository = PortRepository(env, id)
        repository.name = name
        repository.type = type
        repository.url = url
        repository.browseurl = browseurl
        repository.username = username

        yield repository


def BackendsIterator(env):
    cursor = env.get_db_cnx().cursor()
    cursor.execute("SELECT id, protocol, host, uri, credentials, maxparallel, status, type FROM backends ORDER BY id")

    for id, protocol, host, uri, credentials, maxparallel, status, type in cursor:
        backend = Backend(env, id)
        backend.protocol = protocol
        backend.host = host
        backend.uri = uri
        backend.credentials = credentials
        backend.maxparallel = maxparallel
        backend.type = type
        backend.url = backend.getURL()
        backend.setStatus(status)

        yield backend


def BackendbuildsIterator(env):
    cursor = env.get_db_cnx().cursor()
    cursor.execute("SELECT backendbuilds.id, backendbuilds.buildgroup, backendbuilds.backendid, backendbuilds.priority, backendbuilds.status, backendbuilds.buildname, backends.protocol || '://' || backends.host || backends.uri FROM backendbuilds, backends WHERE backendbuilds.backendid = backends.id ORDER BY backendbuilds.backendid, backendbuilds.id")

    lastbackend = None
    for id, buildgroup, backendid, priority, status, buildname, backend in cursor:
        backendbuild = Backendbuild(env, id)
        backendbuild.buildgroup = buildgroup
        backendbuild.backendid = backendid
        backendbuild.priority = priority
        backendbuild.buildname = buildname
        backendbuild.backend = backend
        backendbuild.setStatus(status)

        if lastbackend != backendid:
            backendbuild.head = True
            lastbackend = backendid

        yield backendbuild


class BuildarchiveIterator(object):
    def __init__(self, env):
        self.env = env

    def filter(self, owner=None, queueid=None, revision=None, uniqueports=False):
        self.owner = owner
        self.queueid = queueid
	self.revision = int(revision)
        self.uniqueports = uniqueports

    def count(self):
        cursor = self.env.get_db_cnx().cursor()
        cursor.execute("SELECT count(*) FROM buildqueue WHERE status >= 90" + self._get_filter())
        if cursor.rowcount > 0:
            return cursor.fetchall()[0][0]

        return 0

    def _get_filter(self):
        filter = ''

        if self.queueid:
            filter += "AND buildqueue.id = '%s'" % (self.queueid)

        if self.owner:
            filter += "AND buildqueue.owner = '%s'" % (self.owner)

	if self.revision:
            filter += "AND buildqueue.revision = '%s'" % (self.revision)

        return filter

    def get_data(self, limit=100, offset=0):
        cursor = self.env.get_db_cnx().cursor()
        cursor2 = self.env.get_db_cnx().cursor()

        cursor.execute("SELECT buildqueue.id, owner, replace(replace(browseurl, '%OWNER%', buildqueue.owner), '%REVISION%', revision::text), revision, status, startdate, CASE WHEN enddate < startdate THEN startdate ELSE enddate END, description FROM buildqueue, portrepositories WHERE repository = portrepositories.id AND buildqueue.status >= 10 " + self._get_filter() + " ORDER BY buildqueue.id DESC LIMIT %s OFFSET %s", (limit, offset) )

        for queueid, owner, repository, revision, status, startdate, enddate, description in cursor:
            build = Build(self.env)
            build.queueid = queueid
            build.owner = owner
            build.repository = repository
            build.revision = revision
            build.setStatus(status)
            build.runtime = pretty_timedelta( from_utimestamp(startdate), from_utimestamp(enddate) )
            build.startdate = startdate
            build.enddate = enddate
            build.description = description

            cursor2.execute("SELECT id, buildgroup, portname, pkgversion, status, buildstatus, buildreason, buildlog, wrkdir, startdate, CASE WHEN enddate < startdate THEN extract(epoch from now())*1000000 ELSE enddate END FROM builds WHERE queueid = %s ORDER BY id", (queueid,) )

            lastport = None
            for id, group, portname, pkgversion, status, buildstatus, buildreason, buildlog, wrkdir, startdate, enddate in cursor2:
                port = Port(self.env)
                port.id = id
                port.group = group
                port.portname = portname
                port.pkgversion = pkgversion
                port.buildstatus = buildstatus
                port.buildlog = buildlog
                port.wrkdir = wrkdir
                port.runtime = pretty_timedelta( from_utimestamp(startdate), from_utimestamp(enddate) )
                port.startdate = startdate
                port.enddate = enddate
                port.directory = '/~%s/%s-%s' % ( owner, queueid, id )

                if buildstatus:
                    port.buildstatus = buildstatus.lower()
                if buildstatus and not buildreason:
                    buildreason = buildstatus.lower()

                port.setStatus(status, buildreason)

                if self.uniqueports and lastport == portname:
                    continue

                if lastport != portname:
                    port.head = True
                    lastport = portname

                build.ports.append(port)

            yield build

def BuildstatsAllIterator(env):
    cursor = env.get_db_cnx().cursor()
    cursor.execute("SELECT EXTRACT(epoch FROM date)*1000, count FROM (SELECT DATE_TRUNC('day', TO_TIMESTAMP(enddate/1000000))::timestamptz date, COUNT(*) FROM (SELECT enddate FROM builds WHERE enddate > 0) base WHERE 1=1 GROUP BY DATE_TRUNC('day', TO_TIMESTAMP(enddate/1000000)) ORDER BY 1 DESC LIMIT 30) src WHERE EXTRACT(epoch FROM src.date)::int > 0")

    for date, sum in cursor:
        yield [date, sum]

def BuildstatsUserIterator(env, username):
    cursor = env.get_db_cnx().cursor()
    cursor.execute("SELECT EXTRACT(epoch FROM date)*1000, count FROM (SELECT DATE_TRUNC('day', TO_TIMESTAMP(enddate/1000000))::timestamptz date, COUNT(*) FROM (SELECT builds.enddate FROM builds, buildqueue WHERE buildqueue.id = builds.queueid AND buildqueue.owner = %s) base WHERE 1=1 GROUP BY DATE_TRUNC('day', TO_TIMESTAMP(enddate/1000000)) ORDER BY 1 DESC LIMIT 30) src WHERE EXTRACT(epoch FROM src.date)::int > 0", (username, ))

    for date, sum in cursor:
        yield [date, sum]
