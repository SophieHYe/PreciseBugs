/**
 * @overview Provides fixtures for testing Unix specific functionality.
 * @license MPL-2.0
 */

import { binBash, binDash, binZsh } from "../_constants.cjs";

export const escape = {
  [binBash]: {
    "sample strings": [
      {
        input: "foobar",
        expected: { interpolation: "foobar", noInterpolation: "foobar" },
      },
      {
        input: "Hello world",
        expected: {
          interpolation: "Hello world",
          noInterpolation: "Hello world",
        },
      },
    ],
    "<null> (\\0)": [
      {
        input: "a\x00b",
        expected: { interpolation: "ab", noInterpolation: "ab" },
      },
      {
        input: "a\x00b\x00c",
        expected: { interpolation: "abc", noInterpolation: "abc" },
      },
      {
        input: "a\x00",
        expected: { interpolation: "a", noInterpolation: "a" },
      },
      {
        input: "\x00a",
        expected: { interpolation: "a", noInterpolation: "a" },
      },
    ],
    "<backspace> ('\\b')": [
      {
        input: "a\bb",
        expected: { interpolation: "ab", noInterpolation: "ab" },
      },
      {
        input: "a\bb\bc",
        expected: { interpolation: "abc", noInterpolation: "abc" },
      },
      {
        input: "\ba",
        expected: { interpolation: "a", noInterpolation: "a" },
      },
      {
        input: "a\b",
        expected: { interpolation: "a", noInterpolation: "a" },
      },
    ],
    "<character tabulation> (\\t)": [
      {
        input: "a\tb",
        expected: { interpolation: "a\tb", noInterpolation: "a\tb" },
      },
      {
        input: "a\tb\tc",
        expected: { interpolation: "a\tb\tc", noInterpolation: "a\tb\tc" },
      },
      {
        input: "a\t",
        expected: { interpolation: "a\t", noInterpolation: "a\t" },
      },
      {
        input: "\ta",
        expected: { interpolation: "\ta", noInterpolation: "\ta" },
      },
    ],
    "<end of line> ('\\n')": [
      {
        input: "a\nb",
        expected: { interpolation: "a b", noInterpolation: "a\nb" },
      },
      {
        input: "a\nb\nc",
        expected: { interpolation: "a b c", noInterpolation: "a\nb\nc" },
      },
      {
        input: "a\n",
        expected: { interpolation: "a ", noInterpolation: "a\n" },
      },
      {
        input: "\na",
        expected: { interpolation: " a", noInterpolation: "\na" },
      },
    ],
    "<line tabulation> (\\v)": [
      {
        input: "a\vb",
        expected: { interpolation: "a\vb", noInterpolation: "a\vb" },
      },
      {
        input: "a\vb\vc",
        expected: { interpolation: "a\vb\vc", noInterpolation: "a\vb\vc" },
      },
      {
        input: "a\v",
        expected: { interpolation: "a\v", noInterpolation: "a\v" },
      },
      {
        input: "\va",
        expected: { interpolation: "\va", noInterpolation: "\va" },
      },
    ],
    "<form feed> (\\f)": [
      {
        input: "a\fb",
        expected: { interpolation: "a\fb", noInterpolation: "a\fb" },
      },
      {
        input: "a\fb\fc",
        expected: { interpolation: "a\fb\fc", noInterpolation: "a\fb\fc" },
      },
      {
        input: "a\f",
        expected: { interpolation: "a\f", noInterpolation: "a\f" },
      },
      {
        input: "\fa",
        expected: { interpolation: "\fa", noInterpolation: "\fa" },
      },
    ],
    "<carriage return> ('\\r')": [
      {
        input: "a\rb",
        expected: { interpolation: "ab", noInterpolation: "ab" },
      },
      {
        input: "a\rb\rc",
        expected: { interpolation: "abc", noInterpolation: "abc" },
      },
      {
        input: "\ra",
        expected: { interpolation: "a", noInterpolation: "a" },
      },
      {
        input: "a\r",
        expected: { interpolation: "a", noInterpolation: "a" },
      },
      {
        input: "a\r\nb",
        expected: { interpolation: "a b", noInterpolation: "a\r\nb" },
      },
    ],
    "<escape> ('\\u001B')": [
      {
        input: "a\u001Bb",
        expected: { interpolation: "ab", noInterpolation: "ab" },
      },
      {
        input: "a\u001Bb\u001Bc",
        expected: { interpolation: "abc", noInterpolation: "abc" },
      },
      {
        input: "\u001Ba",
        expected: { interpolation: "a", noInterpolation: "a" },
      },
      {
        input: "a\u001B",
        expected: { interpolation: "a", noInterpolation: "a" },
      },
    ],
    "<space> (' ')": [
      {
        input: "a b",
        expected: { interpolation: "a b", noInterpolation: "a b" },
      },
      {
        input: "a b c",
        expected: { interpolation: "a b c", noInterpolation: "a b c" },
      },
      {
        input: "a ",
        expected: { interpolation: "a ", noInterpolation: "a " },
      },
      {
        input: " a",
        expected: { interpolation: " a", noInterpolation: " a" },
      },
    ],
    "<next line> (\\u0085)": [
      {
        input: "a\u0085b",
        expected: {
          interpolation: "a\u0085b",
          noInterpolation: "a\u0085b",
        },
      },
      {
        input: "a\u0085b\u0085c",
        expected: {
          interpolation: "a\u0085b\u0085c",
          noInterpolation: "a\u0085b\u0085c",
        },
      },
      {
        input: "a\u0085",
        expected: {
          interpolation: "a\u0085",
          noInterpolation: "a\u0085",
        },
      },
      {
        input: "\u0085a",
        expected: {
          interpolation: "\u0085a",
          noInterpolation: "\u0085a",
        },
      },
    ],
    "<control sequence introducer> ('\\u009B')": [
      {
        input: "a\u009Bb",
        expected: { interpolation: "ab", noInterpolation: "ab" },
      },
      {
        input: "a\u009Bb\u009Bc",
        expected: { interpolation: "abc", noInterpolation: "abc" },
      },
      {
        input: "\u009Ba",
        expected: { interpolation: "a", noInterpolation: "a" },
      },
      {
        input: "a\u009B",
        expected: { interpolation: "a", noInterpolation: "a" },
      },
    ],
    "<no break space> (\\u00A0)": [
      {
        input: "a\u00A0b",
        expected: {
          interpolation: "a\u00A0b",
          noInterpolation: "a\u00A0b",
        },
      },
      {
        input: "a\u00A0b\u00A0c",
        expected: {
          interpolation: "a\u00A0b\u00A0c",
          noInterpolation: "a\u00A0b\u00A0c",
        },
      },
      {
        input: "a\u00A0",
        expected: {
          interpolation: "a\u00A0",
          noInterpolation: "a\u00A0",
        },
      },
      {
        input: "\u00A0a",
        expected: {
          interpolation: "\u00A0a",
          noInterpolation: "\u00A0a",
        },
      },
    ],
    "<en quad> (\\u2000)": [
      {
        input: "a\u2000b",
        expected: {
          interpolation: "a\u2000b",
          noInterpolation: "a\u2000b",
        },
      },
      {
        input: "a\u2000b\u2000c",
        expected: {
          interpolation: "a\u2000b\u2000c",
          noInterpolation: "a\u2000b\u2000c",
        },
      },
      {
        input: "a\u2000",
        expected: {
          interpolation: "a\u2000",
          noInterpolation: "a\u2000",
        },
      },
      {
        input: "\u2000a",
        expected: {
          interpolation: "\u2000a",
          noInterpolation: "\u2000a",
        },
      },
    ],
    "<em quad> (\\u2001)": [
      {
        input: "a\u2001b",
        expected: {
          interpolation: "a\u2001b",
          noInterpolation: "a\u2001b",
        },
      },
      {
        input: "a\u2001b\u2001c",
        expected: {
          interpolation: "a\u2001b\u2001c",
          noInterpolation: "a\u2001b\u2001c",
        },
      },
      {
        input: "a\u2001",
        expected: {
          interpolation: "a\u2001",
          noInterpolation: "a\u2001",
        },
      },
      {
        input: "\u2001a",
        expected: {
          interpolation: "\u2001a",
          noInterpolation: "\u2001a",
        },
      },
    ],
    "<en space> (\\u2002)": [
      {
        input: "a\u2002b",
        expected: {
          interpolation: "a\u2002b",
          noInterpolation: "a\u2002b",
        },
      },
      {
        input: "a\u2002b\u2002c",
        expected: {
          interpolation: "a\u2002b\u2002c",
          noInterpolation: "a\u2002b\u2002c",
        },
      },
      {
        input: "a\u2002",
        expected: {
          interpolation: "a\u2002",
          noInterpolation: "a\u2002",
        },
      },
      {
        input: "\u2002a",
        expected: {
          interpolation: "\u2002a",
          noInterpolation: "\u2002a",
        },
      },
    ],
    "<em space> (\\u2003)": [
      {
        input: "a\u2003b",
        expected: {
          interpolation: "a\u2003b",
          noInterpolation: "a\u2003b",
        },
      },
      {
        input: "a\u2003b\u2003c",
        expected: {
          interpolation: "a\u2003b\u2003c",
          noInterpolation: "a\u2003b\u2003c",
        },
      },
      {
        input: "a\u2003",
        expected: {
          interpolation: "a\u2003",
          noInterpolation: "a\u2003",
        },
      },
      {
        input: "\u2003a",
        expected: {
          interpolation: "\u2003a",
          noInterpolation: "\u2003a",
        },
      },
    ],
    "<three-per-em space> (\\u2004)": [
      {
        input: "a\u2004b",
        expected: {
          interpolation: "a\u2004b",
          noInterpolation: "a\u2004b",
        },
      },
      {
        input: "a\u2004b\u2004c",
        expected: {
          interpolation: "a\u2004b\u2004c",
          noInterpolation: "a\u2004b\u2004c",
        },
      },
      {
        input: "a\u2004",
        expected: {
          interpolation: "a\u2004",
          noInterpolation: "a\u2004",
        },
      },
      {
        input: "\u2004a",
        expected: {
          interpolation: "\u2004a",
          noInterpolation: "\u2004a",
        },
      },
    ],
    "<four-per-em space> (\\u2005)": [
      {
        input: "a\u2005b",
        expected: {
          interpolation: "a\u2005b",
          noInterpolation: "a\u2005b",
        },
      },
      {
        input: "a\u2005b\u2005c",
        expected: {
          interpolation: "a\u2005b\u2005c",
          noInterpolation: "a\u2005b\u2005c",
        },
      },
      {
        input: "a\u2005",
        expected: {
          interpolation: "a\u2005",
          noInterpolation: "a\u2005",
        },
      },
      {
        input: "\u2005a",
        expected: {
          interpolation: "\u2005a",
          noInterpolation: "\u2005a",
        },
      },
    ],
    "<six-per-em space> (\\u2006)": [
      {
        input: "a\u2006b",
        expected: {
          interpolation: "a\u2006b",
          noInterpolation: "a\u2006b",
        },
      },
      {
        input: "a\u2006b\u2006c",
        expected: {
          interpolation: "a\u2006b\u2006c",
          noInterpolation: "a\u2006b\u2006c",
        },
      },
      {
        input: "a\u2006",
        expected: {
          interpolation: "a\u2006",
          noInterpolation: "a\u2006",
        },
      },
      {
        input: "\u2006a",
        expected: {
          interpolation: "\u2006a",
          noInterpolation: "\u2006a",
        },
      },
    ],
    "<figure space> (\\u2007)": [
      {
        input: "a\u2007b",
        expected: {
          interpolation: "a\u2007b",
          noInterpolation: "a\u2007b",
        },
      },
      {
        input: "a\u2007b\u2007c",
        expected: {
          interpolation: "a\u2007b\u2007c",
          noInterpolation: "a\u2007b\u2007c",
        },
      },
      {
        input: "a\u2007",
        expected: {
          interpolation: "a\u2007",
          noInterpolation: "a\u2007",
        },
      },
      {
        input: "\u2007a",
        expected: {
          interpolation: "\u2007a",
          noInterpolation: "\u2007a",
        },
      },
    ],
    "<punctuation space> (\\u2008)": [
      {
        input: "a\u2008b",
        expected: {
          interpolation: "a\u2008b",
          noInterpolation: "a\u2008b",
        },
      },
      {
        input: "a\u2008b\u2008c",
        expected: {
          interpolation: "a\u2008b\u2008c",
          noInterpolation: "a\u2008b\u2008c",
        },
      },
      {
        input: "a\u2008",
        expected: {
          interpolation: "a\u2008",
          noInterpolation: "a\u2008",
        },
      },
      {
        input: "\u2008a",
        expected: {
          interpolation: "\u2008a",
          noInterpolation: "\u2008a",
        },
      },
    ],
    "<thin space> (\\u2009)": [
      {
        input: "a\u2009b",
        expected: {
          interpolation: "a\u2009b",
          noInterpolation: "a\u2009b",
        },
      },
      {
        input: "a\u2009b\u2009c",
        expected: {
          interpolation: "a\u2009b\u2009c",
          noInterpolation: "a\u2009b\u2009c",
        },
      },
      {
        input: "a\u2009",
        expected: {
          interpolation: "a\u2009",
          noInterpolation: "a\u2009",
        },
      },
      {
        input: "\u2009a",
        expected: {
          interpolation: "\u2009a",
          noInterpolation: "\u2009a",
        },
      },
    ],
    "<hair space> (\\u200A)": [
      {
        input: "a\u200Ab",
        expected: {
          interpolation: "a\u200Ab",
          noInterpolation: "a\u200Ab",
        },
      },
      {
        input: "a\u200Ab\u200Ac",
        expected: {
          interpolation: "a\u200Ab\u200Ac",
          noInterpolation: "a\u200Ab\u200Ac",
        },
      },
      {
        input: "a\u200A",
        expected: {
          interpolation: "a\u200A",
          noInterpolation: "a\u200A",
        },
      },
      {
        input: "\u200Aa",
        expected: {
          interpolation: "\u200Aa",
          noInterpolation: "\u200Aa",
        },
      },
    ],
    "<line separator> (\\u2028)": [
      {
        input: "a\u2028b",
        expected: {
          interpolation: "a\u2028b",
          noInterpolation: "a\u2028b",
        },
      },
      {
        input: "a\u2028b\u2028c",
        expected: {
          interpolation: "a\u2028b\u2028c",
          noInterpolation: "a\u2028b\u2028c",
        },
      },
      {
        input: "a\u2028",
        expected: {
          interpolation: "a\u2028",
          noInterpolation: "a\u2028",
        },
      },
      {
        input: "\u2028a",
        expected: {
          interpolation: "\u2028a",
          noInterpolation: "\u2028a",
        },
      },
    ],
    "<paragraph separator> (\\u2029)": [
      {
        input: "a\u2029b",
        expected: {
          interpolation: "a\u2029b",
          noInterpolation: "a\u2029b",
        },
      },
      {
        input: "a\u2029b\u2029c",
        expected: {
          interpolation: "a\u2029b\u2029c",
          noInterpolation: "a\u2029b\u2029c",
        },
      },
      {
        input: "a\u2029",
        expected: {
          interpolation: "a\u2029",
          noInterpolation: "a\u2029",
        },
      },
      {
        input: "\u2029a",
        expected: {
          interpolation: "\u2029a",
          noInterpolation: "\u2029a",
        },
      },
    ],
    "<narrow no-break space> (\\u202F)": [
      {
        input: "a\u202Fb",
        expected: {
          interpolation: "a\u202Fb",
          noInterpolation: "a\u202Fb",
        },
      },
      {
        input: "a\u202Fb\u202Fc",
        expected: {
          interpolation: "a\u202Fb\u202Fc",
          noInterpolation: "a\u202Fb\u202Fc",
        },
      },
      {
        input: "a\u202F",
        expected: {
          interpolation: "a\u202F",
          noInterpolation: "a\u202F",
        },
      },
      {
        input: "\u202Fa",
        expected: {
          interpolation: "\u202Fa",
          noInterpolation: "\u202Fa",
        },
      },
    ],
    "<medium mathematical space> (\\u205F)": [
      {
        input: "a\u205Fb",
        expected: {
          interpolation: "a\u205Fb",
          noInterpolation: "a\u205Fb",
        },
      },
      {
        input: "a\u205Fb\u205Fc",
        expected: {
          interpolation: "a\u205Fb\u205Fc",
          noInterpolation: "a\u205Fb\u205Fc",
        },
      },
      {
        input: "a\u205F",
        expected: {
          interpolation: "a\u205F",
          noInterpolation: "a\u205F",
        },
      },
      {
        input: "\u205Fa",
        expected: {
          interpolation: "\u205Fa",
          noInterpolation: "\u205Fa",
        },
      },
    ],
    "<ideographic space> (\\u3000)": [
      {
        input: "a\u3000b",
        expected: {
          interpolation: "a\u3000b",
          noInterpolation: "a\u3000b",
        },
      },
      {
        input: "a\u3000b\u3000c",
        expected: {
          interpolation: "a\u3000b\u3000c",
          noInterpolation: "a\u3000b\u3000c",
        },
      },
      {
        input: "a\u3000",
        expected: {
          interpolation: "a\u3000",
          noInterpolation: "a\u3000",
        },
      },
      {
        input: "\u3000a",
        expected: {
          interpolation: "\u3000a",
          noInterpolation: "\u3000a",
        },
      },
    ],
    "<zero width no-break space> (\\uFEFF)": [
      {
        input: "a\uFEFFb",
        expected: {
          interpolation: "a\uFEFFb",
          noInterpolation: "a\uFEFFb",
        },
      },
      {
        input: "a\uFEFFb\uFEFFc",
        expected: {
          interpolation: "a\uFEFFb\uFEFFc",
          noInterpolation: "a\uFEFFb\uFEFFc",
        },
      },
      {
        input: "a\uFEFF",
        expected: {
          interpolation: "a\uFEFF",
          noInterpolation: "a\uFEFF",
        },
      },
      {
        input: "\uFEFFa",
        expected: {
          interpolation: "\uFEFFa",
          noInterpolation: "\uFEFFa",
        },
      },
    ],
    'single quotes ("\'")': [
      {
        input: "a'b",
        expected: {
          interpolation: "a\\'b",
          noInterpolation: "a'b",
          quoted: "a'\\''b",
        },
      },
      {
        input: "a'b'c",
        expected: {
          interpolation: "a\\'b\\'c",
          noInterpolation: "a'b'c",
          quoted: "a'\\''b'\\''c",
        },
      },
    ],
    "double quotes ('\"')": [
      {
        input: 'a"b',
        expected: { interpolation: 'a\\"b', noInterpolation: 'a"b' },
      },
      {
        input: 'a"b"c',
        expected: { interpolation: 'a\\"b\\"c', noInterpolation: 'a"b"c' },
      },
    ],
    "backticks (')": [
      {
        input: "a`b",
        expected: { interpolation: "a\\`b", noInterpolation: "a`b" },
      },
      {
        input: "a`b`c",
        expected: { interpolation: "a\\`b\\`c", noInterpolation: "a`b`c" },
      },
    ],
    "tildes ('~')": [
      {
        input: "~a",
        expected: { interpolation: "\\~a", noInterpolation: "~a" },
      },
      {
        input: "~a~b",
        expected: { interpolation: "\\~a~b", noInterpolation: "~a~b" },
      },
      {
        input: "a~b",
        expected: { interpolation: "a~b", noInterpolation: "a~b" },
      },
      {
        input: "a~b~c",
        expected: { interpolation: "a~b~c", noInterpolation: "a~b~c" },
      },
      {
        input: "a~b=",
        expected: { interpolation: "a~b=", noInterpolation: "a~b=" },
      },
      {
        input: "a=~",
        expected: { interpolation: "a=\\~", noInterpolation: "a=~" },
      },
      {
        input: "a~b=~",
        expected: { interpolation: "a~b=\\~", noInterpolation: "a~b=~" },
      },
      {
        input: "a=b~",
        expected: { interpolation: "a=b~", noInterpolation: "a=b~" },
      },
      {
        input: "a=~b",
        expected: { interpolation: "a=~b", noInterpolation: "a=~b" },
      },
      {
        input: "a=:~",
        expected: { interpolation: "a=:\\~", noInterpolation: "a=:~" },
      },
      {
        input: "a=b:~",
        expected: { interpolation: "a=b:\\~", noInterpolation: "a=b:~" },
      },
      {
        input: "a=~:",
        expected: { interpolation: "a=\\~:", noInterpolation: "a=~:" },
      },
      {
        input: "a=~:b",
        expected: { interpolation: "a=\\~:b", noInterpolation: "a=~:b" },
      },
      {
        input: "a=~:~",
        expected: { interpolation: "a=\\~:\\~", noInterpolation: "a=~:~" },
      },
      {
        input: "a=~:~:~",
        expected: {
          interpolation: "a=\\~:\\~:\\~",
          noInterpolation: "a=~:~:~",
        },
      },
      {
        input: "a=:~:",
        expected: { interpolation: "a=:\\~:", noInterpolation: "a=:~:" },
      },
      {
        input: "a=:~:b",
        expected: { interpolation: "a=:\\~:b", noInterpolation: "a=:~:b" },
      },
      {
        input: "a=b:~:",
        expected: { interpolation: "a=b:\\~:", noInterpolation: "a=b:~:" },
      },
      {
        input: "a=:b:~",
        expected: { interpolation: "a=:b:\\~", noInterpolation: "a=:b:~" },
      },
      {
        input: "a=\r:~:",
        expected: { interpolation: "a=:\\~:", noInterpolation: "a=:~:" },
      },
      {
        input: "a=\u2028:~:",
        expected: {
          interpolation: "a=\u2028:\\~:",
          noInterpolation: "a=\u2028:~:",
        },
      },
      {
        input: "a=\u2029:~:",
        expected: {
          interpolation: "a=\u2029:\\~:",
          noInterpolation: "a=\u2029:~:",
        },
      },
      {
        input: "a=b:~:c",
        expected: { interpolation: "a=b:\\~:c", noInterpolation: "a=b:~:c" },
      },
      {
        input: "a=~=",
        expected: { interpolation: "a=\\~=", noInterpolation: "a=~=" },
      },
      {
        input: "a=~-",
        expected: { interpolation: "a=\\~-", noInterpolation: "a=~-" },
      },
      {
        input: "a=~+",
        expected: { interpolation: "a=\\~+", noInterpolation: "a=~+" },
      },
      {
        input: "a=~/",
        expected: { interpolation: "a=\\~/", noInterpolation: "a=~/" },
      },
      {
        input: "a=~0",
        expected: { interpolation: "a=\\~0", noInterpolation: "a=~0" },
      },
      {
        input: "a=~ ",
        expected: { interpolation: "a=\\~ ", noInterpolation: "a=~ " },
      },
      {
        input: "a ~b",
        expected: { interpolation: "a \\~b", noInterpolation: "a ~b" },
      },
      {
        input: "a	~b",
        expected: { interpolation: "a	\\~b", noInterpolation: "a	~b" },
      },
    ],
    "hashtags ('#')": [
      {
        input: "#a",
        expected: { interpolation: "\\#a", noInterpolation: "#a" },
      },
      {
        input: "#a#b",
        expected: { interpolation: "\\#a#b", noInterpolation: "#a#b" },
      },
      {
        input: "a#b",
        expected: { interpolation: "a#b", noInterpolation: "a#b" },
      },
      {
        input: "a#b#c",
        expected: { interpolation: "a#b#c", noInterpolation: "a#b#c" },
      },
      {
        input: "a #b",
        expected: { interpolation: "a \\#b", noInterpolation: "a #b" },
      },
      {
        input: "a	#b",
        expected: { interpolation: "a	\\#b", noInterpolation: "a	#b" },
      },
    ],
    "dollar signs ('$')": [
      {
        input: "a$b",
        expected: { interpolation: "a\\$b", noInterpolation: "a$b" },
      },
      {
        input: "a$b$c",
        expected: { interpolation: "a\\$b\\$c", noInterpolation: "a$b$c" },
      },
    ],
    "ampersands ('&')": [
      {
        input: "a&b",
        expected: { interpolation: "a\\&b", noInterpolation: "a&b" },
      },
      {
        input: "a&b&c",
        expected: { interpolation: "a\\&b\\&c", noInterpolation: "a&b&c" },
      },
    ],
    "asterisks ('*')": [
      {
        input: "a*b",
        expected: { interpolation: "a\\*b", noInterpolation: "a*b" },
      },
      {
        input: "a*b*c",
        expected: { interpolation: "a\\*b\\*c", noInterpolation: "a*b*c" },
      },
    ],
    "equals ('=')": [
      {
        input: "=a",
        expected: { interpolation: "=a", noInterpolation: "=a" },
      },
      {
        input: "=a=b",
        expected: { interpolation: "=a=b", noInterpolation: "=a=b" },
      },
      {
        input: "a=b",
        expected: { interpolation: "a=b", noInterpolation: "a=b" },
      },
      {
        input: "a=b=c",
        expected: { interpolation: "a=b=c", noInterpolation: "a=b=c" },
      },
    ],
    "backslashes ('\\')": [
      {
        input: "a\\b",
        expected: { interpolation: "a\\\\b", noInterpolation: "a\\b" },
      },
      {
        input: "a\\b\\c",
        expected: { interpolation: "a\\\\b\\\\c", noInterpolation: "a\\b\\c" },
      },
    ],
    "pipes ('|')": [
      {
        input: "a|b",
        expected: { interpolation: "a\\|b", noInterpolation: "a|b" },
      },
      {
        input: "a|b|c",
        expected: { interpolation: "a\\|b\\|c", noInterpolation: "a|b|c" },
      },
    ],
    "semicolons (';')": [
      {
        input: "a;b",
        expected: { interpolation: "a\\;b", noInterpolation: "a;b" },
      },
      {
        input: "a;b;c",
        expected: { interpolation: "a\\;b\\;c", noInterpolation: "a;b;c" },
      },
    ],
    "question marks ('?')": [
      {
        input: "a?b",
        expected: { interpolation: "a\\?b", noInterpolation: "a?b" },
      },
      {
        input: "a?b?c",
        expected: { interpolation: "a\\?b\\?c", noInterpolation: "a?b?c" },
      },
    ],
    "parentheses ('(', ')')": [
      {
        input: "a(b",
        expected: { interpolation: "a\\(b", noInterpolation: "a(b" },
      },
      {
        input: "a)b",
        expected: { interpolation: "a\\)b", noInterpolation: "a)b" },
      },
      {
        input: "a(b(c",
        expected: { interpolation: "a\\(b\\(c", noInterpolation: "a(b(c" },
      },
      {
        input: "a)b)c",
        expected: { interpolation: "a\\)b\\)c", noInterpolation: "a)b)c" },
      },
      {
        input: "a(b)c",
        expected: { interpolation: "a\\(b\\)c", noInterpolation: "a(b)c" },
      },
      {
        input: "a(b,c)d",
        expected: { interpolation: "a\\(b,c\\)d", noInterpolation: "a(b,c)d" },
      },
    ],
    "square brackets ('[', ']')": [
      {
        input: "a[b",
        expected: { interpolation: "a[b", noInterpolation: "a[b" },
      },
      {
        input: "a]b",
        expected: { interpolation: "a]b", noInterpolation: "a]b" },
      },
      {
        input: "a[b[c",
        expected: { interpolation: "a[b[c", noInterpolation: "a[b[c" },
      },
      {
        input: "a]b]c",
        expected: { interpolation: "a]b]c", noInterpolation: "a]b]c" },
      },
      {
        input: "a[b]c",
        expected: { interpolation: "a[b]c", noInterpolation: "a[b]c" },
      },
      {
        input: "a[b,c]d",
        expected: { interpolation: "a[b,c]d", noInterpolation: "a[b,c]d" },
      },
    ],
    "curly brackets ('{', '}')": [
      {
        input: "a{b",
        expected: { interpolation: "a{b", noInterpolation: "a{b" },
      },
      {
        input: "a}b",
        expected: { interpolation: "a}b", noInterpolation: "a}b" },
      },
      {
        input: "a{b{c",
        expected: { interpolation: "a{b{c", noInterpolation: "a{b{c" },
      },
      {
        input: "a}b}c",
        expected: { interpolation: "a}b}c", noInterpolation: "a}b}c" },
      },
      {
        input: "a{b}c",
        expected: { interpolation: "a{b}c", noInterpolation: "a{b}c" },
      },
      {
        input: "a{b,c}d",
        expected: { interpolation: "a\\{b,c}d", noInterpolation: "a{b,c}d" },
      },
      {
        input: "a{,b}c",
        expected: { interpolation: "a\\{,b}c", noInterpolation: "a{,b}c" },
      },
      {
        input: "a{b,}c",
        expected: { interpolation: "a\\{b,}c", noInterpolation: "a{b,}c" },
      },
      {
        input: "a{bc,de}f",
        expected: {
          interpolation: "a\\{bc,de}f",
          noInterpolation: "a{bc,de}f",
        },
      },
      {
        input: "a{b,{c,d},e}f",
        expected: {
          interpolation: "a\\{b,\\{c,d},e}f",
          noInterpolation: "a{b,{c,d},e}f",
        },
      },
      {
        input: "a{0..2}b",
        expected: { interpolation: "a\\{0..2}b", noInterpolation: "a{0..2}b" },
      },
      {
        input: "a{\u000Db,c}d",
        expected: {
          interpolation: "a\\{b,c}d",
          noInterpolation: "a{b,c}d",
        },
      },
      {
        input: "a{\u2028b,c}d",
        expected: {
          interpolation: "a\\{\u2028b,c}d",
          noInterpolation: "a{\u2028b,c}d",
        },
      },
      {
        input: "a{\u2029b,c}d",
        expected: {
          interpolation: "a\\{\u2029b,c}d",
          noInterpolation: "a{\u2029b,c}d",
        },
      },
      {
        input: "a{b,c\u000D}d",
        expected: {
          interpolation: "a\\{b,c}d",
          noInterpolation: "a{b,c}d",
        },
      },
      {
        input: "a{b,c\u2028}d",
        expected: {
          interpolation: "a\\{b,c\u2028}d",
          noInterpolation: "a{b,c\u2028}d",
        },
      },
      {
        input: "a{b,c\u2029}d",
        expected: {
          interpolation: "a\\{b,c\u2029}d",
          noInterpolation: "a{b,c\u2029}d",
        },
      },
      {
        input: "a{\u000D0..2}b",
        expected: {
          interpolation: "a\\{0..2}b",
          noInterpolation: "a{0..2}b",
        },
      },
      {
        input: "a{\u20280..2}b",
        expected: {
          interpolation: "a\\{\u20280..2}b",
          noInterpolation: "a{\u20280..2}b",
        },
      },
      {
        input: "a{\u20290..2}b",
        expected: {
          interpolation: "a\\{\u20290..2}b",
          noInterpolation: "a{\u20290..2}b",
        },
      },
      {
        input: "a{0..2\u000D}b",
        expected: {
          interpolation: "a\\{0..2}b",
          noInterpolation: "a{0..2}b",
        },
      },
      {
        input: "a{0..2\u2028}b",
        expected: {
          interpolation: "a\\{0..2\u2028}b",
          noInterpolation: "a{0..2\u2028}b",
        },
      },
      {
        input: "a{0..2\u2029}b",
        expected: {
          interpolation: "a\\{0..2\u2029}b",
          noInterpolation: "a{0..2\u2029}b",
        },
      },
      {
        input: "a{{b,c}",
        expected: { interpolation: "a\\{\\{b,c}", noInterpolation: "a{{b,c}" },
      },
      {
        input: "a{b{c,d}",
        expected: {
          interpolation: "a\\{b\\{c,d}",
          noInterpolation: "a{b{c,d}",
        },
      },
    ],
    "angle brackets ('<', '>')": [
      {
        input: "a<b",
        expected: { interpolation: "a\\<b", noInterpolation: "a<b" },
      },
      {
        input: "a>b",
        expected: { interpolation: "a\\>b", noInterpolation: "a>b" },
      },
      {
        input: "a<b<c",
        expected: { interpolation: "a\\<b\\<c", noInterpolation: "a<b<c" },
      },
      {
        input: "a>b>c",
        expected: { interpolation: "a\\>b\\>c", noInterpolation: "a>b>c" },
      },
      {
        input: "a<b>c",
        expected: { interpolation: "a\\<b\\>c", noInterpolation: "a<b>c" },
      },
    ],
  },
  [binDash]: {
    "sample strings": [
      {
        input: "foobar",
        expected: { interpolation: "foobar", noInterpolation: "foobar" },
      },
      {
        input: "Hello world",
        expected: {
          interpolation: "Hello world",
          noInterpolation: "Hello world",
        },
      },
    ],
    "<null> (\\0)": [
      {
        input: "a\x00b",
        expected: { interpolation: "ab", noInterpolation: "ab" },
      },
      {
        input: "a\x00b\x00c",
        expected: { interpolation: "abc", noInterpolation: "abc" },
      },
    ],
    "<backspace> ('\\b')": [
      {
        input: "a\bb",
        expected: { interpolation: "ab", noInterpolation: "ab" },
      },
      {
        input: "a\bb\bc",
        expected: { interpolation: "abc", noInterpolation: "abc" },
      },
      {
        input: "\ba",
        expected: { interpolation: "a", noInterpolation: "a" },
      },
      {
        input: "a\b",
        expected: { interpolation: "a", noInterpolation: "a" },
      },
    ],
    "<character tabulation> (\\t)": [
      {
        input: "a\tb",
        expected: { interpolation: "a\tb", noInterpolation: "a\tb" },
      },
      {
        input: "a\tb\tc",
        expected: { interpolation: "a\tb\tc", noInterpolation: "a\tb\tc" },
      },
      {
        input: "a\t",
        expected: { interpolation: "a\t", noInterpolation: "a\t" },
      },
      {
        input: "\ta",
        expected: { interpolation: "\ta", noInterpolation: "\ta" },
      },
    ],
    "<end of line> ('\\n')": [
      {
        input: "a\nb",
        expected: { interpolation: "a b", noInterpolation: "a\nb" },
      },
      {
        input: "a\nb\nc",
        expected: { interpolation: "a b c", noInterpolation: "a\nb\nc" },
      },
      {
        input: "a\n",
        expected: { interpolation: "a ", noInterpolation: "a\n" },
      },
      {
        input: "\na",
        expected: { interpolation: " a", noInterpolation: "\na" },
      },
    ],
    "<line tabulation> (\\v)": [
      {
        input: "a\vb",
        expected: { interpolation: "a\vb", noInterpolation: "a\vb" },
      },
      {
        input: "a\vb\vc",
        expected: { interpolation: "a\vb\vc", noInterpolation: "a\vb\vc" },
      },
      {
        input: "a\v",
        expected: { interpolation: "a\v", noInterpolation: "a\v" },
      },
      {
        input: "\va",
        expected: { interpolation: "\va", noInterpolation: "\va" },
      },
    ],
    "<form feed> (\\f)": [
      {
        input: "a\fb",
        expected: { interpolation: "a\fb", noInterpolation: "a\fb" },
      },
      {
        input: "a\fb\fc",
        expected: { interpolation: "a\fb\fc", noInterpolation: "a\fb\fc" },
      },
      {
        input: "a\f",
        expected: { interpolation: "a\f", noInterpolation: "a\f" },
      },
      {
        input: "\fa",
        expected: { interpolation: "\fa", noInterpolation: "\fa" },
      },
    ],
    "<carriage return> ('\\r')": [
      {
        input: "a\rb",
        expected: { interpolation: "ab", noInterpolation: "ab" },
      },
      {
        input: "a\rb\rc",
        expected: { interpolation: "abc", noInterpolation: "abc" },
      },
      {
        input: "\ra",
        expected: { interpolation: "a", noInterpolation: "a" },
      },
      {
        input: "a\r",
        expected: { interpolation: "a", noInterpolation: "a" },
      },
      {
        input: "a\r\nb",
        expected: { interpolation: "a b", noInterpolation: "a\r\nb" },
      },
    ],
    "<escape> ('\\u001B')": [
      {
        input: "a\u001Bb",
        expected: { interpolation: "ab", noInterpolation: "ab" },
      },
      {
        input: "a\u001Bb\u001Bc",
        expected: { interpolation: "abc", noInterpolation: "abc" },
      },
      {
        input: "\u001Ba",
        expected: { interpolation: "a", noInterpolation: "a" },
      },
      {
        input: "a\u001B",
        expected: { interpolation: "a", noInterpolation: "a" },
      },
    ],
    "<space> (' ')": [
      {
        input: "a b",
        expected: { interpolation: "a b", noInterpolation: "a b" },
      },
      {
        input: "a b c",
        expected: { interpolation: "a b c", noInterpolation: "a b c" },
      },
      {
        input: "a ",
        expected: { interpolation: "a ", noInterpolation: "a " },
      },
      {
        input: " a",
        expected: { interpolation: " a", noInterpolation: " a" },
      },
    ],
    "<next line> (\\u0085)": [
      {
        input: "a\u0085b",
        expected: {
          interpolation: "a\u0085b",
          noInterpolation: "a\u0085b",
        },
      },
      {
        input: "a\u0085b\u0085c",
        expected: {
          interpolation: "a\u0085b\u0085c",
          noInterpolation: "a\u0085b\u0085c",
        },
      },
      {
        input: "a\u0085",
        expected: {
          interpolation: "a\u0085",
          noInterpolation: "a\u0085",
        },
      },
      {
        input: "\u0085a",
        expected: {
          interpolation: "\u0085a",
          noInterpolation: "\u0085a",
        },
      },
    ],
    "<control sequence introducer> ('\\u009B')": [
      {
        input: "a\u009Bb",
        expected: { interpolation: "ab", noInterpolation: "ab" },
      },
      {
        input: "a\u009Bb\u009Bc",
        expected: { interpolation: "abc", noInterpolation: "abc" },
      },
      {
        input: "\u009Ba",
        expected: { interpolation: "a", noInterpolation: "a" },
      },
      {
        input: "a\u009B",
        expected: { interpolation: "a", noInterpolation: "a" },
      },
    ],
    "<no break space> (\\u00A0)": [
      {
        input: "a\u00A0b",
        expected: {
          interpolation: "a\u00A0b",
          noInterpolation: "a\u00A0b",
        },
      },
      {
        input: "a\u00A0b\u00A0c",
        expected: {
          interpolation: "a\u00A0b\u00A0c",
          noInterpolation: "a\u00A0b\u00A0c",
        },
      },
      {
        input: "a\u00A0",
        expected: {
          interpolation: "a\u00A0",
          noInterpolation: "a\u00A0",
        },
      },
      {
        input: "\u00A0a",
        expected: {
          interpolation: "\u00A0a",
          noInterpolation: "\u00A0a",
        },
      },
    ],
    "<en quad> (\\u2000)": [
      {
        input: "a\u2000b",
        expected: {
          interpolation: "a\u2000b",
          noInterpolation: "a\u2000b",
        },
      },
      {
        input: "a\u2000b\u2000c",
        expected: {
          interpolation: "a\u2000b\u2000c",
          noInterpolation: "a\u2000b\u2000c",
        },
      },
      {
        input: "a\u2000",
        expected: {
          interpolation: "a\u2000",
          noInterpolation: "a\u2000",
        },
      },
      {
        input: "\u2000a",
        expected: {
          interpolation: "\u2000a",
          noInterpolation: "\u2000a",
        },
      },
    ],
    "<em quad> (\\u2001)": [
      {
        input: "a\u2001b",
        expected: {
          interpolation: "a\u2001b",
          noInterpolation: "a\u2001b",
        },
      },
      {
        input: "a\u2001b\u2001c",
        expected: {
          interpolation: "a\u2001b\u2001c",
          noInterpolation: "a\u2001b\u2001c",
        },
      },
      {
        input: "a\u2001",
        expected: {
          interpolation: "a\u2001",
          noInterpolation: "a\u2001",
        },
      },
      {
        input: "\u2001a",
        expected: {
          interpolation: "\u2001a",
          noInterpolation: "\u2001a",
        },
      },
    ],
    "<en space> (\\u2002)": [
      {
        input: "a\u2002b",
        expected: {
          interpolation: "a\u2002b",
          noInterpolation: "a\u2002b",
        },
      },
      {
        input: "a\u2002b\u2002c",
        expected: {
          interpolation: "a\u2002b\u2002c",
          noInterpolation: "a\u2002b\u2002c",
        },
      },
      {
        input: "a\u2002",
        expected: {
          interpolation: "a\u2002",
          noInterpolation: "a\u2002",
        },
      },
      {
        input: "\u2002a",
        expected: {
          interpolation: "\u2002a",
          noInterpolation: "\u2002a",
        },
      },
    ],
    "<em space> (\\u2003)": [
      {
        input: "a\u2003b",
        expected: {
          interpolation: "a\u2003b",
          noInterpolation: "a\u2003b",
        },
      },
      {
        input: "a\u2003b\u2003c",
        expected: {
          interpolation: "a\u2003b\u2003c",
          noInterpolation: "a\u2003b\u2003c",
        },
      },
      {
        input: "a\u2003",
        expected: {
          interpolation: "a\u2003",
          noInterpolation: "a\u2003",
        },
      },
      {
        input: "\u2003a",
        expected: {
          interpolation: "\u2003a",
          noInterpolation: "\u2003a",
        },
      },
    ],
    "<three-per-em space> (\\u2004)": [
      {
        input: "a\u2004b",
        expected: {
          interpolation: "a\u2004b",
          noInterpolation: "a\u2004b",
        },
      },
      {
        input: "a\u2004b\u2004c",
        expected: {
          interpolation: "a\u2004b\u2004c",
          noInterpolation: "a\u2004b\u2004c",
        },
      },
      {
        input: "a\u2004",
        expected: {
          interpolation: "a\u2004",
          noInterpolation: "a\u2004",
        },
      },
      {
        input: "\u2004a",
        expected: {
          interpolation: "\u2004a",
          noInterpolation: "\u2004a",
        },
      },
    ],
    "<four-per-em space> (\\u2005)": [
      {
        input: "a\u2005b",
        expected: {
          interpolation: "a\u2005b",
          noInterpolation: "a\u2005b",
        },
      },
      {
        input: "a\u2005b\u2005c",
        expected: {
          interpolation: "a\u2005b\u2005c",
          noInterpolation: "a\u2005b\u2005c",
        },
      },
      {
        input: "a\u2005",
        expected: {
          interpolation: "a\u2005",
          noInterpolation: "a\u2005",
        },
      },
      {
        input: "\u2005a",
        expected: {
          interpolation: "\u2005a",
          noInterpolation: "\u2005a",
        },
      },
    ],
    "<six-per-em space> (\\u2006)": [
      {
        input: "a\u2006b",
        expected: {
          interpolation: "a\u2006b",
          noInterpolation: "a\u2006b",
        },
      },
      {
        input: "a\u2006b\u2006c",
        expected: {
          interpolation: "a\u2006b\u2006c",
          noInterpolation: "a\u2006b\u2006c",
        },
      },
      {
        input: "a\u2006",
        expected: {
          interpolation: "a\u2006",
          noInterpolation: "a\u2006",
        },
      },
      {
        input: "\u2006a",
        expected: {
          interpolation: "\u2006a",
          noInterpolation: "\u2006a",
        },
      },
    ],
    "<figure space> (\\u2007)": [
      {
        input: "a\u2007b",
        expected: {
          interpolation: "a\u2007b",
          noInterpolation: "a\u2007b",
        },
      },
      {
        input: "a\u2007b\u2007c",
        expected: {
          interpolation: "a\u2007b\u2007c",
          noInterpolation: "a\u2007b\u2007c",
        },
      },
      {
        input: "a\u2007",
        expected: {
          interpolation: "a\u2007",
          noInterpolation: "a\u2007",
        },
      },
      {
        input: "\u2007a",
        expected: {
          interpolation: "\u2007a",
          noInterpolation: "\u2007a",
        },
      },
    ],
    "<punctuation space> (\\u2008)": [
      {
        input: "a\u2008b",
        expected: {
          interpolation: "a\u2008b",
          noInterpolation: "a\u2008b",
        },
      },
      {
        input: "a\u2008b\u2008c",
        expected: {
          interpolation: "a\u2008b\u2008c",
          noInterpolation: "a\u2008b\u2008c",
        },
      },
      {
        input: "a\u2008",
        expected: {
          interpolation: "a\u2008",
          noInterpolation: "a\u2008",
        },
      },
      {
        input: "\u2008a",
        expected: {
          interpolation: "\u2008a",
          noInterpolation: "\u2008a",
        },
      },
    ],
    "<thin space> (\\u2009)": [
      {
        input: "a\u2009b",
        expected: {
          interpolation: "a\u2009b",
          noInterpolation: "a\u2009b",
        },
      },
      {
        input: "a\u2009b\u2009c",
        expected: {
          interpolation: "a\u2009b\u2009c",
          noInterpolation: "a\u2009b\u2009c",
        },
      },
      {
        input: "a\u2009",
        expected: {
          interpolation: "a\u2009",
          noInterpolation: "a\u2009",
        },
      },
      {
        input: "\u2009a",
        expected: {
          interpolation: "\u2009a",
          noInterpolation: "\u2009a",
        },
      },
    ],
    "<hair space> (\\u200A)": [
      {
        input: "a\u200Ab",
        expected: {
          interpolation: "a\u200Ab",
          noInterpolation: "a\u200Ab",
        },
      },
      {
        input: "a\u200Ab\u200Ac",
        expected: {
          interpolation: "a\u200Ab\u200Ac",
          noInterpolation: "a\u200Ab\u200Ac",
        },
      },
      {
        input: "a\u200A",
        expected: {
          interpolation: "a\u200A",
          noInterpolation: "a\u200A",
        },
      },
      {
        input: "\u200Aa",
        expected: {
          interpolation: "\u200Aa",
          noInterpolation: "\u200Aa",
        },
      },
    ],
    "<line separator> (\\u2028)": [
      {
        input: "a\u2028b",
        expected: {
          interpolation: "a\u2028b",
          noInterpolation: "a\u2028b",
        },
      },
      {
        input: "a\u2028b\u2028c",
        expected: {
          interpolation: "a\u2028b\u2028c",
          noInterpolation: "a\u2028b\u2028c",
        },
      },
      {
        input: "a\u2028",
        expected: {
          interpolation: "a\u2028",
          noInterpolation: "a\u2028",
        },
      },
      {
        input: "\u2028a",
        expected: {
          interpolation: "\u2028a",
          noInterpolation: "\u2028a",
        },
      },
    ],
    "<paragraph separator> (\\u2029)": [
      {
        input: "a\u2029b",
        expected: {
          interpolation: "a\u2029b",
          noInterpolation: "a\u2029b",
        },
      },
      {
        input: "a\u2029b\u2029c",
        expected: {
          interpolation: "a\u2029b\u2029c",
          noInterpolation: "a\u2029b\u2029c",
        },
      },
      {
        input: "a\u2029",
        expected: {
          interpolation: "a\u2029",
          noInterpolation: "a\u2029",
        },
      },
      {
        input: "\u2029a",
        expected: {
          interpolation: "\u2029a",
          noInterpolation: "\u2029a",
        },
      },
    ],
    "<narrow no-break space> (\\u202F)": [
      {
        input: "a\u202Fb",
        expected: {
          interpolation: "a\u202Fb",
          noInterpolation: "a\u202Fb",
        },
      },
      {
        input: "a\u202Fb\u202Fc",
        expected: {
          interpolation: "a\u202Fb\u202Fc",
          noInterpolation: "a\u202Fb\u202Fc",
        },
      },
      {
        input: "a\u202F",
        expected: {
          interpolation: "a\u202F",
          noInterpolation: "a\u202F",
        },
      },
      {
        input: "\u202Fa",
        expected: {
          interpolation: "\u202Fa",
          noInterpolation: "\u202Fa",
        },
      },
    ],
    "<medium mathematical space> (\\u205F)": [
      {
        input: "a\u205Fb",
        expected: {
          interpolation: "a\u205Fb",
          noInterpolation: "a\u205Fb",
        },
      },
      {
        input: "a\u205Fb\u205Fc",
        expected: {
          interpolation: "a\u205Fb\u205Fc",
          noInterpolation: "a\u205Fb\u205Fc",
        },
      },
      {
        input: "a\u205F",
        expected: {
          interpolation: "a\u205F",
          noInterpolation: "a\u205F",
        },
      },
      {
        input: "\u205Fa",
        expected: {
          interpolation: "\u205Fa",
          noInterpolation: "\u205Fa",
        },
      },
    ],
    "<ideographic space> (\\u3000)": [
      {
        input: "a\u3000b",
        expected: {
          interpolation: "a\u3000b",
          noInterpolation: "a\u3000b",
        },
      },
      {
        input: "a\u3000b\u3000c",
        expected: {
          interpolation: "a\u3000b\u3000c",
          noInterpolation: "a\u3000b\u3000c",
        },
      },
      {
        input: "a\u3000",
        expected: {
          interpolation: "a\u3000",
          noInterpolation: "a\u3000",
        },
      },
      {
        input: "\u3000a",
        expected: {
          interpolation: "\u3000a",
          noInterpolation: "\u3000a",
        },
      },
    ],
    "<zero width no-break space> (\\uFEFF)": [
      {
        input: "a\uFEFFb",
        expected: {
          interpolation: "a\uFEFFb",
          noInterpolation: "a\uFEFFb",
        },
      },
      {
        input: "a\uFEFFb\uFEFFc",
        expected: {
          interpolation: "a\uFEFFb\uFEFFc",
          noInterpolation: "a\uFEFFb\uFEFFc",
        },
      },
      {
        input: "a\uFEFF",
        expected: {
          interpolation: "a\uFEFF",
          noInterpolation: "a\uFEFF",
        },
      },
      {
        input: "\uFEFFa",
        expected: {
          interpolation: "\uFEFFa",
          noInterpolation: "\uFEFFa",
        },
      },
    ],
    'single quotes ("\'")': [
      {
        input: "a'b",
        expected: {
          interpolation: "a\\'b",
          noInterpolation: "a'b",
          quoted: "a'\\''b",
        },
      },
      {
        input: "a'b'c",
        expected: {
          interpolation: "a\\'b\\'c",
          noInterpolation: "a'b'c",
          quoted: "a'\\''b'\\''c",
        },
      },
    ],
    "double quotes ('\"')": [
      {
        input: 'a"b',
        expected: { interpolation: 'a\\"b', noInterpolation: 'a"b' },
      },
      {
        input: 'a"b"c',
        expected: { interpolation: 'a\\"b\\"c', noInterpolation: 'a"b"c' },
      },
    ],
    "backticks (')": [
      {
        input: "a`b",
        expected: { interpolation: "a\\`b", noInterpolation: "a`b" },
      },
      {
        input: "a`b`c",
        expected: { interpolation: "a\\`b\\`c", noInterpolation: "a`b`c" },
      },
    ],
    "tildes ('~')": [
      {
        input: "~a",
        expected: { interpolation: "\\~a", noInterpolation: "~a" },
      },
      {
        input: "~a~b",
        expected: { interpolation: "\\~a~b", noInterpolation: "~a~b" },
      },
      {
        input: "a~b",
        expected: { interpolation: "a~b", noInterpolation: "a~b" },
      },
      {
        input: "a~b~c",
        expected: { interpolation: "a~b~c", noInterpolation: "a~b~c" },
      },
      {
        input: "a~b=",
        expected: { interpolation: "a~b=", noInterpolation: "a~b=" },
      },
      {
        input: "a=~",
        expected: { interpolation: "a=~", noInterpolation: "a=~" },
      },
      {
        input: "a~b=~",
        expected: { interpolation: "a~b=~", noInterpolation: "a~b=~" },
      },
      {
        input: "a=b~",
        expected: { interpolation: "a=b~", noInterpolation: "a=b~" },
      },
      {
        input: "a=~b",
        expected: { interpolation: "a=~b", noInterpolation: "a=~b" },
      },
      {
        input: "a=:~",
        expected: { interpolation: "a=:~", noInterpolation: "a=:~" },
      },
      {
        input: "a=b:~",
        expected: { interpolation: "a=b:~", noInterpolation: "a=b:~" },
      },
      {
        input: "a=~:",
        expected: { interpolation: "a=~:", noInterpolation: "a=~:" },
      },
      {
        input: "a=~:b",
        expected: { interpolation: "a=~:b", noInterpolation: "a=~:b" },
      },
      {
        input: "a=~:~",
        expected: { interpolation: "a=~:~", noInterpolation: "a=~:~" },
      },
      {
        input: "a=:~:",
        expected: { interpolation: "a=:~:", noInterpolation: "a=:~:" },
      },
      {
        input: "a=:~:b",
        expected: { interpolation: "a=:~:b", noInterpolation: "a=:~:b" },
      },
      {
        input: "a=b:~:",
        expected: { interpolation: "a=b:~:", noInterpolation: "a=b:~:" },
      },
      {
        input: "a=b:~:c",
        expected: { interpolation: "a=b:~:c", noInterpolation: "a=b:~:c" },
      },
      {
        input: "a=~=",
        expected: { interpolation: "a=~=", noInterpolation: "a=~=" },
      },
      {
        input: "a=~-",
        expected: { interpolation: "a=~-", noInterpolation: "a=~-" },
      },
      {
        input: "a=~+",
        expected: { interpolation: "a=~+", noInterpolation: "a=~+" },
      },
      {
        input: "a=~/",
        expected: { interpolation: "a=~/", noInterpolation: "a=~/" },
      },
      {
        input: "a=~0",
        expected: { interpolation: "a=~0", noInterpolation: "a=~0" },
      },
      {
        input: "a=~ ",
        expected: { interpolation: "a=~ ", noInterpolation: "a=~ " },
      },
      {
        input: "a ~b",
        expected: { interpolation: "a \\~b", noInterpolation: "a ~b" },
      },
      {
        input: "a	~b",
        expected: { interpolation: "a	\\~b", noInterpolation: "a	~b" },
      },
    ],
    "hashtags ('#')": [
      {
        input: "#a",
        expected: { interpolation: "\\#a", noInterpolation: "#a" },
      },
      {
        input: "#a#b",
        expected: { interpolation: "\\#a#b", noInterpolation: "#a#b" },
      },
      {
        input: "a#b",
        expected: { interpolation: "a#b", noInterpolation: "a#b" },
      },
      {
        input: "a#b#c",
        expected: { interpolation: "a#b#c", noInterpolation: "a#b#c" },
      },
      {
        input: "a #b",
        expected: { interpolation: "a \\#b", noInterpolation: "a #b" },
      },
      {
        input: "a	#b",
        expected: { interpolation: "a	\\#b", noInterpolation: "a	#b" },
      },
    ],
    "dollar signs ('$')": [
      {
        input: "a$b",
        expected: { interpolation: "a\\$b", noInterpolation: "a$b" },
      },
      {
        input: "a$b$c",
        expected: { interpolation: "a\\$b\\$c", noInterpolation: "a$b$c" },
      },
    ],
    "ampersands ('&')": [
      {
        input: "a&b",
        expected: { interpolation: "a\\&b", noInterpolation: "a&b" },
      },
      {
        input: "a&b&c",
        expected: { interpolation: "a\\&b\\&c", noInterpolation: "a&b&c" },
      },
    ],
    "asterisks ('*')": [
      {
        input: "a*b",
        expected: { interpolation: "a\\*b", noInterpolation: "a*b" },
      },
      {
        input: "a*b*c",
        expected: { interpolation: "a\\*b\\*c", noInterpolation: "a*b*c" },
      },
    ],
    "equals ('=')": [
      {
        input: "=a",
        expected: { interpolation: "=a", noInterpolation: "=a" },
      },
      {
        input: "=a=b",
        expected: { interpolation: "=a=b", noInterpolation: "=a=b" },
      },
      {
        input: "a=b",
        expected: { interpolation: "a=b", noInterpolation: "a=b" },
      },
      {
        input: "a=b=c",
        expected: { interpolation: "a=b=c", noInterpolation: "a=b=c" },
      },
    ],
    "backslashes ('\\')": [
      {
        input: "a\\b",
        expected: { interpolation: "a\\\\b", noInterpolation: "a\\b" },
      },
      {
        input: "a\\b\\c",
        expected: { interpolation: "a\\\\b\\\\c", noInterpolation: "a\\b\\c" },
      },
    ],
    "pipes ('|')": [
      {
        input: "a|b",
        expected: { interpolation: "a\\|b", noInterpolation: "a|b" },
      },
      {
        input: "a|b|c",
        expected: { interpolation: "a\\|b\\|c", noInterpolation: "a|b|c" },
      },
    ],
    "semicolons (';')": [
      {
        input: "a;b",
        expected: { interpolation: "a\\;b", noInterpolation: "a;b" },
      },
      {
        input: "a;b;c",
        expected: { interpolation: "a\\;b\\;c", noInterpolation: "a;b;c" },
      },
    ],
    "question marks ('?')": [
      {
        input: "a?b",
        expected: { interpolation: "a\\?b", noInterpolation: "a?b" },
      },
      {
        input: "a?b?c",
        expected: { interpolation: "a\\?b\\?c", noInterpolation: "a?b?c" },
      },
    ],
    "parentheses ('(', ')')": [
      {
        input: "a(b",
        expected: { interpolation: "a\\(b", noInterpolation: "a(b" },
      },
      {
        input: "a)b",
        expected: { interpolation: "a\\)b", noInterpolation: "a)b" },
      },
      {
        input: "a(b(c",
        expected: { interpolation: "a\\(b\\(c", noInterpolation: "a(b(c" },
      },
      {
        input: "a)b)c",
        expected: { interpolation: "a\\)b\\)c", noInterpolation: "a)b)c" },
      },
      {
        input: "a(b)c",
        expected: { interpolation: "a\\(b\\)c", noInterpolation: "a(b)c" },
      },
      {
        input: "a(b,c)d",
        expected: { interpolation: "a\\(b,c\\)d", noInterpolation: "a(b,c)d" },
      },
    ],
    "square brackets ('[', ']')": [
      {
        input: "a[b",
        expected: { interpolation: "a[b", noInterpolation: "a[b" },
      },
      {
        input: "a]b",
        expected: { interpolation: "a]b", noInterpolation: "a]b" },
      },
      {
        input: "a[b[c",
        expected: { interpolation: "a[b[c", noInterpolation: "a[b[c" },
      },
      {
        input: "a]b]c",
        expected: { interpolation: "a]b]c", noInterpolation: "a]b]c" },
      },
      {
        input: "a[b]c",
        expected: { interpolation: "a[b]c", noInterpolation: "a[b]c" },
      },
      {
        input: "a[b,c]d",
        expected: { interpolation: "a[b,c]d", noInterpolation: "a[b,c]d" },
      },
    ],
    "curly brackets ('{', '}')": [
      {
        input: "a{b",
        expected: { interpolation: "a{b", noInterpolation: "a{b" },
      },
      {
        input: "a}b",
        expected: { interpolation: "a}b", noInterpolation: "a}b" },
      },
      {
        input: "a{b{c",
        expected: { interpolation: "a{b{c", noInterpolation: "a{b{c" },
      },
      {
        input: "a}b}c",
        expected: { interpolation: "a}b}c", noInterpolation: "a}b}c" },
      },
      {
        input: "a{b}c",
        expected: { interpolation: "a{b}c", noInterpolation: "a{b}c" },
      },
      {
        input: "a{b,c}d",
        expected: { interpolation: "a{b,c}d", noInterpolation: "a{b,c}d" },
      },
      {
        input: "a{,b}c",
        expected: { interpolation: "a{,b}c", noInterpolation: "a{,b}c" },
      },
      {
        input: "a{b,}c",
        expected: { interpolation: "a{b,}c", noInterpolation: "a{b,}c" },
      },
      {
        input: "a{bc,de}f",
        expected: {
          interpolation: "a{bc,de}f",
          noInterpolation: "a{bc,de}f",
        },
      },
      {
        input: "a{b,{c,d},e}f",
        expected: {
          interpolation: "a{b,{c,d},e}f",
          noInterpolation: "a{b,{c,d},e}f",
        },
      },
      {
        input: "a{0..2}b",
        expected: { interpolation: "a{0..2}b", noInterpolation: "a{0..2}b" },
      },
      {
        input: "a{\u000Db,c}d",
        expected: {
          interpolation: "a{b,c}d",
          noInterpolation: "a{b,c}d",
        },
      },
      {
        input: "a{\u2028b,c}d",
        expected: {
          interpolation: "a{\u2028b,c}d",
          noInterpolation: "a{\u2028b,c}d",
        },
      },
      {
        input: "a{\u2029b,c}d",
        expected: {
          interpolation: "a{\u2029b,c}d",
          noInterpolation: "a{\u2029b,c}d",
        },
      },
      {
        input: "a{b,c\u000D}d",
        expected: {
          interpolation: "a{b,c}d",
          noInterpolation: "a{b,c}d",
        },
      },
      {
        input: "a{b,c\u2028}d",
        expected: {
          interpolation: "a{b,c\u2028}d",
          noInterpolation: "a{b,c\u2028}d",
        },
      },
      {
        input: "a{b,c\u2029}d",
        expected: {
          interpolation: "a{b,c\u2029}d",
          noInterpolation: "a{b,c\u2029}d",
        },
      },
      {
        input: "a{\u000D0..2}b",
        expected: {
          interpolation: "a{0..2}b",
          noInterpolation: "a{0..2}b",
        },
      },
      {
        input: "a{\u20280..2}b",
        expected: {
          interpolation: "a{\u20280..2}b",
          noInterpolation: "a{\u20280..2}b",
        },
      },
      {
        input: "a{\u20290..2}b",
        expected: {
          interpolation: "a{\u20290..2}b",
          noInterpolation: "a{\u20290..2}b",
        },
      },
      {
        input: "a{0..2\u000D}b",
        expected: {
          interpolation: "a{0..2}b",
          noInterpolation: "a{0..2}b",
        },
      },
      {
        input: "a{0..2\u2028}b",
        expected: {
          interpolation: "a{0..2\u2028}b",
          noInterpolation: "a{0..2\u2028}b",
        },
      },
      {
        input: "a{0..2\u2029}b",
        expected: {
          interpolation: "a{0..2\u2029}b",
          noInterpolation: "a{0..2\u2029}b",
        },
      },
    ],
    "angle brackets ('<', '>')": [
      {
        input: "a<b",
        expected: { interpolation: "a\\<b", noInterpolation: "a<b" },
      },
      {
        input: "a>b",
        expected: { interpolation: "a\\>b", noInterpolation: "a>b" },
      },
      {
        input: "a<b<c",
        expected: { interpolation: "a\\<b\\<c", noInterpolation: "a<b<c" },
      },
      {
        input: "a>b>c",
        expected: { interpolation: "a\\>b\\>c", noInterpolation: "a>b>c" },
      },
      {
        input: "a<b>c",
        expected: { interpolation: "a\\<b\\>c", noInterpolation: "a<b>c" },
      },
    ],
  },
  [binZsh]: {
    "sample strings": [
      {
        input: "foobar",
        expected: { interpolation: "foobar", noInterpolation: "foobar" },
      },
      {
        input: "Hello world",
        expected: {
          interpolation: "Hello world",
          noInterpolation: "Hello world",
        },
      },
    ],
    "<null> (\\0)": [
      {
        input: "a\x00b",
        expected: { interpolation: "ab", noInterpolation: "ab" },
      },
      {
        input: "a\x00b\x00c",
        expected: { interpolation: "abc", noInterpolation: "abc" },
      },
    ],
    "<backspace> ('\\b')": [
      {
        input: "a\bb",
        expected: { interpolation: "ab", noInterpolation: "ab" },
      },
      {
        input: "a\bb\bc",
        expected: { interpolation: "abc", noInterpolation: "abc" },
      },
      {
        input: "\ba",
        expected: { interpolation: "a", noInterpolation: "a" },
      },
      {
        input: "a\b",
        expected: { interpolation: "a", noInterpolation: "a" },
      },
    ],
    "<character tabulation> (\\t)": [
      {
        input: "a\tb",
        expected: { interpolation: "a\tb", noInterpolation: "a\tb" },
      },
      {
        input: "a\tb\tc",
        expected: { interpolation: "a\tb\tc", noInterpolation: "a\tb\tc" },
      },
      {
        input: "a\t",
        expected: { interpolation: "a\t", noInterpolation: "a\t" },
      },
      {
        input: "\ta",
        expected: { interpolation: "\ta", noInterpolation: "\ta" },
      },
    ],
    "<end of line> ('\\n')": [
      {
        input: "a\nb",
        expected: { interpolation: "a b", noInterpolation: "a\nb" },
      },
      {
        input: "a\nb\nc",
        expected: { interpolation: "a b c", noInterpolation: "a\nb\nc" },
      },
      {
        input: "a\n",
        expected: { interpolation: "a ", noInterpolation: "a\n" },
      },
      {
        input: "\na",
        expected: { interpolation: " a", noInterpolation: "\na" },
      },
    ],
    "<line tabulation> (\\v)": [
      {
        input: "a\vb",
        expected: { interpolation: "a\vb", noInterpolation: "a\vb" },
      },
      {
        input: "a\vb\vc",
        expected: { interpolation: "a\vb\vc", noInterpolation: "a\vb\vc" },
      },
      {
        input: "a\v",
        expected: { interpolation: "a\v", noInterpolation: "a\v" },
      },
      {
        input: "\va",
        expected: { interpolation: "\va", noInterpolation: "\va" },
      },
    ],
    "<form feed> (\\f)": [
      {
        input: "a\fb",
        expected: { interpolation: "a\fb", noInterpolation: "a\fb" },
      },
      {
        input: "a\fb\fc",
        expected: { interpolation: "a\fb\fc", noInterpolation: "a\fb\fc" },
      },
      {
        input: "a\f",
        expected: { interpolation: "a\f", noInterpolation: "a\f" },
      },
      {
        input: "\fa",
        expected: { interpolation: "\fa", noInterpolation: "\fa" },
      },
    ],
    "<carriage return> ('\\r')": [
      {
        input: "a\rb",
        expected: { interpolation: "ab", noInterpolation: "ab" },
      },
      {
        input: "a\rb\rc",
        expected: { interpolation: "abc", noInterpolation: "abc" },
      },
      {
        input: "\ra",
        expected: { interpolation: "a", noInterpolation: "a" },
      },
      {
        input: "a\r",
        expected: { interpolation: "a", noInterpolation: "a" },
      },
      {
        input: "a\r\nb",
        expected: { interpolation: "a b", noInterpolation: "a\r\nb" },
      },
    ],
    "<escape> ('\\u001B')": [
      {
        input: "a\u001Bb",
        expected: { interpolation: "ab", noInterpolation: "ab" },
      },
      {
        input: "a\u001Bb\u001Bc",
        expected: { interpolation: "abc", noInterpolation: "abc" },
      },
      {
        input: "\u001Ba",
        expected: { interpolation: "a", noInterpolation: "a" },
      },
      {
        input: "a\u001B",
        expected: { interpolation: "a", noInterpolation: "a" },
      },
    ],
    "<space> (' ')": [
      {
        input: "a b",
        expected: { interpolation: "a b", noInterpolation: "a b" },
      },
      {
        input: "a b c",
        expected: { interpolation: "a b c", noInterpolation: "a b c" },
      },
      {
        input: "a ",
        expected: { interpolation: "a ", noInterpolation: "a " },
      },
      {
        input: " a",
        expected: { interpolation: " a", noInterpolation: " a" },
      },
    ],
    "<next line> (\\u0085)": [
      {
        input: "a\u0085b",
        expected: {
          interpolation: "a\u0085b",
          noInterpolation: "a\u0085b",
        },
      },
      {
        input: "a\u0085b\u0085c",
        expected: {
          interpolation: "a\u0085b\u0085c",
          noInterpolation: "a\u0085b\u0085c",
        },
      },
      {
        input: "a\u0085",
        expected: {
          interpolation: "a\u0085",
          noInterpolation: "a\u0085",
        },
      },
      {
        input: "\u0085a",
        expected: {
          interpolation: "\u0085a",
          noInterpolation: "\u0085a",
        },
      },
    ],
    "<control sequence introducer> ('\\u009B')": [
      {
        input: "a\u009Bb",
        expected: { interpolation: "ab", noInterpolation: "ab" },
      },
      {
        input: "a\u009Bb\u009Bc",
        expected: { interpolation: "abc", noInterpolation: "abc" },
      },
      {
        input: "\u009Ba",
        expected: { interpolation: "a", noInterpolation: "a" },
      },
      {
        input: "a\u009B",
        expected: { interpolation: "a", noInterpolation: "a" },
      },
    ],
    "<no break space> (\\u00A0)": [
      {
        input: "a\u00A0b",
        expected: {
          interpolation: "a\u00A0b",
          noInterpolation: "a\u00A0b",
        },
      },
      {
        input: "a\u00A0b\u00A0c",
        expected: {
          interpolation: "a\u00A0b\u00A0c",
          noInterpolation: "a\u00A0b\u00A0c",
        },
      },
      {
        input: "a\u00A0",
        expected: {
          interpolation: "a\u00A0",
          noInterpolation: "a\u00A0",
        },
      },
      {
        input: "\u00A0a",
        expected: {
          interpolation: "\u00A0a",
          noInterpolation: "\u00A0a",
        },
      },
    ],
    "<en quad> (\\u2000)": [
      {
        input: "a\u2000b",
        expected: {
          interpolation: "a\u2000b",
          noInterpolation: "a\u2000b",
        },
      },
      {
        input: "a\u2000b\u2000c",
        expected: {
          interpolation: "a\u2000b\u2000c",
          noInterpolation: "a\u2000b\u2000c",
        },
      },
      {
        input: "a\u2000",
        expected: {
          interpolation: "a\u2000",
          noInterpolation: "a\u2000",
        },
      },
      {
        input: "\u2000a",
        expected: {
          interpolation: "\u2000a",
          noInterpolation: "\u2000a",
        },
      },
    ],
    "<em quad> (\\u2001)": [
      {
        input: "a\u2001b",
        expected: {
          interpolation: "a\u2001b",
          noInterpolation: "a\u2001b",
        },
      },
      {
        input: "a\u2001b\u2001c",
        expected: {
          interpolation: "a\u2001b\u2001c",
          noInterpolation: "a\u2001b\u2001c",
        },
      },
      {
        input: "a\u2001",
        expected: {
          interpolation: "a\u2001",
          noInterpolation: "a\u2001",
        },
      },
      {
        input: "\u2001a",
        expected: {
          interpolation: "\u2001a",
          noInterpolation: "\u2001a",
        },
      },
    ],
    "<en space> (\\u2002)": [
      {
        input: "a\u2002b",
        expected: {
          interpolation: "a\u2002b",
          noInterpolation: "a\u2002b",
        },
      },
      {
        input: "a\u2002b\u2002c",
        expected: {
          interpolation: "a\u2002b\u2002c",
          noInterpolation: "a\u2002b\u2002c",
        },
      },
      {
        input: "a\u2002",
        expected: {
          interpolation: "a\u2002",
          noInterpolation: "a\u2002",
        },
      },
      {
        input: "\u2002a",
        expected: {
          interpolation: "\u2002a",
          noInterpolation: "\u2002a",
        },
      },
    ],
    "<em space> (\\u2003)": [
      {
        input: "a\u2003b",
        expected: {
          interpolation: "a\u2003b",
          noInterpolation: "a\u2003b",
        },
      },
      {
        input: "a\u2003b\u2003c",
        expected: {
          interpolation: "a\u2003b\u2003c",
          noInterpolation: "a\u2003b\u2003c",
        },
      },
      {
        input: "a\u2003",
        expected: {
          interpolation: "a\u2003",
          noInterpolation: "a\u2003",
        },
      },
      {
        input: "\u2003a",
        expected: {
          interpolation: "\u2003a",
          noInterpolation: "\u2003a",
        },
      },
    ],
    "<three-per-em space> (\\u2004)": [
      {
        input: "a\u2004b",
        expected: {
          interpolation: "a\u2004b",
          noInterpolation: "a\u2004b",
        },
      },
      {
        input: "a\u2004b\u2004c",
        expected: {
          interpolation: "a\u2004b\u2004c",
          noInterpolation: "a\u2004b\u2004c",
        },
      },
      {
        input: "a\u2004",
        expected: {
          interpolation: "a\u2004",
          noInterpolation: "a\u2004",
        },
      },
      {
        input: "\u2004a",
        expected: {
          interpolation: "\u2004a",
          noInterpolation: "\u2004a",
        },
      },
    ],
    "<four-per-em space> (\\u2005)": [
      {
        input: "a\u2005b",
        expected: {
          interpolation: "a\u2005b",
          noInterpolation: "a\u2005b",
        },
      },
      {
        input: "a\u2005b\u2005c",
        expected: {
          interpolation: "a\u2005b\u2005c",
          noInterpolation: "a\u2005b\u2005c",
        },
      },
      {
        input: "a\u2005",
        expected: {
          interpolation: "a\u2005",
          noInterpolation: "a\u2005",
        },
      },
      {
        input: "\u2005a",
        expected: {
          interpolation: "\u2005a",
          noInterpolation: "\u2005a",
        },
      },
    ],
    "<six-per-em space> (\\u2006)": [
      {
        input: "a\u2006b",
        expected: {
          interpolation: "a\u2006b",
          noInterpolation: "a\u2006b",
        },
      },
      {
        input: "a\u2006b\u2006c",
        expected: {
          interpolation: "a\u2006b\u2006c",
          noInterpolation: "a\u2006b\u2006c",
        },
      },
      {
        input: "a\u2006",
        expected: {
          interpolation: "a\u2006",
          noInterpolation: "a\u2006",
        },
      },
      {
        input: "\u2006a",
        expected: {
          interpolation: "\u2006a",
          noInterpolation: "\u2006a",
        },
      },
    ],
    "<figure space> (\\u2007)": [
      {
        input: "a\u2007b",
        expected: {
          interpolation: "a\u2007b",
          noInterpolation: "a\u2007b",
        },
      },
      {
        input: "a\u2007b\u2007c",
        expected: {
          interpolation: "a\u2007b\u2007c",
          noInterpolation: "a\u2007b\u2007c",
        },
      },
      {
        input: "a\u2007",
        expected: {
          interpolation: "a\u2007",
          noInterpolation: "a\u2007",
        },
      },
      {
        input: "\u2007a",
        expected: {
          interpolation: "\u2007a",
          noInterpolation: "\u2007a",
        },
      },
    ],
    "<punctuation space> (\\u2008)": [
      {
        input: "a\u2008b",
        expected: {
          interpolation: "a\u2008b",
          noInterpolation: "a\u2008b",
        },
      },
      {
        input: "a\u2008b\u2008c",
        expected: {
          interpolation: "a\u2008b\u2008c",
          noInterpolation: "a\u2008b\u2008c",
        },
      },
      {
        input: "a\u2008",
        expected: {
          interpolation: "a\u2008",
          noInterpolation: "a\u2008",
        },
      },
      {
        input: "\u2008a",
        expected: {
          interpolation: "\u2008a",
          noInterpolation: "\u2008a",
        },
      },
    ],
    "<thin space> (\\u2009)": [
      {
        input: "a\u2009b",
        expected: {
          interpolation: "a\u2009b",
          noInterpolation: "a\u2009b",
        },
      },
      {
        input: "a\u2009b\u2009c",
        expected: {
          interpolation: "a\u2009b\u2009c",
          noInterpolation: "a\u2009b\u2009c",
        },
      },
      {
        input: "a\u2009",
        expected: {
          interpolation: "a\u2009",
          noInterpolation: "a\u2009",
        },
      },
      {
        input: "\u2009a",
        expected: {
          interpolation: "\u2009a",
          noInterpolation: "\u2009a",
        },
      },
    ],
    "<hair space> (\\u200A)": [
      {
        input: "a\u200Ab",
        expected: {
          interpolation: "a\u200Ab",
          noInterpolation: "a\u200Ab",
        },
      },
      {
        input: "a\u200Ab\u200Ac",
        expected: {
          interpolation: "a\u200Ab\u200Ac",
          noInterpolation: "a\u200Ab\u200Ac",
        },
      },
      {
        input: "a\u200A",
        expected: {
          interpolation: "a\u200A",
          noInterpolation: "a\u200A",
        },
      },
      {
        input: "\u200Aa",
        expected: {
          interpolation: "\u200Aa",
          noInterpolation: "\u200Aa",
        },
      },
    ],
    "<line separator> (\\u2028)": [
      {
        input: "a\u2028b",
        expected: {
          interpolation: "a\u2028b",
          noInterpolation: "a\u2028b",
        },
      },
      {
        input: "a\u2028b\u2028c",
        expected: {
          interpolation: "a\u2028b\u2028c",
          noInterpolation: "a\u2028b\u2028c",
        },
      },
      {
        input: "a\u2028",
        expected: {
          interpolation: "a\u2028",
          noInterpolation: "a\u2028",
        },
      },
      {
        input: "\u2028a",
        expected: {
          interpolation: "\u2028a",
          noInterpolation: "\u2028a",
        },
      },
    ],
    "<paragraph separator> (\\u2029)": [
      {
        input: "a\u2029b",
        expected: {
          interpolation: "a\u2029b",
          noInterpolation: "a\u2029b",
        },
      },
      {
        input: "a\u2029b\u2029c",
        expected: {
          interpolation: "a\u2029b\u2029c",
          noInterpolation: "a\u2029b\u2029c",
        },
      },
      {
        input: "a\u2029",
        expected: {
          interpolation: "a\u2029",
          noInterpolation: "a\u2029",
        },
      },
      {
        input: "\u2029a",
        expected: {
          interpolation: "\u2029a",
          noInterpolation: "\u2029a",
        },
      },
    ],
    "<narrow no-break space> (\\u202F)": [
      {
        input: "a\u202Fb",
        expected: {
          interpolation: "a\u202Fb",
          noInterpolation: "a\u202Fb",
        },
      },
      {
        input: "a\u202Fb\u202Fc",
        expected: {
          interpolation: "a\u202Fb\u202Fc",
          noInterpolation: "a\u202Fb\u202Fc",
        },
      },
      {
        input: "a\u202F",
        expected: {
          interpolation: "a\u202F",
          noInterpolation: "a\u202F",
        },
      },
      {
        input: "\u202Fa",
        expected: {
          interpolation: "\u202Fa",
          noInterpolation: "\u202Fa",
        },
      },
    ],
    "<medium mathematical space> (\\u205F)": [
      {
        input: "a\u205Fb",
        expected: {
          interpolation: "a\u205Fb",
          noInterpolation: "a\u205Fb",
        },
      },
      {
        input: "a\u205Fb\u205Fc",
        expected: {
          interpolation: "a\u205Fb\u205Fc",
          noInterpolation: "a\u205Fb\u205Fc",
        },
      },
      {
        input: "a\u205F",
        expected: {
          interpolation: "a\u205F",
          noInterpolation: "a\u205F",
        },
      },
      {
        input: "\u205Fa",
        expected: {
          interpolation: "\u205Fa",
          noInterpolation: "\u205Fa",
        },
      },
    ],
    "<ideographic space> (\\u3000)": [
      {
        input: "a\u3000b",
        expected: {
          interpolation: "a\u3000b",
          noInterpolation: "a\u3000b",
        },
      },
      {
        input: "a\u3000b\u3000c",
        expected: {
          interpolation: "a\u3000b\u3000c",
          noInterpolation: "a\u3000b\u3000c",
        },
      },
      {
        input: "a\u3000",
        expected: {
          interpolation: "a\u3000",
          noInterpolation: "a\u3000",
        },
      },
      {
        input: "\u3000a",
        expected: {
          interpolation: "\u3000a",
          noInterpolation: "\u3000a",
        },
      },
    ],
    "<zero width no-break space> (\\uFEFF)": [
      {
        input: "a\uFEFFb",
        expected: {
          interpolation: "a\uFEFFb",
          noInterpolation: "a\uFEFFb",
        },
      },
      {
        input: "a\uFEFFb\uFEFFc",
        expected: {
          interpolation: "a\uFEFFb\uFEFFc",
          noInterpolation: "a\uFEFFb\uFEFFc",
        },
      },
      {
        input: "a\uFEFF",
        expected: {
          interpolation: "a\uFEFF",
          noInterpolation: "a\uFEFF",
        },
      },
      {
        input: "\uFEFFa",
        expected: {
          interpolation: "\uFEFFa",
          noInterpolation: "\uFEFFa",
        },
      },
    ],
    'single quotes ("\'")': [
      {
        input: "a'b",
        expected: {
          interpolation: "a\\'b",
          noInterpolation: "a'b",
          quoted: "a'\\''b",
        },
      },
      {
        input: "a'b'c",
        expected: {
          interpolation: "a\\'b\\'c",
          noInterpolation: "a'b'c",
          quoted: "a'\\''b'\\''c",
        },
      },
    ],
    "double quotes ('\"')": [
      {
        input: 'a"b',
        expected: { interpolation: 'a\\"b', noInterpolation: 'a"b' },
      },
      {
        input: 'a"b"c',
        expected: { interpolation: 'a\\"b\\"c', noInterpolation: 'a"b"c' },
      },
    ],
    "backticks (')": [
      {
        input: "a`b",
        expected: { interpolation: "a\\`b", noInterpolation: "a`b" },
      },
      {
        input: "a`b`c",
        expected: { interpolation: "a\\`b\\`c", noInterpolation: "a`b`c" },
      },
    ],
    "tildes ('~')": [
      {
        input: "~a",
        expected: { interpolation: "\\~a", noInterpolation: "~a" },
      },
      {
        input: "~a~b",
        expected: { interpolation: "\\~a~b", noInterpolation: "~a~b" },
      },
      {
        input: "a~b",
        expected: { interpolation: "a~b", noInterpolation: "a~b" },
      },
      {
        input: "a~b~c",
        expected: { interpolation: "a~b~c", noInterpolation: "a~b~c" },
      },
      {
        input: "a ~b",
        expected: { interpolation: "a \\~b", noInterpolation: "a ~b" },
      },
      {
        input: "a	~b",
        expected: { interpolation: "a	\\~b", noInterpolation: "a	~b" },
      },
    ],
    "hashtags ('#')": [
      {
        input: "#a",
        expected: { interpolation: "\\#a", noInterpolation: "#a" },
      },
      {
        input: "#a#b",
        expected: { interpolation: "\\#a#b", noInterpolation: "#a#b" },
      },
      {
        input: "a#b",
        expected: { interpolation: "a#b", noInterpolation: "a#b" },
      },
      {
        input: "a#b#c",
        expected: { interpolation: "a#b#c", noInterpolation: "a#b#c" },
      },
      {
        input: "a #b",
        expected: { interpolation: "a \\#b", noInterpolation: "a #b" },
      },
      {
        input: "a	#b",
        expected: { interpolation: "a	\\#b", noInterpolation: "a	#b" },
      },
    ],
    "dollar signs ('$')": [
      {
        input: "a$b",
        expected: { interpolation: "a\\$b", noInterpolation: "a$b" },
      },
      {
        input: "a$b$c",
        expected: { interpolation: "a\\$b\\$c", noInterpolation: "a$b$c" },
      },
    ],
    "ampersands ('&')": [
      {
        input: "a&b",
        expected: { interpolation: "a\\&b", noInterpolation: "a&b" },
      },
      {
        input: "a&b&c",
        expected: { interpolation: "a\\&b\\&c", noInterpolation: "a&b&c" },
      },
    ],
    "asterisks ('*')": [
      {
        input: "a*b",
        expected: { interpolation: "a\\*b", noInterpolation: "a*b" },
      },
      {
        input: "a*b*c",
        expected: { interpolation: "a\\*b\\*c", noInterpolation: "a*b*c" },
      },
    ],
    "equals ('=')": [
      {
        input: "=a",
        expected: { interpolation: "\\=a", noInterpolation: "=a" },
      },
      {
        input: "=a=b",
        expected: { interpolation: "\\=a=b", noInterpolation: "=a=b" },
      },
      {
        input: "a=b",
        expected: { interpolation: "a=b", noInterpolation: "a=b" },
      },
      {
        input: "a=b=c",
        expected: { interpolation: "a=b=c", noInterpolation: "a=b=c" },
      },
      {
        input: "a =b",
        expected: { interpolation: "a \\=b", noInterpolation: "a =b" },
      },
      {
        input: "a	=b",
        expected: { interpolation: "a	\\=b", noInterpolation: "a	=b" },
      },
    ],
    "backslashes ('\\')": [
      {
        input: "a\\b",
        expected: { interpolation: "a\\\\b", noInterpolation: "a\\b" },
      },
      {
        input: "a\\b\\c",
        expected: { interpolation: "a\\\\b\\\\c", noInterpolation: "a\\b\\c" },
      },
    ],
    "pipes ('|')": [
      {
        input: "a|b",
        expected: { interpolation: "a\\|b", noInterpolation: "a|b" },
      },
      {
        input: "a|b|c",
        expected: { interpolation: "a\\|b\\|c", noInterpolation: "a|b|c" },
      },
    ],
    "semicolons (';')": [
      {
        input: "a;b",
        expected: { interpolation: "a\\;b", noInterpolation: "a;b" },
      },
      {
        input: "a;b;c",
        expected: { interpolation: "a\\;b\\;c", noInterpolation: "a;b;c" },
      },
    ],
    "question marks ('?')": [
      {
        input: "a?b",
        expected: { interpolation: "a\\?b", noInterpolation: "a?b" },
      },
      {
        input: "a?b?c",
        expected: { interpolation: "a\\?b\\?c", noInterpolation: "a?b?c" },
      },
    ],
    "parentheses ('(', ')')": [
      {
        input: "a(b",
        expected: { interpolation: "a\\(b", noInterpolation: "a(b" },
      },
      {
        input: "a)b",
        expected: { interpolation: "a\\)b", noInterpolation: "a)b" },
      },
      {
        input: "a(b(c",
        expected: { interpolation: "a\\(b\\(c", noInterpolation: "a(b(c" },
      },
      {
        input: "a)b)c",
        expected: { interpolation: "a\\)b\\)c", noInterpolation: "a)b)c" },
      },
      {
        input: "a(b)c",
        expected: { interpolation: "a\\(b\\)c", noInterpolation: "a(b)c" },
      },
      {
        input: "a(b,c)d",
        expected: { interpolation: "a\\(b,c\\)d", noInterpolation: "a(b,c)d" },
      },
    ],
    "square brackets ('[', ']')": [
      {
        input: "a[b",
        expected: { interpolation: "a\\[b", noInterpolation: "a[b" },
      },
      {
        input: "a]b",
        expected: { interpolation: "a\\]b", noInterpolation: "a]b" },
      },
      {
        input: "a[b[c",
        expected: { interpolation: "a\\[b\\[c", noInterpolation: "a[b[c" },
      },
      {
        input: "a]b]c",
        expected: { interpolation: "a\\]b\\]c", noInterpolation: "a]b]c" },
      },
      {
        input: "a[b]c",
        expected: { interpolation: "a\\[b\\]c", noInterpolation: "a[b]c" },
      },
      {
        input: "a[b,c]d",
        expected: { interpolation: "a\\[b,c\\]d", noInterpolation: "a[b,c]d" },
      },
    ],
    "curly brackets ('{', '}')": [
      {
        input: "a{b",
        expected: { interpolation: "a\\{b", noInterpolation: "a{b" },
      },
      {
        input: "a}b",
        expected: { interpolation: "a\\}b", noInterpolation: "a}b" },
      },
      {
        input: "a{b{c",
        expected: { interpolation: "a\\{b\\{c", noInterpolation: "a{b{c" },
      },
      {
        input: "a}b}c",
        expected: { interpolation: "a\\}b\\}c", noInterpolation: "a}b}c" },
      },
      {
        input: "a{b}c",
        expected: { interpolation: "a\\{b\\}c", noInterpolation: "a{b}c" },
      },
      {
        input: "a{b,c}d",
        expected: { interpolation: "a\\{b,c\\}d", noInterpolation: "a{b,c}d" },
      },
      {
        input: "a{b,{c,d},e}f",
        expected: {
          interpolation: "a\\{b,\\{c,d\\},e\\}f",
          noInterpolation: "a{b,{c,d},e}f",
        },
      },
      {
        input: "a{0..2}b",
        expected: {
          interpolation: "a\\{0..2\\}b",
          noInterpolation: "a{0..2}b",
        },
      },
    ],
    "angle brackets ('<', '>')": [
      {
        input: "a<b",
        expected: { interpolation: "a\\<b", noInterpolation: "a<b" },
      },
      {
        input: "a>b",
        expected: { interpolation: "a\\>b", noInterpolation: "a>b" },
      },
      {
        input: "a<b<c",
        expected: { interpolation: "a\\<b\\<c", noInterpolation: "a<b<c" },
      },
      {
        input: "a>b>c",
        expected: { interpolation: "a\\>b\\>c", noInterpolation: "a>b>c" },
      },
      {
        input: "a<b>c",
        expected: { interpolation: "a\\<b\\>c", noInterpolation: "a<b>c" },
      },
    ],
  },
};

export const quote = {
  [binDash]: {
    "sample strings": [
      {
        input: "a",
        expected: { escaped: "'a'", notEscaped: "'a'" },
      },
    ],
  },
  [binBash]: {
    "sample strings": [
      {
        input: "a",
        expected: { escaped: "'a'", notEscaped: "'a'" },
      },
    ],
  },
  [binZsh]: {
    "sample strings": [
      {
        input: "a",
        expected: { escaped: "'a'", notEscaped: "'a'" },
      },
    ],
  },
};
