/**
 * versionn
 *
 * @copyright (C) 2014- commenthol
 * @license MIT
 */

'use strict'

/* global describe, it, before, beforeEach */

const assert = require('assert')
const path = require('path')
const async = require('asyncc')
const sh = require('shelljs')

const Version = require('../lib/index')

const fixtures = path.join(__dirname, 'fixtures')

describe('#Version', function () {
  const packageJson = path.join(fixtures, 'package.json')

  it('construct', function () {
    const v = new Version(packageJson)
    assert.ok(v instanceof Version)
  })

  it('construct without new', function () {
    const v = Version(packageJson)
    assert.ok(v instanceof Version)
  })

  it('setVersion', function () {
    const v = new Version(packageJson)
    v.setVersion('0.0.0')
    assert.strictEqual(v.version, '0.0.0')
  })

  it('setVersion with bad semver', function () {
    const v = new Version(packageJson)
    v.setVersion('0a0.0')
    assert.strictEqual(v.version, undefined)
  })

  it('inc', function (done) {
    const v = new Version(packageJson)
    v.extract(function (err) {
      assert.ok(!err, '' + err)
      v.inc('patch')
      assert.strictEqual(v.version, '0.3.7')
      done()
    })
  })

  it('inc with bad semver command', function (done) {
    const v = new Version(packageJson)
    v.extract(function (err) {
      assert.ok(!err, '' + err)
      v.inc('patchit')
      assert.strictEqual(v.version, '0.3.6')
      done()
    })
  })

  it('changefiles with undefined version', function (done) {
    // var v = new Version(fixture('notthere.json'))
    Version.changeFiles([], undefined, function (err) {
      assert.strictEqual(err.message, 'version is undefined')
      done()
    })
  })
})

describe('readFile', function () {
  it('extract with error ', function (done) {
    const v = new Version(path.join(fixtures, 'notthere.json'))
    v.extract(function (err) {
      assert.strictEqual(err.code, 'ENOENT')
      done()
    })
  })

  it('extract from bad json file ', function (done) {
    const v = new Version(path.join(fixtures, 'packagebad.json'))
    v.extract(function (err) {
      assert.ok(err.message.indexOf('Unexpected string in JSON at') === 0, err.message)
      done()
    })
  })

  it('package.json', function (done) {
    const v = new Version(path.join(fixtures, 'package.json'))
    v.extract(function (err, version) {
      assert.ok(!err, '' + err)
      assert.strictEqual(version, '0.3.6')
      done()
    })
  })

  it('VERSION', function (done) {
    const v = new Version(path.join(fixtures, 'VERSION'))
    v.extract(function (err, version) {
      assert.ok(!err, '' + err)
      assert.strictEqual(version, '1.0.3-12')
      done()
    })
  })

  it('file.js', function (done) {
    const v = new Version(path.join(fixtures, 'file.js'))
    v.extract(function (err, version) {
      assert.ok(!err, '' + err)
      assert.strictEqual(version, '5.0.23')
      done()
    })
  })
})

describe('change files', function () {
  before(function (done) {
    sh.cp('-f', path.join(__dirname, 'fixtures/*'), path.join(__dirname, 'tmp/'))
    done()
  })

  it('change with error', function () {
    const v = new Version(path.join(__dirname, 'fixtures/notthere.json'))
    v.setVersion('0.0.1')
    v.change(function (err) {
      assert.strictEqual(err.code, 'ENOENT')
    })
  })

  it('package.json', function (done) {
    const v = new Version(path.join(__dirname, 'tmp/package.json'))
    v.extract(function (err, version) {
      assert.ok(!err, '' + err)
      assert.strictEqual(version, '0.3.6')
      if (v.inc('patch')) {
        v.change(function (err) {
          assert.ok(!err, '' + err)
          assert.strictEqual(err, null)
          v.extract(function (err, version) {
            assert.ok(!err, '' + err)
            assert.strictEqual(version, '0.3.7')
            done()
          })
        })
      } else {
        assert.ok(false, 'could not increment')
      }
    })
  })

  it('VERSION', function (done) {
    const v = new Version(path.join(__dirname, 'tmp/VERSION'))
    v.extract(function (err, version) {
      assert.ok(!err, '' + err)
      assert.strictEqual(version, '1.0.3-12')
      if (v.inc('minor')) {
        v.change(function (err) {
          assert.ok(!err, '' + err)
          assert.strictEqual(err, null)
          v.extract(function (err, version) {
            assert.ok(!err, '' + err)
            assert.strictEqual(version, '1.1.0')
            done()
          })
        })
      } else {
        assert.ok(false, 'could not increment')
      }
    })
  })

  it('file.js', function (done) {
    const v = new Version(path.join(__dirname, 'tmp/file.js'))
    v.extract(function (err, version) {
      assert.ok(!err, '' + err)
      assert.strictEqual(version, '5.0.23')
      if (v.inc('preminor')) {
        v.change(function (err) {
          assert.strictEqual(err, null)
          v.extract(function (err, version) {
            assert.strictEqual(err, undefined)
            assert.strictEqual(version, '5.1.0-0')
            done()
          })
        })
      } else {
        assert.ok(false, 'could not increment')
      }
    })
  })
})

