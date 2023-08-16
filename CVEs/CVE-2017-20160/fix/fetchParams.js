'use strict';

var _ = require('lodash');

var TYPE_DELIMETER = ':'
  , PATH_REGEX = /^{[A-Za-z0-9_]+}$/
  , DEFAULT_VAL_DELIMETER = '|='
  , extraOption;

function getValue(req, keyName) {
  var value;

  if (req.params && req.params[keyName] !== undefined) {
    value = req.params[keyName];
  } else if (req.body && req.body[keyName] !== undefined) {
    value = req.body[keyName];
  } else if (req.query && req.query[keyName] !== undefined) {
    value = req.query[keyName];
  }

  if (Array.isArray(value)) {
    value = value[value.length - 1];
  }

  return value;
}

function getPath(req, keyName) {
  return req.params[keyName];
}

function getExtra(req, keyName) {
  return req[extraOption[keyName]];
}

// type:keyName || type:{keyName}
function getRequiredKeyInfo(param_expression) {
  var keyInfo = {}
    , type_tokenized = param_expression.split(TYPE_DELIMETER)
    , len1 = type_tokenized.length
    , pathKey
    , keyName
    , err;

  if (len1 === 1) {
    keyInfo.type = 'string';

    pathKey = !(type_tokenized[0].match(PATH_REGEX) === null);
    keyName = pathKey ? type_tokenized[0].replace('{', '').replace('}', '') : type_tokenized[0];
  } else if (len1 === 2) {
    keyInfo.type = type_tokenized[0];

    pathKey = !(type_tokenized[1].match(PATH_REGEX) === null);
    keyName = pathKey ? type_tokenized[1].replace('{', '').replace('}', '') : type_tokenized[1];
  } else {
    err = new Error('Invalid required parameter expression. Your Input: ' + param_expression);
    err.code = 400;

    throw err;
  }

  keyInfo.pathKey = pathKey;
  keyInfo.keyName = keyName;

  return keyInfo;
}

function getOptionalKeyInfo(param_expression) {
  var keyInfo = {}
    , type_tokenized = param_expression.split(TYPE_DELIMETER)
    , len1 = type_tokenized.length
    , len2
    , val_tokenized
    , err;

  if (len1 === 1) { //no type, set type as string
    keyInfo.type = 'string';
    val_tokenized = type_tokenized[0].split(DEFAULT_VAL_DELIMETER);
  } else if (len1 === 2) {
    keyInfo.type = type_tokenized[0];
    val_tokenized = type_tokenized[1].split(DEFAULT_VAL_DELIMETER);
  } else {
    err = new Error('Invalid parameter expression. Your Input: ' + param_expression);
    err.code = 400;

    throw err;
  }

  len2 = val_tokenized.length;

  keyInfo.keyName = val_tokenized[0];
  if (len2 === 2) keyInfo.defaultVal = val_tokenized[1];

  if (len2 > 2) {
    err = new Error('Invalid parameter expression. expression have to include \''
      + DEFAULT_VAL_DELIMETER + '\' delimeter one or zero. Your Input: ' + param_expression);
    err.code = 400;

    throw err;
  }

  return keyInfo;
}

//type:key_name|=default_value
function getOptionalParams(req, option_expressions) {
  var options = {}
    , getFunc = getValue
    , err
    , keyInfo
    , key
    , val;

  //possible expression : {type}:keyName|={defaultValue}
  for (var i = 0, li = option_expressions.length; i < li; i++) {
    try {
      keyInfo = getOptionalKeyInfo(option_expressions[i]);
      key = keyInfo.keyName;
    } catch (e) {
      err = e;
      break;
    }

    val = getFunc(req, key);

    var typeMap = {
      int: parseInt,
      float: parseFloat,
      number: parseFloat
    };

    var idx = Object.keys(typeMap).indexOf(keyInfo.type);
    if (idx >= 0) {
      options[key] = typeMap[keyInfo.type](val);
    }

    if (keyInfo.type === 'number') {
      if (val !== undefined && val !== '')
        options[key] = parseFloat(val);
      else if (keyInfo.defaultVal)
        options[key] = parseFloat(keyInfo.defaultVal);
      else
        continue;

      if (isNaN(options[key])) {
        err = new Error('The parameter value is not a number : ' + key);
        err.code = 400;
        break;
      }
    } else {
      options[key] = val !== undefined ? val : keyInfo.defaultVal;
    }

  }

  return {
    err: err,
    params: options
  };
}

function getDefaultRequestInfo(req, extraOption) {
  var options = {};

  for (var key in extraOption) {
    if (extraOption.hasOwnProperty(key)) {
      if (key == 'access-country') {
        var geoCountry = req.headers['x-fetcher-geoinfo'] && req.headers['x-fetcher-geoinfo'].country
          , imsiCountry = req.headers['x-fetcher-imsi'] && req.headers['x-fetcher-imsi'][0] && req.headers['x-fetcher-imsi'][0].country_code;

        options[key] = geoCountry || imsiCountry;
        continue;
      }

      var extraOptionList = extraOption[key].split('.')
        , value = req;

      extraOptionList.forEach(function(extraOpt) {
        value = value[extraOpt];
      });

      options[key] = value;
    }
  }

  return {
    params: options
  }
}

function requiredParameter(req, required_expressions) {
  var options = {}
    , getFunc
    , err
    , key
    , val;

  for (var i = 0, li = required_expressions.length; i < li; i++) {
    var keyInfo;
    try {
      keyInfo = getRequiredKeyInfo(required_expressions[i]);
      key = keyInfo.keyName;
    } catch (e) {
      err = e;
      break;
    }

    if (keyInfo.pathKey)
      getFunc = getPath;
    else if (extraOption[key])
      getFunc = getExtra;
    else
      getFunc = getValue;

    val = getFunc(req, key);

    var typeMap = {
      int: parseInt,
      float: parseFloat,
      number: parseFloat
    };

    var idx = Object.keys(typeMap).indexOf(keyInfo.type);
    if (idx >= 0) {
      if (isNaN(val)) {
        err = new Error('The parameter value is not a number : ' + key);
        err.code = 400;
        break;
      }

      if (keyInfo.type == 'int' && !_.isSafeInteger(val)) {
        err = new Error('The parameter value is not a integer : ' + key);
        err.code = 400;
        break;
      }

      options[key] = typeMap[keyInfo.type](val);
    } else {
      options[key] = val;
    }

    if (!options[key]) {
      err = new Error('No Request Data For Required : ' + key);
      err.code = 400;
      break;
    }
  }

  return {
    err: err,
    params: options
  };
}

//required = ['id', 'number:req_id:path'], optional = ['order', 'number:count:10']
function fetchParameter(required, optional) {
  //문자열 배열인지 먼저 확인하자.
  var requiredResult
    , optionalResult = {}
    , extraResult
    , options = {}
    , req;

  req = this.req;
  if (!this.req) {
    throw Error('insert this express-param middleware into express app!');
  }

  //req.user value is default
  extraOption = this.extraOption || {};
  if (!extraOption.user) extraOption.user = 'user';

  requiredResult = requiredParameter(req, required);

  if (requiredResult.err) {
    return requiredResult.err;
  }

  if (Array.isArray(optional)) {
    optionalResult = getOptionalParams(req, optional);
    if (optionalResult.err) {
      return optionalResult.err;
    }
  }

  extraResult = getDefaultRequestInfo(req, extraOption);

  var extendOptionVal = _.extend(optionalResult.params, extraResult.params);
  options.params = _.extend(requiredResult.params, extendOptionVal);

  return options.params;
}

exports.fetchParameter = fetchParameter;
