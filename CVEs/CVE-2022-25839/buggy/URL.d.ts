/**
 *  URL parser.
 *
 *  @license MIT
 *  @version 2.0.0
 *  @author Dumitru Uzun (DUzun.Me)
 *  @umd AMD, Browser, CommonJs, noDeps
 */

import parseUrl from "./parseUrl";
import fromLocation from "./fromLocation";
import toObject from "./toObject";
import fromObject from "./fromObject";
import { is_url } from "./helpers";
import { is_domain } from "./helpers";


declare namespace URLJS {
    type URLPart =
        | 'protocol'
        | 'username'
        | 'password'
        | 'host'
        | 'hostname'
        | 'port'
        | 'pathname'
        | 'search'
        | 'query'
        | 'hash'
        | 'path'
        | 'origin'
        | 'domain'
        | 'href'
    ;

    export { parseUrl };
    export { fromLocation };
    export { toObject, fromObject };
    export { is_url, is_domain };
}

interface URLJS {
    protocol: string;
    password: string;
    username: string;
    host: string;
    hostname: string;
    port: string;
    pathname: string;
    search: string;
    query: string | object;
    hash: string;
    path: string;
    origin: string;
    href: string;

    (url: string | URLJS, baseURL?: string | URLJS): URLJS;
    new(url: string | URLJS, baseURL?: string | URLJS): URLJS;

    toString(): string;
    valueOf(): string;
}

export default URLJS;
