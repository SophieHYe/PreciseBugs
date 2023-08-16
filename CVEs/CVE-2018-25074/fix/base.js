var INVALID_ESCAPES = /[^\\]\\[^.*+?^${}()|[\]\\bBcdDfnrsStvwWxu0-9]/;
var validators = require('./'),
	formats = {
		'date-time': /^\d{4}-(0[0-9]{1}|1[0-2]{1})-[0-9]{2}[t ]\d{2}:\d{2}:\d{2}(\.\d+)?([zZ]|[+-]\d{2}:\d{2})$/i,
		'date': /^\d{4}-(0[0-9]{1}|1[0-2]{1})-[0-9]{2}$/,
		'time': /^\d{2}:\d{2}:\d{2}$/,
		'color': /^(#[0-9a-f]{3}|#[0-9a-f]{6}|aqua|black|blue|fuchsia|gray|green|lime|maroon|navy|olive|orange|purple|red|silver|teal|white|yellow)$/i,
		'style': /^(?:\s*-?[_A-Z]+[_A-Z0-9-]*:[^\n\r\f;]+;)*\s*-?[_A-Z]+[_A-Z0-9-]*:[^\n\r\f;]+;?\s*$/i,
		'phone': /^(?:(?:\(?(?:00|\+)(?:[1-4]\d\d|[1-9]\d?)\)?)?[\-\.\ \\\/]?)?(?:(?:\(?\d{1,}\)?[\-\.\ \\\/]?){0,})(?:[\-\.\ \\\/]?(?:#|ext\.?|extension|x)[\-\.\ \\\/]?(?:\d+))?$/i,
		'uri': /^(?:([a-z0-9+.-]+:\/\/)((?:(?:[a-z0-9-._~!$&'()*+,;=:]|%[0-9A-F]{2})*)@)?((?:[a-z0-9-._~!$&'()*+,;=]|%[0-9A-F]{2})*)(:(?:\d*))?(\/(?:[a-z0-9-._~!$&'()*+,;=:@\/]|%[0-9A-F]{2})*)?|([a-z0-9+.-]+:)(\/?(?:[a-z0-9-._~!$&'()*+,;=:@]|%[0-9A-F]{2})(?:[a-z0-9-._~!$&'()*+,;=:@\/]|%[0-9A-F]{2})*)?)(\?(?:[a-z0-9-._~!$&'()*+,;=:\/?@]|%[0-9A-F]{2})*)?(#(?:[a-z0-9-._~!$&'()*+,;=:\/?@]|%[0-9A-F]{2})*)?$/i,
		'email': /^[A-Z0-9._%+-]+@(?:[A-Z0-9-]+\.)+[A-Z]{2,}$/i,
		'ipv4': /^(\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.(\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.(\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.(\d{1,2}|1\d\d|2[0-4]\d|25[0-5])$/,
		'ipv6': /^\s*((([0-9A-F]{1,4}:){7}([0-9A-F]{1,4}|:))|(([0-9A-F]{1,4}:){6}(:[0-9A-F]{1,4}|((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-F]{1,4}:){5}(((:[0-9A-F]{1,4}){1,2})|:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-F]{1,4}:){4}(((:[0-9A-F]{1,4}){1,3})|((:[0-9A-F]{1,4})((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-F]{1,4}:){3}(((:[0-9A-F]{1,4}){1,4})|((:[0-9A-F]{1,4}){0,2}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-F]{1,4}:){2}(((:[0-9A-F]{1,4}){1,5})|((:[0-9A-F]{1,4}){0,3}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-F]{1,4}:){1}(((:[0-9A-F]{1,4}){1,6})|((:[0-9A-F]{1,4}){0,4}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(:(((:[0-9A-F]{1,4}){1,7})|((:[0-9A-F]{1,4}){0,5}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:)))(%.+)?\s*$/i,

		// hostname regex from: http://stackoverflow.com/a/1420225/5628
		'hostname': /^(?=.{1,255}$)[0-9A-Z](?:(?:[0-9A-Z]|-){0,61}[0-9A-Z])?(?:\.[0-9A-Z](?:(?:[0-9A-Z]|-){0,61}[0-9A-Z])?)*\.?$/i,

		'utc-millisec': function(subject) {
			var parsed = parseInt(subject, 10);
			return !isNaN(parsed) && parsed.toString() === subject.toString();
		},
		'regex': function (subject) {
			if(INVALID_ESCAPES.test(subject)) return false;
			try {
				new RegExp(subject);
				return true;
			}
			catch(e) {
				return false;
			}
		}
	};

// aliases
formats['host-name'] = formats.hostname;
formats['ip-address'] = formats.ipv4;


function getType(subject) {
	var type = typeof subject;

	if(type === 'object') {
		if(subject === null) return 'null';
		if(Array.isArray(subject)) return 'array';
	}

	if(type === 'number' && subject === Math.round(subject)) return 'integer';

	return type;
}

function format(context, subject, schema) {
	var fmt = schema.format,
		validator = formats[fmt];

	if(!validator)
		throw new Error('Invalid schema: unknown format (' + fmt + ')');

	var valid = validator.test ? validator.test(subject) : validator(subject);
	if(!valid) {
		context.addError('Failed "format" criteria (' + fmt + ')', subject, schema);
	}

	return valid;
}

function validateTypes(context, subject, type, validTypes) {
	var i = validTypes.length,
		validType, valid;
	while(i--) {
		validType = validTypes[i];

		if(validType === 'any') return true;

		if(typeof validType === 'object') {
			valid = context.silently(function() {
				return validateBase(context, subject, validType);
			}); // jshint ignore:line
			if(valid) return true;
			else continue;
		}

		if(!(validType in validators.types))
			throw new Error('Invalid schema: invalid type (' + validType + ')');

		if(validType === 'number' && type === 'integer') return true;

		if(type === validType) return true;
	}

	return false;
}

function allOf(context, subject, schema) {
	var schemas = schema.allOf;

	if(!Array.isArray(schemas))
		throw new Error('Invalid schema: "allOf" value must be an array');

	var i = schemas.length,
		invalidCount = 0;
	while(i--) {
		if(!validateBase(context, subject, schemas[i])) {
			invalidCount += 1;
		}
	}

	if(invalidCount === 0) return true;

	context.addError('Failed "allOf" criteria', subject, schemas);
	return false;
}

function anyOf(context, subject, schema) {
	var schemas = schema.anyOf;

	if(!Array.isArray(schemas))
		throw new Error('Invalid schema: "anyOf" value must be an array');

	var matched = context.silently(function() {
		var i = schemas.length;
		while(i--) {
			if(validateBase(context, subject, schemas[i])) return true;
		}
		return false;
	});

	if(matched) return true;

	context.addError('Failed "anyOf" criteria', subject, schemas);
	return false;
}

function oneOf(context, subject, schema) {
	var schemas = schema.oneOf;

	if(!Array.isArray(schemas))
		throw new Error('Invalid schema: "oneOf" value must be an array');

	var i = schemas.length,
		validCount = 0;
	context.silently(function() {
		while(i--) {
			if(validateBase(context, subject, schemas[i])) validCount += 1;
		}
	});

	if(validCount === 1) return true;

	context.addError('Failed "oneOf" criteria', subject, schemas);
	return false;
}

function not(context, subject, schema) {
	var badSchema = schema.not,
		valid = context.silently(function() {
			return !validateBase(context, subject, badSchema);
		});

	if(valid) return true;

	context.addError('Failed "not" criteria', subject, schema);
	return false;
}

function disallow(context, subject, schema, type) {
	var invalidTypes = Array.isArray(schema.disallow) ? schema.disallow : [ schema.disallow ],
		valid = !validateTypes(context, subject, type, invalidTypes);

	if(!valid) {
		context.addError('Failed "disallow" criteria: expecting ' + invalidTypes.join(' or ') + ', found ' + type, subject, schema);
	}

	return valid;
}

function validateExtends(context, subject, schema) {
	var schemas = Array.isArray(schema["extends"]) ? schema["extends"] : [ schema["extends"] ];

	var i = schemas.length,
		invalidCount = 0;
	while(i--) {
		if(!validateBase(context, subject, schemas[i])) {
			invalidCount += 1;
		}
	}

	return invalidCount === 0;
}

function validateEnum(context, subject, schema) {
	var values = schema['enum'];

	if(!Array.isArray(values))
		throw new Error('Invalid schema: "enum" value must be an array');

	var i = values.length;
	while(i--) {
		if(validators.deepEqual(subject, values[i])) return true;
	}

	context.addError('Failed "enum" criteria', subject, values);
	return false;
}

function validateType(context, subject, schema, type) {
	var validTypes = Array.isArray(schema.type) ? schema.type : [ schema.type ],
		valid = validateTypes(context, subject, type, validTypes);

	if(!valid) {
		context.addError('Failed "type" criteria: expecting ' + validTypes.join(' or ') + ', found ' + type, subject, schema);
	}

	return valid;
}

function typeValidations(context, subject, schema, type) {
	return validators.types[type](context, subject, schema);
}

function pathFromIds(ids) {
	return ids.map(function(id) {
		var lastSlash = id.lastIndexOf("/");
		if(lastSlash === -1) return id;
		return id.substr(0, lastSlash + 1);
	}).join('');
}

function $ref(context, subject, schema) {
	var absolute = /^(#|\/)/.test(schema.$ref),
		ref = absolute ? schema.$ref : pathFromIds(context.id) + schema.$ref,
		refSchema = context.refs.get(ref, context.schema),
		ctx = context;

	if(schema.$ref[0] !== '#') {
		ctx = context.subcontext(context.refs.get(ref, context.schema, true));
	}

	var valid = validateBase(ctx, subject, refSchema);

	context.cleanSubject = ctx.cleanSubject;

	return valid;
}



function validateBase(context, subject, schema) {
	if(schema.$ref) {
		return $ref(context, subject, schema);
	}

	if(schema.id) context.id.push(schema.id);

	var valid = context.runValidations([
		[ 'type' in schema, validateType ],
		[ 'disallow' in schema, disallow ],
		[ 'enum' in schema, validateEnum ],
		[ true, typeValidations ],
		[ 'format' in schema, format ],
		[ 'extends' in schema, validateExtends ],
		[ 'allOf' in schema, allOf ],
		[ 'anyOf' in schema, anyOf ],
		[ 'oneOf' in schema, oneOf ],
		[ 'not' in schema, not ]
	], subject, schema, getType(subject));

	if(schema.id) context.id.pop();

	return valid;
}

module.exports = validateBase;
