"""Classes and methods for TurboGears controllers."""

import logging
import re
import urllib
import urlparse
import types
from itertools import izip
from dispatch import generic, strategy, functions
from inspect import isclass
import cherrypy
from cherrypy import request, response
import turbogears.util as tg_util
from turbogears import view, database, errorhandling, config
from turbogears.decorator import weak_signature_decorator
from turbogears.validators import Invalid
from turbogears.errorhandling import error_handler, exception_handler
from memory_profiler_setup import profile_expose_method

log = logging.getLogger("turbogears.controllers")

if config.get("session_filter.on", None):
    if config.get("session_filter.storage_type", None) == "PostgreSQL":
        import psycopg2
        config.update(
                {'session_filter.get_db': psycopg2.connect(
                    psycopg2.get('sessions.postgres.dsn'))
                    })
    # support for mysql/sqlite/etc here


def _process_output(output, template, format, content_type,
        mapping, fragment=False):
    """Produce final output form from data returned from a controller method.

    See the expose() arguments for more info since they are the same.

    """
    if isinstance(output, dict):
        from turbogears.widgets import js_location

        css = tg_util.setlike()
        js = dict(izip(js_location, iter(tg_util.setlike, None)))
        include_widgets = {}
        include_widgets_lst = config.get("tg.include_widgets", [])

        if config.get("tg.mochikit_all", False):
            include_widgets_lst.insert(0, 'turbogears.mochikit')

        for i in include_widgets_lst:
            widget = tg_util.load_class(i)
            if isclass(widget):
                widget = widget()
            include_widgets["tg_%s" % i.split(".")[-1]] = widget
            for script in widget.retrieve_javascript():
                if hasattr(script, "location"):
                    js[script.location].add(script)
                else:
                    js[js_location.head].add(script)
            css.add_all(widget.retrieve_css())

        for value in output.itervalues():
            if hasattr(value, "retrieve_css"):
                retrieve = getattr(value, "retrieve_css")
                if callable(retrieve):
                    css.add_all(value.retrieve_css())
            if hasattr(value, "retrieve_javascript"):
                retrieve = getattr(value, "retrieve_javascript")
                if callable(retrieve):
                    for script in value.retrieve_javascript():
                        if hasattr(script, "location"):
                            js[script.location].add(script)
                        else:
                            js[js_location.head].add(script)
        output.update(include_widgets)
        output["tg_css"] = css
        for location in iter(js_location):
            output["tg_js_%s" % str(location)] = js[location]

        tg_flash = _get_flash()
        if tg_flash:
            output["tg_flash"] = tg_flash

        headers = {'Content-Type': content_type}
        output = view.render(output, template=template, format=format,
                    mapping=mapping, headers=headers,
                    fragment=fragment)
        content_type = headers['Content-Type']

    if content_type:
        response.headers["Content-Type"] = content_type
    else:
        content_type = response.headers.get("Content-Type", 'text/plain')

    if content_type.startswith("text/"):
        if isinstance(output, unicode):
            output = output.encode(tg_util.get_template_encoding_default())

    return output


class BadFormatError(Exception):
    """Output-format exception."""


