import { NIL, getDomainName } from './helpers';
import toObject from './toObject';

/*globals URL*/

const _parse_url_exp = new RegExp([
    '^([\\w.+\\-\\*]+:)//'          // protocol
    , '(([^:/?#]*)(?::([^/?#]*))?@|)' // username:password
    , '(([^:/?#]*)(?::(\\d+))?)'      // host == hostname:port
    , '(/[^?#]*|)'                    // pathname
    , '(\\?([^#]*)|)'                 // search & query
    , '(#.*|)$'                       // hash
].join(NIL));

const _parse_url_map = {
    protocol: 1
    , username: 3
    , password: 4
    , host: 5
    , hostname: 6
    , port: 7
    , pathname: 8
    , search: 9
    , query: 10
    , hash: 11
};

export default function parseUrl(href, part, parseQuery) {
    href = String(href);
    var match = href.match(_parse_url_exp)
        , map = _parse_url_map
        , i, ret = false
        ;
    if (match) {
        if (part && part in map) {
            ret = match[map[part]] || NIL;
            if (part == 'pathname') {
                if (!ret) ret = '/';
            }
            if (parseQuery && part == 'query') {
                ret = toObject(ret || NIL);
            }
        }
        else {
            let _ = this;
            if(typeof _ != 'function') {
                _ = typeof URL == 'function' && URL.createObjectURL ? URL : Object;
                ret = new _(href);
                // ret.toString = function _uri_to_string_() {
                //     return fromLocation(this);
                // };
            }
            else {
                ret = new _(); // URLJS() constructor?
            }

            for (i in map) if (map.hasOwnProperty(i)) {
                ret[i] = match[map[i]] || NIL;
            }
            if (part && part in ret) return ret[part];

            if (!ret.pathname) ret.pathname = '/';
            if (!ret.path) ret.path = ret.pathname + ret.search;
            if (!ret.origin) ret.origin = ret.protocol + '//' + ret.host;
            if (!ret.domain) ret.domain = getDomainName(ret.hostname);
            if (parseQuery) ret.query = toObject(ret.query || NIL);
            if (!ret.origin) ret.href = String(href); // ??? may need some parse

            if (part) ret = ret[part];
        }
    }
    return ret;
}
