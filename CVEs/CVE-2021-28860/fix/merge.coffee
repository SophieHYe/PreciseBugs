
import {merge} from '../src'

describe 'mixme.merge', ->

  it 'does not alter arguments', ->
    obj1 = a: 1, b: 2, c: 0
    obj2 = a: 1, c: 3, d: 4
    merge obj1, obj2
    .should.eql a: 1, b: 2, c: 3, d: 4
    obj1
    .should.eql a: 1, b: 2, c: 0
    obj2
    .should.eql a: 1, c: 3, d: 4

  it 'null prototype', ->
    obj1 = Object.create null
    obj1.a = 1
    obj1.b = 2
    obj1.c = 0
    obj2 = a: 1, c: 3, d: 4
    merge obj1, obj2
    .should.eql a: 1, b: 2, c: 3, d: 4
    {...obj1}
    .should.eql a: 1, b: 2, c: 0
    obj2
    .should.eql a: 1, c: 3, d: 4

  it 'dont merge proto', ->
    merge {}, JSON.parse '{"__proto__": {"polluted": "ohno"}}'
    obj = Object.create {}
    should(obj.polluted).be.Undefined()
