'use strict';

/* eslint no-unused-vars: ["error", { "varsIgnorePattern": "should" }]*/

let path = require('../src/path'),
    should = require('should'),
    assert = require('assert'),
    doc = {};

describe('doc-path Module', function() {
    describe('evaluatePath', function() {
        beforeEach(function() {
            doc = {};
        });

        it('should get a non-nested property that exists', function(done) {
            doc.testProperty = 'testValue';
            let returnVal = path.evaluatePath(doc, 'testProperty');
            returnVal.should.equal('testValue');
            done();
        });

        it('should return null if the non-nested property does not exist', function(done) {
            let returnVal = path.evaluatePath(doc, 'testProperty');
            assert.equal(returnVal, null);
            done();
        });

        it('should get a non-nested property that exists', function(done) {
            doc.testProperty = {
                testProperty2: 'testValue'
            };
            let returnVal = path.evaluatePath(doc, 'testProperty.testProperty2');
            returnVal.should.equal('testValue');
            done();
        });

        it('should return null if the nested property does not exist', function(done) {
            let returnVal = path.evaluatePath(doc, 'testProperty.testProperty2');
            assert.equal(returnVal, null);
            done();
        });

        it('should work with multiple accesses', (done) => {
            doc = {
                testProperty: {
                    testProperty2: 'testVal'
                },
                testProperty3: 'testVal2'
            };
            let returnVal = path.evaluatePath(doc, 'testProperty.testProperty2');
            assert.equal(returnVal, 'testVal');
            returnVal = path.evaluatePath(doc, 'testProperty3');
            assert.equal(returnVal, 'testVal2');
            done();
        });

        it('should work with equal key value', (done) => {
            doc = {
                testProperty: {
                    testProperty2: 'testVal'
                },
                'testProperty.testProperty2': 'testVal2'
            };
            let returnVal = path.evaluatePath(doc, 'testProperty.testProperty2');
            assert.equal(returnVal, 'testVal2');
            done();
        });

        it('should work with a nested array of objects', (done) => {
            doc = {
                features: [
                    { feature: 'A/C' },
                    { feature: 'Radio' }
                ]
            };
            let returnVal = path.evaluatePath(doc, 'features.feature');
            returnVal.should.deepEqual(['A/C', 'Radio']);
            done();
        });

        it('should work with multiple levels of nested arrays containing objects', (done) => {
            doc = {
                features: [
                    {
                        packages: [
                            {name: 'Base'},
                            {name: 'Premium'}
                        ]
                    },
                    {
                        packages: [
                            {name: 'Convenience'},
                            {name: 'Premium'},
                            5
                        ]
                    }
                ]
            };
            let returnVal = path.evaluatePath(doc, 'features.packages.name');
            returnVal.should.deepEqual([['Base', 'Premium'], ['Convenience', 'Premium', undefined]]);
            done();
        });

        it('should work with an array of objects', (done) => {
            doc = [
                { feature: 'A/C' },
                { feature: 'Radio' }
            ];
            let returnVal = path.evaluatePath(doc, 'feature');
            returnVal.should.deepEqual(['A/C', 'Radio']);
            done();
        });
    });

    describe('setPath', () => {
        beforeEach(() => {
            doc = {};
        });

        it('should get a non-nested property that exists', function(done) {
            let returnVal = path.setPath(doc, 'testProperty', 'null');
            assert.equal(returnVal, doc);
            done();
        });

        it('should return null if the non-nested property does not exist', function(done) {
            try {
                doc = null;
                assert.equal(doc, null);
                path.setPath(doc, 'testProperty', 'null');
            } catch (err) {
                err.message.should.equal('No document was provided.');
                done();
            }
        });

        it('should get a non-nested property that exists', function(done) {
            let returnVal = path.setPath(doc, 'testProperty.testProperty2', 'testValue');
            assert.equal(returnVal, doc);
            done();
        });

        it('should return null if the nested property does not exist', function(done) {
            try {
                doc = null;
                assert.equal(doc, null);
                path.setPath(doc, 'testProperty.test', 'null');
            } catch (err) {
                err.message.should.equal('No document was provided.');
                done();
            }
        });

        it('should work with multiple accesses', (done) => {
            let returnVal = path.setPath(doc, 'testProperty.testProperty2', 'testVal');
            assert.equal(returnVal, doc);
            returnVal = path.setPath(doc, 'testProperty.testProperty2', 'testVal2');
            assert.equal(returnVal, doc);
            done();
        });

        it('should work an array of objects', (done) => {
            doc = {
                features: [
                    { feature: 'A/C' },
                    { feature: 'Radio' }
                ]
            };

            let returnVal = path.setPath(doc, 'features.feature', 'None');
            returnVal.should.deepEqual({
                features: [
                    { feature: 'None' },
                    { feature: 'None' }
                ]
            });
            done();
        });

        it('should work an array of objects', (done) => {
            doc = {
                features: [
                    {
                        packages: [
                            {name: 'Base'},
                            {name: 'Premium'}
                        ]
                    },
                    {
                        packages: [
                            {name: 'Convenience'},
                            {name: 'Premium'}
                        ]
                    }
                ]
            };

            let returnVal = path.setPath(doc, 'features.packages.name', 'None');
            returnVal.should.deepEqual({
                features: [
                    {
                        packages: [
                            {name: 'None'},
                            {name: 'None'}
                        ]
                    },
                    {
                        packages: [
                            {name: 'None'},
                            {name: 'None'}
                        ]
                    }
                ]
            });
            done();
        });

        it('should protect against prototype pollution via __proto__', (done) => {
            doc = {};
            path.setPath(doc, '__proto__.polluted', 'yes');
            assert.equal(doc.__proto__.polluted, undefined);
            assert.equal(doc.polluted, undefined);
            assert.equal({}.polluted, undefined);
            done();
        });
    });
});
