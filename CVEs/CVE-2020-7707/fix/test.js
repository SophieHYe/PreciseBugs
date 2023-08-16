const a = require('assert')
const expr = require('./index')
const compiler = require('./compiler')

const root = (typeof global == 'object' && global) || this

function runSetterGetterTests({ setter, getter }) {
  let obj = {
    foo: {
      bar: ['baz', 'bux'],
      fux: 5,
      '00N40000002S5U0': 1,
      N40000002S5U0: 2,
      'FE43-D880-21AE': 3,
    },
  }

  // -- Getters --
  a.strictEqual(getter('foo.fux')(obj), 5)
  a.deepEqual(getter('foo.bar')(obj), ['baz', 'bux'])

  a.strictEqual(getter('foo.bar[1]')(obj), 'bux')
  a.strictEqual(getter('["foo"]["bar"][1]')(obj), 'bux')
  a.strictEqual(getter('[1]')([1, 'bux']), 'bux')

  // safe access
  a.strictEqual(getter('foo.fux', true)(obj), 5)
  a.deepEqual(getter('foo.bar', true)(obj), ['baz', 'bux'])

  a.strictEqual(getter('foo.bar[1]', true)(obj), 'bux')
  a.strictEqual(getter('["foo"]["bar"][1]', true)(obj), 'bux')
  a.strictEqual(getter('[1]', true)([1, 'bux']), 'bux')

  a.strictEqual(getter('foo.gih.df[0]', true)(obj), undefined)
  a.strictEqual(getter('["fr"]["bzr"][1]', true)(obj), undefined)

  a.strictEqual(getter('foo["00N40000002S5U0"]', true)(obj), 1)
  a.strictEqual(getter('foo.00N40000002S5U0', true)(obj), 1)
  a.strictEqual(getter('foo["N40000002S5U0"]', true)(obj), 2)
  a.strictEqual(getter('foo.N40000002S5U0', true)(obj), 2)
  a.strictEqual(getter('foo["FE43-D880-21AE"]', true)(obj), 3)
  a.strictEqual(getter('foo.FE43-D880-21AE', true)(obj), 3)

  // -- Setters --
  setter('foo.fux')(obj, 10)
  a.strictEqual(obj.foo.fux, 10)

  setter('foo.bar[1]')(obj, 'bot')
  a.strictEqual(obj.foo.bar[1], 'bot')

  setter('[\'foo\']["bar"][1]')(obj, 'baz')

  a.strictEqual(obj.foo.bar[1], 'baz')
  //
  ;['__proto__', 'constructor', 'prototype'].forEach((keyToTest) => {
    setter(`${keyToTest}.a`)({}, 'newValue')

    a.notEqual(root['a'], 'newValue')

    const b = 'oldValue'

    a.equal(b, 'oldValue')
    a.notEqual(root['b'], 'newValue')

    setter(`${keyToTest}.b`)({}, 'newValue')
    a.equal(b, 'oldValue')
    a.notEqual(root['b'], 'newValue')
    a.equal(root['b'], undefined)
  })
}

console.log('--- Test Start ---')

// -- Cache --
const cache = new expr.Cache(3)
a.strictEqual(cache._size, 0)
a.strictEqual(cache.set('a', a), a)
a.strictEqual(cache.get('a'), a)
a.strictEqual(cache._size, 1)
a.strictEqual(cache.set('b', 123), 123)
a.strictEqual(cache.get('b'), 123)
a.strictEqual(cache.set('b', 321), 321)
a.strictEqual(cache.get('b'), 321)
a.strictEqual(cache.set('c', null), null)
a.strictEqual(cache.get('c'), null)
a.strictEqual(cache._size, 3)
a.strictEqual(cache.set('d', 444), 444)
a.strictEqual(cache._size, 1)
cache.clear()
a.strictEqual(cache._size, 0)
a.strictEqual(cache.get('a'), undefined)

// -- split --

let parts = expr.split('foo.baz["bar"][1]')

a.strictEqual(parts.length, 4)

// -- join --

parts = expr.split('foo.baz["bar"][1]')

a.strictEqual(expr.join(['0', 'baz', '"bar"', 1]), '[0].baz["bar"][1]')

a.strictEqual(expr.join(parts), 'foo.baz["bar"][1]')

// -- forEach --

let count = 0

expr.forEach('foo.baz["bar"][1]', function (part, isBracket, isArray, idx) {
  count = idx

  switch (idx) {
    case 0:
      a.strictEqual(part, 'foo')
      a.strictEqual(isBracket, false)
      a.strictEqual(isArray, false)
      break
    case 1:
      a.strictEqual(part, 'baz')
      a.strictEqual(isBracket, false)
      a.strictEqual(isArray, false)
      break
    case 2:
      a.strictEqual(part, '"bar"')
      a.strictEqual(isBracket, true)
      a.strictEqual(isArray, false)
      break
    case 3:
      a.strictEqual(part, '1')
      a.strictEqual(isBracket, false)
      a.strictEqual(isArray, true)
      break
  }
})

a.strictEqual(count, 3)

// -- normalizePath --

a.deepEqual(
  expr.normalizePath('foo[ " bux\'s " ].bar["baz"][ 9 ][ \' s \' ]'),
  ['foo', " bux's ", 'bar', 'baz', '9', ' s ']
)

runSetterGetterTests(expr)
runSetterGetterTests(compiler)

console.log('--- Tests Passed ---')
