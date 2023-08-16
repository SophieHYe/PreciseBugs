import unittest
import formencode
import cherrypy
import pkg_resources
from turbogears import config, controllers, database, \
    error_handler, exception_handler, expose, flash, redirect, \
    startup, testutil, url, validate, validators


class SubApp(controllers.RootController):

    [expose()]
    def index(self):
        return url("/Foo/")


class MyRoot(controllers.RootController):

    [expose()]
    def index(self):
        pass

    def validation_error_handler(self, tg_source, tg_errors, *args, **kw):
        self.functionname = tg_source.__name__
        self.values = kw
        self.errors = tg_errors
        return "Error Message"

    [expose(template="turbogears.tests.simple", allow_json=True)]
    def test(self):
        return dict(title="Foobar", mybool=False, someval="niggles")

    [expose(html="turbogears.tests.simple")]
    def test_deprecated(self):
        return dict(title="Oldbar", mybool=False, someval="giggles")

    [expose()]
    def invalid(self):
        return None

    [expose()]
    def pos(self, posvalue):
        self.posvalue = posvalue
        return ""

    [expose()]
    def servefile(self, tg_exceptions=None):
        self.servedit = True
        self.serve_exceptions = tg_exceptions
        return cherrypy.lib.cptools.serveFile(
            pkg_resources.resource_filename(
                "turbogears.tests", "test_controllers.py"))

    [expose(content_type='text/plain')]
    def basestring(self):
        return 'hello world'

    [expose(content_type='text/plain')]
    def list(self):
        return ['hello', 'world']

    [expose(content_type='text/plain')]
    def generator(self):
        yield 'hello'
        yield 'world'

    [expose()]
    def unicode(self):
        cherrypy.response.headers["Content-Type"] = "text/html"
        return u'\u00bfHabla espa\u00f1ol?'

    [expose()]
    def returnedtemplate(self):
        return dict(title="Foobar", mybool=False, someval="foo",
            tg_template="turbogears.tests.simple")

    [expose()]
    def returnedtemplate_short(self):
        return dict(title="Foobar", mybool=False, someval="foo",
            tg_template="turbogears.tests.simple")

    [expose(template="turbogears.tests.simple")]
    def exposetemplate_short(self):
        return dict(title="Foobar", mybool=False, someval="foo")

    [expose()]
    [validate(validators={'value': validators.StringBoolean()})]
    def istrue(self, value):
        self.value = value
        return str(value)
    istrue = error_handler(validation_error_handler)(istrue)

    [expose()]
    [validate(validators={'value': validators.StringBoolean()})]
    def nestedcall(self, value):
        return self.istrue(str(value))

    [expose()]
    [validate(validators={'value': validators.StringBoolean()})]
    def errorchain(self, value):
        return "No Error"
    errorchain = error_handler(istrue)(errorchain)

    [expose(format="json", template="turbogears.tests.simple")]
    def returnjson(self):
        return dict(title="Foobar", mybool=False, someval="foo",
            tg_template="turbogears.tests.simple")

    [expose(template="turbogears.tests.simple", allow_json=False)]
    def allowjson(self):
        return dict(title="Foobar", mybool=False, someval="foo",
             tg_template="turbogears.tests.simple")

    [expose(format="json")]
    def impliedjson(self):
        return dict(title="Blah")

    [expose('json')]
    def explicitjson(self):
        return dict(title="Blub")

    [expose(template="turbogears.tests.simple", allow_json=True)]
    def jsonerror_handler(self):
        return dict(someval="errors")

    [expose(allow_json=True)]
    def jsonerror(self):
        raise ValueError
    jsonerror = exception_handler(jsonerror_handler)(jsonerror)

    [expose(content_type="xml/atom")]
    def contenttype(self):
        return "Foobar"

    [expose()]
    [validate(validators={
        "firstname": validators.String(min=2, not_empty=True),
        "lastname": validators.String()})]
    def save(self, submit, firstname, lastname="Miller"):
        self.submit = submit
        self.firstname = firstname
        self.lastname = lastname
        self.fullname = "%s %s" % (self.firstname, self.lastname)
        return self.fullname
    save = error_handler(validation_error_handler)(save)

    class Registration(formencode.Schema):
        allow_extra_fields = True
        firstname = validators.String(min=2, not_empty=True)
        lastname = validators.String()

    [expose()]
    [validate(validators=Registration())]
    def save2(self, submit, firstname, lastname="Miller"):
        return self.save(submit, firstname, lastname)
    save2 = error_handler(validation_error_handler)(save2)

    [expose(template="turbogears.tests.simple")]
    def useother(self):
        return dict(tg_template="turbogears.tests.othertemplate")

    [expose(template="cheetah:turbogears.tests.simplecheetah")]
    def usecheetah(self):
        return dict(someval="chimps")

    rwt_called = 0
    def rwt(self, func, *args, **kw):
        self.rwt_called += 1
        func(*args, **kw)

    [expose(template="turbogears.tests.simple", allow_json=True)]
    def flash_plain(self):
        flash("plain")
        return dict(title="Foobar", mybool=False, someval="niggles")

    [expose(template="turbogears.tests.simple", allow_json=True)]
    def flash_unicode(self):
        flash(u"\xfcnicode")
        return dict(title="Foobar", mybool=False, someval="niggles")

    [expose(template="turbogears.tests.simple", allow_json=True)]
    def flash_data_structure(self):
        flash(dict(uni=u"\xfcnicode", testing=[1, 2, 3]))
        return dict(title="Foobar", mybool=False, someval="niggles")

    [expose(template="turbogears.tests.simple", allow_json=True)]
    def flash_redirect(self):
        flash(u"redirect \xfcnicode")
        redirect("/flash_redirected?tg_format=json")

    [expose(template="turbogears.tests.simple", allow_json=True)]
    def flash_redirect_with_trouble_chars(self):
        flash(u"$foo, k\xe4se;\tbar!")
        redirect("/flash_redirected?tg_format=json")

    [expose(template="turbogears.tests.simple", allow_json=True)]
    def flash_redirected(self):
        return dict(title="Foobar", mybool=False, someval="niggles")

    def exc_h_value(self, tg_exceptions=None):
        """Exception handler for the ValueError in raise_value_exc"""
        return dict(handling_value=True, exception=str(tg_exceptions))

    [expose()]
    def raise_value_exc(self):
        raise ValueError('Some Error in the controller')
    raise_value_exc = exception_handler(exc_h_value,
        "isinstance(tg_exceptions, ValueError)")(raise_value_exc)

    def exc_h_key(self, tg_exceptions=None):
        """Exception handler for KeyErrors in  raise_all_exc"""
        return dict(handling_key=True, exception=str(tg_exceptions))

    def exc_h_index(self, tg_exceptions=None):
        """Exception handler for the ValueError in raise_value_exc"""
        return dict(handling_index=True, exception=str(tg_exceptions))

    [expose()]
    def raise_index_exc(self):
        raise IndexError('Some IndexError')
    raise_index_exc = exception_handler(exc_h_index,
        "isinstance(tg_exceptions, IndexError)")(raise_index_exc)

    [expose()]
    def raise_all_exc(self, num=2):
        num = int(num)
        if num < 2:
            raise ValueError('Inferior to 2')
        elif num == 2:
            raise IndexError('Equals to 2')
        elif num > 2:
            raise KeyError('No such number 2 in the integer range')
    raise_all_exc = exception_handler(exc_h_index,
        "isinstance(tg_exceptions, IndexError)")(raise_all_exc)
    raise_all_exc = exception_handler(exc_h_value,
        "isinstance(tg_exceptions, ValueError)")(raise_all_exc)
    raise_all_exc = exception_handler(exc_h_key,
        "isinstance(tg_exceptions, KeyError)")(raise_all_exc)

    [expose()]
    def internal_redirect(self, **kwargs):
        raise cherrypy.InternalRedirect('/internal_redirect_target')

    [expose()]
    def internal_redirect_target(self, **kwargs):
        return "redirected OK"

    [expose()]
    def redirect_to_path_str(self, path):
        raise redirect(path + '/index')

    [expose()]
    def redirect_to_path_list(self, path):
        raise redirect([path, 'index'])

    [expose()]
    def redirect_to_path_tuple(self, path):
        raise redirect((path, 'index'))


