"use strict";

const fs = require("fs")
    , abs = require("abs")
    , ExecLimiter = require("exec-limiter")
    , ul = require("ul")
    ;

// Create a global exec limiter
const el = new ExecLimiter();

class Gry {
    /**
     * Gry
     * Creates a new `Gry` instance.
     *
     * @name Gry
     * @function
     * @param {Object} options An object containing the following fields:
     *
     *  - `path` (String): The path to the git repository.
     *  - `limit` (Number): The limit of commands to run same time.
     *
     * @return {Gry} The `Gry` instance.
     */
    constructor (options) {
        if (typeof options === "string") {
            options = {
                path: options
            };
        }

        options = ul.merge(options, {
            limit: 30
        });

        options.path = abs(options.path);

        this.options = options;
        this.cwd = options.path;
    }

    /**
     * exec
     * Executes a git command in the repository directory.
     *
     * @name exec
     * @function
     * @param {String} command The git command that should be executed in the repository directory.
     * @param {Array} args An array of options passed to the spawned process. This is optional (if not provided, `exec` will be used instead).
     * @param {Function} callback The callback function.
     * @return {Gry} The `Gry` instance.
     */
    exec (command, args, callback) {

        var eargs = [];
        if (typeof args === "function") {
            callback = args;
            args = null;
        }

        // Handle spawn
        if (Array.isArray(args)) {
            eargs.push("git", [command].concat(args));
        } else {
            eargs.push("git " + command.trim());
        }

        eargs.push({ cwd: this.cwd });

        // Add the callback function
        eargs.push((err, stdout) => {
            if (err) { return callback(err); }
            callback(null, stdout.trimRight());
        });

        el.add.apply(el, eargs);
        return this;
    }

    /**
     * init
     * Inits the git repository.
     *
     * @name init
     * @function
     * @param {Function} callback The callback function.
     * @return {Gry} The `Gry` instance.
     */
    init (callback) {
        return this.exec("init", callback);
    }

    /**
     * create
     * Creates a git repository.
     *
     * @name create
     * @function
     * @param {String} path The path of the repository.
     * @param {Function} callback The callback function
     * @return {Gry} The `Gry` instance.
     */
    create (callback) {
        fs.mkdir(this.cwd, err => {
            if (err) {
                return callback(err);
            }
            this.init(callback);
        });
        return this;
    }

    /**
     * commit
     * Creates a commit, providing the `message`.
     *
     * @name commit
     * @function
     * @param {String} message The commit message
     * @param {String} options Additional options passed to the commit command.
     * @param {Function} callback The callback function.
     * @return {Gry} The `Gry` instance.
     */
    commit (message, options, callback) {
        message = message.replace(/\"/g, "\\");
        if (typeof options === "function") {
            callback = options;
            options = "";
        }
        return this.exec("commit -m \"" + message + "\" " + options, callback)
    }

    /**
     * pull
     * Runs `git pull`.
     *
     * @name pull
     * @function
     * @param {String} options Additional options passed to the `pull` command.
     * @param {Function} callback The callback function.
     * @return {Gry} The `Gry` instance.
     */
    pull (options, callback) {
        if (typeof options === "function") {
            callback = options;
            options = "";
        }
        return this.exec("pull " + options, callback);
    }

    /**
     * add
     * Runs `git add`.
     *
     * @name add
     * @function
     * @param {String} options Additional options passed to the `add` command.
     * @param {Function} callback The callback function.
     * @return {Gry} The `Gry` instance.
     */
    add (options, callback) {
        if (typeof options === "function") {
            callback = options;
            options = ".";
        }
        return this.exec("add " + options, callback);
    }

    /**
     * branch
     * Runs `git branch`.
     *
     * @name branch
     * @function
     * @param {String} options Additional options passed to the `branch` command.
     * @param {Function} callback The callback function.
     * @return {Gry} The `Gry` instance.
     */
    branch (options, callback) {
        if (typeof options === "function") {
            callback = options;
            options = "";
        }
        return this.exec("branch " + options, callback);
    }

    /**
     * checkout
     * Runs `git checkout`.
     *
     * @name checkout
     * @function
     * @param {String} options Additional options passed to the `checkout` command.
     * @param {Function} callback The callback function.
     * @return {Gry} The `Gry` instance.
     */
    checkout (options, callback) {
        if (typeof options === "function") {
            callback = options;
            options = "";
        }
        return this.exec("checkout " + options, callback);
    }

    /**
     * clone
     * Runs `git clone`.
     *
     * @name clone
     * @function
     * @param {String} gitUrl The git url of the repository that should be cloned.
     * @param {String} options Additional options passed to the `checkout` command.
     * @param {Function} callback The callback function.
     * @return {Gry} The `Gry` instance.
     */
    clone (gitUrl, options, callback) {
        if (typeof options === "function") {
            callback = options;
            options = "";
        }
        return this.exec("clone " + gitUrl + " " + options, callback);
    }
}

Gry.limiter = el;

module.exports = Gry;
