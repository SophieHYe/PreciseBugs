import parseUrl from './parseUrl';
import fromLocation from './fromLocation';
import fromObject from './fromObject';
import toObject from './toObject';
import { NIL, defProp, is_domain, is_url, getDomainName } from './helpers';

/**
 *  URL parser.
 *
 *  @license MIT
 *  @version 2.0.0
 *  @author Dumitru Uzun (DUzun.Me)
 *  @umd AMD, Browser, CommonJs, noDeps
 */
export default function URLJS(url, baseURL, parseQuery) {
    if (url != undefined) {
        let _url = parseUrl.call(URLJS, url, undefined, parseQuery);
        if (_url !== false) return _url;
        if (baseURL) {
            _url = baseURL instanceof URLJS ? baseURL : parseUrl.call(URLJS, baseURL);
            if (_url === false) return;
            url = String(url);
            if (url.slice(0, 2) == '//') {
                return parseUrl.call(URLJS, _url.protocol + url, undefined, parseQuery);
            }
            let _path;
            if (url.slice(0, 1) == '/') {
                _path = url;
            }
            else {
                _path = _url.pathname.split('/');
                _path[_path.length - 1] = url;
                _path = _path.join('/');
            }
            return parseUrl.call(URLJS, _url.origin + _path, undefined, parseQuery);
        }
        throw new SyntaxError(`Failed to construct 'URL': Invalid URL`);
    }
}

// ---------------------------------------------------------------------------
URLJS.parseUrl = parseUrl;
URLJS.fromLocation = fromLocation;
URLJS.toObject = toObject;
URLJS.fromObject = fromObject;
// ---------------------------------------------------------------------------
URLJS.is_url = is_url;
URLJS.is_domain = is_domain;

// ---------------------------------------------------------------------------
const __ = URLJS.prototype;

// ---------------------------------------------------------------------------
const __ex = typeof Object.defineProperty == 'function'
        ? (name, func, proto) => {
            Object.defineProperty(proto || __, name, {
                value: func,
                configurable: true,
                enumerable: false,
                writeable: true
            });
        }
        : (name, func, proto) => {
            // Take care with (for ... in) on strings!
            (proto || __)[name] = func;
        }
    ;
// ---------------------------------------------------------------------------
function _uri_to_string_() {
    return fromLocation(this);
}
__ex('toString', _uri_to_string_);
__ex('valueOf', _uri_to_string_);

// ---------------------------------------------------------------------------

if (Object.assign) {
    Object.assign(__, {
        protocol: undefined,
        username: undefined,
        password: undefined,
        host: undefined,
        hostname: undefined,
        port: undefined,
        pathname: undefined,
        search: undefined,
        query: undefined,
        hash: undefined,
    });
}

defProp(__, 'origin',
    function () {
        const u = this;
        if (u.protocol || u.host || u.hostname) {
            return u.protocol + '//' + (u.host || (u.hostname + (u.port ? ':' + u.port : NIL)));
        }
    },
    function (origin) {
        const u = this;
        if(u.origin == origin) return;
        let v = new URLJS(origin, u);
        u.protocol = v.protocol;
        u.hostname = v.hostname;
        u.port = v.port;

        // In the case these props have been overwritten
        // u.host = v.host;
    }
);

defProp(__, 'href',
    function () { return String(this); },
    function(href) {
        const u = this;
        if (u.href == href) return;

        let v = new URLJS(href, u);
        u.protocol = v.protocol;
        u.username = v.username;
        u.password = v.password;
        u.hostname = v.hostname;
        u.port = v.port;
        u.pathname = v.pathname;
        u.search = v.search;
        u.hash = v.hash;

        u.query = search2query(v.search, typeof u.query == 'object');

        // In the case these props have been overwritten
        // u.host = v.host;
        // u.origin = v.origin;
    }
);

defProp(__, 'host',
    function () {
        const u = this;
        if (u.hostname || u.port) {
            return (u.hostname||NIL) + (u.port ? ':' + u.port : NIL);
        }
    },
    function (host) {
        const u = this;
        if (u.host == host) return;

        let v = String(host).split(':');
        u.hostname = v.shift();
        u.port = v.length ? v.shift() : NIL;
    }
);

defProp(__, 'path',
    function () {
        const u = this;
        return (u.pathname || NIL) + (u.search || NIL);
    },
    function(path) {
        const u = this;
        if (u.path == path) return;

        let v = String(path).split('?');
        u.pathname = v.shift();
        if (u.pathname.slice(0, 1) != '/') u.pathname = '/' + u.pathname;

        let query = v.length ? v.join('?') : undefined;
        u.search = query != undefined ? '?' + query : NIL;
        u.query = typeof u.query == 'object' ? toObject(query) : query || '';
    }
);

defProp(__, 'pathname', () => '/');
defProp(__, 'domain', function () {
    const u = this;
    return getDomainName(u.hostname || NIL);
});


function search2query(search, asObj) {
    let query = search ? String(search).replace(/^\?/, NIL) : NIL;
    return asObj ? toObject(query) : query;
}
