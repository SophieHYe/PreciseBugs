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

import gribbit.auth.User.Token.TokenType;
import gribbit.exception.UnauthorizedException;
import gribbit.model.DBModelStringKey;
import gribbit.request.Request;
import gribbit.response.Response;
import gribbit.server.GribbitServer;
import gribbit.server.siteresources.Database;
import gribbit.util.AppException;
import gribbit.util.Hash;
import gribbit.util.WebUtils;

import java.time.Instant;
import java.time.ZonedDateTime;
import java.time.format.DateTimeFormatter;
import java.time.temporal.ChronoUnit;
import java.util.HashMap;

import org.mongojack.MongoCollection;

/**
 * Used to store user identity and authentication information in the database.
 */
@MongoCollection(name = "users")
public class User extends DBModelStringKey {

    /** Key/value data for user */
    public HashMap<String, String> data;

    public String passwordHash;

    public String createdDate;

    public Boolean emailValidated;

    /**
     * Auth token, stored in both encrypted session-in-client cookie and server. Allows for browser cookie to be
     * revoked.
     */
    public Token sessionTok;

    /** Store CSRF token in User object to avoid re-calculating it where possible */
    public String csrfTok;

    /** Token used for validating email address. */
    public Token emailValidationTok;

    /** Token used for resetting password. */
    public Token passwordResetTok;

    // -----------------------------------------------------------------------------------------------------------------

    // Placeholder in password field for federated logins
    public static final String FEDERATED_LOGIN_PASSWORD_HASH_PLACEHOLDER = "FEDERATED LOGIN";

    // -----------------------------------------------------------------------------------------------------------------

    public User() {
    }

    public User(String email) {
        super(email);
    }

    // -----------------------------------------------------------------------------------------------------------------

    /** An authentication token, a password reset token or an email address verification token */
    public static class Token {
        public String token;
        public Long expires;
        public Token.TokenType tokType;

        public enum TokenType {
            SESSION, PW_RESET, EMAIL_VERIF
        };

        public Token() {
        }

        public Token(Token.TokenType tokType, Instant expires) {
            if (expires.isBefore(Instant.now())) {
                throw new IllegalArgumentException("Token already expired");
            }
            this.tokType = tokType;
            // Generate token as random base64-encoded number
            this.token = Cookie.generateRandomSessionToken();
            this.expires = expires.toEpochMilli();
        }

        public Token(Token.TokenType tokType, int numSecondsValid) {
            this(tokType, Instant.now().plus(numSecondsValid, ChronoUnit.SECONDS));
        }

        public boolean hasExpired() {
            return expires == null || expires < Instant.now().toEpochMilli();
        }

        @Override
        public String toString() {
            return tokType + "\t" + token + "\t" + expires;
        }
    }

    /**
     * Take an encrypted token, decrypt it, extract the username and token, look up the user, and make sure that the
     * copy of the auth token in the user matches the copy in the encrypted token, and that the token has not expired.
     * Throws an exception if any of this fails. Returns the user if it all succeeds.
     */
    // FIXME: remove this, and store tokens in user
    public static User validateTok(String email, String suppliedToken, TokenType tokType) throws Exception {
        if (email.isEmpty() || email.equals("null") || email.indexOf('@') < 0) {
            throw new AppException("Invalid email address");
        }
        if (suppliedToken == null || suppliedToken.isEmpty()) {
            throw new AppException("Invalid token");
        }

        // Look up user with this email addr
        User user = User.findByEmail(email);
        if (user == null) {
            throw new AppException("User account does not exist");
        }

        switch (tokType) {

        case SESSION:
            if (user.sessionTok == null || user.sessionTok.hasExpired() || !user.sessionTok.token.equals(suppliedToken)) {
                // Clear token if there is a mismatch, this will prevent users that manage to crack the cookie
                // encryption key from doing much, because they would also have to guess the token to log in.
                // Each attempt to guess the auth token will log them out and require them to log in successfully
                // to generate a new token. The account cannot be accessed using any old session-in-client cookie,
                // because there is no auth token on the server until the next successful login.
                user.clearSessionTok();
            } else {
                // User exists and token is valid; return user
                return user;
            }
            break;

        case EMAIL_VERIF:
            if (user.emailValidationTok == null || user.emailValidationTok.hasExpired()
                    || !user.emailValidationTok.token.equals(suppliedToken)) {
                // Clear token if there is a mismatch, this means that if a user uses an old email validation link,
                // it will invalidate the most recent link.
                user.clearEmailValidationTok();
            } else {
                // User exists and token is valid; return user
                return user;
            }
            break;

        case PW_RESET:
            if (user.passwordResetTok == null || user.passwordResetTok.hasExpired()
                    || !user.passwordResetTok.token.equals(suppliedToken)) {
                // Clear token if there is a mismatch, this means that if a user uses an old password reset link,
                // it will invalidate the most recent link.
                user.clearPasswordResetTok();
            } else {
                // User exists and token is valid; return user
                return user;
            }
            break;

        default:
            break;
        }
        throw new AppException("Token has expired or does not match");
    }

