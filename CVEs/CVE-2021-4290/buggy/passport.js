/*jslint node:true */
'use strict';

/**
 * Passport.js config file, heavily inspired by
 * http://scotch.io/tutorials/javascript/easy-node-authentication-setup-and-local
 */

var LocalStrategy = require('passport-local').Strategy,
    passwordHash = require('password-hash'),
    connection = require('./../config/db.js')(10);

module.exports = function (passport) {

    // =========================================================================
    // passport session setup ==================================================
    // =========================================================================
    // required for persistent login sessions
    // passport needs ability to serialize and unserialize users out of session

    // used to serialize the user for the session
    passport.serializeUser(function (user, done) {
        done(null, user.id);
    });

    // used to deserialize the user
    passport.deserializeUser(function (id, done) {
        connection.query("select * from users where id = " + id, function (err, rows) {
            done(err, rows[0]);
        });
    });


    // =========================================================================
    // LOCAL SIGNUP ============================================================
    // =========================================================================
    // we are using named strategies since we have one for login and one for signup
    // by default, if there was no name, it would just be called 'local'

    passport.use('local-signup', new LocalStrategy({
            // by default, local strategy uses username and password, we will override with email
            usernameField: 'email',
            passwordField: 'password',
            passReqToCallback: true // allows us to pass back the entire request to the callback
        },
        function (req, email, password, done) {
            // find a user whose email is the same as the forms email
            // we are checking to see if the user trying to login already exists
            connection.query("select * from users where email = '" + email + "'", function (err, rows) {
                if (err) {return done(err);}
                if (rows.length) {
                    req.signUpMessage = 'Diese e-Mail ist bei uns bereits registriert';
                    return done(null, false);
                } else {

                    // if there is no user with that email
                    // create the user
                    var newUserMysql = {};
                    newUserMysql.title = req.body.title;
                    newUserMysql.email = email;
                    newUserMysql.firstName = req.body.firstName;
                    newUserMysql.lastName = req.body.lastName;
                    newUserMysql.password = passwordHash.generate(password);

                    connection.query('INSERT INTO users SET ?', [newUserMysql], function (err, rows) {
                        newUserMysql.id = rows.insertId;

                        return done(null, newUserMysql);
                    });
                }
            });
        }));

    // =========================================================================
    // LOCAL LOGIN =============================================================
    // =========================================================================
    // we are using named strategies since we have one for login and one for signup
    // by default, if there was no name, it would just be called 'local'

    passport.use('local-login', new LocalStrategy({
            // by default, local strategy uses username and password, we will override with email
            usernameField: 'email',
            passwordField: 'password',
            passReqToCallback: true // allows us to pass back the entire request to the callback
        },
        function (req, email, password, done) { // callback with email and password from our form
            connection.query("SELECT * FROM `users` WHERE `email` = '" + email + "'", function (err, rows) {
                if (err) {return done(err);}

                if (!rows.length) {
                    req.loginMessage='Die eingegebene E-Mail-Adresse oder das Passwort ist falsch.';
                    return done(null, false);
                }

                // if the user is found but the password is wrong
                if (!passwordHash.verify(password, rows[0].password)) {
                    req.loginMessage = 'Die eingegebene E-Mail-Adresse oder das Passwort ist falsch.';
                    return done(null, false);
                }

                // all is well, return successful user
                return done(null, rows[0]);

            });
        }));
};
