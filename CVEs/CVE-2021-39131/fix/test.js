import test from 'ava'
import ced from '.'

test('detects UTF-8', t => {
  const buf = Buffer.from('tést', 'utf8')
  t.is(ced(buf), 'UTF8')
})

test('detects ASCII', t => {
  const buf = Buffer.from('tést', 'ascii')
  t.is(ced(buf), 'ASCII')
})

test('throws a TypeError if the argument is not a buffer', t => {
  t.throws(() => {
    ced('tést')
  }, TypeError)
})
