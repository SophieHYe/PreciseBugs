<?php


namespace Neoan3\Apps;

use PHPUnit\Framework\TestCase;

class TemplateTest extends TestCase
{

    static function setUpBeforeClass(): void
    {
        define('path', dirname(__FILE__));
    }
    public function setUp(): void
    {
        TemplateFunctions::setDelimiter('{{','}}');
    }

    public function testEmbrace()
    {
        $str = '<div>{{easy}}-{{deep.value}}</div>';
        $sub = ['easy' => 'a', 'deep' => ['value' => 'b']];
        $this->assertSame('<div>a-b</div>', Template::embrace($str, $sub));
    }

    public function testEmbraceSanitation()
    {
        $array = [
            's/t' => 1, 's\t' => 1, 's+t' => 1, 's-t' => 1, 's{t' => 1
        ];
        $template = '<p n-for="array as key => item">{{item}}</p>';
        $res = '';
        foreach ($array as $item){
            $res .= '<p>'. $item .'</p>';
        }
        $this->assertSame($res, trim(Template::embrace($template, ['array' =>$array])));
    }

    public function testFlattenArray()
    {
        $testArray = ['one' => ['some' => 'value'], 'two' => ['item1', 'item2']];
        $shouldBe = [
            'one' => 'Array',
            'one.length' => 1,
            'one.some' => 'value',
            'two' => 'Array',
            'two.length' => 2,
            'two.0' => 'item1',
            'two.1' => 'item2'
        ];
        $this->assertSame($shouldBe, Template::flattenArray($testArray));
    }

    public function testTEmbrace()
    {
        $str = 'any <t>how</t>';
        $sub = ['how' => 'test'];
        $this->assertSame('any test', Template::tEmbrace($str, $sub));
    }

    public function testHardEmbrace()
    {
        $str = 'any [[how]]';
        $sub = ['how' => 'test'];
        $this->assertSame('any test', Template::hardEmbrace($str, $sub));
    }

    public function testEmbraceFromFile()
    {
        $sub = ['easy' => 'a', 'deep' => ['value' => 'b']];
        $t = Template::embraceFromFile('embrace.html', $sub);
        $this->assertSame('<div>a-b</div>', trim($t));
    }

    public function testConditional()
    {
        $str = '<custom-ele n-if="testKey">out</custom-ele>';
        $sub = ['testKey' => false];
        $this->assertEmpty(trim(Template::embrace($str, $sub)));
        $sub['testKey'] = true;
        $this->assertStringContainsString('custom-ele', Template::embrace($str, $sub));
    }

    public function testComplexEmbrace()
    {
        $array = ['items' => ['one', 'two'], 'sub' => [1, 2, 3], 'deeps' => ['one' => 1, 'two' => 2]];
        $t = Template::embraceFromFile('embraceComplex.html', $array);
        $this->assertStringContainsString('<li>1</li><li>2</li>', $t);
        $this->assertIsString($t);
    }

    public function testNestedCondition()
    {
        $array = ['deeps' => [['number' => 1], ['number' => 2]]];
        $t = Template::embraceFromFile('nestedCondition.html', $array);
        $this->assertStringContainsString('exists', $t);
        $this->assertStringNotContainsString('not', $t);
    }

    public function testEmbraceTypes()
    {
        $array = ['string' => 'String', 'number' => 2, 'boolean' => true, 'falseExpression' => false];
        $t = Template::embraceFromFile('typeTest.html', $array);
        $expectedResult = ['String', 'Boolean', 'yes'];
        foreach ($expectedResult as $true) {
            $this->assertStringContainsString($true, $t);
        }
        // cross-check
        $this->assertStringNotContainsString('no', $t);
    }

    public function testCallback()
    {
        $array = [
            'some' => 'value'
        ];
        TemplateFunctions::registerClosure('myFunc',function($x){
            return strtoupper($x);
        });
        $t = Template::embraceFromFile('callback.html', $array);

        $this->assertStringContainsString('<p>VALUE</p>', $t);
        $this->assertStringContainsString('<li>show me</li>', $t);
    }
    public function testNoCallback()
    {
        $array = [
            'items' => ['one', 'two'],
            'som' => 'value'
        ];
        TemplateFunctions::registerClosure('myFunc',function($x) {
            return $x . '-shouldnt';
        });
        $t = Template::embraceFromFile('callback.html', $array);
        $this->assertStringContainsString('myFunc(some)',$t);

    }

    public function testCallbackDeep()
    {
        $array = [
            'items' => ['one', 'two'],
            'some' => 'value'
        ];
        TemplateFunctions::registerClosure('deepFunc',function($input){
            return $input . '!';
        });
        TemplateFunctions::registerClosure('myFunc',function ($x) {
            return strtoupper($x);
        });

        $t = Template::embraceFromFile('callback.html', $array);
        $this->assertStringContainsString('one!', $t);
        $this->assertStringContainsString('VALUE', $t);

    }

    public function testCustomDelimiter()
    {
        $array = ['test' => '[value]'];
        TemplateFunctions::setDelimiter('<!--','-->');
        $t = Template::embraceFromFile('callback.html', $array);
        $this->assertStringContainsString('[value]', $t);
    }
    public function testEvaluateTypedConditionNull()
    {
        $array = ['test' => null];
        $t = Template::embrace('<p n-if="test === false">some</p>', $array);
        $this->assertStringContainsString('p>some', $t);
    }
}
