"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
const assert = require("assert");
const index_1 = require("../index");
describe('setDeepProperty', () => {
    it('should set a property with deep 1', () => {
        const obj = {
            test: "A"
        };
        index_1.setDeepProperty(obj, "test", "B");
        assert.equal(obj.test, "B");
    });
    it('should set a property with deep 2', () => {
        const obj = {
            parent: {
                test: "A"
            }
        };
        index_1.setDeepProperty(obj, "parent.test", "B");
        assert.equal(obj.parent.test, "B");
    });
    it('should not allow to set a not existing property', () => {
        const obj = {
            test: "A"
        };
        assert.throws(() => index_1.setDeepProperty(obj, "not_existing", "B"));
    });
    it('should not allow to set a property on null/undefined obj', () => {
        assert.throws(() => index_1.setDeepProperty(null, "not_existing", "B"));
        assert.throws(() => index_1.setDeepProperty(undefined, "not_existing", "B"));
    });
    it('should not allow to set a null/undefined property', () => {
        const obj = {
            test: "A"
        };
        assert.throws(() => index_1.setDeepProperty(obj, null, "B"));
        assert.throws(() => index_1.setDeepProperty(obj, undefined, "B"));
        assert.throws(() => index_1.setDeepProperty(obj, "", "B"));
    });
    it('should not allow to set __proto__ property', () => {
        const obj = {
            test: "A"
        };
        assert.throws(() => index_1.setDeepProperty(obj, "__proto__.xyz", "B"));
    });
});
//# sourceMappingURL=setDeepProperty.test.js.map