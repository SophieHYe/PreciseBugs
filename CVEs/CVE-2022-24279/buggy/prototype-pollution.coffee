chai        = require "chai"
objectUtils = require "../lib/utils.js"

describe( "Prototype pollution", () ->
    describe( "#setValue()", () ->
        it( "Should not pollute value", () ->
            objectUtils.setValue( '__proto__.polluted', {}, true )

            chai.expect( global.polluted ).to.eql( undefined )
        )
    )
)
