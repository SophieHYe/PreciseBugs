
const {expect} = require('chai');

const bmoor = require('./index.js');

describe('Testing object setting/getting', function() {
	it('should have get working', function(){
		var t = {
				eins : 1,
				zwei: {
					drei: 3
				}
			};

		expect( bmoor.get(t,'eins') ).to.equal(1);
		expect( bmoor.get(t,'zwei.drei') ).to.equal(3);
	});

	it('should have get working with empty strings', function(){
		var t = {
				eins : 1,
				zwei: {
					drei: 3
				}
			};

		expect( bmoor.get(t,'') ).to.equal(t);
	});

	it('should have makeGetter working', function(){
		var t = {
				eins : 1,
				zwei: {
					drei: 3
				}
			},
			f1 = bmoor.makeGetter('eins'),
			f2 = bmoor.makeGetter('zwei.drei');

		expect( f1(t) ).to.equal(1);
		expect( f2(t) ).to.equal(3);
	});

	it('should have makeGetter working with empty strings', function(){
		var t = {
				eins : 1,
				zwei: {
					drei: 3
				}
			},
			f1 = bmoor.makeGetter('');

		expect( f1(t) ).to.equal(t);
	});

	it('should have set working', function(){
		var t = {};

		bmoor.set(t,'eins',1);
		bmoor.set(t,'zwei.drei',3);

		expect( t.eins ).to.equal(1);
		expect( t.zwei.drei ).to.equal(3);
	});

	it('should have makeSetter working', function(){
		var t = {},
			f1 = bmoor.makeSetter('eins'),
			f2 = bmoor.makeSetter('zwei.drei');

		f1(t,1);
		f2(t,3);

		expect( t.eins ).to.equal(1);
		expect( t.zwei.drei ).to.equal(3);
	});

	it('should have del working', function(){
		var t = {
			eins : 1,
			zwei: {
				drei: 3
			}
		};

		expect( bmoor.del(t,'eins') ).to.equal(1);
		expect( bmoor.del(t,'zwei.drei') ).to.equal(3);
		expect( t.eins ).to.not.exist;
		expect( t.zwei ).to.exist;
		expect( t.zwei.drei ).to.not.exist;
	});

	describe('::parse', function(){
		it('should parse an array correctly', function(){
			expect(bmoor.parse([1,2,3]))
			.to.deep.equal([1,2,3]);
		});

		it('should parse dot notation correctly', function(){
			expect(bmoor.parse('1.2.3'))
			.to.deep.equal(['1','2','3']);
		});

		it('should parse brackets correctly', function(){
			expect(bmoor.parse('[1][2][3]'))
			.to.deep.equal(['1','2','3']);
		});

		it('should parse brackets with quotes correctly', function(){
			expect(bmoor.parse('[\'1\']["2"][3]'))
			.to.deep.equal(['1','2','3']);
		});

		it('should parse mixed correctly', function(){
			expect(bmoor.parse('foo["bar"].ok[hello]'))
			.to.deep.equal(['foo','bar','ok','hello']);
		});
	});
});

describe('Testing object functions', function() {
	// mask
	it('should allow for the creation of object from a base object', function(){
		var t,
			v;

		function Foo( derp ){
			this.woot = derp;
		}

		Foo.prototype.bar = 'yay';

		t = new Foo();

		v = bmoor.object.mask( t );

		expect( v.bar ).to.equal( 'yay' );
	});


	// extend
	it('should allow for objects to be extended by other objects', function(){
		var t = {
			'foo'  : 1,
			'bar'  : 2 ,
			'woot' : 3
		};

		bmoor.object.extend( t, {
			'yay' : 'sup',
			'foo' : 'foo2'
		},{
			'woot' : '3!'
		});

		expect( t.foo ).to.equal( 'foo2' );
		expect( t.woot ).to.equal( '3!' );
	});
	// copy
	// TODO : yeah, need to do this one

	// equals
	// TODO : yeah, need to do this one

	// map
	it('should allow for the mapping of variables onto an object', function(){
		var o = {},
			t = bmoor.object.explode({
				hello:'world'
			},{
				'eins': 1, 
				'zwei': 2,
				'drei': 3,
				'foo.bar': 'woot',
				'help.me': o
			});

		expect( t.eins ).to.equal( 1 );
		expect( t.foo.bar ).to.equal( 'woot' );
		expect( t.hello ).to.equal( 'world' );
		expect( t.help.me ).to.equal( o );
	});

	it('should allow for a new variable to be created from a map', function(){
		var o = {},
			t = bmoor.object.explode({},
			{
				'eins': 1, 
				'foo.bar': 'woot',
				'hello.world': o
			});

		expect( t.eins, 1 );
		expect( t.foo.bar, 'woot' );
		expect( t.hello.world ).to.equal( o );
	});

	/*
	describe('override', function(){
		it( 'should prune old properties', function(){
			var t = {
					eins : 1,
					zwei : {
						foo : 1,
						bar : 2
					}
				}

			bmoor.object.override( t, {
				drei : 3
			});

			expect( t.eins ).to.not.to.exist;
			expect( t.zwei ).to.not.to.exist;
			expect( t.drei ).to.equal( 3 );
		});

		it( 'should handle shallow object copy', function(){
			var t = {
					eins : 1,
					zwei : {
						foo : 1,
						bar : 2
					}
				},
				o = {
					drei : {
						hello: 'world'
					}
				};

			bmoor.object.override( t, o );

			o.drei.hello = 'woot';

			expect( t.drei.hello ).to.equal( 'woot' );
		});

		it( 'should handle deep object copy', function(){
			var t = {
					eins : 1,
					zwei : {
						foo : 1,
						bar : 2
					}
				},
				o = {
					drei : {
						hello: 'world'
					}
				};

			bmoor.object.override( t, o, true );

			o.drei.hello = 'woot';

			expect( t.drei.hello ).to.equal( 'world' );
		});
	});
	*/
	it( 'should allow for data to be merged', function(){
		var t = {
			eins : 1,
			zwei : {
				foo : 1,
				bar : 2
			},
			drei : 3
		};

		bmoor.object.merge( t, {
			eins : 2,
			zwei : {
				foo : 2
			},
			fier : 4
		});

		expect( t.eins ).to.equal( 2 );
		expect( t.zwei ).to.to.exist;
		expect( t.zwei.foo ).to.equal( 2 );
		expect( t.drei ).to.equal( 3 );
		expect( t.fier ).to.equal( 4 );
	});
});

