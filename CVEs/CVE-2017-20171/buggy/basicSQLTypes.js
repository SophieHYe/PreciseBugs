/**
 * Created by ctalmacel on 12/15/15.
 */


exports.registerTypeConverters = function(persistence){
    persistence.registerConverter("string",
        function(value){
            return value;
        },
        function(value){
            return value;
        },
        'varchar'
    )
    persistence.registerConverter("textString",
        function(value){
            return value;
        },
        function(value){
            return value;
        },
        'text'
    )

    persistence.registerConverter('int',
        function(value){
            return (value);
        },
        function(value){
            return value;
        },
        "int"
    )

    persistence.registerConverter('float',
        function(value){
            return value;
        },
        function(value){
            return value;
        },
        "float"
    )

    persistence.registerConverter('boolean',
        function(value){
            
            if(value[0] != '0') {
                return true;
            }
            else {
                return false;
            }
        },
        function(value){
            if(value){
                return "1"
            }
            else{
                return "0"
            }
        },
        "bit"
    );

    persistence.registerConverter('date',
        function(value){
            return value.toISOString().substring(0,10);
        },
        function(javascriptDate){
            return javascriptDate.toISOString().slice(0, 19).replace('T', ' ');
        },
        "date"
    )

    persistence.registerConverter("dateFormat",
        function(value, typeDescription){
            if(!value){
                return null;
            }
            var m = moment(value,typeDescription.format);
            return m;
        },
        function(value, typeDescription){
            var txt = value.format(typeDescription.format);
            return txt;
        },
        "varchar"
    );

    persistence.registerConverter("array",
        function(blobOfStuff, typeDescription){
            if (blobOfStuff == null || blobOfStuff == undefined){
                return "null";
            }
            return JSON.parse(blobOfStuff);
        },
        function(arrayOfStuff, typeDescription){
            if(arrayOfStuff == "null"){
                return null;
            }
            if(arrayOfStuff.length>0&&arrayOfStuff[0].__meta){
                //the array is of lazy objects and was filled
                arrayOfStuff = arrayOfStuff.map(function(lazyLoadedObject){
                    return lazyLoadedObject.__meta.getPK()
                })
            }

            return JSON.stringify(arrayOfStuff)
        },
        'blob'
    )
}