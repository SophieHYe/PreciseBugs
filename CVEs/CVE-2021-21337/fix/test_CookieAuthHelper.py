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
try:
    from base64 import encodebytes
except ImportError:  # Python < 3.1
    from base64 import encodestring as encodebytes

import codecs
import unittest

import six
from six.moves.urllib.parse import quote

from ...tests.conformance import IChallengePlugin_conformance
from ...tests.conformance import ICredentialsResetPlugin_conformance
from ...tests.conformance import ICredentialsUpdatePlugin_conformance
from ...tests.conformance import ILoginPasswordHostExtractionPlugin_conformance
from ...tests.test_PluggableAuthService import FauxContainer
from ...tests.test_PluggableAuthService import FauxObject
from ...tests.test_PluggableAuthService import FauxRequest
from ...tests.test_PluggableAuthService import FauxResponse
from ...tests.test_PluggableAuthService import FauxRoot


class FauxSettableRequest(FauxRequest):

    def set(self, name, value):
        self._dict[name] = value


class FauxCookieResponse(FauxResponse):

    def __init__(self):
        self.cookies = {}
        self.redirected = False
        self.status = '200'
        self.headers = {}

    def setCookie(self, cookie_name, cookie_value, path):
        self.cookies[(cookie_name, path)] = cookie_value

    def expireCookie(self, cookie_name, path):
        if (cookie_name, path) in self.cookies:
            del self.cookies[(cookie_name, path)]

    def redirect(self, location, status=302, lock=0):
        self.status = status
        self.headers['Location'] = location

    def setHeader(self, name, value):
        self.headers[name] = value