    // -----------------------------------------------------------------------------------------------------------------

    /**
     * Puts a key-value pair into the user data HashMap. N.B. does not save user, you need to do that manually once all
     * key-value pairs have been put.
     */
    public void putData(String key, String val) {
        if (this.data == null) {
            this.data = new HashMap<>();
        }
        this.data.put(key, val);
    }

    /** Gets the value corresponding to a given key in the user data HashMap, or null if not found. */
    public String getData(String key) {
        return this.data == null ? null : this.data.get(key);
    }

    // -----------------------------------------------------------------------------------------------------------------

    /** Return true if auth token is absent for user or has expired */
    public boolean sessionTokHasExpired() {
        return sessionTok == null || sessionTok.hasExpired();
    }

    /**
     * Create a new email validation token, store it in the user's account, and return the token. Expires after 1 day.
     */
    public String generateNewEmailValidationTok() {
        emailValidationTok = new Token(TokenType.EMAIL_VERIF, 1);
        emailValidated = false;
        save();
        return emailValidationTok.token;
    }

    /**
     * Create a new password reset token, store it in the user's account, and return the token. Expires after 1 day.
     */
    public String generateNewPasswordResetTok() {
        passwordResetTok = new Token(TokenType.PW_RESET, 1);
        save();
        return passwordResetTok.token;
    }

    /** Clear the auth token, meaning the user will have to log in again to get another token. */
    public void clearSessionTok() {
        sessionTok = null;
        csrfTok = null;
        save();
    }

    /** Clear the password reset token, and save user account */
    public void clearPasswordResetTok() {
        passwordResetTok = null;
        save();
    }

    /** Clear the email validation token, and save user account */
    public void clearEmailValidationTok() {
        emailValidationTok = null;
        save();
    }

    public void markEmailValidated() {
        emailValidated = true;
        save();
    }

    public boolean emailIsValidated() {
        return emailValidated != null && emailValidated;
    }

    // -----------------------------------------------------------------------------------------------------------------

    /**
     * Change a user's password, forcing other clients to be logged out, then log this client in with a new
     * authentication token.
     * 
     * @throws AppException
     *             if new password is too short or invalid, or if user is not logged in, or session has expired.
     * @throws UnauthorizedException
     *             if user is not whitelisted for login
     */
    public void changePassword(String newPassword, Response response) throws UnauthorizedException, AppException {
        if (sessionTokHasExpired()) {
            throw new UnauthorizedException("Session has expired");
        }
        // Re-hash user password
        passwordHash = Hash.hashPassword(newPassword);
        // Generate a new session token and save user.
        // Invalidates current session, forcing other clients to be logged out.
        logIn(response);
    }

    // -----------------------------------------------------------------------------------------------------------------

    public static User findByEmail(String email) {
        if (email == null || email.isEmpty())
            return null;
        String emailNormalized = WebUtils.validateAndNormalizeEmailAddr(email);
        if (emailNormalized == null)
            return null;
        return Database.findOneById(User.class, email);
    }

    // -----------------------------------------------------------------------------------------------------------------

    /**
     * Authenticate a user based on values of POST params "email" and "password".
     * 
     * @return User if successfully authenticated, null otherwise
     */
    public static User authenticate(String email, String password, Response response) throws UnauthorizedException {
        // FIXME: Allow only one login attempt per email address every 5 seconds. Add email addrs to a ConcurrentTreeSet
        // or something (*if* the email addr is already in the database, to prevent DoS), and every 5s, purge old
        // entries from the tree. If an attempt is made in less than 5s, then return an error rather than blocking for
        // up to 5s, again to prevent DoS.   
        User user = findByEmail(email);
        if (user != null) {
            // Get the hash password from the salt + clear password (N.B. It takes 80ms to run BCrypt)
            if (!FEDERATED_LOGIN_PASSWORD_HASH_PLACEHOLDER.equals(user.passwordHash)
                    && Hash.checkPassword(password, user.passwordHash)) {
                // User successfully authenticated.
                user.logIn(response);
                return user;
            }
        }
        // If user was not successfully logged in for some reason, delete cookie to be extra paranoid
        user.logOut(response);
        return null;
    }

