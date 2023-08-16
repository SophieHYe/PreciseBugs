
var apersistence = require("./abstractPersistence.js");

function ModelDescription(typeName, description, strategy){
    this.persistentProperties = [];
    this.transientProperties = [];  //these properties are other objects and are loaded lazily
    var self = this;
    var pkField = "id";
    var template = {};
    var functions = {};
    var indexes = [];
    var _hasIndexAll = false;

    this.getFieldType = function(fieldName){
        var desc = description[fieldName];
        if(!desc){
            return null;
        }
        return desc.type;
    };

    this.getFieldDescription = function(fieldName){
        var desc = description[fieldName];
        if(!desc){
            return null;
        }
        return desc;
    };

    this.getIndexes = function(){
        return indexes;
    }

    this.hasIndexAll = function(){
         return _hasIndexAll;
    }

    this.getPKField = function(){
        return pkField;
    }

    this.createRaw = function(pkValue){
        var args = [];
        for(var i = 0; i<arguments.length;i++){
            args.push(arguments[i]);
        }

        var res = {
            __meta:{
                    typeName:typeName,
                    freshRawObject:true,
                    savedValues: {},
                    getPK : function(){
                        if(pkField){
                            return res[pkField];
                        } else {
                            throw new Error("No pk member found for type " + typeName);
                        }
                    },
                    getPKField : function(){
                        if(pkField){
                            return pkField;
                        } else {
                            throw new Error("No pk member found for type " + typeName);
                        }
                    },
                    loadLazyField : function(field,callback){
                        var typeDescription = self.getFieldDescription(field).type;
                        var typeOfField = self.isArray(field)?typeDescription.split(":")[1]:typeDescription;

                        var persistence = apersistence.getPersistenceForType(typeOfField);
                        var relationFields = description[field].relation.split(":");

                        var myField = relationFields[0];
                        var hisField = relationFields[1];
                        var filter = {};
                        filter[hisField] = res[myField];
                        persistence.filter(typeOfField,filter,function(err,results){
                            if(err){
                                callback(err);
                            }else{
                                if(!self.isArray(field)) {
                                    results = results.length !== 0 ? results[0] : undefined
                                }

                                Object.defineProperty(res,field, {
                                    get:function(field){
                                        return results;
                                    }
                                });

                                callback(null,res);
                            }
                        });
                    },
                    loadLazyFields: function(callback){
                        var errs = {};
                        var numErrs = 0;
                        var left = self.transientProperties.length;
                        self.transientProperties.forEach(function(transientField){
                            res.__meta.loadLazyField(transientField,function(err,result){
                                if(err){
                                    errs[transientField] = err;
                                    numErrs++;
                                }
                                left--;
                                if(left===0 ){
                                    if(numErrs>0) {
                                        callback(errs, res);
                                    }else{
                                        callback(null,res);
                                    }
                                }
                            })
                        })
                    }
                }
            };

        res.assign = castAssign.bind(res);

        res.__meta.getPK = res.__meta.getPK.bind(res);

        for(var v in functions){
            var field = description[v];
            res[v] = field.bind(res);
        }

        for(var v in template){
            res[v] = template[v];
        }

        // throw erros if trying to access lazy fields that are not loaded or setting fields #this is not OOP:)
        self.transientProperties.forEach(function(field){
            Object.defineProperty(res,field,{
                get:function(field){
                    return null;
                },
                set:function(){
                    throw new Error("Cannot set lazy fields\nUse the relationship table for such things")
                },
                configurable:true,
                enumerable:true
            })

        });

        res[pkField] = pkValue;

        if(description.ctor){
            description.ctor.apply(res,args);
        }

        return res;
    };

    this.isTransient = function(field){
        return !strategy.getConverterTo(description[field].type) &&
            !strategy.getConverterTo(description[field].type.split(":")[1])
    };

    this.isArray = function(field){
        return description[field].type.match("array")?true:false;
    };

    for(var v in description){
        var field = description[v];
        if(typeof field !== "function"){
            if(this.isTransient(v)){
                description[v].loadLazy = true;
                this.transientProperties.push(v);
            }else{
                this.persistentProperties.push(v);
                if(field.pk === true){
                    pkField = v;
                }

                if(field.index === true){
                    _hasIndexAll = true;
                    indexes.push(v);
                }
                template[v] = field.default;
            }
        } else {
            functions[v] = field;
        }
    }
    

    function castAssign(fieldName, value){ //will get binding to a model object
        this[fieldName] = convertFrom(strategy, this.__meta.typeName, fieldName, value)
    }
}

var models = {};