describe('change files with modifier', function () {
  before(function (done) {
    sh.cp('-f', path.join(__dirname, 'fixtures/*'), path.join(__dirname, 'tmp/'))
    done()
  })

  it('change package.json using --same', function (done) {
    const v = new Version(path.join(__dirname, 'tmp/package.json'))
    v.extract(function (err, version) {
      assert.ok(!err, '' + err)
      assert.strictEqual(version, '0.3.6')
      if (v.inc('same')) {
        v.change(function (err) {
          assert.ok(!err, '' + err)
          assert.strictEqual(err, null)
          v.extract(function (err, version) {
            assert.ok(!err, '' + err)
            assert.strictEqual(version, '0.3.6')
            done()
          })
        })
      } else {
        assert.ok(false, 'could not increment')
      }
    })
  })
})

describe('change multiple files', function () {
  beforeEach(function (done) {
    sh.cp('-f', path.join(__dirname, 'fixtures/*'), path.join(__dirname, 'tmp/'))
    done()
  })

  it('from VERSION', function (done) {
    const v = new Version(path.join(__dirname, 'tmp/VERSION'))
    let files = ['VERSION', 'package.json', 'file.js']

    files = files.map(function (file) {
      return path.join(__dirname, 'tmp', file)
    })

    v.extract(function (err, version) {
      assert.ok(!err, '' + err)
      assert.strictEqual(version, '1.0.3-12')
      if (v.inc('minor')) {
        Version.changeFiles(files, v.version, function (err) {
          assert.strictEqual(err.length, 0)
          async.eachLimit(5, files,
            function (file, _cb) {
              const vv = new Version(file)
              vv.extract(function (err, version) {
                assert.ok(!err, '' + err)
                assert.strictEqual(version, v.version)
                _cb()
              })
            }, function (_err) {
              done()
            })
        })
      } else {
        assert.ok(false, 'could not increment')
      }
    })
  })

  it('from VERSION changes notthere.json', function (done) {
    const v = new Version(path.join(__dirname, 'tmp/VERSION'))
    let files = ['VERSION', 'notthere.json', 'package.json', 'file.js']

    files = files.map(function (file) {
      return path.join(__dirname, 'tmp', file)
    })

    v.extract(function (err, version) {
      assert.ok(!err, '' + err)
      assert.strictEqual(version, '1.0.3-12')
      if (v.inc('minor')) {
        Version.changeFiles(files, v.version, function (err) {
          assert.strictEqual(err.length, 1)
          async.eachLimit(5, files,
            function (file, _cb) {
              const vv = new Version(file)
              vv.extract(function (_err, version) {
                if (~file.indexOf('notthere.json')) {
                  assert.strictEqual(version, undefined)
                } else {
                  assert.strictEqual(version, v.version)
                }
                _cb()
              })
            }, function (_err) {
              done()
            })
        })
      } else {
        assert.ok(false, 'could not increment')
      }
    })
  })

  it('from notthere.json', function (done) {
    const v = new Version(path.join(__dirname, 'tmp/notthere.json'))

    v.extract(function (err, version) {
      assert.strictEqual(err.code, 'ENOENT')
      assert.strictEqual(version, undefined)
      try {
        v.inc('same')
      } catch (e) {
        assert.ok(e.message, 'could not increment')
      }
      done()
    })
  })
})
