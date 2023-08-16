/**
 * This file is part of the Gribbit Web Framework.
 * 
 *     https://github.com/lukehutch/gribbit
 * 
 * @author Luke Hutchison
 * 
 * --
 * 
 * @license Apache 2.0 
 * 
 * Copyright 2015 Luke Hutchison
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *     http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
package gribbit.auth;

import gribbit.server.config.GribbitProperties;
import gribbit.util.RandomTokenGenerator;
import gribbit.util.WebUtils;
import io.netty.handler.codec.http.DefaultCookie;
import io.netty.handler.codec.http.ServerCookieEncoder;

/**
 * Cookies!
 */
public class Cookie {

    private final String name;

    private final String path;

    /** The unencoded cookie value. */
    private String value;

    private long maxAgeSeconds;

    private boolean discardAtEndOfBrowserSession;

    // -----------------------------------------------------------------------------------------------------

    /** The name of the email address cookie. Used to notify the Persona client as to who is logged in. */
    public static final String EMAIL_COOKIE_NAME = "_email";

    /** The name of the flash cookie. */
    public static final String FLASH_COOKIE_NAME = "_flash";

    /** The name of the cookie that indicates the auth-required URI the user was trying to visit before logging in. */
    public static final String REDIRECT_AFTER_LOGIN_COOKIE_NAME = "_redir";

    // -----------------------------------------------------------------------------------------------------

    /** The name of the session cookie. */
    public static final String SESSION_COOKIE_NAME = "_session";

    /** How long a session cookie lasts for. */
    public static final int SESSION_COOKIE_MAX_AGE_SECONDS = 30 * 24 * 60 * 60;

    /** Session cookie length (number of random bytes generated before base 64 encoding) */
    public static final int SESSION_COOKIE_LENGTH = 20;

    public static String generateRandomSessionToken() {
        return RandomTokenGenerator.generateRandomTokenBase64(Cookie.SESSION_COOKIE_LENGTH);
    }

    // ------------------------------------------------------------------------------------------------------

    // Valid characters for cookie fields and values
    private static final boolean[] VALID_CHAR = new boolean[256];
    static {
        for (int i = 33; i <= 126; i++)
            VALID_CHAR[i] = true;
        for (char c : new char[] { '\'', '"', ',', ';', '\\' })
            VALID_CHAR[c] = false;
    }

    // Check the chars in a cookie's name and path are valid
    private static void checkValidCookieFieldStr(String str) {
        if (str.length() > 3500) {
            throw new RuntimeException("Cookie value too long: " + str);
        }
        for (int i = 0, n = str.length(); i < n; i++) {
            char c = str.charAt(i);
            if (c > 255 || !VALID_CHAR[c]) {
                throw new RuntimeException("Invalid cookie field: " + str);
            }
        }
    }

    // -----------------------------------------------------------------------------------------------------

    /**
     * Create a cookie.
     */
    public Cookie(String name, String path, String cookieValue, long maxAgeSeconds, boolean discardAtEndOfBrowserSession) {
        this.name = name;
        checkValidCookieFieldStr(name);
        this.path = path;
        if (path != null) {
            checkValidCookieFieldStr(path);
        }
        this.value = cookieValue;
        this.maxAgeSeconds = maxAgeSeconds;
        this.discardAtEndOfBrowserSession = discardAtEndOfBrowserSession;

        if (this.maxAgeSeconds <= 0 && this.maxAgeSeconds != Long.MIN_VALUE) {
            // If maxAge <= 0, cookie is expired immediately (so there is nothing to encode)
            this.value = "";
            this.maxAgeSeconds = 0;
        } else {
            // if maxAge == Long.MIN_VALUE or discardAtEndOfBrowserSession is true, cookie expires at end of session
            if (maxAgeSeconds == Long.MIN_VALUE) {
                this.discardAtEndOfBrowserSession = true;
            } else if (this.discardAtEndOfBrowserSession) {
                this.maxAgeSeconds = Long.MIN_VALUE;
            }
        }
    }

    /**
     * Create a cookie with the discard flag set to false (cookie is not discarded when browser session closes).
     */
    public Cookie(String name, String path, String cookieValue, long maxAgeInSeconds) {
        this(name, path, cookieValue, maxAgeInSeconds, false);
    }

    /**
     * Create a cookie with path unset (meaning, according to the HTTP spec, it will default to the path of the object
     * currently being requested), and the discard flag set to false (cookie is not discarded when browser session
     * closes).
     */
    public Cookie(String name, String cookieValue, long maxAgeInSeconds) {
        this(name, null, cookieValue, maxAgeInSeconds, false);
    }

    // -----------------------------------------------------------------------------------------------------

    /**
     * Parse a cookie from a Netty Cookie. Will throw an exception if cookie decoding failed for some reason (in this
     * case, ignore the cookie).
     */
    public Cookie(io.netty.handler.codec.http.Cookie nettyCookie) {
        this.name = nettyCookie.name();
        this.path = nettyCookie.path();
        this.value = WebUtils.unescapeCookieValue(nettyCookie.value());
        this.maxAgeSeconds = nettyCookie.maxAge();
        this.discardAtEndOfBrowserSession = nettyCookie.isDiscard();
    }

    /** Create a Netty cookie from this Cookie object. */
    public io.netty.handler.codec.http.Cookie toNettyCookie() {
        io.netty.handler.codec.http.Cookie nettyCookie = new DefaultCookie(name, WebUtils.escapeCookieValue(value));
        if (path != null && !path.isEmpty()) {
            nettyCookie.setPath(path);
        }
        nettyCookie.setMaxAge(maxAgeSeconds);
        nettyCookie.setDiscard(discardAtEndOfBrowserSession);
        nettyCookie.setHttpOnly(true);  // TODO
        if (GribbitProperties.SSL) {
            nettyCookie.setSecure(true);  // TODO
        }
        return nettyCookie;
    }

    // -----------------------------------------------------------------------------------------------------

    /**
     * Create a cookie that, if set in response, overwrites and deletes the named cookie (by setting maxAgeSeconds to
     * zero). Have to specify the path since there can be multiple cookies with the same name but with different paths;
     * this will only delete the cookie with the matching path.
     */
    public static Cookie deleteCookie(String name, String path) {
        return new Cookie(name, path, "", 0, false);
    }

    /**
     * Create a cookie that, if set in response, overwrites and deletes the cookie with the same name and path (by
     * setting maxAgeSeconds to zero).
     */
    public static Cookie deleteCookie(Cookie cookie) {
        return new Cookie(cookie.getName(), cookie.getPath(), "", 0, false);
    }

    // -----------------------------------------------------------------------------------------------------

    /**
     * Get the cookie as an HTTP header string, including all cookie headers, with the value escaped or base64-encoded.
     */
    @Override
    public String toString() {
        return ServerCookieEncoder.encode(toNettyCookie());
    }

    /** Get the name of the cookie. */
    public String getName() {
        return name;
    }

    /** Get the cookie path, or "" if the cookie path is not set. */
    public String getPath() {
        return path == null ? "" : path;
    }

    /** Get unencoded value of cookie. */
    public String getValue() {
        return value;
    }

    /** Return true if the cookie has expired. */
    public boolean hasExpired() {
        return maxAgeSeconds <= 0 && maxAgeSeconds != Long.MIN_VALUE;
    }
}
