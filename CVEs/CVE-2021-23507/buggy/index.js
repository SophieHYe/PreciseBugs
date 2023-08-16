'use strict';

// https://github.com/jonschlinkert/assign-deep/commit/90bf1c551d05940898168d04066bbf15060f50cc
var isValidKey = function (key) {
  return key !== '__proto__' && key !== 'constructor' && key !== 'prototype';
};

var setPath = function (obj, path, value, delimiter) {
  var arr;
  var key;
  if (!obj || typeof obj !== 'object') {
    obj = {};
  }
  if (typeof path === 'string') {
    path = path.split(delimiter || '.');
  }
  if (Array.isArray(path) && path.length > 0) {
    arr = path;
    key = arr[0];
    if (isValidKey(key)) {
      if (arr.length > 1) {
        arr.shift();
        obj[key] = setPath(obj[key], arr, value, delimiter);
      } else {
        obj[key] = value;
      }
    }
  }
  return obj;
};

module.exports = exports = setPath;