def validate(form=None, validators=None,
             failsafe_schema=errorhandling.FailsafeSchema.none,
             failsafe_values=None, state_factory=None):
    """Validate input.

    @param form: a form instance that must be passed throught the validation
    process... you must give a the same form instance as the one that will
    be used to post data on the controller you are putting the validate
    decorator on.
    @type form: a form instance

    @param validators: individual validators to use for parameters.
    If you use a schema for validation then the schema instance must
    be the sole argument.
    If you use simple validators, then you must pass a dictionary with
    each value name to validate as a key of the dictionary and the validator
    instance (eg: tg.validators.Int() for integer) as the value.
    @type validators: dictionary or schema instance

    @param failsafe_schema: a schema for handling failsafe values.
    The default is 'none', but you can also use 'values', 'map_errors',
    or 'defaults' to map erroneous inputs to values, corresponding exceptions
    or method defaults.
    @type failsafe_schema: errorhandling.FailsafeSchema

    @param failsafe_values: replacements for erroneous inputs. You can either
    define replacements for every parameter, or a single replacement value
    for all parameters. This is only used when failsafe_schema is 'values'.
    @type failsafe_values: a dictionary or a single value

    @param state_factory: If this is None, the initial state for validation
    is set to None, otherwise this must be a callable that returns the initial
    state to be used for validation.
    @type state_factory: callable or None

    """
    def entangle(func):
        if callable(form) and not hasattr(form, "validate"):
            init_form = lambda self: form(self)
        else:
            init_form = lambda self: form

        def validate(func, *args, **kw):
            # do not validate a second time if already validated
            if hasattr(request, 'validation_state'):
                return func(*args, **kw)

            form = init_form(args and args[0] or kw["self"])
            args, kw = tg_util.to_kw(func, args, kw)

            errors = {}
            if state_factory is not None:
                state = state_factory()
            else:
                state = None

            if form:
                value = kw.copy()
                try:
                    kw.update(form.validate(value, state))
                except Invalid, e:
                    errors = e.unpack_errors()
                    request.validation_exception = e
                request.validated_form = form

            if validators:
                if isinstance(validators, dict):
                    for field, validator in validators.iteritems():
                        try:
                            kw[field] = validator.to_python(
                                kw.get(field, None), state)
                        except Invalid, error:
                            errors[field] = error
                else:
                    try:
                        value = kw.copy()
                        kw.update(validators.to_python(value, state))
                    except Invalid, e:
                        errors = e.unpack_errors()
                        request.validation_exception = e
            request.validation_errors = errors
            request.input_values = kw.copy()
            request.validation_state = state

            if errors:
                kw = errorhandling.dispatch_failsafe(failsafe_schema,
                                            failsafe_values, errors, func, kw)
            args, kw = tg_util.from_kw(func, args, kw)
            return errorhandling.run_with_errors(errors, func, *args, **kw)

        return validate
    return weak_signature_decorator(entangle)


class CustomDispatch(functions.GenericFunction):

    def combine(self, cases):
        strict = [strategy.ordered_signatures, strategy.safe_methods]
        cases = strategy.separate_qualifiers(
            cases,
            primary = strict,
        )
        primary = strategy.method_chain(cases.get('primary', []))
        if type(primary) != types.FunctionType:
            for i in primary:
                for y in i:
                    return y[1]
        return primary


def _add_rule(_expose, found_default, as_format, accept_format, template,
              rulefunc):
    if as_format == "default":
        if found_default:
            colon = template.find(":")
            if colon == -1:
                as_format = template
            else:
                as_format = template[:colon]
        else:
            found_default = True
    ruleparts = ['kw.get("tg_format", "default") == "%s"' % as_format]
    if accept_format:
        ruleparts.append('(accept == "%s" and kw.get("tg_format"'
            ', "default") == "default")' % accept_format)
    rule = " or ".join(ruleparts)
    log.debug("Generated rule %s", rule)
    _expose.when(rule)(rulefunc)

    return found_default


def _build_rules(func):
    [generic(CustomDispatch)]
    def _expose(func, accept, allow_json, *args, **kw):
        pass

    if func._allow_json:
        log.debug("Adding allow_json rule: "
            'allow_json and (kw.get("tg_format", None) == "json"'
            ' or accept in ("application/json", "text/javascript"))')
        _expose.when('allow_json and (kw.get("tg_format", None) == "json"'
            ' or accept in ("application/json", "text/javascript"))')(
            lambda _func, accept, allow_json, *args, **kw:
                _execute_func(_func, "json", "json", "application/json",
                    None, False, args, kw))

    found_default = False
    for ruleinfo in func._ruleinfo:
        found_default = _add_rule(_expose, found_default, **ruleinfo)

    func._expose = _expose