describe('Testing the test functions', function(){
	// isBoolean
	it('should be able to test booleans', function(){
		expect( bmoor.isBoolean(true) ).to.equal( true );
		expect( bmoor.isBoolean(false) ).to.equal( true );
		expect( bmoor.isBoolean(1) ).to.equal( false );
		expect( bmoor.isBoolean(0) ).to.equal( false );
	});
	// isDefined
	it('should be able to test for variables being defined', function(){
		var n = {},
			t;

		expect( bmoor.isDefined(true) ).to.equal( true );
		expect( bmoor.isDefined(false) ).to.equal( true );
		expect( bmoor.isDefined(1) ).to.equal( true );
		expect( bmoor.isDefined(0) ).to.equal( true );
		expect( bmoor.isDefined(n) ).to.equal( true );
		expect( bmoor.isDefined(t) ).to.equal( false );
	});
	// isUndefined
	it('should be able to test for variables being undefined', function(){
		var n = {},
			t;

		expect( bmoor.isUndefined(true) ).to.equal( false );
		expect( bmoor.isUndefined(false) ).to.equal( false );
		expect( bmoor.isUndefined(1) ).to.equal( false );
		expect( bmoor.isUndefined(0) ).to.equal( false );
		expect( bmoor.isUndefined(n) ).to.equal( false );
		expect( bmoor.isUndefined(t) ).to.equal( true );
	});
	// isArray
	it('should be able to test for variables being arrays', function(){
		expect( bmoor.isArray([]) ).to.equal( true );
		expect( bmoor.isArray({}) ).to.equal( false );
		expect( bmoor.isArray(1) ).to.equal( false );
		expect( bmoor.isArray({length:0}) ).to.equal( false );
		expect( bmoor.isArray('') ).to.equal( false );
	});
	// isArrayLike
	it('should be able to test for variables being array like', function(){
		expect( bmoor.isArrayLike([]) ).to.equal( true );
		expect( bmoor.isArrayLike({}) ).to.equal( false );
		expect( bmoor.isArrayLike(1) ).to.equal( false );
		expect( bmoor.isArrayLike({length:0}) ).to.equal( true );
		expect( bmoor.isArrayLike('') ).to.equal( false );
	});
	// isObject
	it('should be able to test for variables being an object', function(){
		function Temp(){}
		var t = new Temp();

		expect( bmoor.isObject([]) ).to.equal( true );
		expect( bmoor.isObject({}) ).to.equal( true );
		expect( bmoor.isObject(1) ).to.equal( false );
		expect( bmoor.isObject(false) ).to.equal( false );
		expect( bmoor.isObject(Temp) ).to.equal( false );
		expect( bmoor.isObject(t) ).to.equal( true );
		expect( bmoor.isObject('') ).to.equal( false );
	});
	// isFunction
	it('should be able to test for variables being a function', function(){
		function Temp(){}
		var t = new Temp();

		expect( bmoor.isFunction([]) ).to.equal( false );
		expect( bmoor.isFunction({}) ).to.equal( false );
		expect( bmoor.isFunction(1) ).to.equal( false );
		expect( bmoor.isFunction(false) ).to.equal( false );
		expect( bmoor.isFunction(Temp) ).to.equal( true );
		expect( bmoor.isFunction(t) ).to.equal( false );
		expect( bmoor.isFunction('') ).to.equal( false );
	});
	// isNumber
	it('should be able to test for variables being a number', function(){
		function Temp(){}
		var t = new Temp();

		expect( bmoor.isNumber([]) ).to.equal( false );
		expect( bmoor.isNumber({}) ).to.equal( false );
		expect( bmoor.isNumber(1) ).to.equal( true );
		expect( bmoor.isNumber(false) ).to.equal( false );
		expect( bmoor.isNumber(Temp) ).to.equal( false );
		expect( bmoor.isNumber(t) ).to.equal( false );
		expect( bmoor.isNumber('') ).to.equal( false );
	});
	
	// isString
	it('should be able to test for variables being a function', function(){
		function Temp(){}
		var t = new Temp();

		expect( bmoor.isString([]) ).to.equal( false );
		expect( bmoor.isString({}) ).to.equal( false );
		expect( bmoor.isString(1) ).to.equal( false );
		expect( bmoor.isString(false) ).to.equal( false );
		expect( bmoor.isString(Temp) ).to.equal( false );
		expect( bmoor.isString(t) ).to.equal( false );
		expect( bmoor.isString('') ).to.equal( true );
	});
	
	// isEmpty
	it('should be able to test for variables being a function', function(){
		var t;

		expect( bmoor.isEmpty([]) ).to.equal( true );
		expect( bmoor.isEmpty({}) ).to.equal( true );
		expect( bmoor.isEmpty(0) ).to.equal( false );
		expect( bmoor.isEmpty(t) ).to.equal( true );
		expect( bmoor.isEmpty(null) ).to.equal( false );
		expect( bmoor.isEmpty([0]) ).to.equal( false );
		expect( bmoor.isEmpty({'v':0}) ).to.equal( false );
	});
});
