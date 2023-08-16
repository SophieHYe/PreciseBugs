# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog], and this project adheres to [Semantic
Versioning].

## [Unreleased]

- _No changes yet_

## [1.1.2] - 2021-01-07

- Add in-source JSDoc documentation.

## [1.1.1] - 2020-12-30

- Improve error message when a value is not stringable.

## [1.1.0] - 2020-12-22

- Add `escapeAll` function to escape an array of arguments.
- Recommend usage of `escapeAll` when using `fork`/`spawn`/`execFile`.

## [1.0.0] - 2020-12-10

- (!) Remove ability to call `shescape()` directly.
- (!) Automatically convert input to array in `quoteAll()`.
- Fix numbering in documentation's "Install" section.

## [0.4.1] - 2020-12-09

- Support non-string values as arguments.

## [0.4.0] - 2020-12-08

- Add `quoteAll` function to quote and escape an array of arguments.
- Create website with full documentation ([link](https://ericcornelissen.github.io/shescape/)).

## [0.3.1] - 2020-12-07

- Deprecate calling `shescape()` directly.

## [0.3.0] - 2020-12-07

- Add `escape` function to escape an argument (same as `shescape()`).
- Add `quote` function to quote and escape an argument.

## [0.2.1] - 2020-11-07

- Fix missing released files.

## [0.2.0] - 2020-11-07

- Add support for escaping of double quotes on Windows.

## [0.1.0] - 2020-11-06

- Escape individual shell arguments.

[keep a changelog]: https://keepachangelog.com/en/1.0.0/
[semantic versioning]: https://semver.org/spec/v2.0.0.html
