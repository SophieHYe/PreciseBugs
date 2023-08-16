assert = require 'assert'
_ = require('underscore')
_.mixin require('../underscore.deep.js')

describe '_.deepFromFlat', ->

  tests = [
    input:
      "a.b":1
      "a.c": 2
      "b": 3
      "5.4.3.2.1": 0
    output:
      5: 4: 3: 2: 1: 0
      a:
        b: 1
        c: 2
      b: 3
  ]
  _(tests).each (test) ->
    it "deepens #{JSON.stringify test.input}", ->
      assert.deepEqual _.deepFromFlat(test.input), test.output

  it "does not merge special `Object` properties", ->
    _.deepFromFlat({ "__proto__.polluted1": true })
    _.deepFromFlat({ "constructor.prototype.polluted2": true })
    p1 = {}.polluted1
    p2 = {}.polluted2
    assert.strictEqual(p1, undefined)
    assert.strictEqual(p2, undefined)
    delete Object.prototype.polluted1
    delete Object.prototype.polluted2
