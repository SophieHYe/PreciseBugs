'use strict'

const bindings = require('bindings')('ced.node')

module.exports = buf => {
  if (!Buffer.isBuffer(buf)) {
    throw new TypeError('Expected a buffer')
  }

  return bindings.detectEncoding(buf)
}
