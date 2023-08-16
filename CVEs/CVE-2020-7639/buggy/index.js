var assert = require('assert');
var dot = require('..');

var tests = module.exports = {
  'test set': function () {
    var obj = {};
    var ret = dot.set(obj, 'cool.aid', 'rocks');
    assert(obj.cool.aid === 'rocks');
    assert(obj === ret);
  },

  'test get': function () {
    var obj = {};
    obj.cool = {};
    obj.cool.aid = 'rocks';
    var value = dot.get(obj, 'cool.aid');
    assert(value === 'rocks');
  },

  'test delete': function () {
    var obj = {};
    obj.cool = {};
    obj.cool.aid = 'rocks';
    obj.cool.hello = ['world'];
    dot.delete(obj, 'cool.aid');
    dot.delete(obj, 'cool.hello.0');
    assert(!obj.cool.hasOwnProperty('aid'))
    assert(obj.cool.hello.length == 0);
  }
}

for (var t in tests) {
  tests[t]();
}

console.log('All tests passed!');
