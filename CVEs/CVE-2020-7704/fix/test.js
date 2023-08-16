#!/usr/bin/env node

const {deepStrictEqual} = require('assert')

const linuxCmdline = require('.')


const cmdline = 'initrd=/init-lin.img root=UUID=uuid someflag a.b=c d.e f=g,h'
const expected =
{
  initrd: '/init-lin.img',
  root: 'UUID=uuid',
  someflag: true,
  a: { b: 'c' },
  d: { e: true },
  f: [ 'g', 'h' ]
}

const result = linuxCmdline(cmdline)

deepStrictEqual(result, expected)


// Don't pollute prototype
const result2 = linuxCmdline('__proto__.polluted=foo')
const expected2 =
{
  ['__proto__']:
  {
    polluted: 'foo'
  }
}

deepStrictEqual(result2, expected2)

deepStrictEqual({}.__proto__.polluted, undefined)
