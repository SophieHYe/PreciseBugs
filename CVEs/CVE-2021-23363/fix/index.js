'use strict'

const exec = require('child_process').execSync

exports.killByPort = function (port) {
  var processId = null
  try {
    processId = exec(`lsof -t -i:${parseInt(port, 10)}`)
  } catch (e) {

  }

  if (processId !== null) { // if exists kill
    exec(`kill ${processId}`)
  }
}
