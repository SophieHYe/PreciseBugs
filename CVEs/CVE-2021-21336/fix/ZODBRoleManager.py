##############################################################################
#
# Copyright (c) 2001 Zope Foundation and Contributors
#
# This software is subject to the provisions of the Zope Public License,
# Version 2.1 (ZPL).  A copy of the ZPL should accompany this
# distribution.
# THIS SOFTWARE IS PROVIDED "AS IS" AND ANY AND ALL EXPRESS OR IMPLIED
# WARRANTIES ARE DISCLAIMED, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
# WARRANTIES OF TITLE, MERCHANTABILITY, AGAINST INFRINGEMENT, AND FITNESS
# FOR A PARTICULAR PURPOSE.
#
##############################################################################
""" Classes: ZODBRoleManager
"""

import logging

from AccessControl import ClassSecurityInfo
from AccessControl.class_init import InitializeClass
from AccessControl.requestmethod import postonly
from Acquisition import aq_inner
from Acquisition import aq_parent
from BTrees.OOBTree import OOBTree
from Products.PageTemplates.PageTemplateFile import PageTemplateFile
from zope.interface import Interface

from ..interfaces.plugins import IRoleAssignerPlugin
from ..interfaces.plugins import IRoleEnumerationPlugin
from ..interfaces.plugins import IRolesPlugin
from ..permissions import ManageUsers
from ..plugins.BasePlugin import BasePlugin
from ..utils import classImplements
from ..utils import csrf_only


LOG = logging.getLogger('PluggableAuthService')


class MultiplePrincipalError(Exception):
    pass


class IZODBRoleManager(Interface):
    """ Marker interface.
    """


manage_addZODBRoleManagerForm = PageTemplateFile(
    'www/zrAdd', globals(), __name__='manage_addZODBRoleManagerForm')


def addZODBRoleManager(dispatcher, id, title=None, REQUEST=None):
    """ Add a ZODBRoleManager to a Pluggable Auth Service. """

    zum = ZODBRoleManager(id, title)
    dispatcher._setObject(zum.getId(), zum)

    if REQUEST is not None:
        REQUEST['RESPONSE'].redirect('%s/manage_workspace'
                                     '?manage_tabs_message='
                                     'ZODBRoleManager+added.' %
                                     dispatcher.absolute_url())


