##############################################################################
#
# Copyright (c) 2004 Zope Foundation and Contributors.
#
# This software is subject to the provisions of the Zope Public License,
# Version 2.1 (ZPL).  A copy of the ZPL should accompany this distribution.
# THIS SOFTWARE IS PROVIDED "AS IS" AND ANY AND ALL EXPRESS OR IMPLIED
# WARRANTIES ARE DISCLAIMED, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
# WARRANTIES OF TITLE, MERCHANTABILITY, AGAINST INFRINGEMENT, AND FITNESS
# FOR A PARTICULAR PURPOSE.
#
##############################################################################
""" Various context implementations for export / import of configurations.

Wrappers representing the state of an import / export operation.
"""

import logging
import os
import time
from io import BytesIO
from tarfile import DIRTYPE
from tarfile import TarFile
from tarfile import TarInfo

import six

from AccessControl.class_init import InitializeClass
from AccessControl.Permissions import view
from AccessControl.SecurityInfo import ClassSecurityInfo
from Acquisition import Implicit
from Acquisition import aq_base
from Acquisition import aq_inner
from Acquisition import aq_parent
from Acquisition import aq_self
from DateTime.DateTime import DateTime
from OFS.DTMLDocument import DTMLDocument
from OFS.Folder import Folder
from OFS.Image import File
from OFS.Image import Image
from Products.PageTemplates.ZopePageTemplate import ZopePageTemplate
from Products.PythonScripts.PythonScript import PythonScript
from zope.interface import implementer

from .interfaces import SKIPPED_FILES
from .interfaces import SKIPPED_SUFFIXES
from .interfaces import IChunkableExportContext
from .interfaces import IChunkableImportContext
from .interfaces import IExportContext
from .interfaces import IImportContext
from .interfaces import ISetupEnviron
from .interfaces import IWriteLogger
from .permissions import ManagePortal


@implementer(IWriteLogger)
class Logger:

    def __init__(self, id, messages):
        """Initialize the logger with a name and an optional level.
        """
        self._id = id
        self._messages = messages
        self._logger = logging.getLogger('GenericSetup.%s' % id)

    def debug(self, msg, *args, **kwargs):
        """Log 'msg % args' with severity 'DEBUG'.
        """
        self.log(logging.DEBUG, msg, *args, **kwargs)

    def info(self, msg, *args, **kwargs):
        """Log 'msg % args' with severity 'INFO'.
        """
        self.log(logging.INFO, msg, *args, **kwargs)

    def warning(self, msg, *args, **kwargs):
        """Log 'msg % args' with severity 'WARNING'.
        """
        self.log(logging.WARNING, msg, *args, **kwargs)

    def error(self, msg, *args, **kwargs):
        """Log 'msg % args' with severity 'ERROR'.
        """
        self.log(logging.ERROR, msg, *args, **kwargs)

    def exception(self, msg, *args):
        """Convenience method for logging an ERROR with exception information.
        """
        self.error(msg, *args, **{'exc_info': 1})

    def critical(self, msg, *args, **kwargs):
        """Log 'msg % args' with severity 'CRITICAL'.
        """
        self.log(logging.CRITICAL, msg, *args, **kwargs)

    def log(self, level, msg, *args, **kwargs):
        """Log 'msg % args' with the integer severity 'level'.
        """
        self._messages.append((level, self._id, msg))
        self._logger.log(level, msg, *args, **kwargs)


@implementer(ISetupEnviron)
class SetupEnviron(Implicit):

    """Context for body im- and exporter.
    """

    security = ClassSecurityInfo()

    def __init__(self):
        self._should_purge = True

    @security.protected(ManagePortal)
    def getLogger(self, name):
        """Get a logger with the specified name, creating it if necessary.
        """
        return logging.getLogger('GenericSetup.%s' % name)

    @security.protected(ManagePortal)
    def shouldPurge(self):
        """When installing, should the existing setup be purged?
        """
        return self._should_purge


InitializeClass(SetupEnviron)


class BaseContext(SetupEnviron):

    security = ClassSecurityInfo()

    def __init__(self, tool, encoding):

        self._tool = tool
        self._site = aq_parent(aq_inner(tool))
        self._loggers = {}
        self._messages = []
        self._encoding = encoding
        self._should_purge = True

    @security.protected(ManagePortal)
    def getSite(self):
        """ See ISetupContext.
        """
        return aq_self(self._site)

    @security.protected(ManagePortal)
    def getSetupTool(self):
        """ See ISetupContext.
        """
        return self._tool

    @security.protected(ManagePortal)
    def getEncoding(self):
        """ See ISetupContext.
        """
        return self._encoding

    @security.protected(ManagePortal)
    def getLogger(self, name):
        """ See ISetupContext.
        """
        return self._loggers.setdefault(name, Logger(name, self._messages))

    @security.protected(ManagePortal)
    def listNotes(self):
        """ See ISetupContext.
        """
        return self._messages[:]

    @security.protected(ManagePortal)
    def clearNotes(self):
        """ See ISetupContext.
        """
        self._messages[:] = []