exports.registerModel = function(typeName, description, strategy){
    models[typeName] = new ModelDescription(typeName, description, strategy);
    return models[typeName];
};

exports.ModelDescription = ModelDescription;

function convertFrom(strategy, modelName, fieldName, fromData){
    var model = models[modelName];
    var typeDesc = model.getFieldDescription(fieldName);
    var typeName = typeDesc.type;
    if(!typeName){
        throw new Error("Unknown type name for field "+fieldName+" in model "+modelName);
    }

    if(typeName.match('array')){
        typeName = 'array';
    }
    var converterFrom = strategy.getConverterFrom(typeName);
    if(!converterFrom){
        throw new Error("No register convertor can deserialize field of type "+typeName);
    }

    if(fromData == null || fromData == undefined){
        return fromData;
    }
    return converterFrom(fromData,typeDesc);
}

function convertTo(modelName, fieldName,value, strategy){
    var model = models[modelName];
    var typeDesc = model.getFieldDescription(fieldName);
    var typeName = typeDesc.type;
    if(!typeName){
        throw new Error("Unknown type name for field "+fieldName+" in model "+modelName);
    }

    if(typeName.match('array')){
        typeName = 'array';
    }
    var converterOut = strategy.getConverterTo(typeName);
    if(!converterOut){
        throw new Error("No register convertor can serialize field of type "+typeName);
    }
    if(value == null || value == undefined){
        return value;
    }
    return converterOut(value,typeDesc);
}

exports.load = function( rawObject, from , strategy){
    var rawModel = models[rawObject.__meta.typeName];
    var props = rawModel.persistentProperties;
    props.forEach(function(p){
        if(from.hasOwnProperty(p)) {
            var value = convertFrom(strategy, rawObject.__meta.typeName, p, from[p]);
            rawObject[p] = value;
            rawObject.__meta.savedValues[p] = value;
        }
    });
    delete rawObject.__meta.freshRawObject;
};

exports.updateObject = function(modelObject,from,strategy){
    var props = models[modelObject.__meta.typeName].persistentProperties
    props.forEach(function(property){
        if(from[property]) {
            modelObject[property] = convertFrom(strategy, modelObject.__meta.typeName, property, from[property]);
        }
    })
};

exports.serialiseField = function(typeName,field,value,strategy){
    return convertTo(typeName,field,value,strategy);
};

exports.serialiseObjectValues = function(typeName,object,strategy){
    var ser = {};
    for(var field in object){
        var s = exports.serialiseField(typeName,field,object[field],strategy)
        ser[field] = s;
    }
    return ser;
};

exports.deserialiseField = function(typeName,field,value,strategy){
    return convertFrom(strategy,typeName,field,value);
};

exports.changesDiff = function(obj){
    var diff = [];
    var modelObject = models[obj.__meta.typeName];
    modelObject.persistentProperties.forEach(function (p) {
        if (!modelObject.isArray(p)) {
            if (obj[p] !== obj.__meta.savedValues[p]) {
                diff.push(p);
            }
        } else {
            if (!arraysMatch(obj[p], obj.__meta.savedValues[p])) {
                diff.push(p);
            }
        }
    });    
    return diff;
    function arraysMatch(arr1,arr2){
        try {
            if (arr1.length !== arr2.length) {
                return false;
            }
            for (var arrIndex = 0; arrIndex < arr1.length; arrIndex++) {
                if (arr1[arrIndex] !== arr2[arrIndex]) {
                    return false;
                }
            }
            return true;
        }catch(e){
            //one of the arrays is probably undefined
            return false;
        }
    }
};

exports.createObjectFromData = function(typename,data){
    var m = models[typename];
    var raw = exports.createRaw(typename, data[m.getPKField()]);
    var props = m.persistentProperties;
    props.forEach(function(p){
        raw[p]= data[p];
    })
    delete raw.__meta.freshRawObject;
    return raw;
}

exports.createRaw = function(typeName, pk,strategy){
    var d = models[typeName];
    return d.createRaw(pk);
}

exports.getIndexes = function(typeName){
    var d = models[typeName];
    return d.getIndexes();
}

exports.hasIndexAll = function(typeName){
    var d = models[typeName];
    return d.hasIndexAll();
}

exports.getPKField = function(typeName){
    var d = models[typeName];
    return d.getPKField();
}

exports.getModel = function(typeName){
    return models[typeName];
}

exports.getInnerValues = function(obj, strategy){
    var ret = {};
    for(var field in obj){
        if(field != "__meta" && typeof obj[field] != "function"){
            ret[field] = obj[field];
        }
    }
    return ret;
}

