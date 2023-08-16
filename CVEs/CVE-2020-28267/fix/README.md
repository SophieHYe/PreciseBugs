set [![License](https://img.shields.io/npm/l/@strikeentco/set.svg)](https://github.com/strikeentco/set/blob/master/LICENSE)  [![npm](https://img.shields.io/npm/v/@strikeentco/set.svg)](https://www.npmjs.com/package/@strikeentco/set)
==========
[![Build Status](https://travis-ci.org/strikeentco/set.svg)](https://travis-ci.org/strikeentco/set)  [![node](https://img.shields.io/node/v/@strikeentco/set.svg)](https://www.npmjs.com/package/@strikeentco/set) [![Test Coverage](https://api.codeclimate.com/v1/badges/450e530044d31f690dc5/test_coverage)](https://codeclimate.com/github/strikeentco/set/test_coverage)

One of the smallest (*31 sloc*) and most effective implementations of setting a nested value on an object.

# Usage

```sh
$ npm install @strikeentco/set --save
```

```javascript
const set = require('@strikeentco/set');

set({ a: { b: 'c' } }, 'a.b', 'd');
//=> { a: { b: 'd' } }

set({ a: { b: ['c', 'd'] } }, 'a.b.1', 'e');
//=> { a: { b: ['c', 'e'] } }

set({ a: { b: ['c', 'd'] } }, ['a', 'b'], 'c');
//=> { a: { b: 'c' } }

set({ a: { b: 'c' } }, 'a.b.c.d', 'e');
//=> { a: { b: { c: { d: 'e' } } } }

set({ a: { b: 'c' } }, 'a:b', 'd', ':');
//=> { a: { b: 'd' } }
```
## API

### set(obj, path, val, [separator])

#### Params:
* **obj** (*Object*) - Source object.
* **path** (*String|Array*) - String or array with path.
* **val** (*Any*) - Value to set.
* **[separator]** (*String*) - `.` by default.

## License

The MIT License (MIT)<br/>
Copyright (c) 2018-present Alexey Bystrov