    /**
     * Decrypt the session cookie in the HttpRequest, look up the user, and return the user if the user's auth token is
     * valid (i.e. if their session has not expired or been revoked).
     * 
     * @return Returns null if session is invalid or user is no longer allowed to log in.
     */
    public static User getLoggedInUser(Request req) {
        // Get email address from cookie
        Cookie emailCookie = req.getCookie(Cookie.EMAIL_COOKIE_NAME);
        if (emailCookie != null && !emailCookie.hasExpired()) {
            String email = emailCookie.getValue();

            // Check user against login whitelist, if it exists (in case whitelist has changed)
            if (GribbitServer.loginWhitelistChecker == null
                    || GribbitServer.loginWhitelistChecker.allowUserToLogin(email)) {

                // Get session cookie
                Cookie sessionCookie = req.getCookie(Cookie.SESSION_COOKIE_NAME);
                if (sessionCookie != null && !sessionCookie.hasExpired()) {
                    try {
                        // Look up email address in database, and check session cookie against session token stored in
                        // the database for that email address
                        User user = validateTok(email, sessionCookie.getValue(), TokenType.SESSION);
                        // If no exception thrown, user is logged in and auth token is valid
                        return user;

                    } catch (Exception e) {
                    }
                }
            }
        }
        return null;
    }

    /** Delete session cookies */
    public static void removeLoginCookies(Response response) {
        response.deleteCookie(Cookie.SESSION_COOKIE_NAME);
        response.deleteCookie(Cookie.EMAIL_COOKIE_NAME);
    }

    /**
     * Invalidate all current login sessions for this user.
     */
    public void logOut(Response response) {
        clearSessionTok();
        removeLoginCookies(response);
    }

    /**
     * See if there is a logged in user, and log them out if they are logged in.
     */
    public static void logOutUser(Request request, Response response) {
        User user = getLoggedInUser(request);
        if (user != null) {
            user.logOut(response);
        } else {
            // If no logged in user, just delete the session cookies
            removeLoginCookies(response);
        }
    }

    /**
     * Create a new authentication token for user and save session cookie.
     * 
     * @throws UnauthorizedException
     *             if the user is not whitelisted for login, or their login session has expired.
     */
    public void logIn(Response response) throws UnauthorizedException {
        // Check user against login whitelist, if it exists
        if (GribbitServer.loginWhitelistChecker == null || GribbitServer.loginWhitelistChecker.allowUserToLogin(id)) {

            // Create new session token
            sessionTok = new Token(TokenType.SESSION, Cookie.SESSION_COOKIE_MAX_AGE_SECONDS);
            
            // Create new random CSRF token every time user logs in
            csrfTok = CSRF.generateRandomCSRFToken();
            
            if (sessionTokHasExpired()) {
                // Shouldn't happen, since we just created session tok, but just in case
                clearSessionTok();
                throw new UnauthorizedException("Couldn't create auth session");
            }

            // Save tokens in database
            save();

            // Save login cookies in result
            response.setCookie(new Cookie(Cookie.SESSION_COOKIE_NAME, "/", sessionTok.token,
                    Cookie.SESSION_COOKIE_MAX_AGE_SECONDS));
            response.setCookie(new Cookie(Cookie.EMAIL_COOKIE_NAME, "/", id, Cookie.SESSION_COOKIE_MAX_AGE_SECONDS));

        } else {
            // User is not authorized
            throw new UnauthorizedException("User is not whitelisted for login: " + id);
        }
    }

    // -----------------------------------------------------------------------------------------------------

    /**
     * Create a user and log them in.
     * 
     * @throws UnauthorizedException
     *             if a user with this email addr already exists.
     */
    private static User create(String email, String passwordHash, boolean validateEmail, Response response)
            throws UnauthorizedException {
        // Check user against login whitelist, if it exists
        if (GribbitServer.loginWhitelistChecker == null || GribbitServer.loginWhitelistChecker.allowUserToLogin(email)) {

            // Check if a user of this name already exists, and if not, create user record in database.
            // Should probably be a transaction, although if the record is created twice within a
            // short period of time, one of the two account creation operations will simply have its
            // authorization cookie overwritten by the other, so the first session will be logged out.
            // Either way, users creating an account with the given email address must be in control
            // of that email account, so this is still secure.
            if (findByEmail(email) != null) {
                throw new UnauthorizedException("Could not create new user: user \"" + email + "\" already exists");
            }

            User user = new User(email);

            user.passwordHash = passwordHash;

            user.createdDate = ZonedDateTime.now().format(DateTimeFormatter.ISO_ZONED_DATE_TIME);
            user.emailValidated = validateEmail;

            // Log in and save user 
            user.logIn(response);

            return user;

        } else {
            // User is not authorized
            throw new UnauthorizedException("User is not whitelisted for account creation: " + email);
        }
    }

    /**
     * Create a user from email and password hash, and log them in.
     * 
     * @throws UnauthorizedException
     *             if a user with this email addr already exists.
     */
    public static User create(String email, String passwordHash, Response response) throws UnauthorizedException {
        return create(email, passwordHash, /* validateEmail = */false, response);
    }

    /**
     * Create a user from a Persona login, and log them in.
     *
     * @throws UnauthorizedException
     *             if a user with this email addr already exists.
     */
    public static User createFederatedLoginUser(String email, Response response) throws UnauthorizedException {
        return create(email, FEDERATED_LOGIN_PASSWORD_HASH_PLACEHOLDER, /* validateEmail = */true, response);
    }
}