class ZODBRoleManager(BasePlugin):

    """ PAS plugin for managing roles in the ZODB.
    """
    meta_type = 'ZODB Role Manager'
    zmi_icon = 'fas fa-user-tag'

    security = ClassSecurityInfo()

    def __init__(self, id, title=None):

        self._id = self.id = id
        self.title = title

        self._roles = OOBTree()
        self._principal_roles = OOBTree()

    def manage_afterAdd(self, item, container):

        if item is self:
            role_holder = aq_parent(aq_inner(container))
            for role in getattr(role_holder, '__ac_roles__', ()):
                try:
                    if role not in ('Anonymous', 'Authenticated'):
                        self.addRole(role)
                except KeyError:
                    pass

        if 'Manager' not in self._roles:
            self.addRole('Manager')

    #
    #   IRolesPlugin implementation
    #
    @security.private
    def getRolesForPrincipal(self, principal, request=None):
        """ See IRolesPlugin.
        """
        result = list(self._principal_roles.get(principal.getId(), ()))

        getGroups = getattr(principal, 'getGroups', lambda: ())
        for group_id in getGroups():
            result.extend(self._principal_roles.get(group_id, ()))

        return tuple(result)

    #
    #   IRoleEnumerationPlugin implementation
    #
    @security.private
    def enumerateRoles(self, id=None, exact_match=False, sort_by=None,
                       max_results=None, **kw):
        """ See IRoleEnumerationPlugin.
        """
        role_info = []
        role_ids = []
        plugin_id = self.getId()

        if isinstance(id, str):
            id = [id]

        if exact_match and (id):
            role_ids.extend(id)

        if role_ids:
            role_filter = None

        else:   # Searching
            role_ids = self.listRoleIds()
            role_filter = _ZODBRoleFilter(id, **kw)

        for role_id in role_ids:

            if self._roles.get(role_id):
                e_url = '%s/manage_roles' % self.getId()
                p_qs = 'role_id=%s' % role_id
                m_qs = 'role_id=%s&assign=1' % role_id

                info = {}
                info.update(self._roles[role_id])

                info['pluginid'] = plugin_id
                info['properties_url'] = '%s?%s' % (e_url, p_qs)
                info['members_url'] = '%s?%s' % (e_url, m_qs)

                if not role_filter or role_filter(info):
                    role_info.append(info)

        return tuple(role_info)

    #
    #   IRoleAssignerPlugin implementation
    #
    @security.private
    def doAssignRoleToPrincipal(self, principal_id, role):
        return self.assignRoleToPrincipal(role, principal_id)

    @security.private
    def doRemoveRoleFromPrincipal(self, principal_id, role):
        return self.removeRoleFromPrincipal(role, principal_id)

    #
    #   Role management API
    #
    @security.protected(ManageUsers)
    def listRoleIds(self):
        """ Return a list of the role IDs managed by this object.
        """
        return self._roles.keys()

    @security.protected(ManageUsers)
    def listRoleInfo(self):
        """ Return a list of the role mappings.
        """
        return self._roles.values()

    @security.protected(ManageUsers)
    def getRoleInfo(self, role_id):
        """ Return a role mapping.
        """
        return self._roles[role_id]

    @security.private
    def addRole(self, role_id, title='', description=''):
        """ Add 'role_id' to the list of roles managed by this object.

        o Raise KeyError on duplicate.
        """
        if self._roles.get(role_id) is not None:
            raise KeyError('Duplicate role: %s' % role_id)

        self._roles[role_id] = {'id': role_id, 'title': title,
                                'description': description}

    @security.private
    def updateRole(self, role_id, title, description):
        """ Update title and description for the role.

        o Raise KeyError if not found.
        """
        self._roles[role_id].update({'title': title,
                                     'description': description})

    @security.private
    def removeRole(self, role_id, REQUEST=None):
        """ Remove 'role_id' from the list of roles managed by this object.

        o Raise KeyError if not found.

        Note that if you really want to remove a role you should first
        remove it from the roles in the root of the site (at the
        bottom of the Security tab at manage_access).
        """
        for principal_id in self._principal_roles.keys():
            self.removeRoleFromPrincipal(role_id, principal_id)

        del self._roles[role_id]

    #
    #   Role assignment API
    #
    @security.protected(ManageUsers)
    def listAvailablePrincipals(self, role_id, search_id):
        """ Return a list of principal IDs to whom a role can be assigned.

        o If supplied, 'search_id' constrains the principal IDs;  if not,
          return empty list.

        o Omit principals with existing assignments.
        """
        result = []

        if search_id:  # don't bother searching if no criteria

            parent = aq_parent(self)

            for info in parent.searchPrincipals(max_results=20,
                                                sort_by='id',
                                                id=search_id,
                                                exact_match=False):
                id = info['id']
                title = info.get('title', id)
                if role_id not in self._principal_roles.get(id, ()) and \
                        role_id != id:
                    result.append((id, title))

        return result

    @security.protected(ManageUsers)
    def listAssignedPrincipals(self, role_id):
        """ Return a list of principal IDs to whom a role is assigned.
        """
        result = []

        for k, v in self._principal_roles.items():
            if role_id in v:
                # should be at most one and only one mapping to 'k'

                parent = aq_parent(self)
                info = parent.searchPrincipals(id=k, exact_match=True)

                if len(info) > 1:
                    message = ('Multiple groups or users exist with the '
                               'name "%s". Remove one of the duplicate groups '
                               'or users.' % (k))
                    LOG.error(message)
                    raise MultiplePrincipalError(message)

                if len(info) == 0:
                    title = '<%s: not found>' % k
                else:
                    title = info[0].get('title', k)
                result.append((k, title))

        return result

    @security.private
    def assignRoleToPrincipal(self, role_id, principal_id):
        """ Assign a role to a principal (user or group).

        o Return a boolean indicating whether a new assignment was created.

        o Raise KeyError if 'role_id' is unknown.
        """
        # raise KeyError if unknown!
        role_info = self._roles[role_id]  # noqa

        current = self._principal_roles.get(principal_id, ())
        already = role_id in current

        if not already:
            new = current + (role_id,)
            self._principal_roles[principal_id] = new
            self._invalidatePrincipalCache(principal_id)

        return not already

    @security.private
    def removeRoleFromPrincipal(self, role_id, principal_id):
        """ Remove a role from a principal (user or group).

        o Return a boolean indicating whether the role was already present.

        o Raise KeyError if 'role_id' is unknown.

        o Ignore requests to remove a role not already assigned to the
          principal.
        """
        # raise KeyError if unknown!
        role_info = self._roles[role_id]  # noqa

        current = self._principal_roles.get(principal_id, ())
        new = tuple([x for x in current if x != role_id])
        already = current != new

        if already:
            self._principal_roles[principal_id] = new
            self._invalidatePrincipalCache(principal_id)

        return already

    #
    #   ZMI
    #
    manage_options = (({'label': 'Roles', 'action': 'manage_roles'},)
                      + BasePlugin.manage_options)

    security.declareProtected(ManageUsers, 'manage_roles')  # NOQA: D001
    manage_roles = PageTemplateFile('www/zrRoles', globals(),
                                    __name__='manage_roles')

    security.declareProtected(ManageUsers, 'manage_twoLists')  # NOQA: D001
    manage_twoLists = PageTemplateFile('../www/two_lists', globals(),
                                       __name__='manage_twoLists')

    @security.protected(ManageUsers)
    @csrf_only
    @postonly
    def manage_addRole(self, role_id, title, description, RESPONSE=None,
                       REQUEST=None):
        """ Add a role via the ZMI.
        """
        if not role_id:
            message = 'Please+provide+a+Role+ID'
        else:
            self.addRole(role_id, title, description)
            message = 'Role+added'

        if RESPONSE is not None:
            RESPONSE.redirect('%s/manage_roles?manage_tabs_message=%s' %
                              (self.absolute_url(), message))

    @security.protected(ManageUsers)
    @csrf_only
    @postonly
    def manage_updateRole(self, role_id, title, description, RESPONSE=None,
                          REQUEST=None):
        """ Update a role via the ZMI.
        """
        self.updateRole(role_id, title, description)

        message = 'Role+updated'

        if RESPONSE is not None:
            RESPONSE.redirect('%s/manage_roles?role_id=%s&'
                              'manage_tabs_message=%s' %
                              (self.absolute_url(), role_id, message))

    @security.protected(ManageUsers)
    @csrf_only
    @postonly
    def manage_removeRoles(self, role_ids, RESPONSE=None, REQUEST=None):
        """ Remove one or more role assignments via the ZMI.

        Note that if you really want to remove a role you should first
        remove it from the roles in the root of the site (at the
        bottom of the Security tab at manage_access).
        """
        role_ids = [_f for _f in role_ids if _f]

        if not role_ids:
            message = 'no+roles+selected'

        else:

            for role_id in role_ids:
                self.removeRole(role_id)

            message = 'Role+assignments+removed'

        if RESPONSE is not None:
            RESPONSE.redirect('%s/manage_roles?manage_tabs_message=%s' %
                              (self.absolute_url(), message))

    @security.protected(ManageUsers)
    @csrf_only
    @postonly
    def manage_assignRoleToPrincipals(self, role_id, principal_ids,
                                      RESPONSE, REQUEST=None):
        """ Assign a role to one or more principals via the ZMI.
        """
        assigned = []

        for principal_id in principal_ids:
            if self.assignRoleToPrincipal(role_id, principal_id):
                assigned.append(principal_id)

        if not assigned:
            message = 'Role+%s+already+assigned+to+all+principals' % role_id
        else:
            message = 'Role+%s+assigned+to+%s' % (role_id, '+'.join(assigned))

        if RESPONSE is not None:
            RESPONSE.redirect('%s/manage_roles?role_id=%s&assign=1'
                              '&manage_tabs_message=%s' %
                              (self.absolute_url(), role_id, message))

    @security.protected(ManageUsers)
    @csrf_only
    @postonly
    def manage_removeRoleFromPrincipals(self, role_id, principal_ids,
                                        RESPONSE=None, REQUEST=None):
        """ Remove a role from one or more principals via the ZMI.
        """
        removed = []

        for principal_id in principal_ids:
            if self.removeRoleFromPrincipal(role_id, principal_id):
                removed.append(principal_id)

        if not removed:
            message = 'Role+%s+alread+removed+from+all+principals' % role_id
        else:
            message = 'Role+%s+removed+from+%s' % (role_id, '+'.join(removed))

        if RESPONSE is not None:
            RESPONSE.redirect('%s/manage_roles?role_id=%s&assign=1'
                              '&manage_tabs_message=%s' %
                              (self.absolute_url(), role_id, message))


classImplements(ZODBRoleManager, IZODBRoleManager, IRolesPlugin,
                IRoleEnumerationPlugin, IRoleAssignerPlugin)


InitializeClass(ZODBRoleManager)


class _ZODBRoleFilter:

    def __init__(self, id=None, **kw):

        self._filter_ids = id

    def __call__(self, role_info):

        if self._filter_ids:

            key = 'id'

        else:
            return 1  # ???:  try using 'kw'

        value = role_info.get(key)

        if not value:
            return False

        for id in self._filter_ids:
            if value.find(id) >= 0:
                return 1

        return False
