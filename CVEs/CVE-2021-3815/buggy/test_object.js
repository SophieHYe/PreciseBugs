var utils = require('../dist/utils.js');
var test = utils.test;
var obj = utils.object;

describe('object', function() {
    describe('assign', function() {
        var f = obj.assign;
        it('test same instance', function() {
            var o1 = { a:1, b:2, c:3 };
            var r = f(o1, o1);
            test.assertEqual(r, { a:1, b:2, c:3 });
        });
        it('test diff instance', function() {
            var o1 = { a:1, b:2, c:3 };
            var o2 = { d:4, e:5, f:6 };
            var r = f(o1, o2);
            test.assertTrue(o1 !== o2);
            test.assertTrue(o1 === r);
            test.assertTrue(o2 !== r);
            test.assertEqual(r, { a:1, b:2, c:3, d:4, e:5, f:6 });
        });
        it('test multiple instances', function() {
            var o1 = { a:1, b:2, c:3 };
            var o2 = { d:4, e:5, f:6 };
            var o3 = { g:7, h:8, i:9 };
            var o4 = { a:0, e:0, i:0 };
            var r = f(o1, o2, o3, o4);
            test.assertEqual(r, { a:0, b:2, c:3, d:4, e:0, f:6, g:7, h:8, i:0 });
        });
        it('test props overwrite', function() {
            var o1 = { a:1, b:2, c:3 };
            var o2 = { a:4, b:5, c:6 };
            var r = f(o1, o2);
            test.assertEqual(r['a'], 4);
            test.assertEqual(r['b'], 5);
            test.assertEqual(r['c'], 6);
            test.assertEqual(r, { a:4, b:5, c:6 });
        });
    });
    describe('clean', function() {
        var f = obj.clean;
        it('test soft', function() {
            var o1 = { a:1, b:undefined, c:3, d:null, e:'', f:0, g:NaN, h:false, i:[null], j:{ k:null } };
            var r = f(o1);
            test.assertEqual(obj.length(r), 7);
            test.assertEqual(r, { a:1, c:3, e:'', f:0, h:false, i:[null], j:{ k:null } });
        });
        it('test hard', function() {
            var o1 = { a:1, b:undefined, c:3, d:null, e:'', f:0, g:NaN, h:false, i:[null], j:{ k:null } };
            var r = f(o1, true);
            test.assertEqual(obj.length(r), 4);
            test.assertEqual(r, { a:1, c:3, f:0, h:false });
        });
    });
    describe('clone', function() {
        var f = obj.clone;
        it('test new instance and key/values cloned', function() {
            var o = { a:1, b:2, c:3 };
            var r = f(o);
            test.assertFalse(r === o);
            test.assertEqual(r, o);
        });
        it('test array property cloned', function() {
            var a = { a:[1, 2, 3] };
            var o = { a:a };
            var r = f(o);
            test.assertFalse(r['a'] === a);
            test.assertEqual(r['a'], a);
        });
        it('test boolean property cloned', function() {
            var b = true;
            var o = { b:b };
            var r = f(o);
            test.assertTrue(r['b'] === b);
            test.assertEqual(r['b'], b);
        });
        it('test date property cloned', function() {
            var d = new Date(1900);
            var o = { d:d };
            var r = f(o);
            test.assertFalse(r['d'] === d);
            test.assertEqual(r['d'], d);
        });
        it('test object property cloned', function() {
            var a = { a:1, b:2, c:3 };
            var o = { o:a };
            var r = f(o);
            test.assertFalse(r['o'] === a);
            test.assertEqual(r['o'], a);
        });
        it('test number property cloned', function() {
            var n = 123;
            var o = { n:n };
            var r = f(o);
            test.assertTrue(r['n'] === n);
            test.assertEqual(r['n'], n);
        });
        it('test string property cloned', function() {
            var s = 'ok';
            var o = { s:s };
            var r = f(o);
            test.assertTrue(r['s'] === s);
            test.assertEqual(r['s'], s);
        });
    });
    // describe('decodeBase64', function() {
    //     it('TODO', function() {
    //         test.assertTrue(true);
    //     });
    // });
    // describe('decodeJSON', function() {
    //     it('TODO', function() {
    //         test.assertTrue(true);
    //     });
    // });
    // describe('encodeBase64', function() {
    //     it('TODO', function() {
    //         test.assertTrue(true);
    //     });
    // });
    // describe('encodeJSON', function() {
    //     it('TODO', function() {
    //         test.assertTrue(true);
    //     });
    // });
    describe('equals', function() {
        var f = obj.equals;
        var a = {
            foods:{
                fruits: ["orange", "lemon"]
            },
            numbers:{
                "Decimal": {
                    tens:[40, 50, 20],
                    hundreds:[300, 500]
                },
                "Roman": {
                    small:["I", "VII", "IX"],
                    hundreds:[300, 500]
                },
            },
            bikes: ["recumbent", "upright"]
        };
        var b = {
            foods:{
                fruits:["orange", "lemon"]
            },
            numbers:{
                "Decimal": {
                    tens:[40, 50, 20],
                    hundreds:[300, 500]
                },
                "Roman": {
                    small:["I", "VII", "IX"],
                    hundreds:[300, 500]
                },
            },
            bikes: ["recumbent", "upright"]
        };
        var c = {
            foods:{
                fruits:["orange", "lemon"]
            },
            numbers:{
                "Decimal": {
                    tens:[40, 50, 20],
                    hundreds:[300, 700]
                },
                "Roman": {
                    small:["I", "VII", "IX"],
                    large:["MCXVII", "MCXXVIII", "MMVIII"]
                },
            },
            bikes: ["recumbent", "upright"]
        };
        it('test simple', function() {
            test.assertTrue(f(a, a));
            test.assertTrue(f(a, b));
            test.assertFalse(f(a, c));
            test.assertFalse(f(b, c));
        });
        it('test objects containing numbers', function() {
            var a = { x:0.0, y:0.0 };
            var b = { x:0.0, y:0.0000000001 };
            var c = { x:0.0, y:0.0001 };
            test.assertTrue(f(a, b));
            test.assertFalse(f(a, c));
        });
        it('test objects containing different keys', function() {
            var a = { x:0.0, y:0.0 };
            var b = { x:0.0, y:0.0, z:0.0 };
            var c = { x:0.0 };
            test.assertFalse(f(a, b));
            test.assertFalse(f(a, c));
            test.assertFalse(f(b, c));
        });
    });
    describe('keys', function() {
        var f = obj.keys;
        it('test no keys', function() {
            var o1 = {};
            var r = f(o1);
            test.assertEqual(r, []);
        });
        it('test keys unsorted', function() {
            var o1 = { a:1, b:2, c:3 };
            var r = f(o1);
            test.assertTrue(r.indexOf('a') !== -1);
            test.assertTrue(r.indexOf('b') !== -1);
            test.assertTrue(r.indexOf('c') !== -1);
        });
        it('test keys sorted', function() {
            var o1 = { a:1, b:2, c:3 };
            var r = f(o1, true);
            test.assertEqual(r, ['a', 'b', 'c']);
        });
    });
    describe('keypath', function() {
        it('test object', function() {
            test.assertObject(obj.keypath);
        });
    });
    describe('keypath.get', function() {
        var f = obj.keypath.get;
        var o = {
            a: 1,
            b: {
                y: 'ok'
            },
            c: {},
            d: 'ok',
            e: {
                f: {
                    g: 'ok'
                }
            }
        };
        var r;
        it('test on 1 non-existing key', function() {
            r = f(o, 'z');
            test.assertEqual(r, undefined);
        });
        it('test on 1 non-existing key default value', function() {
            r = f(o, 'z', 0);
            test.assertEqual(r, 0);
        });
        it('test on 1 existing key', function() {
            r = f(o, 'a');
            test.assertEqual(r, 1);
        });
        it('test on 2 non-existing keys', function() {
            r = f(o, 'a.x');
            test.assertEqual(r, undefined);
        });
        it('test on 2 non-existing keys default value', function() {
            r = f(o, 'a.x', 2);
            test.assertEqual(r, 2);
        });
        it('test on 2 existing keys', function() {
            r = f(o, 'b.y');
            test.assertEqual(r, 'ok');
        });
        it('test on 3 non-existing keys', function() {
            r = f(o, 'x.y.z');
            test.assertEqual(r, undefined);
        });
        it('test on 3 non-existing keys default value', function() {
            r = f(o, 'x.y.z', 1000);
            test.assertEqual(r, 1000);
        });
        it('test on 3 existing keys', function() {
            r = f(o, 'e.f.g');
            test.assertEqual(r, 'ok');
        });
    });
    describe('keypath.set', function() {
        var f = obj.keypath.set;
        var o = {
            a: {},
            b: {},
            c: {}
        };
        it('test on 1 non-existing key', function() {
            f(o, 'd', 'ok 1');
            test.assertEqual(o['d'], 'ok 1');
        });
        it('test on 1 existing key', function() {
            f(o, 'd', 'ok 2');
            test.assertEqual(o['d'], 'ok 2');
        });
        it('test on 2 non-existing keys', function() {
            f(o, 'e.f', 'ok 1');
            test.assertEqual(o['e']['f'], 'ok 1');
        });
        it('test on 2 existing keys', function() {
            f(o, 'e.f', 'ok 2');
            test.assertEqual(o['e']['f'], 'ok 2');
        });
        it('test on 3 non-existing keys', function() {
            f(o, 'e.f.g', 'ok 1');
            test.assertEqual(o['e']['f']['g'], 'ok 1');
        });
        it('test on 3 existing keys', function() {
            f(o, 'e.f.g', 'ok 2');
            test.assertEqual(o['e']['f']['g'], 'ok 2');
        });
    });
    describe('length', function() {
        var f = obj.length;
        it('test empty object', function() {
            var o1 = {};
            var r = f(o1);
            test.assertEqual(r, 0);
        });
        it('test simple object', function() {
            var o1 = { a:1, b:2, c:3 };
            var r = f(o1);
            test.assertEqual(r, 3);
        });
    });
    describe('map', function() {
        var f = obj.map;
        it('test all items', function() {
            var o1 = { a:1, b:2, c:3 };
            var keys = obj.keys(o1);
            var values = obj.values(o1);
            var r = f(o1, function(val, key, item){
                test.assertTrue(item === o1);
                test.assertTrue(keys.indexOf(key) !== -1);
                test.assertTrue(values.indexOf(val) !== -1);
            });
        });
        it('test result', function() {
            var o1 = { a:1, b:2, c:3 };
            var r = f(o1, function(val, key, item){
                return (val * 2);
            });
            test.assertEqual(r, { a:2, b:4, c:6 });
        });
    });
    describe('merge', function() {
        var f = obj.merge;
        it('test same instance', function() {
            var o1 = { a:1, b:2, c:3 };
            var r = f(o1, o1);
            test.assertFalse(o1 === r);
            test.assertEqual(r, { a:1, b:2, c:3 });
        });
        it('test diff instance', function() {
            var o1 = { a:1, b:2, c:3 };
            var o2 = { d:4, e:5, f:6 };
            var r = f(o1, o2);
            test.assertTrue(o1 !== o2);
            test.assertTrue(o1 !== r);
            test.assertTrue(o2 !== r);
            test.assertEqual(r, { a:1, b:2, c:3, d:4, e:5, f:6 });
        });
        it('test multiple instances', function() {
            var o1 = { a:1, b:2, c:3 };
            var o2 = { d:4, e:5, f:6 };
            var o3 = { g:7, h:8, i:9 };
            var o4 = { a:0, e:0, i:0 };
            var r = f(o1, o2, o3, o4);
            test.assertEqual(r, { a:0, b:2, c:3, d:4, e:0, f:6, g:7, h:8, i:0 });
        });
        it('test props overwrite', function() {
            var o1 = { a:1, b:2, c:3 };
            var o2 = { a:4, b:5, c:6 };
            var r = f(o1, o2);
            test.assertEqual(r, { a:4, b:5, c:6 });
        });
    });
    describe('search', function() {
        var f = obj.search;
        it('test empty filter', function() {
            var objs = [
                { a:1, b:2, c:null, d:'ok', e:false, f:true },
                { a:1, b:2, c:null, d:'ok', e:false, f:true },
                { a:1, b:2, c:null, d:'ok', e:false, f:true },
                { a:1, b:2, c:null, d:'ok', e:false, f:true }
            ];
            var r = f(objs, {});
            test.assertEqual(r, objs);
        });
        it('test simple filter result found', function() {
            var objs = [
                { a:1, b:2, c:null, d:'ok', e:false, f:true },
                { a:2, b:2, c:null, d:'ok', e:false, f:true },
                { a:3, b:2, c:null, d:'ok', e:false, f:true },
                { a:4, b:2, c:null, d:'ok', e:false, f:true }
            ];
            var r = f(objs, { a:3 });
            test.assertEqual(r, [objs[2]]);
        });
        it('test simple filter result not found', function() {
            var objs = [
                { a:1, b:2, c:null, d:'ok', e:false, f:true },
                { a:2, b:2, c:null, d:'ok', e:false, f:true },
                { a:3, b:2, c:null, d:'ok', e:false, f:true },
                { a:4, b:2, c:null, d:'ok', e:false, f:true }
            ];
            var r = f(objs, { a:5 });
            test.assertEqual(r, []);
        });
        it('test simple filter multiple result found', function() {
            var objs = [
                { a:1, b:2, c:null, d:'ok', e:false, f:true },
                { a:2, b:2, c:null, d:'ok', e:false, f:true },
                { a:1, b:2, c:null, d:'ok', e:false, f:false },
                { a:2, b:2, c:null, d:'ok', e:false, f:true },
                { a:1, b:2, c:null, d:'ok', e:false, f:true },
                { a:2, b:2, c:null, d:'ok', e:false, f:true }
            ];
            var r = f(objs, { a:1, f:true });
            test.assertEqual(r, [objs[0], objs[4]]);
        });
    });
    describe('values', function() {
        var f = obj.values;
        it('test no values', function() {
            var o1 = {};
            var r = f(o1);
            test.assertEqual(r, []);
        });
        it('test values unsorted', function() {
            var o1 = { a:1, b:2, c:3 };
            var r = f(o1);
            test.assertTrue(r.indexOf(1) !== -1);
            test.assertTrue(r.indexOf(2) !== -1);
            test.assertTrue(r.indexOf(3) !== -1);
        });
        it('test values sorted', function() {
            var o1 = { a:1, b:2, c:3 };
            var r = f(o1, true);
            test.assertEqual(r, [1, 2, 3]);
        });
    });
});