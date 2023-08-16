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
""" Class: CookieAuthHelper
"""

try:
    from base64 import decodebytes
    from base64 import encodebytes
except ImportError:  # Python < 3.1
    from base64 import decodestring as decodebytes
    from base64 import encodestring as encodebytes

import codecs
from binascii import Error
from binascii import hexlify

import six
from six.moves.urllib.parse import quote
from six.moves.urllib.parse import unquote

from AccessControl.class_init import InitializeClass
from AccessControl.Permissions import view
from AccessControl.SecurityInfo import ClassSecurityInfo
from OFS.Folder import Folder
from Products.PageTemplates.PageTemplateFile import PageTemplateFile
from Products.PageTemplates.ZopePageTemplate import ZopePageTemplate
from zope.interface import Interface

from ..interfaces.plugins import IChallengePlugin
from ..interfaces.plugins import ICredentialsResetPlugin
from ..interfaces.plugins import ICredentialsUpdatePlugin
from ..interfaces.plugins import ILoginPasswordHostExtractionPlugin
from ..plugins.BasePlugin import BasePlugin
from ..utils import classImplements


class ICookieAuthHelper(Interface):
    """ Marker interface.
    """


manage_addCookieAuthHelperForm = PageTemplateFile(
    'www/caAdd', globals(), __name__='manage_addCookieAuthHelperForm')


def addCookieAuthHelper(dispatcher, id, title=None, cookie_name='',
                        REQUEST=None):
    """ Add a Cookie Auth Helper to a Pluggable Auth Service. """
    sp = CookieAuthHelper(id, title, cookie_name)
    dispatcher._setObject(sp.getId(), sp)

    if REQUEST is not None:
        REQUEST['RESPONSE'].redirect('%s/manage_workspace'
                                     '?manage_tabs_message='
                                     'CookieAuthHelper+added.' %
                                     dispatcher.absolute_url())


def decode_cookie(raw):
    value = unquote(raw)
    if six.PY3:
        value = value.encode('utf8')
    value = decodebytes(value)
    if six.PY3:
        value = value.decode('utf8')
    return value


def decode_hex(raw):
    if isinstance(raw, six.text_type):
        raw = raw.encode('utf8')
    value = codecs.decode(raw, 'hex_codec')
    if six.PY3:
        value = value.decode('utf-8')
    return value


