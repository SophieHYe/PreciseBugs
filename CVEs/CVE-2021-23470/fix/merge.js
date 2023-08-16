/* eslint-disable */
const assert = require('assert');
const merge = require('../');

describe('merge', function() {

  it('should throw if target is not an object', function() {
    assert.throws(() => {
      merge([], {});
    });
  });

  it('should throw if source is not an object', function() {
    assert.throws(() => {
      merge({}, []);
    });
  });

  it('should ignore if source is null', function() {
    const a = {};
    const b = merge(a);
    assert.strictEqual(b, a);
  });

  it('should copy regular values to target', function() {
    const a = {a: 1, b: '2', e: 5};
    const b = {a: 2, c: 3, d: null, e: undefined};
    let o = merge(a, b);
    assert.deepStrictEqual(o, {
          a: 2,
          b: '2',
          c: 3,
          d: null,
          e: 5
        }
    );
  });

  it('should copy object values to target', function() {
    const a = {a: 1, b: '2'};
    const b = {a: '1', c: {foo: 1}};
    let o = merge(a, b);
    assert.deepStrictEqual(o, {
          a: '1',
          b: '2',
          c: {foo: 1}
        }
    );
    b.c.foo = 2;
    assert.deepStrictEqual(o, {
          a: '1',
          b: '2',
          c: {foo: 2}
        }
    );
  });

  it('should clone object values to target', function() {
    const a = {a: 1, b: '2'};
    const b = {a: '1', c: {foo: 1}};
    let o = merge(a, b, {clone: true});
    b.c.foo = 2;
    assert.deepStrictEqual(o, {
          a: '1',
          b: '2',
          c: {foo: 1}
        }
    );
  });

  it('should deep clone object values to target', function() {
    const a = {a: 1, b: '2', c: {fop: 1}};
    const b = {a: '1', c: {foo: {bar: {baz: 1}}}};
    let o = merge(a, b, {deep: true, clone: true});
    b.c.foo.bar = 2;
    assert.deepStrictEqual(o, {
          a: '1',
          b: '2',
          c: {fop: 1, foo: {bar: {baz: 1}}}
        }
    );
  });

  it('should copy array values to target', function() {
    const a = {foo: [1, 2]};
    const b = {foo: [2, 3, 4]};
    let o = merge(a, b);
    assert.deepStrictEqual(o, b);
    assert.strictEqual(o.foo, b.foo);
  });

  it('should clone array values to target', function() {
    const a = {foo: [1, 2]};
    const b = {foo: [2, 3, 4]};
    let o = merge(a, b, {clone: true});
    assert.deepStrictEqual(o, b);
    assert.notStrictEqual(o.foo, b.foo);
  });

  it('should merge array values to target', function() {
    const a = {foo: [1, 2]};
    const b = {foo: [2, 3, 4]};
    let o = merge(a, b, {arrayMerge: true});
    assert.deepStrictEqual(o, {foo: [1, 2, 3, 4]});
    assert.notStrictEqual(o.foo, b.foo);
  });

  it('should perform custom array merge function', function() {
    const a = {foo: [1, 2]};
    const b = {foo: [2, 3, 4]};
    let o = merge(a, b, {
      arrayMerge: () => {
        return ['a', 'b'];
      }
    });
    assert.deepStrictEqual(o, {foo: ['a', 'b']});
  });

  it('should copy symbol values to target', function() {
    const foo = Symbol.for('sym');
    const a = {};
    const b = {foo};
    let o = merge(a, b);
    assert.deepStrictEqual(o, b);
    assert.strictEqual(o.foo, b.foo);
  });

  it('should copy descriptors', function() {
    const a = {};
    Object.defineProperty(a, 'foo', {
      configurable: true,
      enumerable: false,
      writable: false,
      value: 1
    });
    let o = merge({}, a, {descriptor: true});
    assert.deepStrictEqual(Object.getOwnPropertyDescriptor(o, 'foo'), {
          configurable: true,
          enumerable: false,
          writable: false,
          value: 1
        }
    );
  });

  it('should copy getters and setters and bind to target', function() {
    const a = {
      bar: 0,
      get foo() {
        return ++this.bar;
      },
      set foo(v) {
        this.bar = v;
      }
    };
    let o = merge({}, a, {descriptor: true});
    assert.strictEqual(a.foo, 1);
    assert.strictEqual(o.foo, 1);
    assert.strictEqual(o.foo, 2);
    o.foo = 5;
    assert.strictEqual(o.foo, 6);
  });

  it('should only copy properties if options.combine is true', function() {
    const a = {a: 1, b: '2'};
    const b = {a: 2, c: 3};
    let o = merge(a, b, {combine: true});
    assert.deepStrictEqual(o, {
          a: 1,
          b: '2',
          c: 3
        }
    );
  });

  it('should copy functions', function() {
    const a = {
      bar: 0,
      getFoo() {
        return ++this.bar;
      }
    };
    let o = merge({}, a);
    assert.strictEqual(a.getFoo(), 1);
    assert.strictEqual(o.getFoo(), 1);
    assert.strictEqual(o.getFoo(), 2);
  });

  it('should do nothing if source = target', function() {
    const a = {};
    const o = merge(a, a);
    assert.strictEqual(o, a);
  });

  it('should perform filter before merge', function() {
    const a = {a: 1, b: '2'};
    const b = {a: '1', c: {foo: 1}};
    let o = merge(a, b, {
      filter: (src, key) => {
        return key !== 'c';
      }
    });
    assert.deepStrictEqual(o, {
          a: '1',
          b: '2'
        }
    );
  });

  it('should perform deep filter before merge', function() {
    const a = {a: 1, b: '2'};
    const b = {a: '1', c: {foo: 1}};
    let o = merge(a, b, {
      deep: true,
      filter: (src, key) => {
        return key !== 'foo';
      }
    });
    assert.deepStrictEqual(o, {
          a: '1',
          b: '2',
          c: {}
        }
    );
  });

  it('should merge.all() perform bulk merge', function() {
    const a = {a: 1};
    const b = {b: 2};
    const c = {c: 3};
    let o = merge.all([a, b, c]);
    assert.deepStrictEqual(o, {
          a: 1,
          b: 2,
          c: 3
        }
    );
  });

  it('should prevent Prototype Pollution vulnerability (__proto__)', function() {
    const payload = JSON.parse('{"__proto__":{"polluted":"Yes! Its Polluted"}}');
    const obj = {};
    merge(obj, payload, {deep: true});
    assert.strictEqual(obj.polluted, undefined);
  });

  it('should prevent Prototype Pollution vulnerability (constructor)', function() {
    const payload = JSON.parse('{"constructor": {"prototype": {"polluted": "yes"}}}');
    let obj = {};
    merge(obj, payload, {deep: true});
    assert.strictEqual(obj.polluted, undefined);
  });

});
