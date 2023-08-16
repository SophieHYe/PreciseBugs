var should = require('chai').should();
var expect = require('chai').expect;
var Config = require('./conf-cfg-ini');

var testData = [
    ";comment\n[SectionA]\na=1\nb=2\n",
    ";comment\r\n[SectionA]\r\na=1\r\nb=2\r\n",
    "stray=true;comment\r\n[SectionA]\r\na=1\r\nb=2\r\n"
];

describe('Config', function() {
    it('should be defined', function () {
        should.exist(Config);
    });
    
    it('setOptions should overwrite options', function () {
        var config = new Config();
        config.options.lineEnding = "\n";
        config.options.trimLines = true;
        config.setOptions({lineEnding: "\r\n", trimLines: undefined});
        expect(config.options.lineEnding).to.equal("\r\n");
        expect(config.options.trimLines).to.equal(true);
    });
    
    it('detectLineEndings should detect windows style (\\r\\n)', function () {
        var config = new Config();
        config.detectLineEnding("line1\r\nline2\r\n").should.equal("\r\n");
    });
    
    it('detectLineEndings should detect unix style (\\n)', function () {
        var config = new Config();
        config.detectLineEnding("line1\nline2\n").should.equal("\n");
    });
    
    it('detectLineEndings should detect mac style (\\r)', function () {
        var config = new Config();
        config.detectLineEnding("line1\rline2\r").should.equal("\r");
    });
    
    it('detectLineEndings should detect wtf style (\\n\\r)', function () {
        var config = new Config();
        config.detectLineEnding("line1\n\rline2\n\r").should.equal("\n\r");
    });
    
    it('decode should return a object', function () {
        var config = new Config();
        for(var i = 0; i < testData.length; i++){
            config.decode(testData[i]).should.be.a('object');
        }
    });
    
    it('encode return should a string', function () {
        var config = new Config();
        config.encode({'Section':{'a': 1}}).should.be.a('string');
        config.encode({'a':1}).should.be.a('string');
        config.encode({}).should.be.a('string');
    });
    
    it('decode should handle attributes without section', function () {
        var config = new Config();
        config.options.lineEnding = "\n";
        var result = config.decode("stray=foo\n[Section1]\na=b\n");
        expect(result.stray).to.equal("foo");
    });
    
    it('encode should handle attributes without section', function () {
        var config = new Config();
        config.options.lineEnding = "\n";
        var encoded = config.encode({stray:'foo','SectionA':{'a': 1}});
        var decoded = config.decode(encoded);
        expect(decoded.stray).to.equal("foo");
    });
    
    it('decode should return object with same attributes', function () {
        var data = ";comment\n[SectionA]\nkey=value\n";
        var config = new Config();
        config.options.lineEnding = config.detectLineEnding(data);
        var result = config.decode(data);
        result.should.be.a('object');
        should.exist(result.SectionA);
        result.SectionA.key.should.equal("value");
    });
    
    it('decode>encode>decode>encode return should produce consistent results', function () {
        for(var i = 0; i < testData.length; i++){
            var data = testData[i];
            var config = new Config();
            config.options.lineEnding = config.detectLineEnding(data);
            var decoded1 = config.decode(data);
            var encoded1 = config.encode(decoded1);
            var decoded2 = config.decode(encoded1);
            var encoded2 = config.encode(decoded2);
            expect(encoded1).to.equal(encoded2);
            expect(decoded1).to.deep.equal(decoded2);
        }
    });
    
    it('decode should be able to handle multiple comment identifier', function () {
        var config = new Config();
        config.options.lineEnding = "\n";
        config.options.commentIdentifiers = [';','//','#'];
        var result = config.decode(";comment1\n//comment2\n#comment3\n");
        expect(result).to.deep.equal({});
    });
    
    it('decode should be able to handle custom assign identifier', function () {
        var config = new Config();
        config.options.lineEnding = "\n";
        config.options.assignIdentifier = ":";
        var result = config.decode("[Section]\nfoo:bar\n");
        should.exist(result.Section);
        expect(result.Section.foo).to.equal("bar");
    });

    it('decode should prevent prototype pollution attacks', function () {
        var config = new Config();
        config.options.lineEnding = "\n";
        config.options.assignIdentifier = ":";
        var result = config.decode("[__proto__]\nfoo:bar\n");
        should.not.exist(result.__proto__.foo);
        result = config.decode("[Section]\n__proto__:bar\n");
        expect(result.Section.__proto__).to.not.equal("bar");
    });

    it('valueTrim should trim custom chars', function () {
        var config = new Config();
        expect(config.valueTrim('"Te"s"t"', '"')).to.equal('Te"s"t');
        expect(config.valueTrim('"Te"s"t"', '')).to.equal('"Te"s"t"');
        expect(config.valueTrim('"Te"s"t"', '#')).to.equal('"Te"s"t"');
        expect(config.valueTrim('""Te"s"t""', '""')).to.equal('"Te"s"t"');
        expect(config.valueTrim('[Te"s"t]', '[]')).to.equal('Te"s"t');
    })

    it('valueIdentifiers should trimed or added', function () {
        var data = "[SectionA]\nkey1='val1'\nkey2='val2'\n";
        var config = new Config();
        config.options.lineEnding = "\n";
        config.options.valueIdentifier = "'"
        var result = config.decode(data);
        expect(result.SectionA.key1).to.equal("val1");
        expect(result.SectionA.key2).to.equal("val2");
        var data2 = config.encode(result);
        expect(data2).to.equal(data);
    })

    it('ignoreMultipleAssignIdentifier should ignore multiple assing identifiers', function () {
        var data = "a\t1\nb\t\t2\nc\t3\t\n";
        var config = new Config();
        config.options.assignIdentifier = '\t'
        config.options.lineEnding = "\n";
        config.options.ignoreMultipleAssignIdentifier = true;
        config.options.trimLines = false;
        var result = config.decode(data);
        expect(result.a).to.equal("1");
        expect(result.b).to.equal("2");
        expect(result.c).to.equal("3\t");
    })
});
