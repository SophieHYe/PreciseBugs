var test = require('tap').test
var v = require('../npm-user-validate.js').email

test('email misses an @', function (t) {
  var err = v('namedomain')
  t.type(err, 'object')
  t.end()
})

test('email misses a dot', function (t) {
  var err = v('name@domain')
  t.type(err, 'object')
  t.end()
})

test('email misses a string before the @', function (t) {
  var err = v('@domain')
  t.type(err, 'object')
  t.end()
})

test('email is ok', function (t) {
  var err = v('name@domain.com')
  t.type(err, 'null')
  t.end()
})
