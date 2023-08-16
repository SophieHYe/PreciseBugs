from AccessControl import getSecurityManager
from Acquisition import aq_base
from collective.dms.basecontent import _
from five import grok
from html import escape
from Products.CMFCore.utils import getToolByName
from Products.CMFCore.WorkflowCore import WorkflowException
from Products.CMFPlone.utils import safe_unicode
from z3c.table import interfaces
from zope.component import getMultiAdapter
from zope.i18n import translate
from zope.i18nmessageid import MessageFactory

import Missing
import os.path
import plone.api
import z3c.table.column
import z3c.table.table


PMF = MessageFactory('plone')

grok.templatedir('templates')


class Column(z3c.table.column.Column, grok.MultiAdapter):
    grok.baseclass()
    grok.provides(interfaces.IColumn)


def get_value(item, attribute, default=None):
    try:
        value = getattr(aq_base(item), attribute)
        if value is Missing.Value:
            return default
    except AttributeError:
        obj = item.getObject()
        value = getattr(obj, attribute, default)

    if callable(value):
        value = value()

    return value


class DateColumn(Column):
    grok.baseclass()
    attribute = NotImplemented

    def renderCell(self, item):
        value = get_value(item, self.attribute)
        return self.table.format_date(value)


class DateTimeColumn(Column):
    grok.baseclass()
    attribute = NotImplemented

    def renderCell(self, item):
        value = get_value(item, self.attribute)
        return self.table.format_date(value, long_format=True)


class PrincipalColumn(Column):
    grok.baseclass()
    attribute = NotImplemented

    def renderCell(self, item):
        value = get_value(item, self.attribute, default=())

        if not isinstance(value, (list, tuple)):
            value = (value,)

        gtool = getToolByName(plone.api.portal.get(), 'portal_groups')
        mtool = getToolByName(plone.api.portal.get(), 'portal_membership')
        principals = []
        for principal_id in value:
            user = mtool.getMemberById(principal_id)
            if user is not None:
                principals.append(escape(user.getProperty('fullname', None)) or user.getId())
            else:
                group = gtool.getGroupById(principal_id)
                if group is not None:
                    principals.append(escape(group.getProperty('title', None)) or group.getId())

        return ', '.join(principals).decode('utf-8')


class LinkColumn(z3c.table.column.LinkColumn, Column):
    grok.baseclass()

    def getLinkURL(self, item):
        """Setup link url."""
        if self.linkName is not None:
            return '%s/%s' % (item.getURL(), self.linkName)
        return item.getURL()

    def renderCell(self, item):
        # setup a tag
        return '<a href="%s"%s%s%s>%s</a>' % (
            self.getLinkURL(item),  # originally escaped
            self.getLinkTarget(item),
            self.getLinkCSS(item),
            self.getLinkTitle(item),
            self.getLinkContent(item),  # originally escaped
        )


class TitleColumn(LinkColumn):
    grok.baseclass()
    header = PMF("Title")
    weight = 10

    def getLinkContent(self, item):
        title = get_value(item, 'Title')
        return escape(safe_unicode(title))


class IconColumn(LinkColumn):
    grok.baseclass()

    def getLinkContent(self, item):
        content = super(IconColumn, self).getLinkContent(item)  # escaped
        return u"""<img title="%s" src="%s" />""" % (
            content,
            '%s/%s' % (self.table.portal_url, self.iconName))


class DeleteColumn(IconColumn):
    grok.baseclass()
    header = u""
    weight = 9
    linkName = "delete_confirmation"
    linkContent = PMF('Delete')
    linkCSS = 'edm-delete-popup'
    iconName = "delete_icon.png"
    linkContent = PMF(u"Delete")

    def actionAvailable(self, item):
        obj = item.getObject()
        sm = getSecurityManager()
        return sm.checkPermission('Delete objects', obj)

    def renderCell(self, item):
        if not self.actionAvailable(item):
            return u""

        return super(DeleteColumn, self).renderCell(item)


class DownloadColumn(IconColumn):
    grok.baseclass()
    header = u""
    weight = 1
    linkName = "@@download"
    iconName = "download_icon.png"
    linkContent = _(u"Download file")


class ExternalEditColumn(IconColumn):
    grok.baseclass()
    header = u""
    weight = 3
    linkName = "@@external_edit"
    iconName = "extedit_icon.png"
    linkContent = PMF(u"Edit with external application")

    def actionAvailable(self, item):
        obj = item.getObject()
        sm = getSecurityManager()
        if not sm.checkPermission('Modify portal content', obj):
            return False

        if obj.file is None:
            return False

        ext = os.path.splitext(obj.file.filename)[-1].lower()
        if ext in (u'.pdf', u'.jpg', '.jpeg'):
            return False

        view = getMultiAdapter((obj, self.request), name='externalEditorEnabled')
        if not view.available():
            return False

        return True

    def renderCell(self, item):
        if not self.actionAvailable(item):
            return u""

        return super(ExternalEditColumn, self).renderCell(item)


class EditColumn(IconColumn):
    grok.baseclass()
    header = u""
    weight = 2
    linkName = "edit"
    iconName = "++resource++fade_edit.png"
    linkContent = PMF(u"Edit")
    linkCSS = 'overlay-form-reload'

    def actionAvailable(self, item):
        obj = item.getObject()
        sm = getSecurityManager()
        return sm.checkPermission('Modify portal content', obj)

    def renderCell(self, item):
        if not self.actionAvailable(item):
            return u""

        return super(EditColumn, self).renderCell(item)


class StateColumn(Column):
    grok.baseclass()
    header = PMF(u"State")
    weight = 50

    def renderCell(self, item):
        try:
            wtool = self.table.wtool
            portal_type = get_value(item, 'portal_type')
            review_state = get_value(item, 'review_state')
            if not review_state:
                return u""
            state_title = wtool.getTitleForStateOnType(review_state,
                                                       portal_type)
            return translate(PMF(state_title), context=self.request)
        except WorkflowException:
            return u""


class LabelColumn(Column):
    grok.baseclass()
    attribute = NotImplemented

    def renderCell(self, item):
        value = get_value(item, self.attribute)
        if value is None:
            value = ''
        return value
