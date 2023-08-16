# -*- coding: utf-8 -*-
"""Define tables and columns."""

from collective.task import _
from collective.task import PMF
from collective.task.adapters import EMPTY_STRING
from plone import api
from Products.CMFPlone.utils import normalizeString
from Products.CMFPlone.utils import safe_unicode
from z3c.table.column import Column
from z3c.table.column import LinkColumn
from z3c.table.table import Table
from zope.cachedescriptors.property import CachedProperty
from zope.i18n import translate

try:
    from imio.prettylink.interfaces import IPrettyLink
except ImportError:
    pass


class TasksTable(Table):

    """Table that displays tasks info."""

    cssClassEven = u'even'
    cssClassOdd = u'odd'
    cssClasses = {'table': 'listing taskContainerListing icons-on'}

    batchSize = 20
    startBatchingAt = 30
    results = []

    @CachedProperty
    def translation_service(self):
        return api.portal.get_tool('translation_service')

    @CachedProperty
    def wtool(self):
        return api.portal.get_tool('portal_workflow')

    @CachedProperty
    def portal_url(self):
        return api.portal.get().absolute_url()

    @CachedProperty
    def values(self):
        return self.results


class UserColumn(Column):

    """Base user column."""

    field = NotImplemented

    def renderCell(self, value):
        username = getattr(value, self.field, '')
        if username and username != EMPTY_STRING:
            member = api.user.get(username)
            return member.getUser().getProperty('fullname').decode('utf-8')

        return ""


class TitleColumn(LinkColumn):

    """Column that displays title."""

    header = PMF("Title")
    weight = 10

    def getLinkCSS(self, item):
        return ' class="state-%s contenttype-%s"' % (api.content.get_state(obj=item),
                                                     normalizeString(item.portal_type))

    def getLinkContent(self, item):
        return safe_unicode(item.title)


class PrettyLinkTitleColumn(TitleColumn):

    """Column that displays prettylink title."""

    header = PMF("Title")
    weight = 10

    params = {}

    def getPrettyLink(self, obj):
        pl = IPrettyLink(obj)
        for k, v in self.params.items():
            setattr(pl, k, v)
        return pl.getLink()

    def renderCell(self, item):
        """ """
        return self.getPrettyLink(item)


class EnquirerColumn(UserColumn):

    """Column that displays enquirer."""

    header = _("Enquirer")
    weight = 20
    field = 'enquirer'


class AssignedGroupColumn(Column):

    """Column that displays assigned group."""

    header = _("Assigned group")
    weight = 30

    def renderCell(self, value):
        if value.assigned_group:
            group = api.group.get(value.assigned_group).getGroup()
            return group.getProperty('title').decode('utf-8')

        return ""


class AssignedUserColumn(UserColumn):

    """Column that displays assigned user."""

    header = _("Assigned user")
    weight = 40
    field = 'assigned_user'


class DueDateColumn(Column):

    """Column that displays due date."""

    header = _("Due date")
    weight = 50
    long_format = False
    time_only = False

    def renderCell(self, value):
        if value.due_date:
            return api.portal.get_localized_time(datetime=value.due_date, long_format=self.long_format,
                                                 time_only=self.time_only)

        return ""


class ReviewStateColumn(Column):

    """Column that displays value's review state."""

    header = PMF("Review state")
    weight = 60

    def renderCell(self, value):
        state = api.content.get_state(value)
        if state:
            wtool = api.portal.get_tool('portal_workflow')
            state_title = wtool.getTitleForStateOnType(state, value.portal_type)
            return translate(PMF(state_title), context=self.request)

        return ''
