( ( factory ) ->
    if typeof exports is "object"
        module.exports = factory()
    else if typeof define is "function" and define.amd
        define( [], factory )

)( () ->
    isObject = ( value ) ->
        type = typeof value
        return value != null and ( type is 'object' or type is 'function' )

    isArray = ( object ) ->
        # This is lifted from underscore.js
        # Reason is that it was the only reason to add underscore to some
        # modules so this saves us more then a few kilobytes
        #
        if Array.isArray?
            Array.isArray( object )
        else
            Object.prototype.toString.call( object ) == "[object Array]"

    # Convenience function to retrieve a value from an object as if it was a
    # selector-like path
    #
    # Example: child.attributes.0.value
    #
    getValue = ( path, object, valueIfMissing = undefined ) ->
        if not object? then return valueIfMissing

        aPath = "#{path}".split( "." )
        value = object
        key   = aPath.shift()

        if aPath.length is 0
            # This is only a 1 deep check
            #
            value = value[ key.replace( "%2E", "." ) ]
            value = valueIfMissing if not value?

        else
            while value and key
                value = value[ key.replace( "%2E", "." ) ]
                value = valueIfMissing if not value?
                key   = aPath.shift()

            value = if 0 is aPath.length then value else valueIfMissing

        return value

    getAndCreate = ( path, object, defaultValue ) ->
        if not object? then return
        if not isObject( object ) then return

        aPath = "#{path}".split( "." )
        value = object
        key   = aPath.shift()

        while key
            key = key.replace( "%2E", "." )

            # Create non existing path element
            #
            if not value[ key ]?
                value[ key ] = {}

            if not value.hasOwnProperty(key)
                return

            if aPath.length is 0
                # Assign the default value to the newly created key if supplied
                #
                value[ key ] = defaultValue if defaultValue?

            # Process the next path element
            #
            value = value[ key ]
            key   = aPath.shift()

        return value

    setValue = ( path, object, value ) ->
        getAndCreate( path, object, value )
        return object

    ###*
    #   A small set of utility functions for working with objects
    #
    #   @author     mdoeswijk
    #   @module     objectUtils
    #   @version    0.1
    ###
    objectUtils =
        ###*
        #   Checks if the provided parameter is an array
        #
        #   @function isArray
        #   @param {Mixed}  object  The object to check
        #
        #   @return {Boolean} Returns true if the provided object is an array
        #
        ###
        isArray:        isArray

        ###*
        #   Retrieves a value from the target object using the provided path
        #
        #   @function getValue
        #   @param {String} path                The path to check on the object
        #   @param {Object} object              The object to retrieve the value from
        #   @param {Mixed}  [valueIfMissing]    Optional default value to return if the path isn't found
        #
        #   @return {Mixed} Returns the found value
        #
        ###
        getValue:       getValue

        ###*
        #   Retrieves a value from the target object using the provided path
        #   Creates the entire path if missing
        #
        #   @function getAndCreate
        #   @param {String} path                The path to check on the object
        #   @param {Object} object              The object to retrieve the value from
        #   @param {Mixed}  [valueIfMissing]    Optional default value to set return if the path isn't found
        #
        #   @return {Mixed} Returns the found and/or created value
        #
        ###
        getAndCreate:   getAndCreate

        ###*
        #   Sets a value on the target object using the provided path
        #
        #   @function setValue
        #   @param {String} path                The path to check on the object
        #   @param {Object} object              The object to retrieve the value from
        #   @param {Mixed}  value               The value to set on the object at the provided path
        #
        #   @return {Object} Returns the updated object
        #
        ###
        setValue:       setValue
 )