class CookieAuthHelper(Folder, BasePlugin):
    """ Multi-plugin for managing details of Cookie Authentication. """

    meta_type = 'Cookie Auth Helper'
    zmi_icon = 'fas fa-cookie-bite'
    cookie_name = '__ginger_snap'
    login_path = 'login_form'
    security = ClassSecurityInfo()

    _properties = ({'id': 'title', 'label': 'Title',
                    'type': 'string', 'mode': 'w'},
                   {'id': 'cookie_name', 'label': 'Cookie Name',
                    'type': 'string', 'mode': 'w'},
                   {'id': 'login_path', 'label': 'Login Form',
                    'type': 'string', 'mode': 'w'})

    manage_options = (BasePlugin.manage_options[:1]
                      + Folder.manage_options[:1]
                      + Folder.manage_options[2:])

    def __init__(self, id, title=None, cookie_name=''):
        self._setId(id)
        self.title = title

        if cookie_name:
            self.cookie_name = cookie_name

    @security.private
    def extractCredentials(self, request):
        """ Extract credentials from cookie or 'request'. """
        creds = {}
        cookie = request.get(self.cookie_name, '')
        # Look in the request.form for the names coming from the login form
        login = request.form.get('__ac_name', '')

        if login and '__ac_password' in request.form:
            creds['login'] = login
            creds['password'] = request.form.get('__ac_password', '')

        elif cookie and cookie != 'deleted':
            try:
                cookie_val = decode_cookie(cookie)
            except Error:
                # Cookie is in a different format, so it is not ours
                return creds

            try:
                login, password = cookie_val.split(':')
            except ValueError:
                # Cookie is in a different format, so it is not ours
                return creds

            try:
                creds['login'] = decode_hex(login)
                creds['password'] = decode_hex(password)
            except (Error, TypeError):
                # Cookie is in a different format, so it is not ours
                return {}

        if creds:
            creds['remote_host'] = request.get('REMOTE_HOST', '')

            try:
                creds['remote_address'] = request.getClientAddr()
            except AttributeError:
                creds['remote_address'] = request.get('REMOTE_ADDR', '')

        return creds

    @security.private
    def challenge(self, request, response, **kw):
        """ Challenge the user for credentials. """
        return self.unauthorized()

    @security.private
    def get_cookie_value(self, login, new_password):
        cookie_str = b':'.join([
            hexlify(login.encode('utf-8')),
            hexlify(new_password.encode('utf-8'))])
        cookie_val = encodebytes(cookie_str)
        cookie_val = cookie_val.rstrip()
        return cookie_val

    @security.private
    def updateCredentials(self, request, response, login, new_password):
        """ Respond to change of credentials (NOOP for basic auth). """
        cookie_val = self.get_cookie_value(login, new_password)
        response.setCookie(self.cookie_name, quote(cookie_val), path='/')

    @security.private
    def resetCredentials(self, request, response):
        """ Raise unauthorized to tell browser to clear credentials. """
        response.expireCookie(self.cookie_name, path='/')

    @security.private
    def manage_afterAdd(self, item, container):
        """ Setup tasks upon instantiation """
        if 'login_form' not in self.objectIds():
            login_form = ZopePageTemplate(id='login_form',
                                          text=BASIC_LOGIN_FORM)
            login_form.title = 'Login Form'
            login_form.manage_permission(view, roles=['Anonymous'], acquire=1)
            self._setObject('login_form', login_form, set_owner=0)

    @security.private
    def unauthorized(self):
        req = self.REQUEST
        resp = req['RESPONSE']

        # If we set the auth cookie before, delete it now.
        if self.cookie_name in resp.cookies:
            del resp.cookies[self.cookie_name]

        # Redirect if desired.
        url = self.getLoginURL()
        if url is not None:
            came_from = req.get('came_from', None)

            if came_from is None:
                came_from = req.get('ACTUAL_URL', '')
                query = req.get('QUERY_STRING')
                if query:
                    if not query.startswith('?'):
                        query = '?' + query
                    came_from = came_from + query
            else:
                # If came_from contains a value it means the user
                # must be coming through here a second time
                # Reasons could be typos when providing credentials
                # or a redirect loop (see below)
                req_url = req.get('ACTUAL_URL', '')

                if req_url and req_url == url:
                    # Oops... The login_form cannot be reached by the user -
                    # it might be protected itself due to misconfiguration -
                    # the only sane thing to do is to give up because we are
                    # in an endless redirect loop.
                    return 0

            if '?' in url:
                sep = '&'
            else:
                sep = '?'
            url = '%s%scame_from=%s' % (url, sep, quote(came_from))
            resp.redirect(url, lock=1)
            resp.setHeader('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT')
            resp.setHeader('Cache-Control', 'no-cache')
            return 1

        # Could not challenge.
        return 0

    @security.private
    def getLoginURL(self):
        """ Where to send people for logging in """
        if self.login_path.startswith('/') or '://' in self.login_path:
            return self.login_path
        elif self.login_path != '':
            return '%s/%s' % (self.absolute_url(), self.login_path)
        else:
            return None

    @security.public
    def login(self):
        """ Set a cookie and redirect to the url that we tried to
        authenticate against originally.
        """
        request = self.REQUEST
        response = request['RESPONSE']

        login = request.get('__ac_name', '')
        password = request.get('__ac_password', '')

        # In order to use the CookieAuthHelper for its nice login page
        # facility but store and manage credentials somewhere else we need
        # to make sure that upon login only plugins activated as
        # IUpdateCredentialPlugins get their updateCredentials method
        # called. If the method is called on the CookieAuthHelper it will
        # simply set its own auth cookie, to the exclusion of any other
        # plugins that might want to store the credentials.
        pas_instance = self._getPAS()

        if pas_instance is not None:
            pas_instance.updateCredentials(request, response, login, password)

        came_from = request.form['came_from']

        return response.redirect(came_from)


classImplements(CookieAuthHelper, ICookieAuthHelper,
                ILoginPasswordHostExtractionPlugin, IChallengePlugin,
                ICredentialsUpdatePlugin, ICredentialsResetPlugin)

InitializeClass(CookieAuthHelper)


BASIC_LOGIN_FORM = """<html>
  <head>
    <title> Login Form </title>
  </head>

  <body>

    <h3> Please log in </h3>

    <form method="post" action=""
          tal:attributes="action string:${here/absolute_url}/login">

      <input type="hidden" name="came_from" value=""
             tal:attributes="value request/came_from | string:"/>
      <table cellpadding="2">
        <tr>
          <td><b>Login:</b> </td>
          <td><input type="text" name="__ac_name" size="30" /></td>
        </tr>
        <tr>
          <td><b>Password:</b></td>
          <td><input type="password" name="__ac_password" size="30" /></td>
        </tr>
        <tr>
          <td colspan="2">
            <br />
            <input type="submit" value=" Log In " />
          </td>
        </tr>
      </table>

    </form>

  </body>

</html>
"""