class TestRoot(unittest.TestCase):

    def setUp(self):
        cherrypy.root = None
        cherrypy.tree.mount_points = {}
        cherrypy.tree.mount(MyRoot(), "/")
        cherrypy.tree.mount(SubApp(), "/subthing")

    def tearDown(self):
        cherrypy.root = None
        cherrypy.tree.mount_points = {}

    def test_js_files(self):
        """Can access the JavaScript files"""
        testutil.create_request("/tg_js/MochiKit.js")
        assert cherrypy.response.headers["Content-Type"] in [
            "application/javascript", "application/x-javascript"]
        assert cherrypy.response.status == "200 OK"

    def test_json_output(self):
        testutil.create_request("/test?tg_format=json")
        import simplejson
        values = simplejson.loads(cherrypy.response.body[0])
        assert values == dict(title="Foobar", mybool=False,
            someval="niggles", tg_flash=None)
        assert cherrypy.response.headers["Content-Type"] == "application/json"

    def test_implied_json(self):
        testutil.create_request("/impliedjson?tg_format=json")
        assert '"title": "Blah"' in cherrypy.response.body[0]
        assert cherrypy.response.headers["Content-Type"] == "application/json"

    def test_explicit_json(self):
        testutil.create_request("/explicitjson")
        assert '"title": "Blub"' in cherrypy.response.body[0]
        assert cherrypy.response.headers["Content-Type"] == "application/json"
        testutil.create_request("/explicitjson?tg_format=json")
        assert '"title": "Blub"' in cherrypy.response.body[0]
        assert cherrypy.response.headers["Content-Type"] == "application/json"

    def test_allow_json(self):
        testutil.create_request("/allowjson?tg_format=json")
        assert cherrypy.response.headers["Content-Type"] == "text/html"

    def test_allow_json_config(self):
        """JSON output can be enabled via config."""
        config.update({'tg.allow_json':True})
        class JSONRoot(controllers.RootController):
            [expose(template="turbogears.tests.simple")]
            def allowjsonconfig(self):
                return dict(title="Foobar", mybool=False, someval="foo",
                     tg_template="turbogears.tests.simple")
        cherrypy.root = JSONRoot()
        testutil.create_request('/allowjsonconfig?tg_format=json')
        assert cherrypy.response.headers["Content-Type"] == "application/json"
        config.update({'tg.allow_json': False})

    def test_allow_json_config_false(self):
        """Make sure JSON can still be restricted with a global config on."""
        config.update({'tg.allow_json': True})
        class JSONRoot(controllers.RootController):
            [expose(template="turbogears.tests.simple")]
            def allowjsonconfig(self):
                return dict(title="Foobar", mybool=False, someval="foo",
                     tg_template="turbogears.tests.simple")
        cherrypy.root = JSONRoot()
        testutil.create_request('/allowjson?tg_format=json')
        assert cherrypy.response.headers["Content-Type"] == "text/html"
        config.update({'tg.allow_json': False})

    def test_json_error(self):
        """The error handler should return JSON if requested."""
        testutil.create_request("/jsonerror")
        assert cherrypy.response.headers["Content-Type"] == "text/html; charset=utf-8"
        assert "Paging all errors" in cherrypy.response.body[0]
        testutil.create_request("/jsonerror?tg_format=json")
        assert cherrypy.response.headers["Content-Type"] == "application/json"
        assert '"someval": "errors"' in cherrypy.response.body[0]

    def test_invalid_return(self):
        testutil.create_request("/invalid")
        assert cherrypy.response.status.startswith("500")

    def test_strict_parameters(self):
        config.update({"tg.strict_parameters": True})
        testutil.create_request(
            "/save?submit=save&firstname=Foo&lastname=Bar&badparam=1")
        assert cherrypy.response.status.startswith("500")
        assert not hasattr(cherrypy.root, "errors")

    def test_throw_out_random(self):
        """Can append random value to the URL to avoid caching problems."""
        testutil.create_request("/test?tg_random=1")
        assert "Paging all niggles" in cherrypy.response.body[0]
        config.update({"tg.strict_parameters": True})
        testutil.create_request("/test?tg_random=1")
        assert cherrypy.response.status.startswith("200")
        assert "Paging all niggles" in cherrypy.response.body[0]
        testutil.create_request("/test?tg_not_random=1")
        assert cherrypy.response.status.startswith("500")
        assert not hasattr(cherrypy.root, "errors")

    def test_ignore_parameters(self):
        config.update({"tg.strict_parameters": True})
        testutil.create_request("/test?ignore_me=1")
        assert cherrypy.response.status.startswith("500")
        assert not hasattr(cherrypy.root, "errors")
        config.update({"tg.ignore_parameters": ['ignore_me', 'me_too']})
        testutil.create_request("/test?ignore_me=1")
        assert "Paging all niggles" in cherrypy.response.body[0]
        testutil.create_request("/test?me_too=1")
        assert cherrypy.response.status.startswith("200")
        assert "Paging all niggles" in cherrypy.response.body[0]
        testutil.create_request("/test?me_not=1")
        assert cherrypy.response.status.startswith("500")
        assert not hasattr(cherrypy.root, "errors")

    def test_retrieve_dict_directly(self):
        d = testutil.call(cherrypy.root.returnjson)
        assert d["title"] == "Foobar"

    def test_template_output(self):
        testutil.create_request("/test")
        assert "Paging all niggles" in cherrypy.response.body[0]

    def test_template_deprecated(self):
        testutil.create_request("/test_deprecated")
        assert "Paging all giggles" in cherrypy.response.body[0]

    def test_unicode(self):
        testutil.create_request("/unicode")
        firstline = cherrypy.response.body[0].split('\n')[0].decode('utf-8')
        assert firstline == u'\u00bfHabla espa\u00f1ol?'

    def test_default_format(self):
        """The default format can be set via expose"""
        testutil.create_request("/returnjson")
        firstline = cherrypy.response.body[0]
        assert '"title": "Foobar"' in firstline
        testutil.create_request("/returnjson?tg_format=html")
        firstline = cherrypy.response.body[0]
        assert '"title": "Foobar"' not in firstline

    def test_content_type(self):
        """The content-type can be set via expose"""
        testutil.create_request("/contenttype")
        assert cherrypy.response.headers["Content-Type"] == "xml/atom"

    def test_returned_template_name(self):
        testutil.create_request("/returnedtemplate")
        data = cherrypy.response.body[0].lower()
        assert "<body>" in data
        assert 'groovy test template' in data

    def test_returned_template_short(self):
        testutil.create_request("/returnedtemplate_short")
        assert "Paging all foo" in cherrypy.response.body[0]

    def test_expose_template_short(self):
        testutil.create_request("/exposetemplate_short")
        assert "Paging all foo" in cherrypy.response.body[0]

    def test_validation(self):
        """Data can be converted and validated"""
        testutil.create_request("/istrue?value=true")
        assert cherrypy.root.value is True
        testutil.create_request("/istrue?value=false")
        assert cherrypy.root.value is False
        cherrypy.root = MyRoot()
        testutil.create_request("/istrue?value=foo")
        assert not hasattr(cherrypy.root, "value")
        assert cherrypy.root.functionname == "istrue"
        testutil.create_request("/save?submit=send&firstname=John&lastname=Doe")
        assert cherrypy.root.fullname == "John Doe"
        assert cherrypy.root.submit == "send"
        testutil.create_request("/save?submit=send&firstname=Arthur")
        assert cherrypy.root.fullname == "Arthur Miller"
        testutil.create_request("/save?submit=send&firstname=Arthur&lastname=")
        assert cherrypy.root.fullname == "Arthur "
        testutil.create_request("/save?submit=send&firstname=D&lastname=")
        assert len(cherrypy.root.errors) == 1
        assert cherrypy.root.errors.has_key("firstname")
        assert "characters" in cherrypy.root.errors["firstname"].msg.lower()
        testutil.create_request("/save?submit=send&firstname=&lastname=")
        assert len(cherrypy.root.errors) == 1
        assert cherrypy.root.errors.has_key("firstname")

    def test_validation_chained(self):
        """Validation is not repeated if it already happened"""
        cherrypy.root.value = None
        testutil.create_request("/errorchain?value=true")
        assert cherrypy.root.value is None
        testutil.create_request("/errorchain?value=notbool")
        assert cherrypy.root.value == 'notbool'

    def test_validation_nested(self):
        """Validation is not repeated in nested method call"""
        cherrypy.root.value = None
        testutil.create_request("/nestedcall?value=true")
        assert cherrypy.root.value == 'True'
        testutil.create_request("/nestedcall?value=false")
        assert cherrypy.root.value == 'False'

    def test_validation_with_schema(self):
        """Data can be converted/validated with formencode.Schema instance"""
        testutil.create_request("/save2?submit=send&firstname=Joe&lastname=Doe")
        assert cherrypy.root.fullname == "Joe Doe"
        assert cherrypy.root.submit == "send"
        testutil.create_request("/save2?submit=send&firstname=Arthur&lastname=")
        assert cherrypy.root.fullname == "Arthur "
        testutil.create_request("/save2?submit=send&firstname=&lastname=")
        assert len(cherrypy.root.errors) == 1
        assert cherrypy.root.errors.has_key("firstname")
        testutil.create_request("/save2?submit=send&firstname=D&lastname=")
        assert len(cherrypy.root.errors) == 1
        assert cherrypy.root.errors.has_key("firstname")

    def test_other_template(self):
        """'tg_template' in a returned dict will use the template specified there"""
        testutil.create_request("/useother")
        assert "This is the other template" in cherrypy.response.body[0]

    def test_cheetah_template(self):
        """Cheetah templates can be used as well"""
        testutil.create_request("/usecheetah")
        body = cherrypy.response.body[0]
        assert "This is the Cheetah test template." in body
        assert "Paging all chimps." in body

    def test_run_with_trans(self):
        """run_with_transaction is called only on topmost exposed method"""
        oldrwt = database.run_with_transaction
        database.run_with_transaction = cherrypy.root.rwt
        testutil.create_request("/nestedcall?value=true")
        database.run_with_transaction = oldrwt
        assert cherrypy.root.value
        assert cherrypy.root.rwt_called == 1

    def test_positional(self):
        """Positional parameters should work"""
        testutil.create_request("/pos/foo")
        assert cherrypy.root.posvalue == "foo"

    def test_flash_plain(self):
        """flash with strings should work"""
        testutil.create_request("/flash_plain?tg_format=json")
        import simplejson
        values = simplejson.loads(cherrypy.response.body[0])
        assert values["tg_flash"] == "plain"
        assert not cherrypy.response.simple_cookie.has_key("tg_flash")

    def test_flash_unicode(self):
        """flash with unicode objects should work"""
        testutil.create_request("/flash_unicode?tg_format=json")
        import simplejson
        values = simplejson.loads(cherrypy.response.body[0])
        assert values["tg_flash"] == u"\xfcnicode"
        assert not cherrypy.response.simple_cookie.has_key("tg_flash")

    def test_flash_on_redirect(self):
        """flash must survive a redirect"""
        testutil.create_request("/flash_redirect?tg_format=json")
        assert cherrypy.response.status.startswith("302")
        testutil.create_request(cherrypy.response.headers["Location"],
            headers=dict(Cookie=cherrypy.response.simple_cookie.output(
                header="").strip()))
        import simplejson
        values = simplejson.loads(cherrypy.response.body[0])
        assert values["tg_flash"] == u"redirect \xfcnicode"

    def test_flash_redirect_with_trouble_chars(self):
        """flash redirect with chars that can cause troubles in cookies"""
        testutil.create_request("/flash_redirect_with_trouble_chars?tg_format=json")
        assert cherrypy.response.status.startswith("302")
        value = cherrypy.response.simple_cookie["tg_flash"].value
        assert '$' not in value
        assert ',' not in value and ';' not in value
        assert ' ' not in value and '\t' not in value
        assert 'foo' in value and 'bar' in value
        assert u'k\xe4se'.encode('utf-8') in value
        assert '!' in value
        testutil.create_request(cherrypy.response.headers["Location"],
            headers=dict(Cookie=cherrypy.response.simple_cookie.output(
                header="").strip()))
        import simplejson
        values = simplejson.loads(cherrypy.response.body[0])
        assert values["tg_flash"] == u"$foo, k\xe4se;\tbar!"

    def test_double_flash(self):
        """latest set flash should have precedence"""
        # Here we are calling method that sets a flash message. However flash
        # cookie is still there. Turbogears should discard old flash message
        # from cookie and use new one, set by flash_plain().
        testutil.create_request("/flash_plain?tg_format=json",
            headers=dict(Cookie='tg_flash="old flash"; Path=/;'))
        import simplejson
        values = simplejson.loads(cherrypy.response.body[0])
        assert values["tg_flash"] == "plain"
        assert cherrypy.response.simple_cookie.has_key("tg_flash"), \
                "Cookie clearing request should be present"
        flashcookie = cherrypy.response.simple_cookie['tg_flash']
        assert flashcookie['expires'] == 0

    def test_set_kid_outputformat_in_config(self):
        """the outputformat for kid can be set in the config"""
        config.update({'kid.outputformat': 'xhtml'})
        testutil.create_request('/test')
        response = cherrypy.response.body[0]
        assert '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML ' in response
        config.update({'kid.outputformat': 'html'})
        testutil.create_request('/test')
        response = cherrypy.response.body[0]
        assert  '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML ' in response
        assert '    This is the groovy test ' in response
        config.update({'kid.outputformat': 'html compact'})
        testutil.create_request('/test')
        response = cherrypy.response.body[0]
        assert  '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML ' in response
        assert 'This is the groovy test ' in response
        assert '    ' not in response

    def test_fileserving(self):
        testutil.create_request("/servefile")
        assert cherrypy.root.servedit
        assert not cherrypy.root.serve_exceptions

    def test_basestring(self):
        testutil.create_request("/basestring")
        assert cherrypy.response.body[0] == 'hello world'
        assert cherrypy.response.headers["Content-Type"] == "text/plain"

    def test_list(self):
        testutil.create_request("/list")
        assert cherrypy.response.body[0] == 'helloworld'
        assert cherrypy.response.headers["Content-Type"] == "text/plain"

    def test_generator(self):
        testutil.create_request("/generator")
        assert cherrypy.response.body[0] == 'helloworld'
        assert cherrypy.response.headers["Content-Type"] == "text/plain"

    def test_internal_redirect(self):
        """regression test for #1022, #1407 and #1598"""
        testutil.create_request("/internal_redirect")
        firstline = cherrypy.response.body[0]
        assert "redirected OK" in firstline

    def test_internal_redirect_nested_variables(self):
        """regression test for #1022, #1407 and #1598"""
        testutil.create_request(
            "/internal_redirect?a=1&a-1.b=2&a-2.c=3&a-2.c-1=4")
        firstline = cherrypy.response.body[0]
        assert "redirected OK" in firstline

    def test_exc_value(self):
        """Exception is handled gracefully by the right exception handler."""
        testutil.create_request("/raise_value_exc")
        assert 'handling_value' in cherrypy.response.body[0]

    def test_exc_index(self):
        """Exception is handled gracefully by the right exception handler."""
        testutil.create_request("/raise_index_exc")
        assert 'handling_index' in cherrypy.response.body[0]

    def test_exc_all(self):
        """Test a controller that is protected by multiple exception handlers.

        It should raise either of the 3 exceptions but all should be handled
        by their respective handlers without problem...

        """
        testutil.create_request("/raise_all_exc?num=1")
        assert 'handling_value' in cherrypy.response.body[0]
        testutil.create_request("/raise_all_exc?num=2")
        assert 'handling_index' in cherrypy.response.body[0]
        testutil.create_request("/raise_all_exc?num=3")
        assert 'handling_key' in cherrypy.response.body[0]


