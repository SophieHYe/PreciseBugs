'use strict'

var child = require('child_process')

function GitFn (version, options) {
  this._version = version
  this._options = {
    cwd: options.dir,
    env: process.env,
    setsid: false,
    stdio: [0, 1, 2]
  }
}
module.exports = GitFn

GitFn.prototype = {
  tag: function (cb) {
    var cmd = ['git', 'tag', 'v' + this._version].join(' ')
    this._exec(cmd, cb)
  },
  untag: function (cb) {
    var cmd = ['git', 'tag', '-d', 'v' + this._version].join(' ')
    this._exec(cmd, cb)
  },
  commit: function (cb) {
    var cmd = ['git', 'commit', '-am', '"' + this._version + '"'].join(' ')
    this._exec(cmd, cb)
  },
  _exec: function (cmd, cb) {
    child.exec(cmd, this._options, cb)
  }
}