InitializeClass(BaseContext)


@implementer(IChunkableImportContext)
class DirectoryImportContext(BaseContext):

    security = ClassSecurityInfo()

    def __init__(self, tool, profile_path, should_purge=False,
                 encoding=None):

        BaseContext.__init__(self, tool, encoding)
        self._profile_path = profile_path
        self._should_purge = bool(should_purge)

    @security.protected(ManagePortal)
    def openDataFile(self, filename, subdir=None):
        """ See IImportContext.
        """
        if subdir is None:
            full_path = os.path.join(self._profile_path, filename)
        else:
            full_path = os.path.join(self._profile_path, subdir, filename)

        if not os.path.exists(full_path):
            return None

        return open(full_path, 'rb')

    @security.protected(ManagePortal)
    def readDataFile(self, filename, subdir=None):
        """ See IImportContext.
        """
        result = None
        file = self.openDataFile(filename, subdir)
        if file is not None:
            result = file.read()
            file.close()
        return result

    @security.protected(ManagePortal)
    def getLastModified(self, path):
        """ See IImportContext.
        """
        full_path = os.path.join(self._profile_path, path)

        if not os.path.exists(full_path):
            return None

        return DateTime(os.path.getmtime(full_path))

    @security.protected(ManagePortal)
    def isDirectory(self, path):
        """ See IImportContext.
        """
        full_path = os.path.join(self._profile_path, path)

        if not os.path.exists(full_path):
            return None

        return os.path.isdir(full_path)

    @security.protected(ManagePortal)
    def listDirectory(self, path, skip=SKIPPED_FILES,
                      skip_suffixes=SKIPPED_SUFFIXES):
        """ See IImportContext.
        """
        if path is None:
            path = ''

        full_path = os.path.join(self._profile_path, path)

        if not os.path.exists(full_path) or not os.path.isdir(full_path):
            return None

        names = []
        for name in os.listdir(full_path):
            if name in skip:
                continue
            if [s for s in skip_suffixes if name.endswith(s)]:
                continue
            names.append(name)

        return names


InitializeClass(DirectoryImportContext)


@implementer(IChunkableExportContext)
class DirectoryExportContext(BaseContext):

    security = ClassSecurityInfo()

    def __init__(self, tool, profile_path, encoding=None):

        BaseContext.__init__(self, tool, encoding)
        self._profile_path = profile_path

    @security.protected(ManagePortal)
    def openDataFile(self, filename, content_type, subdir=None):
        """ See IChunkableExportContext.
        """
        if subdir is None:
            prefix = self._profile_path
        else:
            prefix = os.path.join(self._profile_path, subdir)

        full_path = os.path.join(prefix, filename)

        if not os.path.exists(prefix):
            os.makedirs(prefix)

        return open(full_path, 'wb')

    @security.protected(ManagePortal)
    def writeDataFile(self, filename, text, content_type, subdir=None):
        """ See IExportContext.
        """
        if isinstance(text, six.text_type):
            encoding = self.getEncoding() or 'utf-8'
            text = text.encode(encoding)
        file = self.openDataFile(filename, content_type, subdir)
        file.write(text)
        file.close()


InitializeClass(DirectoryExportContext)


