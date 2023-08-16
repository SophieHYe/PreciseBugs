const test = require('ava')
const { unflatten } = require('../src/index.js')

test('it should return an unflattened object', (t) => {
  const original = {
    'a.b': 1
  }

  const expected = {
    a: {
      b: 1
    }
  }

  t.deepEqual(unflatten(original), expected)
})

test('it should handle empty arrays', t => {
  const original = { a: [], b: 1, 'c.d': [], 'e.0': 1, 'e.1': 2 }
  const expected = { a: [], b: 1, c: { d: [] }, e: [1, 2] }

  t.deepEqual(unflatten(original), expected)
})

test('it should handle nested arrays', (t) => {
  const original = {
    'a.0': 0,
    'a.1': 1
  }

  const expected = {
    a: [0, 1]
  }

  t.deepEqual(unflatten(original), expected)
})

test('it should handle circular objects', (t) => {
  const original = {
    'a.b.c': 'value',
    'a.b.d': '[Circular]',
    'a.b.e.g': 'value',
    'a.b.e.f': '[Circular]',
    'x.y.z': '[Circular]'
  }

  const expected = {
    a: {
      b: {
        c: 'value',
        d: '[Circular]',
        e: {
          f: '[Circular]',
          g: 'value'
        }
      }
    },
    x: {
      y: {
        z: '[Circular]'
      }
    }
  }

  t.deepEqual(unflatten(original), expected)
})

test('it should use the passed in delimiter', (t) => {
  const original = {
    a_b: 1
  }

  const expected = {
    a: {
      b: 1
    }
  }

  t.deepEqual(unflatten(original, '_'), expected)
})

test('it should handle deep nesting', (t) => {
  const original = {
    'a.b.c.0.val': 'one',
    'a.b.c.1.val': 'two',
    'a.b.c.2': '[Circular]',
    'a.b.d': 'three',
    'a.e': 'four',
    'a.b.f': '[Circular]'
  }

  const expected = {
    a: {
      b: {
        c: [{
          val: 'one'
        }, {
          val: 'two'
        },
        '[Circular]'
        ],
        d: 'three',
        f: '[Circular]'
      },
      e: 'four'
    }
  }
  t.deepEqual(unflatten(original), expected)
})

test('it should do nothing for flat objects', (t) => {
  const original = {
    a: 'one',
    b: 'two'
  }
  t.deepEqual(unflatten(original), original)
})

test('it should return the original value if not an object', (t) => {
  const original = 'string'
  t.deepEqual(unflatten(original), original)
})

test('it should handle date objects', (t) => {
  const date = new Date()

  t.deepEqual(unflatten(date), date)

  const original = {
    'a.b.c': date,
    'a.b.d': 'one',
    'a.e.f': date,
    'a.e.g.h': date
  }

  const expected = {
    a: {
      b: {
        c: date,
        d: 'one'
      },
      e: {
        f: date,
        g: {
          h: date
        }
      }
    }
  }

  t.deepEqual(unflatten(original), expected)
})

test('it should not pollute the prototype', (t) => {
  const original = {
    '__proto__.polluted': 'Attempt to pollute the prototype',
    'a.prototype.polluted': 'Attempt to pollute the prototype',
    'a.b': 'This attribute is safe',
    'c.constructor.polluted': 'Attempt to pollute the prototype',
    'constructor.polluted': 'Attempt to pollute the prototype'
  }
  unflatten(original)
  t.assert({}.polluted == null)
})