class TestURLs(unittest.TestCase):

    def setUp(self):
        cherrypy.tree.mount_points = {}
        cherrypy.root = MyRoot()
        cherrypy.root.subthing = SubApp()
        cherrypy.root.subthing.subsubthing = SubApp()

    def tearDown(self):
        config.update({"server.webpath": ""})
        startup.startTurboGears()

    def test_basic_urls(self):
        testutil.create_request("/")
        assert "/foo" == url("/foo")
        assert "foo/bar" == url(["foo", "bar"])
        assert url("/foo", bar=1, baz=2) in \
            ["/foo?bar=1&baz=2", "/foo?baz=2&bar=1"]
        assert url("/foo", dict(bar=1, baz=2)) in \
            ["/foo?bar=1&baz=2", "/foo?baz=2&bar=1"]
        assert url("/foo", dict(bar=1, baz=None)) == "/foo?bar=1"

    def test_url_without_request_available(self):
        cherrypy.serving.request = None
        assert url("/foo") == "/foo"

    def test_approots(self):
        testutil.create_request("/subthing/")
        assert cherrypy.response.status.startswith("200")
        assert url("foo") == "foo"
        assert url("/foo") == "/subthing/foo"
        testutil.create_request("/nosubthing/")
        assert cherrypy.response.status.startswith("404")
        assert url("foo") == "foo"
        assert url("/foo") == "/foo"

    def test_lower_approots(self):
        testutil.create_request("/subthing/subsubthing/")
        assert url("/foo") == "/subthing/subsubthing/foo"

    def test_approots_with_path(self):
        config.update({"server.webpath": "/coolsite/root"})
        startup.startTurboGears()
        testutil.create_request("/coolsite/root/subthing/")
        assert url("/foo") == "/coolsite/root/subthing/foo"

    def test_redirect(self):
        config.update({"server.webpath": "/coolsite/root"})
        startup.startTurboGears()
        testutil.create_request("/coolsite/root/subthing/")
        try:
            redirect("/foo")
            assert False, "redirect exception should have been raised"
        except cherrypy.HTTPRedirect, e:
            assert "http://localhost/coolsite/root/subthing/foo" in e.urls
        try:
            raise redirect("/foo")
            assert False, "redirect exception should have been raised"
        except cherrypy.HTTPRedirect, e:
            assert "http://localhost/coolsite/root/subthing/foo" in e.urls
        try:
            redirect("foo")
            assert False, "redirect exception should have been raised"
        except cherrypy.HTTPRedirect, e:
            assert "http://localhost/coolsite/root/subthing/foo" in e.urls

    def test_redirect_to_path(self):
        for path_type in ('str', 'list', 'tuple'):
            for path in ('subthing', '/subthing'):
                url = "/redirect_to_path_%s?path=%s" % (path_type, path)
                testutil.create_request(url)
                assert cherrypy.response.status.startswith("302"), url
                assert (cherrypy.response.headers['Location']
                    == 'http://localhost/subthing/index'), url

    def test_multi_values(self):
        testutil.create_request("/")
        assert url("/foo", bar=[1, 2]) in \
            ["/foo?bar=1&bar=2", "/foo?bar=2&bar=1"]
        assert url("/foo", bar=("asdf", "qwer")) in \
            ["/foo?bar=qwer&bar=asdf", "/foo?bar=asdf&bar=qwer"]

    def test_unicode(self):
        """url() can handle unicode parameters"""
        testutil.create_request("/")
        assert url('/', x=u'\N{LATIN SMALL LETTER A WITH GRAVE}'
            u'\N{LATIN SMALL LETTER E WITH GRAVE}'
            u'\N{LATIN SMALL LETTER I WITH GRAVE}'
            u'\N{LATIN SMALL LETTER O WITH GRAVE}'
            u'\N{LATIN SMALL LETTER U WITH GRAVE}') \
            == '/?x=%C3%A0%C3%A8%C3%AC%C3%B2%C3%B9'

    def test_list(self):
        """url() can handle list parameters, with unicode too"""
        testutil.create_request("/")
        assert url('/', foo=['bar', u'\N{LATIN SMALL LETTER A WITH GRAVE}']
            ) == '/?foo=bar&foo=%C3%A0'

    def test_existing_query_string(self):
        """url() can handle URL with existing query string"""
        testutil.create_request("/")
        test_url = url('/foo', {'first': 1})
        assert url(test_url, {'second': 2}) == '/foo?first=1&second=2'

    def test_url_kwargs_overwrite_tgparams(self):
        """Keys in tgparams in call to url() overwrite kw args"""
        params = {'spamm': 'eggs'}
        assert 'spamm=ham' in url('/foo', params, spamm='ham')

    def test_url_doesnt_change_tgparams(self):
        """url() does not change the dict passed as second arg"""
        params = {'spamm': 'eggs'}
        assert 'foo' in url('/foo', params, spamm='ham')
        assert params['spamm'] == 'eggs'

    def test_index_trailing_slash(self):
        """If there is no trailing slash on an index method call, redirect"""
        cherrypy.root = SubApp()
        cherrypy.root.foo = SubApp()
        testutil.create_request("/foo")
        assert cherrypy.response.status.startswith("302")

    def test_can_use_internally_defined_arguments(self):
        """Can use argument names that are internally used by TG in controllers"""

        class App(controllers.RootController):

            [expose()]
            def index(self, **kw):
                return "\n".join(["%s:%s" % i for i in kw.iteritems()])

        cherrypy.root = App()
        testutil.create_request("/?format=foo&template=bar&fragment=boo")
        output = cherrypy.response.body[0]
        assert "format:foo" in output
        assert "template:bar" in output
        assert "fragment:boo" in output
