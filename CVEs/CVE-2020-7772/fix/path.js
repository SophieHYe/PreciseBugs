'use strict';

module.exports = {
    evaluatePath,
    setPath
};

function evaluatePath(document, keyPath) {
    if (!document) {
        return null;
    }

    let {indexOfDot, currentKey, remainingKeyPath} = computeStateInformation(keyPath);

    // If there is a '.' in the keyPath and keyPath doesn't appear in the document, recur on the subdocument
    if (indexOfDot >= 0 && !document[keyPath]) {
        // If there's an array at the currentKey in the document, then iterate over those items evaluating the remaining path
        if (Array.isArray(document[currentKey])) {
            return document[currentKey].map((doc) => evaluatePath(doc, remainingKeyPath));
        }
        // Otherwise, we can just recur
        return evaluatePath(document[currentKey], remainingKeyPath);
    } else if (Array.isArray(document)) {
        // If this "document" is actually an array, then iterate over those items evaluating the path
        return document.map((doc) => evaluatePath(doc, keyPath));
    }

    // Otherwise, we can just return value directly
    return document[keyPath];
}

function setPath(document, keyPath, value) {
    if (!document) {
        throw new Error('No document was provided.');
    } else if (!keyPath) {
        throw new Error('No keyPath was provided.');
    }

    // If this is clearly a prototype pollution attempt, then refuse to modify the path
    if (keyPath.startsWith('__proto__') || keyPath.startsWith('constructor')) {
        return document;
    }

    return _setPath(document, keyPath, value);
}

function _setPath(document, keyPath, value) {
    if (!document) {
        throw new Error('No document was provided.');
    }

    let {indexOfDot, currentKey, remainingKeyPath} = computeStateInformation(keyPath);

    if (indexOfDot >= 0) {
        // If there is a '.' in the keyPath, recur on the subdoc and ...
        if (!document[currentKey] && Array.isArray(document)) {
            // If this is an array and there are multiple levels of keys to iterate over, recur.
            return document.forEach((doc) => _setPath(doc, keyPath, value));
        } else if (!document[currentKey]) {
            // If the currentKey doesn't exist yet, populate it
            document[currentKey] = {};
        }
        _setPath(document[currentKey], remainingKeyPath, value);
    } else if (Array.isArray(document)) {
        // If this "document" is actually an array, then we can loop over each of the values and set the path
        return document.forEach((doc) => _setPath(doc, remainingKeyPath, value));
    } else {
        // Otherwise, we can set the path directly
        document[keyPath] = value;
    }

    return document;
}

/**
 * Helper function that returns some information necessary to evaluate or set a path
 *   based on the provided keyPath value
 * @param keyPath
 * @returns {{indexOfDot: Number, currentKey: String, remainingKeyPath: String}}
 */
function computeStateInformation(keyPath) {
    let indexOfDot = keyPath.indexOf('.');

    return {
        indexOfDot,
        currentKey: keyPath.slice(0, indexOfDot >= 0 ? indexOfDot : undefined),
        remainingKeyPath: keyPath.slice(indexOfDot + 1)
    };
}
