/*jshint node:true*/
"use strict";

var exec = require("child_process").exec;

function runCommand(command, args, done) {
    if(typeof args === "function") {
        done = args;
        args = "";
    }

    exec("p4 " + command + " " + (args || ""), function(err, stdOut, stdErr) {
        if(err) return done(err);
        if(stdErr) return done(new Error(stdErr));

        done(null, stdOut);
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
