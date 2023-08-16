/*jshint node:true*/
"use strict";

var exec = require("child_process").spawn;

function runCommand(command, args, done) {
    if(typeof args === "function") {
        done = args;
        args = "";
    }

    if(!Array.isArray(args)) {
        args = [args];
    }
    args.unshift(command);

    var child = spawn("p4", args);
    var stdOutBuf = "";
    var stdErrBuf = "";

    child.stdout.on("data", (data) => stdOutBuf += data);
    child.stderr.on("data", (data) => stdErrBuf += data)
    child.on("exit", (code) => {
        if (code !== 0) {
            return done(new Error(`p4 subcommand exited with return code ${}`));
        }

        if (stdErrBuf.length > 0) {
            return done(new Error(stdErrBuf));
        }

        done(null, stdOutBuf);
    });
}

function edit(path, done) {
    runCommand("edit", path, done);
}

function add(path, done) {
    runCommand("add", path, done);
}

function smartEdit(path, done) {
    edit(path, function(err, stdout) {
        if(!err) return done(err, stdout);

        add(path, done);
    });
}

function revertUnchanged(path, done) {
    runCommand("revert", "-a", done);
}

exports.edit = edit;
exports.add = add;
exports.smartEdit = smartEdit;
exports.run = runCommand;
exports.revertUnchanged = revertUnchanged;
