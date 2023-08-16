"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
const assert = require("assert");
const __1 = require("..");
describe('setDeepProperty', () => {
    it('should set a property with deep 1', () => {
        const obj = {
            test: "A"
        };
        __1.setDeepProperty(obj, "test", "B");
        assert.equal(obj.test, "B");
    });
    it('should set a property with deep 2', () => {
        const obj = {
            parent: {
                test: "A"
            }
        };
        __1.setDeepProperty(obj, "parent.test", "B");
        assert.equal(obj.parent.test, "B");
    });
});
//# sourceMappingURL=setDeepProperty.test.js.map