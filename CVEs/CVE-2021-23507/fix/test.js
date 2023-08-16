'use strict';
var setPath = require('./index.js');
var now = new Date();
var obj;
var getDefaultObject = function () {
  return {
    nested: {
      thing: {
        foo: 'bar',
      },
      is: {
        cool: true,
      },
    },
    dataUndefined: undefined,
    dataDate: now,
    dataNumber: 42,
    dataString: 'foo',
    dataNull: null,
    dataBoolean: true,
  };
};

describe('object-path-set', function () {
  beforeEach(function () {
    obj = getDefaultObject();
  });
  it('should be able to set and overwrite types', function () {
    var newValue = 'newValue';

    obj = setPath(obj, 'dataUndefined', newValue);
    expect(typeof obj.dataUndefined).toBe('string');
    expect(obj.dataUndefined).toBe(newValue);

    obj = setPath(obj, 'dataDate', newValue);
    expect(typeof obj.dataDate).toBe('string');
    expect(obj.dataDate).toBe(newValue);

    obj = setPath(obj, 'nested', newValue);
    expect(typeof obj.nested).toBe('string');
    expect(obj.nested).toBe(newValue);

    obj = setPath(obj, 'nested.foo', newValue);
    expect(typeof obj.nested).toBe('object');
    expect(typeof obj.nested.foo).toBe('string');
    expect(obj.nested.foo).toBe(newValue);
  });
  it('should covert things to objects', function () {
    expect(setPath(1234, 'a', 42)).toEqual({ a: 42 });
    expect(setPath(null, 'a', 42)).toEqual({ a: 42 });
    expect(setPath(true, 'a', 42)).toEqual({ a: 42 });
    expect(setPath({ a: 123 }, 'a.b', 42)).toEqual({ a: { b: 42 } });
    expect(setPath(null, 'a.b.c.d', null)).toEqual({
      a: { b: { c: { d: null } } },
    });
  });
  it('should be able to use custom delimiters', function () {
    expect(setPath({}, 'a|b|c|d', 42)).toEqual({ 'a|b|c|d': 42 });
    expect(setPath({}, 'a|b|c|d', 42, '|')).toEqual({
      a: { b: { c: { d: 42 } } },
    });
    expect(setPath({}, 'a.b.c.d', 42, '|')).toEqual({ 'a.b.c.d': 42 });
  });
  it('should set the correct values', function () {
    expect(setPath({}, 'a.b', 42)).toEqual({ a: { b: 42 } });
    expect(setPath({}, 'a.b', undefined)).toEqual({ a: { b: undefined } });
    expect(setPath({}, 'a.b', true)).toEqual({ a: { b: true } });
    expect(setPath({}, 'a.b', 'wow')).toEqual({ a: { b: 'wow' } });
  });
  it('should handle arrays as paths', function () {
    expect(setPath({}, ['a', 'b'], 42)).toEqual({ a: { b: 42 } });
    expect(setPath({}, ['a', 'b'], undefined)).toEqual({ a: { c: undefined } });
    expect(setPath({}, ['a', 'b'], true)).toEqual({ a: { b: true } });
    expect(setPath({}, ['a', 'b'], 'wow')).toEqual({ a: { b: 'wow' } });
  });
  it('should be able to be called multiple times', function () {
    obj = {};
    obj = setPath(obj, 'a', 42);
    obj = setPath(obj, 'b', true);
    obj = setPath(obj, 'c.d', {});
    obj = setPath(obj, 'c.d.e', {});
    obj = setPath(obj, 'c.d.f', 'foo');
    expect(obj).toEqual({ a: 42, b: true, c: { d: { e: {}, f: 'foo' } } });
  });
  it('should return the default object when key is not a string or array', function () {
    var defaultValue = Math.random();
    [{}, null, 42, undefined, true].forEach(function (path) {
      expect(setPath(getDefaultObject(), path, defaultValue)).toEqual(
        getDefaultObject()
      );
    });
  });
  it('should return the default object when key is an empty array', function () {
    var defaultValue = Math.random();
    expect(setPath(obj, [], defaultValue)).toEqual(getDefaultObject());
  });
  it('should allow empty strings as a path', function () {
    var defaultValue = Math.random();
    var obj2 = getDefaultObject();
    obj2[''] = defaultValue;
    expect(setPath(obj, '', defaultValue)).toEqual(obj2);
  });
  it('should not pollute __proto__', function () {
    var obj = {};
    expect(obj.polluted).toBeUndefined();
    setPath(obj, '__proto__.polluted', 'yes');
    var obj2 = {};
    expect(obj.polluted).toBeUndefined();
    expect(obj2.polluted).toBeUndefined();
  });
  it('should not pollute __proto__ when using arrays', function () {
    var obj = {};
    expect(obj.polluted).toBeUndefined();
    setPath(obj, [['__proto__'], 'polluted'], 'yes');
    var obj2 = {};
    expect(obj.polluted).toBeUndefined();
    expect(obj2.polluted).toBeUndefined();
  });
  it('should not pollute constructor', function () {
    var obj = {};
    expect(obj.polluted).toBeUndefined();
    setPath(obj, 'constructor.polluted', 'yes');
    var obj2 = {};
    expect(obj.polluted).toBeUndefined();
    expect(obj2.polluted).toBeUndefined();
  });
  it('should not pollute prototype', function () {
    var obj = {};
    expect(obj.polluted).toBeUndefined();
    setPath(obj, 'prototype.polluted', 'yes');
    // eslint-disable-next-line
    var obj2 = new Object();
    expect(obj.polluted).toBeUndefined();
    expect(obj2.polluted).toBeUndefined();
  });
});
