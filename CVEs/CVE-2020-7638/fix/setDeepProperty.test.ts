import * as assert from 'assert';
import { setDeepProperty } from '../index';

describe('setDeepProperty', () => {

  it('should set a property with deep 1', () => {
    const obj = {
      test: "A"
    }
    setDeepProperty(obj, "test", "B");
    assert.equal(obj.test, "B");
  });

  it('should set a property with deep 2', () => {
    const obj = {
      parent: {
        test: "A"
      }
    }
    setDeepProperty(obj, "parent.test", "B");
    assert.equal(obj.parent.test, "B");
  });

  it('should not allow to set a not existing property', () => {
    const obj = {
      test: "A"
    }
    assert.throws(() => setDeepProperty(obj, "not_existing", "B"));
  });

  it('should not allow to set a property on null/undefined obj', () => {
    assert.throws(() => setDeepProperty(null as any, "not_existing", "B"));
    assert.throws(() => setDeepProperty(undefined as any, "not_existing", "B"));
  });

  it('should not allow to set a null/undefined property', () => {
    const obj = {
      test: "A"
    }
    assert.throws(() => setDeepProperty(obj, null as any, "B"));
    assert.throws(() => setDeepProperty(obj, undefined as any, "B"));
    assert.throws(() => setDeepProperty(obj, "", "B"));
  });

  it('should not allow to set __proto__ property', () => {
    const obj = {
      test: "A"
    }
    assert.throws(() => setDeepProperty(obj, "__proto__.xyz", "B"));
  });

});