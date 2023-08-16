/**
 * Encode and decode ini/conf/cfg files
 * @author Rolf Loges
 * @licence MIT
 * @param {{lineEnding: string, sectionOpenIdentifier: string, sectionCloseIdentifier: string, defaultValue: boolean, assignIdentifier: string, commentIdentifiers: string, trimLines: boolean, ignoreMultipleAssignIdentifier: boolean}} options
 */
function Config(options){
    this.options = {
        lineEnding: "\r\n",
        sectionOpenIdentifier: '[',
        sectionCloseIdentifier: ']',
        defaultValue: true,
        assignIdentifier: "=",
        valueIdentifier: undefined,
        commentIdentifiers: [";"],
        trimLines: true,
        ignoreMultipleAssignIdentifier: false
    };
    if(typeof options === 'object'){
        this.setOptions(options);
    }
}

/**
 * Decode a config-string
 * 
 * @param {string} data
 * @return {{}}
 */
Config.prototype.decode = function(data){
    if(typeof data != 'string'){
        if(typeof data.toString === 'function'){
            data = data.toString();
        } else {
            throw new Error('expecting string but got '+typeof data);
        }
    }
    var result = {};
    var currentSection = undefined;
    var lines = data.split(this.options.lineEnding);
    for(var i = 0; i < lines.length; i++){
        var line = lines[i];
        if(this.options.trimLines === true){
            line = line.trim();
        }
        if(line.length == 0 || stringBeginsWithOnOfTheseStrings(line,this.options.commentIdentifiers)){
            continue;
        }
        
        var sectionRegExp = new RegExp("^\\"+this.options.sectionOpenIdentifier+"(.*?)\\"+this.options.sectionCloseIdentifier+"$");
        var newSection = line.match(sectionRegExp);
        if(newSection !== null){
            currentSection = newSection[1];
            if(typeof result[currentSection] === 'undefined'){
                result[currentSection] = {};
            }
            continue;
        }

        var assignPosition = line.indexOf(this.options.assignIdentifier);
        var key = undefined;
        var value = undefined;
        if(assignPosition === -1){
            key = line;
            value = this.options.defaultValue;
        } else {
            var assignIdentifierLength = this.options.assignIdentifier.length
            if (this.options.ignoreMultipleAssignIdentifier) {
                var regExp = new RegExp(escapeRegExp(this.options.assignIdentifier) + '+')
                var matchResult = line.match(regExp)
                if (matchResult !== null) {
                    assignIdentifierLength = matchResult[0].length
                }
            }
            key = line.substr(0,assignPosition);
            value = line.substr(assignPosition+assignIdentifierLength);
        }
        if (typeof this.options.valueIdentifier === 'string') {
            value = this.valueTrim(value, this.options.valueIdentifier);
        }
        if(typeof currentSection === 'undefined'){
            result[key] = value;
        } else {
            result[currentSection][key] = value;
        }
    }
    return result;
}

/**
 * Encode a object
 * no nesting section supported!
 * 
 * @param {{}} object
 * @return {string}
 */
Config.prototype.encode = function(object){
    var resultSections = "";
    var resultAttributesWithoutSection = "";
    var sections = Object.keys(object);
    if (typeof this.options.valueIdentifier === 'string'){
        var valueIdentifier = this.options.valueIdentifier;
    } else {
        var valueIdentifier = "";
    }
    for(var i = 0; i < sections.length; i++){
        if(typeof object[sections[i]] === 'object'){
            if(resultSections != ""){
                resultSections += this.options.lineEnding;
            }
            resultSections += this.options.sectionOpenIdentifier;
            resultSections += sections[i];
            resultSections += this.options.sectionCloseIdentifier;
            resultSections += this.options.lineEnding;
            var attributes = Object.keys(object[sections[i]]);
            for(var j = 0; j < attributes.length; j++){
                resultSections += attributes[j];
                resultSections += this.options.assignIdentifier;
                resultSections += valueIdentifier;
                resultSections += object[sections[i]][attributes[j]];
                resultSections += valueIdentifier;
                resultSections += this.options.lineEnding;
            }
        } else {
            resultAttributesWithoutSection += sections[i];
            resultAttributesWithoutSection += this.options.assignIdentifier;
            resultAttributesWithoutSection += object[sections[i]];
            resultAttributesWithoutSection += this.options.lineEnding;
        }
    }
    return resultAttributesWithoutSection+resultSections;
}

/**
 * Set Options
 * @param {{lineEnding: string, sectionOpenIdentifier: string, sectionCloseIdentifier: string, defaultValue: boolean, assignIdentifier: string, commentIdentifiers: string, trimLines: boolean}} options
 */
Config.prototype.setOptions = function(options){
    if(typeof options !== 'object'){
        throw new Error('expecting object but got '+typeof options);
    }
    var option = Object.keys(options);
    for(var i = 0; i < option.length; i++){
        if(typeof options[option[i]] !== 'undefined'){
            this.options[option[i]] = options[option[i]];
        }
    }
}

/**
 * Try to detect the used line ending
 * (windows, unix, mac)
 * @param {string} data
 * @return {string}
 */
Config.prototype.detectLineEnding = function(data){
    var hasCaridgeReturn = data.indexOf("\r") !== -1;
    var hasLineFeed = data.indexOf("\n") !== -1
    if(hasCaridgeReturn && hasLineFeed){
        if(data.indexOf("\r\n") !== -1){
            return "\r\n";
        } else if(data.indexOf("\n\r") !== -1){
            return "\n\r";
        } else {
            throw new Error('found multiple line endings');
        }
    } else if(hasLineFeed){
        return "\n";
    } else if(hasCaridgeReturn){
        return "\r";
    } else {
        return "\n";
    }
}

/**
 * @param string value
 * @param string chars
 */
Config.prototype.valueTrim = function(value, chars){
    var charsEscaped = chars.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    var regEx = new RegExp("^["+charsEscaped+"]?");
    value = value.replace(regEx, '');
    regEx = new RegExp("["+charsEscaped+"]?$");
    value = value.replace(regEx, '');
    return value;
}

/**
 * @param {string} string
 * @param {string[]} stringList
 * @return {boolean}
 */
function stringBeginsWithOnOfTheseStrings(string, stringList){
    for(var i = 0; i < stringList.length; i++){
        if(string.indexOf(stringList[i]) === 0){
            return true;
        }
    }
    return false;
}

/**
 * @param {string} string
 * @returns {string}
 */
function escapeRegExp(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

module.exports = Config;
