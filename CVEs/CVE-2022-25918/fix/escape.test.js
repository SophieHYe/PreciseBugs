/**
 * @overview Contains unit tests for the escaping functionality on Unix systems.
 * @license Unlicense
 */

import test from "ava";

import { fixtures, macros } from "./_.js";

import * as unix from "../../../src/unix.js";

Object.entries(fixtures.escape).forEach(([shellName, scenarios]) => {
  const cases = Object.values(scenarios).flat();

  cases.forEach(({ input, expected }) => {
    test(macros.escape, {
      expected: expected.noInterpolation,
      input,
      interpolation: false,
      platform: unix,
      quoted: false,
      shellName,
    });
  });

  cases.forEach(({ input, expected }) => {
    test(macros.escape, {
      expected: expected.interpolation,
      input,
      interpolation: true,
      platform: unix,
      quoted: false,
      shellName,
    });
  });

  cases.forEach(({ input, expected }) => {
    test(macros.escape, {
      expected: expected.quoted || expected.noInterpolation,
      input,
      interpolation: false,
      platform: unix,
      quoted: true,
      shellName,
    });
  });
});

fixtures.redos().forEach((s, i) => {
  test(`bash, ReDoS #${i}`, (t) => {
    const escape = unix.getEscapeFunction("bash");
    escape(s, true, false);
    t.pass();
  });

  test(`dash, ReDoS #${i}`, (t) => {
    const escape = unix.getEscapeFunction("dash");
    escape(s, true, false);
    t.pass();
  });

  test(`zsh, ReDoS #${i}`, (t) => {
    const escape = unix.getEscapeFunction("zsh");
    escape(s, true, false);
    t.pass();
  });
});

test(macros.unsupportedShell, { fn: unix.getEscapeFunction });
