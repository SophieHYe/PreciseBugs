/**
 * @overview Contains functionality specifically for Windows systems.
 * @license MPL-2.0
 * @author Eric Cornelissen <ericornelissen@gmail.com>
 */

/**
 * Escape a shell argument.
 *
 * @param {string} arg The argument to escape.
 * @returns {string} The escaped argument.
 */
function escapeShellArg(arg) {
  return arg.replace(/\u{0}/gu, "").replace(/"/g, `""`);
}

module.exports.escapeShellArg = escapeShellArg;