class CookieAuthHelperTests(unittest.TestCase,
                            ILoginPasswordHostExtractionPlugin_conformance,
                            IChallengePlugin_conformance,
                            ICredentialsResetPlugin_conformance,
                            ICredentialsUpdatePlugin_conformance):

    def _getTargetClass(self):

        from ...plugins.CookieAuthHelper import CookieAuthHelper

        return CookieAuthHelper

    def _makeOne(self, id='test', *args, **kw):

        return self._getTargetClass()(id=id, *args, **kw)

    def _makeTree(self):

        rc = FauxObject('rc')
        root = FauxRoot('root').__of__(rc)
        folder = FauxContainer('folder').__of__(root)
        object = FauxObject('object').__of__(folder)

        return rc, root, folder, object

    def test_extractCredentials_no_creds(self):

        helper = self._makeOne()
        response = FauxCookieResponse()
        request = FauxRequest(RESPONSE=response)

        self.assertEqual(helper.extractCredentials(request), {})

    def test_extractCredentials_with_form_creds(self):

        helper = self._makeOne()
        response = FauxCookieResponse()
        request = FauxSettableRequest(__ac_name='foo',
                                      __ac_password='b:ar',
                                      RESPONSE=response)

        self.assertEqual(len(response.cookies), 0)
        self.assertEqual(helper.extractCredentials(request),
                         {'login': 'foo',
                          'password': 'b:ar',
                          'remote_host': '',
                          'remote_address': ''})
        self.assertEqual(len(response.cookies), 0)

    def test_extractCredentials_with_deleted_cookie(self):
        # http://www.zope.org/Collectors/PAS/43
        # Edge case: The ZPublisher sets a cookie's value to "deleted"
        # in the current request if expireCookie is called. If we hit
        # extractCredentials in the same request after this, it would
        # blow up trying to deal with the invalid cookie value.
        helper = self._makeOne()
        response = FauxCookieResponse()
        req_data = {helper.cookie_name: 'deleted', 'RESPONSE': response}
        request = FauxSettableRequest(**req_data)
        self.assertEqual(len(response.cookies), 0)

        self.assertEqual(helper.extractCredentials(request), {})

    def test_challenge(self):
        rc, root, folder, object = self._makeTree()
        response = FauxCookieResponse()
        testPath = '/some/path'
        testURL = 'http://test' + testPath
        request = FauxRequest(RESPONSE=response, URL=testURL,
                              ACTUAL_URL=testURL)
        root.REQUEST = request

        helper = self._makeOne().__of__(root)

        helper.challenge(request, response)
        self.assertEqual(response.status, 302)
        self.assertEqual(len(response.headers), 3)
        loc = response.headers['Location']
        self.assertTrue(loc.endswith(quote(testPath)))
        self.assertNotIn(testURL, loc)
        self.assertEqual(response.headers['Cache-Control'], 'no-cache')
        self.assertEqual(response.headers['Expires'],
                         'Sat, 01 Jan 2000 00:00:00 GMT')

    def test_challenge_with_vhm(self):
        rc, root, folder, object = self._makeTree()
        response = FauxCookieResponse()
        vhm = 'http://localhost/VirtualHostBase/http/test/VirtualHostRoot/xxx'
        actualURL = 'http://test/xxx'

        request = FauxRequest(RESPONSE=response, URL=vhm,
                              ACTUAL_URL=actualURL)
        root.REQUEST = request

        helper = self._makeOne().__of__(root)

        helper.challenge(request, response)
        self.assertEqual(response.status, 302)
        self.assertEqual(len(response.headers), 3)
        loc = response.headers['Location']
        self.assertTrue(loc.endswith(quote('/xxx')))
        self.assertFalse(loc.endswith(quote(vhm)))
        self.assertNotIn(actualURL, loc)
        self.assertEqual(response.headers['Cache-Control'], 'no-cache')
        self.assertEqual(response.headers['Expires'],
                         'Sat, 01 Jan 2000 00:00:00 GMT')

    def test_resetCredentials(self):
        helper = self._makeOne()
        response = FauxCookieResponse()
        request = FauxRequest(RESPONSE=response)

        helper.resetCredentials(request, response)
        self.assertEqual(len(response.cookies), 0)

    def test_loginWithoutCredentialsUpdate(self):
        helper = self._makeOne()
        response = FauxCookieResponse()
        request = FauxSettableRequest(__ac_name='foo', __ac_password='bar',
                                      RESPONSE=response)
        request.form['came_from'] = ''
        helper.REQUEST = request

        helper.login()
        self.assertEqual(len(response.cookies), 0)

    def test_extractCredentials_from_cookie_with_colon_in_password(self):
        # http://www.zope.org/Collectors/PAS/51
        # Passwords with ":" characters broke authentication
        helper = self._makeOne()
        response = FauxCookieResponse()
        request = FauxSettableRequest(RESPONSE=response)

        username = codecs.encode(b'foo', 'hex_codec')
        password = codecs.encode(b'b:ar', 'hex_codec')
        cookie_str = b'%s:%s' % (username, password)
        cookie_val = encodebytes(cookie_str)
        cookie_val = cookie_val.rstrip()
        if six.PY3:
            cookie_val = cookie_val.decode('utf8')
        request.set(helper.cookie_name, cookie_val)

        self.assertEqual(helper.extractCredentials(request),
                         {'login': 'foo',
                          'password': 'b:ar',
                          'remote_host': '',
                          'remote_address': ''})

    def test_extractCredentials_from_cookie_with_colon_that_is_not_ours(self):
        # http://article.gmane.org/gmane.comp.web.zope.plone.product-developers/5145
        helper = self._makeOne()
        response = FauxCookieResponse()
        request = FauxSettableRequest(RESPONSE=response)

        cookie_str = b'cookie:from_other_plugin'
        cookie_val = encodebytes(cookie_str)
        cookie_val = cookie_val.rstrip()
        if six.PY3:
            cookie_val = cookie_val.decode('utf8')
        request.set(helper.cookie_name, cookie_val)

        self.assertEqual(helper.extractCredentials(request), {})

    def test_extractCredentials_from_cookie_with_bad_binascii(self):
        # this might happen between browser implementations
        helper = self._makeOne()
        response = FauxCookieResponse()
        request = FauxSettableRequest(RESPONSE=response)

        cookie_val = 'NjE2NDZkNjk2ZTo3MDZjNmY2ZTY1MzQ3NQ%3D%3D'[:-1]
        request.set(helper.cookie_name, cookie_val)

        self.assertEqual(helper.extractCredentials(request), {})