@implementer(IImportContext)
class TarballImportContext(BaseContext):

    security = ClassSecurityInfo()

    def __init__(self, tool, archive_bits, encoding=None, should_purge=False):
        BaseContext.__init__(self, tool, encoding)
        self._archive_stream = BytesIO(archive_bits)
        self._archive = TarFile.open('foo.bar', 'r:gz', self._archive_stream)
        self._should_purge = bool(should_purge)

    def readDataFile(self, filename, subdir=None):
        """ See IImportContext.
        """
        if subdir is not None:
            filename = '/'.join((subdir, filename))

        try:
            file = self._archive.extractfile(filename)
        except KeyError:
            return None

        return file.read()

    def getLastModified(self, path):
        """ See IImportContext.
        """
        info = self._getTarInfo(path)
        return info and DateTime(info.mtime) or None

    def isDirectory(self, path):
        """ See IImportContext.
        """
        info = self._getTarInfo(path)

        if info is not None:
            return info.isdir()

    def listDirectory(self, path, skip=SKIPPED_FILES,
                      skip_suffixes=SKIPPED_SUFFIXES):
        """ See IImportContext.
        """
        if path is None:  # root is special case:  no leading '/'
            path = ''
        else:
            if not self.isDirectory(path):
                return None

            if not path.endswith('/'):
                path = path + '/'

        pfx_len = len(path)

        names = []
        for info in self._archive.getmembers():
            name = info.name.rstrip('/')
            if name == path or not name.startswith(path):
                continue
            name = name[pfx_len:]
            if '/' in name:
                # filter out items in subdirs
                continue
            if name in skip:
                continue
            if [s for s in skip_suffixes if name.endswith(s)]:
                continue
            names.append(name)

        return names

    def shouldPurge(self):
        """ See IImportContext.
        """
        return self._should_purge

    def _getTarInfo(self, path):
        if path.endswith('/'):
            path = path[:-1]
        try:
            return self._archive.getmember(path)
        except KeyError:
            pass
        try:
            return self._archive.getmember(path + '/')
        except KeyError:
            return None


InitializeClass(TarballImportContext)


@implementer(IExportContext)
class TarballExportContext(BaseContext):

    security = ClassSecurityInfo()

    def __init__(self, tool, encoding=None):

        BaseContext.__init__(self, tool, encoding)

        timestamp = time.gmtime()
        archive_name = ('setup_tool-%4d%02d%02d%02d%02d%02d.tar.gz'
                        % timestamp[:6])

        self._archive_stream = BytesIO()
        self._archive_filename = archive_name
        self._archive = TarFile.open(archive_name, 'w:gz',
                                     self._archive_stream)

    @security.protected(ManagePortal)
    def writeDataFile(self, filename, text, content_type, subdir=None):
        """ See IExportContext.
        """
        if subdir is not None:
            filename = '/'.join((subdir, filename))

        parents = filename.split('/')[:-1]
        while parents:
            path = '/'.join(parents) + '/'
            if path not in self._archive.getnames():
                info = TarInfo(path)
                info.type = DIRTYPE
                # tarfile.filemode(0o755) == '-rwxr-xr-x'
                info.mode = 0o755
                info.mtime = time.time()
                self._archive.addfile(info)
            parents.pop()

        info = TarInfo(filename)
        if isinstance(text, six.text_type):
            encoding = self.getEncoding() or 'utf-8'
            text = text.encode(encoding)

        if isinstance(text, six.binary_type):
            stream = BytesIO(text)
            info.size = len(text)
        else:
            # Assume text is a an instance of a class like
            # Products.Archetypes.WebDAVSupport.PdataStreamIterator,
            # as in the case of ATFile
            stream = text.file
            info.size = text.size
        info.mtime = time.time()
        self._archive.addfile(info, stream)

    @security.protected(ManagePortal)
    def getArchive(self):
        """ Close the archive, and return it as a big string.
        """
        self._archive.close()
        return self._archive_stream.getvalue()

    @security.protected(ManagePortal)
    def getArchiveFilename(self):
        """ Close the archive, and return it as a big string.
        """
        return self._archive_filename


InitializeClass(TarballExportContext)


@implementer(IExportContext)
class SnapshotExportContext(BaseContext):

    security = ClassSecurityInfo()

    def __init__(self, tool, snapshot_id, encoding=None):

        BaseContext.__init__(self, tool, encoding)
        self._snapshot_id = snapshot_id

    @security.protected(ManagePortal)
    def writeDataFile(self, filename, text, content_type, subdir=None):
        """ See IExportContext.
        """
        if subdir is not None:
            filename = '/'.join((subdir, filename))

        sep = filename.rfind('/')
        if sep != -1:
            subdir = filename[:sep]
            filename = filename[sep+1:]

        if six.PY2 and isinstance(text, six.text_type):
            encoding = self.getEncoding() or 'utf-8'
            text = text.encode(encoding)

        folder = self._ensureSnapshotsFolder(subdir)

        # MISSING: switch on content_type
        ob = self._createObjectByType(filename, text, content_type)
        folder._setObject(str(filename), ob)  # No Unicode IDs!
        # Tighten the View permission on the new object.
        # Only the owner and Manager users may view the log.
        # file_ob = self._getOb(name)
        ob.manage_permission(view, ('Manager', 'Owner'), 0)

    @security.protected(ManagePortal)
    def getSnapshotURL(self):
        """ See IExportContext.
        """
        return '%s/%s' % (self._tool.absolute_url(), self._snapshot_id)

    @security.protected(ManagePortal)
    def getSnapshotFolder(self):
        """ See IExportContext.
        """
        return self._ensureSnapshotsFolder()

    #
    #   Helper methods
    #
    @security.private
    def _createObjectByType(self, name, body, content_type):
        encoding = self.getEncoding() or 'utf-8'

        if six.PY2 and isinstance(body, six.text_type):
            body = body.encode(encoding)

        if name.endswith('.py'):
            ob = PythonScript(name)
            ob.write(body)
            return ob

        if name.endswith('.dtml'):
            ob = DTMLDocument('', __name__=name)
            ob.munge(body)
            return ob

        if content_type in ('text/html', 'text/xml'):
            return ZopePageTemplate(name, body, content_type=content_type)

        if isinstance(body, six.text_type):
            body = body.encode(encoding)

        if content_type[:6] == 'image/':
            return Image(name, '', body, content_type=content_type)

        return File(name, '', body, content_type=content_type)

    @security.private
    def _ensureSnapshotsFolder(self, subdir=None):
        """ Ensure that the appropriate snapshot folder exists.
        """
        path = ['snapshots', self._snapshot_id]

        if subdir is not None:
            path.extend(subdir.split('/'))

        current = self._tool

        for element in path:

            if element not in current.objectIds():
                # No Unicode IDs!
                current._setObject(str(element), Folder(element))
                current = current._getOb(element)
                current.manage_permission(view, ('Manager', 'Owner'), 0)
            else:
                current = current._getOb(element)

        return current


