// Dependencies
var Deffy = require("deffy");

/**
 * SetOrGet
 * Sets or gets an object field value.
 *
 * @name SetOrGet
 * @function
 * @param {Object|Array} input The cache/input object.
 * @param {String|Number} field The field you want to update/create.
 * @param {Object|Array} def The default value.
 * @return {Object|Array} The field value.
 */
function SetOrGet(input, field, def) {
    if (field == '__proto__' || field == 'constructor' || field == 'prototype')
        throw new Error('Restricted setting magical attributes')
    return input[field] = Deffy(input[field], def);
}

module.exports = SetOrGet;
