import { isArray, isMissing, isBasicType, isObject } from './TypeUtil';
import { ICollideModifiers } from '../interfaces';
import { BasicType } from '../types';

/**
 * collide 2 arguments
 * @param arg1 first argument to collide into (note: original object won't be modified directly)
 * @param arg2 second argument to collide from
 * @param modifiers
 * @param startPath the entry path to start modifier path generation. Default value: $
 * @returns collide result
 */
export function collide(arg1: any, arg2: any, modifiers?: ICollideModifiers, startPath = '$'): any {
    const arg1Clone = isMissing(arg1) ? arg1 : JSON.parse(JSON.stringify(arg1));
    const arg2Clone = isMissing(arg2) ? arg2 : JSON.parse(JSON.stringify(arg2));

    return collideUnsafe(arg1Clone, arg2Clone, modifiers, startPath);
}

/**
 * Collide unsafe (arg1 may be modified directly)
 * @param arg1 first argument to collide into (note: original object will be modified directly)
 * @param arg2 second argument to collide from
 * @param modifiers
 * @param startPath the entry path to start modifier path generation. Default value: $
 * @returns collide result
 */
export function collideUnsafe(arg1: any, arg2: any, modifiers?: ICollideModifiers, startPath = '$'): any {
    if (arg2 === undefined) {
        return arg1;
    }

    if (isMissing(arg1)) {
        return arg2;
    }

    if (isBasicType(arg1)) {
        return collideBasic(arg1, arg2, startPath, modifiers);
    }

    if (isArray(arg1)) {
        return collideArrays(arg1, arg2, startPath, modifiers);
    }

    return collideObjects(arg1, arg2, startPath, modifiers);
}

/**
 * collide basic value types
 * @param arg1
 * @param arg2
 * @param path
 * @param modifiers
 */
function collideBasic(arg1: BasicType, arg2: BasicType, path: string, modifiers?: ICollideModifiers): any {
    if (modifiers && modifiers[path]) {
        return modifiers[path](arg1, arg2);
    }

    return arg2;
}

/**
 * collide objects
 * @param obj1
 * @param obj2
 * @param path
 * @param modifiers
 */
function collideObjects(obj1: any, obj2: any, path: string, modifiers?: ICollideModifiers): any {
    if (!isObject(obj2)) {
        throw new Error(`Unable to collide. Collide value at path ${path} is not an object.`);
    }

    if (modifiers && modifiers[path]) {
        return modifiers[path](obj1, obj2);
    }

    for (const key of Object.keys(obj2)) {
        const subPath = path + '.' + key;
        if (key !== '__proto__') {
            if (obj1[key] === undefined) {
                obj1[key] = obj2[key];
            } else {
                if (modifiers && modifiers[subPath]) {
                    obj1[key] = modifiers[subPath](obj1[key], obj2[key]);
                } else {
                    obj1[key] = collideUnsafe(obj1[key], obj2[key], modifiers, subPath);
                }
            }
        }
    }

    return obj1;
}

/**
 * collide arrays. Default behaviour to push values of arr2 into arr1.
 * @param arr1
 * @param arr2
 * @param path
 * @param modifiers
 */
function collideArrays(arr1: any[], arr2: any[], path: string, modifiers?: ICollideModifiers): any {
    if (!isArray(arr2)) {
        throw new Error(`Unable to collide. Collide value at path ${path} is not an array.`);
    }

    if (modifiers && modifiers[path]) {
        return modifiers[path](arr1, arr2);
    } else {
        for (const item of arr2) {
            if (arr1.indexOf(item) < 0) {
                arr1.push(item);
            }
        }
    }

    return arr1;
}
