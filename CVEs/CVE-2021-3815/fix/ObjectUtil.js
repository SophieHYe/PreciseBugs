/** global: ArrayUtil */
/** global: Base64Util */
/** global: DateUtil */
/** global: FunctionUtil */
/** global: JSONUtil */
/** global: MathUtil */
/** global: ObjectUtil */
/** global: StringUtil */
/** global: TypeUtil */
/** global: URLUtil */

ObjectUtil = {

    assign: function(obj, other)
    {
        var objs = [other].concat(FunctionUtil.args(arguments, 2));
        var i, j, k;
        for (i = 0, j = objs.length; i < j; i++) {
            for (k in objs[i]) {
                obj[k] = objs[i][k];
            }
        }
        return obj;
    },

    clean: function(obj, hard)
    {
        var keys = ObjectUtil.keys(obj);
        var key, val;
        for (var i = 0, j = keys.length; i < j; i++) {
            key = keys[i];
            val = obj[key];
            if (hard === true) {
                switch (TypeUtil.of(val)) {
                    case TypeUtil.ARRAY:
                        val = obj[key] = ArrayUtil.clean(val, hard);
                        if (val.length === 0) {
                            val = null;
                        }
                        break;
                    case TypeUtil.OBJECT:
                        val = obj[key] = ObjectUtil.clean(val, hard);
                        if (ObjectUtil.length(val) === 0) {
                            val = null;
                        }
                        break;
                    case TypeUtil.STRING:
                        val = obj[key] = StringUtil.trim(val);
                        if (val === '') {
                            val = null;
                        }
                        break;
                }
            }
            if (TypeUtil.isNone(val)) {
                delete obj[key];
            }
        }
        return obj;
    },

    clone: function(obj)
    {
        var cln = {};
        var keys = ObjectUtil.keys(obj);
        var key, val;
        for (var i = 0, j = keys.length; i < j; i++) {
            key = keys[i];
            val = obj[key];
            switch (TypeUtil.of(val)) {
                case TypeUtil.ARRAY:
                    cln[key] = ArrayUtil.clone(val);
                    break;
                case TypeUtil.DATE:
                    cln[key] = DateUtil.clone(val);
                    break;
                case TypeUtil.OBJECT:
                    cln[key] = ObjectUtil.clone(val);
                    break;
                default:
                    cln[key] = obj[key];
                    break;
            }
        }
        return cln;
    },

    decodeBase64: function(str)
    {
        return JSONUtil.decode(Base64Util.decode(str));
    },

    decodeJSON: function(str)
    {
        return JSONUtil.decode(str);
    },

    decodeParameters: function(str)
    {
        return URLUtil.getParametersDict('?' + str);
    },

    encodeBase64: function(obj)
    {
        return Base64Util.encode(JSONUtil.encode(obj));
    },

    encodeJSON: function(obj)
    {
        return JSONUtil.encode(obj);
    },

    encodeParameters: function(obj, keysFilter)
    {
        var objClean = ObjectUtil.clean(ObjectUtil.clone(obj), true);
        var keys = (TypeUtil.isArray(keysFilter) ? keysFilter : ObjectUtil.keys(obj, true));
        var key, val, keyval = [];

        for (var i = 0, j = keys.length; i < j; i++) {
            key = keys[i];
            if (key in objClean) {
                val = objClean[key];
                keyval.push(key + '=' + encodeURIComponent(val));
            }
        }

        return keyval.join('&');
    },

    equals: function(obj1, obj2)
    {
        if (obj1 === obj2 || ObjectUtil.is(obj1, obj2)) {
            return true;
        }

        var key, val1, val2, type1, type2;

        type1 = TypeUtil.of(obj1);
        type2 = TypeUtil.of(obj2);

        if (type1 !== type2) {
            return false;
        }

        switch (type1) {
            case TypeUtil.ARRAY:
            case TypeUtil.OBJECT:
                break;
            case TypeUtil.NUMBER:
                return MathUtil.equals(obj1, obj2);
            default:
                return String(obj1) === String(obj2);
        }

        for (key in obj2) {
            if (!(key in obj1)) {
                return false;
            }
        }

        for (key in obj1) {
            val1 = obj1[key];
            val2 = obj2[key];

            if (ObjectUtil.is(obj1, val1) || ObjectUtil.is(obj2, val2) || ObjectUtil.is(val1, val2) || val1 === val2) {
                continue;
            }

            if (!ObjectUtil.equals(val1, val2)) {
                return false;
            }
        }

        return true;
    },

    is: function(obj1, obj2)
    {
        // https://developer.mozilla.org/it/docs/Web/JavaScript/Reference/Global_Objects/Object/is
        if (!Object.is) {
            Object.is = function(x, y) {
                // Algoritmo SameValue
                if (x === y) { // Steps 1-5, 7-10
                    // Steps 6.b-6.e: +0 != -0
                    return x !== 0 || 1 / x === 1 / y;
                } else {
                    // Step 6.a: NaN === NaN
                    return x !== x && y !== y;
                }
            };
        }
        return Object.is(obj1, obj2);
    },

    keypath: {

        get: function(obj, path, defaultValue)
        {
            var keys = path.split('.');
            var key;
            var cursor = obj;
            for (var i = 0, j = keys.length; i < j; i++) {
                key = keys[i];
                try {
                    cursor = cursor[key];
                } catch(e) {
                    return defaultValue;
                }
            }
            return (TypeUtil.isUndefined(cursor) ? defaultValue : cursor);
        },

        set: function(obj, path, value)
        {
            var keys = path.split('.');
            var key;
            var cursor = obj;
            for (var i = 0, j = keys.length; i < j; i++) {
                key = keys[i];
                if (key === '__proto__') {
                    break;
                }
                if (!TypeUtil.isObject(cursor[key])) {
                    cursor[key] = {};
                }
                if (i < (j - 1)) {
                    cursor = cursor[key];
                } else {
                    cursor[key] = value;
                }
            }
        }
    },

    keys: function(obj, sorted)
    {
        var k = Object.keys(obj);
        if (sorted === true) {
            k.sort();
        }
        return k;
    },

    length: function(obj)
    {
        return ObjectUtil.keys(obj).length;
    },

    map: function(obj, func)
    {
        var m = {};
        ObjectUtil.keys(obj).forEach(function(k) {
            m[k] = func.call(null, obj[k], k, obj);
        });
        return m;
    },

    merge: function(obj1, obj2)
    {
        var objs = [{}, obj1, obj2].concat(FunctionUtil.args(arguments, 2));
        var obj = ObjectUtil.assign.apply(null, objs);
        return obj;
    },

    search: function(objs, filter)
    {
        var results = [], i, j, k, m, obj, res, keys, key, val;
        for (i = 0, j = objs.length; i < j; i++) {
            obj = objs[i];
            res = obj;
            keys = ObjectUtil.keys(filter);
            for (k = 0, m = keys.length; k < m; k++) {
                key = keys[k];
                val = filter[key];
                if (!ObjectUtil.equals(obj[key], val)) {
                    res = null;
                }
            }
            if (res) {
                results.push(res);
            }
        }
        return results;
    },

    values: function(obj, sorted)
    {
        var keys = ObjectUtil.keys(obj, sorted);
        var vals = [];
        for (var i = 0, j = keys.length; i < j; i++) {
            vals.push(obj[keys[i]]);
        }
        return vals;
    }

};