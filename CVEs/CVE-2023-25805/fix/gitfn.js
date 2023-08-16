'use strict'

const child = require('child_process')
const semver = require('semver')

const assertVersionValid = version => {
  if (!semver.valid(version)) {
    throw new Error('version is invalid')
  }
}

const exec = (cmd, options, cb) => child.exec(cmd, options, cb)

class GitFn {
  constructor (version, options) {
    this._version = version
    this._options = {
      cwd: options.dir,
      env: process.env,
      setsid: false,
      stdio: [0, 1, 2]
    }
  }

  tag (cb) {
    assertVersionValid(this._version)
    const cmd = ['git', 'tag', 'v' + this._version].join(' ')
    exec(cmd, this._options, cb)
  }

  untag (cb) {
    assertVersionValid(this._version)
    const cmd = ['git', 'tag', '-d', 'v' + this._version].join(' ')
    exec(cmd, this._options, cb)
  }

  commit (cb) {
    assertVersionValid(this._version)
    const cmd = ['git', 'commit', '-am', '"' + this._version + '"'].join(' ')
    exec(cmd, this._options, cb)
  }
}

module.exports = GitFn