def expose(template=None, validators=None, allow_json=None, html=None,
           format=None, content_type=None, inputform=None, fragment=False,
           as_format="default", mapping=None, accept_format=None,
           exclude_from_memory_profiling=False):
    """Exposes a method to the web.

    By putting the expose decorator on a method, you tell TurboGears that
    the method should be accessible via URL traversal. Additionally, expose
    handles the output processing (turning a dictionary into finished
    output) and is also responsible for ensuring that the request is
    wrapped in a database transaction.

    You can apply multiple expose decorators to a method, if
    you'd like to support multiple output formats. The decorator that's
    listed first in your code without as_format or accept_format is
    the default that is chosen when no format is specifically asked for.
    Any other expose calls that are missing as_format and accept_format
    will have as_format implicitly set to the whatever comes before
    the ":" in the template name (or the whole template name if there
    is no ":". For example, <code>expose("json")</code>, if it's not
    the default expose, will have as_format set to "json".

    When as_format is set, passing the same value in the tg_format
    parameter in a request will choose the options for that expose
    decorator. Similarly, accept_format will watch for matching
    Accept headers. You can also use both. expose("json", as_format="json",
    accept_format="application/json") will choose JSON output for either
    case: tg_format=json as a parameter or Accept: application/json as a
    request header.

    Passing allow_json=True to an expose decorator
    is equivalent to adding the decorator just mentioned.

    Each expose decorator has its own set of options, and each one
    can choose a different template or even template engine (you can
    use Kid for HTML output and Cheetah for plain text, for example).
    See the other expose parameters below to learn about the options
    you can pass to the template engine.

    Take a look at the
    <a href="tests/test_expose-source.html">test_expose.py</a> suite
    for more examples.

    @param template "templateengine:dotted.reference" reference along the
            Python path for the template and the template engine. For
            example, "kid:foo.bar" will have Kid render the bar template in
            the foo package.
    @keyparam format format for the template engine to output (if the
            template engine can render different formats. Kid, for example,
            can render "html", "xml" or "xhtml")
    @keyparam content_type sets the content-type http header
    @keyparam allow_json allow the function to be exposed as json
    @keyparam fragment for template engines (like Kid) that generate
            DOCTYPE declarations and the like, this is a signal to
            just generate the immediate template fragment. Use this
            if you're building up a page from multiple templates or
            going to put something onto a page with .innerHTML.
    @keyparam mapping mapping with options that are sent to the template
            engine
    @keyparam as_format designates which value of tg_format will choose
            this expose.
    @keyparam accept_format which value of an Accept: header will
            choose this expose.
    @keyparam html deprecated in favor of template
    @keyparam validators deprecated. Maps argument names to validator
            applied to that arg
    @keyparam inputform deprecated. A form object that generates the
            input to this method
    @keyparam exclude_from_memory_profiling allows to exclude individual end points from memory profiling. Can be 
            used for performance or in case profiling generates errors
    """
    if html:
        template = html
    if not template:
        template = format
    if format == "json" or (format is None and template is None):
        template = "json"
        allow_json = True
    if content_type is None:
        content_type = config.get("tg.content_type", None)

    if config.get("tg.session.automatic_lock", None):
        cherrypy.session.acquire_lock()

    def entangle(func):
        log.debug("Exposing %s", func)
        log.debug("template: %s, format: %s, allow_json: %s, "
            "content-type: %s", template, format, allow_json, content_type)
        if not getattr(func, "exposed", False):
            def expose(func, *args, **kw):
                accept = request.headers.get('Accept', "").lower()
                accept = tg_util.simplify_http_accept_header(accept)
                if not hasattr(func, "_expose"):
                    _build_rules(func)
                if hasattr(request, "in_transaction"):
                    output = func._expose(func, accept, func._allow_json,
                                *args, **kw)
                else:
                    request.in_transaction = True
                    output = profile_expose_method(_run_with_transaction, accept, args, func, kw,
                                                   exclude_from_memory_profiling)
                return output
            func.exposed = True
            func._ruleinfo = []
            allow_json_from_config = config.get("tg.allow_json", False)
            func._allow_json = allow_json_from_config or template == 'json'
        else:
            expose = lambda func, *args, **kw: func(*args, **kw)

        func._ruleinfo.insert(0, dict(as_format=as_format,
            accept_format=accept_format, template=template,
            rulefunc = lambda _func, accept, allow_json, *args, **kw:
                _execute_func(_func, template, format, content_type,
                    mapping, fragment, args, kw)))

        if allow_json:
            func._allow_json = True

        if inputform or validators:
            import warnings
            warnings.warn(
                "Use a separate decorator validate() rather than passing "
                "arguments validators and/or inputform to decorator "
                "expose().",
                DeprecationWarning, 2)
            func = validate(form=inputform, validators=validators)(func)

        return expose
    return weak_signature_decorator(entangle)


def _run_with_transaction(accept, args, func, kw):
    return database.run_with_transaction(
             func._expose, func, accept, func._allow_json,
             *args, **kw)


def _execute_func(func, template, format, content_type, mapping, fragment, args, kw):
    """Call controller method and process it's output."""
    if config.get("tg.strict_parameters", False):
        tg_util.remove_keys(kw, ["tg_random", "tg_format"]
            + config.get("tg.ignore_parameters", []))
    else:
        # get special parameters used by upstream decorators like paginate
        try:
            tg_kw = dict([(k, v) for k, v in kw.items() if k in func._tg_args])
        except AttributeError:
            tg_kw = {}
        # remove excessive parameters
        args, kw = tg_util.adapt_call(func, args, kw)
        # add special parameters again
        kw.update(tg_kw)
    if config.get('server.environment', 'development') == 'development':
        # Only output this in development mode: If it's a field storage object,
        # this means big memory usage, and we don't want that in production
        log.debug("Calling %s with *(%s), **(%s)", func, args, kw)
    output = errorhandling.try_call(func, *args, **kw)
    assert isinstance(output, (basestring, dict, list, types.GeneratorType)), \
           "Method %s.%s() returned unexpected output. Output should " \
           "be of type basestring, dict, list or generator." % (
            args[0].__class__.__name__, func.__name__)
    if isinstance(output, dict):
        template = output.pop("tg_template", template)
        format = output.pop("tg_format", format)
    if template and template.startswith("."):
        template = func.__module__[:func.__module__.rfind('.')]+template
    return _process_output(output, template, format, content_type, mapping, fragment)


