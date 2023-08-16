import * as assert from 'assert';
import { setDeepProperty } from '..';

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

});