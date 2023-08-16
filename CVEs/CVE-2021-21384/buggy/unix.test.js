const assert = require("assert");

const { escapeShellArg } = require("../src/unix.js");

describe("unix.js", function () {
  it("should return the input if nothing has to be escaped", function () {
    const input = `Hello world!`;
    const output = escapeShellArg(input);
    assert.strictEqual(output, input);
  });

  describe("escape single quotes", function () {
    it("escapes one single quote", function () {
      const input = `' & ls -al`;
      const output = escapeShellArg(input);
      assert.strictEqual(output, `'\\'' & ls -al`);
    });

    it("escapes two single quotes", function () {
      const input = `' & echo 'Hello world!'`;
      const output = escapeShellArg(input);
      assert.strictEqual(output, `'\\'' & echo '\\''Hello world!'\\''`);
    });
  });
});