def flash(message):
    """Set a message to be displayed in the browser on next page display."""
    message = tg_util.quote_cookie(tg_util.to_utf8(message))
    response.simple_cookie['tg_flash'] = message
    response.simple_cookie['tg_flash']['path'] = '/'


def _get_flash():
    """Retrieve the flash message (if one is set), clearing the message."""
    request_cookie = request.simple_cookie
    response_cookie = response.simple_cookie

    def clearcookie():
        response_cookie["tg_flash"] = ""
        response_cookie["tg_flash"]['expires'] = 0
        response_cookie['tg_flash']['path'] = '/'

    if response_cookie.has_key("tg_flash"):
        message = response_cookie["tg_flash"].value
        response_cookie.pop("tg_flash")
        if request_cookie.has_key("tg_flash"):
            # New flash overrided old one sitting in cookie. Clear that old cookie.
            clearcookie()
    elif request_cookie.has_key("tg_flash"):
        message = request_cookie["tg_flash"].value
        if not response_cookie.has_key("tg_flash"):
            clearcookie()
    else:
        message = None
    if message:
        message = unicode(tg_util.unquote_cookie(message), 'utf-8')
    return message


class Controller(object):
    """Base class for a web application's controller.

    Currently, this provides positional parameters functionality
    via a standard default method.

    """


class RootController(Controller):
    """Base class for the root of a web application.

    Your web application should have one of these. The root of
    your application is used to compute URLs used by your app.

    """
    is_app_root = True

    msglog = logging.getLogger('cherrypy.msg')
    msglogfunc = {0: msglog.info, 1: msglog.warning, 2: msglog.error}
    def _cp_log_message(self, msg, context = 'nocontext', severity = 0):
        log = self.msglogfunc[severity]
        text = ''.join((context, ': ', msg))
        log(text)

    accesslog = logging.getLogger("turbogears.access")
    def _cp_log_access(self):
        tmpl = '%(h)s %(l)s %(u)s "%(r)s" %(s)s %(b)s "%(f)s" "%(a)s"'
        try:
            username = request.user_name
            if username:
                if isinstance(username, unicode):
                    # might get concatenated to non-ascii byte strings
                    username = username.encode('utf-8')
            else:
                username = "-"
        except AttributeError:
            username = "-"
        s = tmpl % {'h': request.remote_host,
                   'l': '-',
                   'u': username,
                   'r': request.requestLine,
                   's': response.status.split(" ", 1)[0],
                   'b': response.headers.get('Content-Length',
                            '') or "-",
                   'f': request.headers.get('referer', ''),
                   'a': request.headers.get('user-agent', ''),
        }
        self.accesslog.info(s)

Root = RootController


def url(tgpath, tgparams=None, **kw):
    """Computes URLs.

    tgpath can be a list or a string. If the path is absolute (starts
    with a "/"), the server.webpath, SCRIPT_NAME and the approot of the
    application are prepended to the path. In order for the approot to
    be detected properly, the root object should extend
    controllers.RootController.

    Query parameters for the URL can be passed in as a dictionary in
    the second argument *or* as keyword parameters.

    Values which are a list or a tuple are used to create multiple
    key-value pairs.

    """
    if not isinstance(tgpath, basestring):
        tgpath = '/'.join(list(tgpath))
    if tgpath.startswith('/'):
        webpath = (config.get('server.webpath') or '').rstrip('/')
        if tg_util.request_available():
            check_app_root()
            tgpath = request.app_root + tgpath
            try:
                webpath += request.wsgi_environ['SCRIPT_NAME'].rstrip('/')
            except (AttributeError, KeyError):
                pass
        tgpath = webpath + tgpath
    if tgparams is None:
        tgparams = kw
    else:
        try:
            tgparams = tgparams.copy()
            tgparams.update(kw)
        except AttributeError:
            raise TypeError('url() expects a dictionary for query parameters')
    args = []
    for key, value in tgparams.iteritems():
        if value is None:
            continue
        if isinstance(value, (list, tuple)):
            pairs = [(key, v) for v in value]
        else:
            pairs = [(key, value)]
        for k, v in pairs:
            if v is None:
                continue
            if isinstance(v, unicode):
                v = v.encode('utf8')
            args.append((k, str(v)))
    if args:
        query_string = urllib.urlencode(args, True)
        if '?' in tgpath:
            tgpath += '&' + query_string
        else:
            tgpath += '?' + query_string
    return tgpath


