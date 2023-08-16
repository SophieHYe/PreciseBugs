'use strict';

const should = require('should/as-function');
const set = require('./main');

describe('set', () => {
  it('should return non-objects', () => {
    should(set('foo', 'a.b', 'c')).be.eql('foo');
  });

  it('should set a value using custom separator', () => {
    should(set({ a: 'a', b: { c: 'd' } }, 'b:c:d', 'eee', ':')).be.eql({ a: 'a', b: { c: { d: 'eee' } } });
  });

  it('should create a nested property if it does not already exist', () => {
    const o = {};
    set(o, 'a.b', 'c');
    should(o.a.b).be.eql('c');
  });

  it('should extend an array', () => {
    const o = { a: [] };
    set(o, 'a.0', { y: 'z' });
    should(o.a[0]).be.eql({ y: 'z' });
  });

  it('should extend a function', () => {
    function log() { }
    const warning = function () { };
    const o = {};

    set(o, 'helpers.foo', log);
    set(o, 'helpers.foo.warning', warning);
    should(typeof o.helpers.foo).be.eql('function');
    should(typeof o.helpers.foo.warning).be.eql('function');
  });

  it('should extend an object in an array', () => {
    const o = { a: [{}, {}, {}] };
    set(o, 'a.0.a', { y: 'z' });
    set(o, 'a.1.b', { y: 'z' });
    set(o, 'a.2.c', { y: 'z' });
    should(o.a[0].a).be.eql({ y: 'z' });
    should(o.a[1].b).be.eql({ y: 'z' });
    should(o.a[2].c).be.eql({ y: 'z' });
  });

  it('should create a deeply nested property if it does not already exist', () => {
    const o = {};
    set(o, 'a.b.c.d.e', 'c');
    should(o.a.b.c.d.e).be.eql('c');
  });

  it('should not create a nested property if it does already exist', () => {
    const first = { name: 'Halle' };
    const o = { a: first };
    set(o, 'a.b', 'c');
    should(o.a.b).be.eql('c');
    should(o.a).be.eql(first);
    should(o.a.name).be.eql('Halle');
  });

  it('should support immediate properties', () => {
    const o = {};
    set(o, 'a', 'b');
    should(o.a).be.eql('b');
  });

  it('should use property paths to set nested values from the source object', () => {
    const o = {};
    set(o, 'a.locals.name', { first: 'Brian' });
    set(o, 'b.locals.name', { last: 'Woodward' });
    set(o, 'b.locals.name.last', 'Woodward');
    should(o).be.eql({ a: { locals: { name: { first: 'Brian' } } }, b: { locals: { name: { last: 'Woodward' } } } });
  });

  it('should add the property even if a value is not defined', () => {
    const fixture = {};
    should(set(fixture, 'a.locals.name')).be.eql({ a: { locals: { name: undefined } } });
    should(set(fixture, 'b.locals.name')).be.eql({ b: { locals: { name: undefined } }, a: { locals: { name: undefined } } });
  });

  it('should set the specified property', () => {
    should(set({ a: 'aaa', b: 'b' }, 'a', 'bbb')).be.eql({ a: 'bbb', b: 'b' });
  });

  it('should support passing an array as the key', () => {
    should(set({ a: 'a', b: { c: 'd' } }, ['b', 'c', 'd'], 'eee')).be.eql({ a: 'a', b: { c: { d: 'eee' } } });
  });

  it('should set a deeply nested value', () => {
    should(set({ a: 'a', b: { c: 'd' } }, 'b.c.d', 'eee')).be.eql({ a: 'a', b: { c: { d: 'eee' } } });
  });

  it('should return the entire object if no property is passed', () => {
    should(set({ a: 'a', b: { c: 'd' } })).be.eql({ a: 'a', b: { c: 'd' } });
  });

  it('should set a value only', () => {
    should(set({ a: 'a', b: { c: 'd' } }, 'b.c')).be.eql({ a: 'a', b: { c: undefined } });
  });

  it('should set non-plain objects', () => {
    const date = new Date();
    const o = {};
    set(o, 'a.b', date);
    should(o.a.b.getTime()).be.eql(date.getTime());
  });

  it('should not indirectly set Object properties', () => {
    const o = {};
    set(o, 'constructor.a', 1);
    should(o.constructor.a).be.eql(undefined);

    set(o, ['constructor', 'b'], 1);
    should(o.constructor.b).be.eql(undefined);
  });

  it('should not indirectly set Object properties', () => {
    const o = {};
    set(o, '__proto__.a', 1);
    should(o.a).be.eql(undefined);

    set(o, ['__proto__', 'b'], 1);
    should(o.b).be.eql(undefined);
  });

  it('should not indirectly set Object properties', () => {
    const o = {};
    const ob = { o };
    set(o, 'ob.constructor.a', 1);
    should(ob.a).be.eql(undefined);

    set(o, ['ob.constructor', 'b'], 1);
    should(ob.b).be.eql(undefined);
  });

  it('should not indirectly set Object properties', () => {
    const o = {};
    const ob = { o };
    set(o, 'ob.__proto__.a', 1);
    should(ob.a).be.eql(undefined);

    set(o, ['ob.__proto__', 'b'], 1);
    should(ob.b).be.eql(undefined);
  });
});