InitializeClass(SnapshotExportContext)


@implementer(IImportContext)
class SnapshotImportContext(BaseContext):

    security = ClassSecurityInfo()

    def __init__(self, tool, snapshot_id, should_purge=False, encoding=None):
        BaseContext.__init__(self, tool, encoding)
        self._snapshot_id = snapshot_id
        self._encoding = encoding
        self._should_purge = bool(should_purge)

    @security.protected(ManagePortal)
    def readDataFile(self, filename, subdir=None):
        """ See IImportContext.
        """
        if subdir is not None:
            filename = '/'.join((subdir, filename))

        sep = filename.rfind('/')
        if sep != -1:
            subdir = filename[:sep]
            filename = filename[sep+1:]
        try:
            snapshot = self._getSnapshotFolder(subdir)
            object = snapshot._getOb(filename)
        except (AttributeError, KeyError):
            return None

        if isinstance(object, File):
            # OFS File Object have only one way to access the raw
            # data directly, __str__. The code explicitly forbids
            # to store unicode, so str() is safe here
            data = six.binary_type(aq_base(object.data))
        else:
            data = object.read()
        if isinstance(data, six.text_type):
            data = data.encode('utf-8')
        return data

    @security.protected(ManagePortal)
    def getLastModified(self, path):
        """ See IImportContext.
        """
        try:
            snapshot = self._getSnapshotFolder()
            object = snapshot.restrictedTraverse(path)
        except (AttributeError, KeyError):
            return None
        else:
            mtime = getattr(object, '_p_mtime', None)
            if mtime is None:
                # test hook
                mtime = getattr(object, '_faux_mod_time', None)
                if mtime is None:
                    return DateTime()
            return DateTime(mtime)

    @security.protected(ManagePortal)
    def isDirectory(self, path):
        """ See IImportContext.
        """
        try:
            snapshot = self._getSnapshotFolder()
            object = snapshot.restrictedTraverse(str(path))
        except (AttributeError, KeyError):
            return None
        else:
            folderish = getattr(object, 'isPrincipiaFolderish', False)
            return bool(folderish)

    @security.protected(ManagePortal)
    def listDirectory(self, path, skip=(), skip_suffixes=()):
        """ See IImportContext.
        """
        try:
            snapshot = self._getSnapshotFolder()
            subdir = snapshot.restrictedTraverse(path)
        except (AttributeError, KeyError):
            return None
        else:
            if not getattr(subdir, 'isPrincipiaFolderish', False):
                return None

            names = []
            for name in subdir.objectIds():
                if name in skip:
                    continue
                if [s for s in skip_suffixes if name.endswith(s)]:
                    continue
                names.append(name)

            return names

    @security.protected(ManagePortal)
    def shouldPurge(self):
        """ See IImportContext.
        """
        return self._should_purge

    #
    #   Helper methods
    #
    @security.private
    def _getSnapshotFolder(self, subdir=None):
        """ Return the appropriate snapshot (sub)folder.
        """
        path = ['snapshots', self._snapshot_id]

        if subdir is not None:
            path.extend(subdir.split('/'))

        return self._tool.restrictedTraverse(path)


InitializeClass(SnapshotImportContext)