def check_app_root():
    """Sets request.app_root if needed."""
    if hasattr(request, 'app_root'):
        return
    found_root = False
    trail = request.object_trail
    top = len(trail) - 1
    # compute the app_root by stepping back through the object
    # trail and collecting up the path elements after the first
    # root we find
    # we can eliminate this if we find a way to use
    # CherryPy's mounting mechanism whenever a new root
    # is hit.
    rootlist = []
    for i in xrange(len(trail) - 1, -1, -1):
        path, obj = trail[i]
        if not found_root and isinstance(obj, RootController):
            if i == top:
                break
            found_root = True
        if found_root and i > 0:
            rootlist.insert(0, path)
    app_root = '/'.join(rootlist)
    if not app_root.startswith('/'):
        app_root = '/' + app_root
    if app_root.endswith('/'):
        app_root = app_root[:-1]
    request.app_root = app_root


def get_server_name(): 
    """Return name of the server this application runs on. 
 
    Respects 'Host' and 'X-Forwarded-Host' header. 
 
    See the docstring of the 'absolute_url' function for more information. 
 
    """ 
    get = config.get 
    h = request.headers 
    host = get('tg.url_domain') or h.get('X-Forwarded-Host', h.get('Host')) 
    if not host: 
        host = '%s:%s' % (get('server.socket_host', 'localhost'), 
            get('server.socket_port', 8080)) 
    return host 


def absolute_url(tgpath='/', params=None, **kw): 
    """Return absolute URL (including schema and host to this server). 
 
    Tries to account for 'Host' header and reverse proxying 
    ('X-Forwarded-Host'). 
 
    The host name is determined this way: 
 
    * If the config setting 'tg.url_domain' is set and non-null, use this value. 
    * Else, if the 'base_url_filter.use_x_forwarded_host' config setting is 
      True, use the value from the 'Host' or 'X-Forwarded-Host' request header. 
    * Else, if config setting 'base_url_filter.on' is True and 
      'base_url_filter.base_url' is non-null, use its value for the host AND 
      scheme part of the URL. 
    * As a last fallback, use the value of 'server.socket_host' and 
      'server.socket_port' config settings (defaults to 'localhost:8080'). 
 
    The URL scheme ('http' or 'http') used is determined in the following way: 
 
    * If 'base_url_filter.base_url' is used, use the scheme from this URL. 
    * If there is a 'X-Use-SSL' request header, use 'https'. 
    * Else, if the config setting 'tg.url_scheme' is set, use its value. 
    * Else, use the value of 'cherrypy.request.scheme'. 
 
    """ 
    get = config.get 
    use_xfh = get('base_url_filter.use_x_forwarded_host', False) 
    if request.headers.get('X-Use-SSL'): 
        scheme = 'https' 
    else: 
        scheme = get('tg.url_scheme') 
    if not scheme: 
        scheme = request.scheme 
    base_url = '%s://%s' % (scheme, get_server_name()) 
    if get('base_url_filter.on', False) and not use_xfh: 
        base_url = get('base_url_filter.base_url').rstrip('/') 
    return '%s%s' % (base_url, url(tgpath, params, **kw)) 


def redirect(redirect_path, redirect_params=None, **kw):
    """Redirect (via cherrypy.HTTPRedirect).

    Raises the exception instead of returning it, this to allow
    users to both call it as a function or to raise it as an exception.

    """
    if not isinstance(redirect_path, basestring):
        redirect_path = '/'.join(list(redirect_path))
    if not redirect_path.startswith('/'):
        path = request.path_info
        check_app_root()
        if path.startswith(request.app_root):
            path = path[len(request.app_root):]
        redirect_path = urlparse.urljoin(path, redirect_path)
    raise cherrypy.HTTPRedirect(url(tgpath=redirect_path,
        tgparams=redirect_params, **kw))


__all__ = [
    "Controller",
    "error_handler",
    "exception_handler",
    "expose",
    "flash",
    "redirect",
    "Root",
    "RootController",
    "url",
    "validate",
]
