<?php

namespace TraderInteractive;

use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StdClass;
use TraderInteractive\Exceptions\FilterException;

/**
 * @coversDefaultClass \TraderInteractive\Filterer
 * @covers ::<private>
 */
final class FiltererTest extends TestCase
{
    public function setUp()
    {
        Filterer::setFilterAliases(Filterer::DEFAULT_FILTER_ALIASES);
    }

    /**
     * @test
     * @covers ::filter
     * @dataProvider provideValidFilterData
     *
     * @param array $spec  The filter specification to be use.
     * @param array $input The input to be filtered.
     * @param array $options The filterer options
     * @param array $expectedResult The expected filterer result.
     */
    public function filter(array $spec, array $input, array $options, array $expectedResult)
    {
        $this->assertSame($expectedResult, Filterer::filter($spec, $input, $options));
    }

    public function provideValidFilterData() : array
    {
        return [
            'not required field not present' => [
                'spec' => ['fieldOne' => ['required' => false]],
                'input' => [],
                'options' => [],
                'result' => [true, [], null, []],
            ],
            'Required With A Default Without Input' => [
                'spec' => ['fieldOne' => ['required' => true, 'default' => 'theDefault']],
                'input' => [],
                'options' => [],
                'result' => [true, ['fieldOne' => 'theDefault'], null, []],
            ],
            'Required With A Null Default Without Input' => [
                'spec' => ['fieldOne' => ['required' => true, 'default' => null]],
                'input' => [],
                'options' => [],
                'result' => [true, ['fieldOne' => null], null, []],
            ],
            'Required With A Default With Input' => [
                'spec' => ['fieldOne' => ['required' => true, 'default' => 'theDefault']],
                'input' => ['fieldOne' => 'notTheDefault'],
                'options' => [],
                'result' => [true, ['fieldOne' => 'notTheDefault'], null, []],
            ],
            'Not Required With A Default Without Input' => [
                'spec' => ['fieldOne' => ['default' => 'theDefault']],
                'input' => [],
                'options' => [],
                'result' => [true, ['fieldOne' => 'theDefault'], null, []],
            ],
            'Not Required With A Default With Input' => [
                'spec' => ['fieldOne' => ['default' => 'theDefault']],
                'input' => ['fieldOne' => 'notTheDefault'],
                'options' => [],
                'result' => [true, ['fieldOne' => 'notTheDefault'], null, []],
            ],
            'Required Fail' => [
                'spec' => ['fieldOne' => ['required' => true]],
                'input' => [],
                'options' => [],
                'result' => [false, null, "Field 'fieldOne' was required and not present", []],
            ],
            'Required Default Pass' => [
                'spec' => ['fieldOne' => []],
                'input' => [],
                'options' => [],
                'result' => [true, [], null, []],
            ],
            'requiredDefaultFail' => [
                'spec' => ['fieldOne' => []],
                'input' => [],
                'options' => ['defaultRequired' => true],
                'result' => [false, null, "Field 'fieldOne' was required and not present", []],
            ],
            'filterPass' => [
                'spec' => ['fieldOne' => [['floatval']]],
                'input' => ['fieldOne' => '3.14'],
                'options' => [],
                'result' => [true, ['fieldOne' => 3.14], null, []],
            ],
            'filterDefaultShortNamePass' => [
                'spec' => ['fieldOne' => [['float']]],
                'input' => ['fieldOne' => '3.14'],
                'options' => [],
                'result' => [true, ['fieldOne' => 3.14], null, []],
            ],
            'filterFail' => [
                'spec' => ['fieldOne' => [['\TraderInteractive\FiltererTest::failingFilter']]],
                'input' => ['fieldOne' => 'valueOne'],
                'options' => [],
                'result' => [
                    false,
                    null,
                    "Field 'fieldOne' with value 'valueOne' failed filtering, message 'i failed'",
                    [],
                ],
            ],
            'chainPass' => [
                'spec' => ['fieldOne' => [['trim', 'a'], ['floatval']]],
                'input' => ['fieldOne' => 'a3.14'],
                'options' => [],
                'result' => [true, ['fieldOne' => 3.14], null, []],
            ],
            'chainFail' => [
                'spec' => ['fieldOne' => [['trim'], ['\TraderInteractive\FiltererTest::failingFilter']]],
                'input' => ['fieldOne' => 'the value'],
                'options' => [],
                'result' => [
                    false,
                    null,
                    "Field 'fieldOne' with value 'the value' failed filtering, message 'i failed'",
                    [],
                ],
            ],
            'multiInputPass' => [
                'spec' => ['fieldOne' => [['trim']], 'fieldTwo' => [['strtoupper']]],
                'input' => ['fieldOne' => ' value', 'fieldTwo' => 'bob'],
                'options' => [],
                'result' => [true, ['fieldOne' => 'value', 'fieldTwo' => 'BOB'], null, []],
            ],
            'multiInputFail' => [
                'spec' => [
                    'fieldOne' => [['\TraderInteractive\FiltererTest::failingFilter']],
                    'fieldTwo' => [['\TraderInteractive\FiltererTest::failingFilter']],
                ],
                'input' => ['fieldOne' => 'value one', 'fieldTwo' => 'value two'],
                'options' => [],
                'result' => [
                    false,
                    null,
                    "Field 'fieldOne' with value 'value one' failed filtering, message 'i failed'\n"
                    . "Field 'fieldTwo' with value 'value two' failed filtering, message 'i failed'",
                    [],
                ],
            ],
            'emptyFilter' => [
                'spec' => ['fieldOne' => [[]]],
                'input' => ['fieldOne' => 0],
                'options' => [],
                'result' => [true, ['fieldOne' => 0], null, []],
            ],
            'unknownsAllowed' => [
                'spec' => [],
                'input'=> ['fieldTwo' => 0],
                'options' => ['allowUnknowns' => true],
                'result' => [true, [], null, ['fieldTwo' => 0]],
            ],
            'unknownsNotAllowed' => [
                'spec' => [],
                'input' => ['fieldTwo' => 0],
                'options' => [],
                'result' => [false, null, "Field 'fieldTwo' with value '0' is unknown", ['fieldTwo' => 0]],
            ],
            'objectFilter' => [
                'spec' => ['fieldOne' => [[[$this, 'passingFilter']]]],
                'input' => ['fieldOne' => 'foo'],
                'options' => [],
                'result' => [true, ['fieldOne' => 'fooboo'], null, []],
            ],
            'filterWithCustomError' => [
                'spec' => [
                    'fieldOne' => [
                        'error' => 'My custom error message',
                        ['\TraderInteractive\FiltererTest::failingFilter'],
                    ],
                ],
                'input' => ['fieldOne' => 'valueOne'],
                'options' => [],
                'result' => [false, null, 'My custom error message', []],
            ],
            'filterWithCustomErrorContainingValuePlaceholder' => [
                'spec' => [
                    'fieldOne' => [
                        'error' => "The value '{value}' is invalid.",
                        ['uint'],
                    ],
                ],
                'input' => ['fieldOne' => 'abc'],
                'options' => [],
                'result' => [false, null, "The value 'abc' is invalid.", []],
            ],
            'arrayizeAliasIsCalledProperly' => [
                'spec' => ['field' => [['arrayize']]],
                'input' => ['field' => 'a string value'],
                'options' => [],
                'result' => [true, ['field' => ['a string value']], null, []],
            ],
            'concatAliasIsCalledProperly' => [
                'spec' => ['field' => [['concat', '%', '%']]],
                'input' => ['field' => 'value'],
                'options' => [],
                'result' => [true, ['field' => '%value%'], null, []],
            ],
        ];
    }

    /**
     * @test
     * @covers ::filter
     * @covers ::setFilterAliases
     */
    public function filterCustomShortNamePass()
    {
        Filterer::setFilterAliases(['fval' => 'floatval']);
        $result = Filterer::filter(['fieldOne' => [['fval']]], ['fieldOne' => '3.14']);
        $this->assertSame([true, ['fieldOne' => 3.14], null, []], $result);
    }

    /**
     * @test
     * @covers ::filter
     * @covers ::setFilterAliases
     * @covers ::getFilterAliases
     */
    public function filterGetSetKnownFilters()
    {
        $knownFilters = ['lower' => 'strtolower', 'upper' => 'strtoupper'];
        Filterer::setFilterAliases($knownFilters);
        $this->assertSame($knownFilters, Filterer::getFilterAliases());
    }

    /**
     * @test
     * @covers ::filter
     * @expectedException Exception
     * @expectedExceptionMessage Function 'boo' for field 'foo' is not callable
     */
    public function notCallable()
    {
        Filterer::filter(['foo' => [['boo']]], ['foo' => 0]);
    }

    /**
     * @test
     * @covers ::filter
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage 'allowUnknowns' option was not a bool
     */
    public function allowUnknownsNotBool()
    {
        Filterer::filter([], [], ['allowUnknowns' => 1]);
    }

    /**
     * @test
     * @covers ::filter
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage 'defaultRequired' option was not a bool
     */
    public function defaultRequiredNotBool()
    {
        Filterer::filter([], [], ['defaultRequired' => 1]);
    }

    /**
     * @test
     * @covers ::filter
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage filters for field 'boo' was not a array
     */
    public function filtersNotArrayInLeftOverSpec()
    {
        Filterer::filter(['boo' => 1], []);
    }

    /**
     * @test
     * @covers ::filter
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage filters for field 'boo' was not a array
     */
    public function filtersNotArrayWithInput()
    {
        Filterer::filter(['boo' => 1], ['boo' => 'notUnderTest']);
    }

    /**
     * @test
     * @covers ::filter
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage filter for field 'boo' was not a array
     */
    public function filterNotArray()
    {
        Filterer::filter(['boo' => [1]], ['boo' => 'notUnderTest']);
    }

    /**
     * @test
     * @covers ::filter
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage 'required' for field 'boo' was not a bool
     */
    public function requiredNotBool()
    {
        Filterer::filter(['boo' => ['required' => 1]], []);
    }

    /**
     * @test
     * @covers ::registerAlias
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $alias was not a string or int
     */
    public function registerAliasAliasNotString()
    {
        Filterer::registerAlias(true, 'strtolower');
    }

    /**
     * @test
     * @covers ::registerAlias
     * @expectedException Exception
     * @expectedExceptionMessage Alias 'upper' exists
     */
    public function registerExistingAliasOverwriteFalse()
    {
        Filterer::setFilterAliases([]);
        Filterer::registerAlias('upper', 'strtoupper');
        Filterer::registerAlias('upper', 'strtoupper', false);
    }

    /**
     * @test
     * @covers ::registerAlias
     */
    public function registerExistingAliasOverwriteTrue()
    {
        Filterer::setFilterAliases(['upper' => 'strtoupper', 'lower' => 'strtolower']);
        Filterer::registerAlias('upper', 'ucfirst', true);
        $this->assertSame(['upper' => 'ucfirst', 'lower' => 'strtolower'], Filterer::getFilterAliases());
    }

    public static function failingFilter()
    {
        throw new Exception('i failed');
    }

    public static function passingFilter($value)
    {
        return $value . 'boo';
    }

    /**
     * Verify behavior of filter() when 'error' is not a string value.
     *
     * @test
     * @covers ::filter
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage error for field 'fieldOne' was not a non-empty string
     *
     * @return void
     */
    public function filterWithNonStringError()
    {
        Filterer::filter(
            ['fieldOne' => [['strtoupper'], 'error' => new StdClass()]],
            ['fieldOne' => 'valueOne']
        );
    }

    /**
     * Verify behavior of filter() when 'error' is an empty string.
     *
     * @test
     * @covers ::filter
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage error for field 'fieldOne' was not a non-empty string
     *
     * @return void
     */
    public function filterWithEmptyStringError()
    {
        Filterer::filter(
            ['fieldOne' => [['strtoupper'], 'error' => "\n   \t"]],
            ['fieldOne' => 'valueOne']
        );
    }

    /**
     * @test
     * @covers ::ofScalars
     */
    public function ofScalars()
    {
        $this->assertSame([1, 2], Filterer::ofScalars(['1', '2'], [['uint']]));
    }

    /**
     * @test
     * @covers ::ofScalars
     */
    public function ofScalarsChained()
    {
        $this->assertSame([3.3, 5.5], Filterer::ofScalars(['a3.3', 'a5.5'], [['trim', 'a'], ['floatval']]));
    }

    /**
     * @test
     * @covers ::ofScalars
     */
    public function ofScalarsWithMeaninglessKeys()
    {
        $this->assertSame(['key1' => 1, 'key2' => 2], Filterer::ofScalars(['key1' => '1', 'key2' => '2'], [['uint']]));
    }

    /**
     * @test
     * @covers ::ofScalars
     */
    public function ofScalarsFail()
    {
        try {
            Filterer::ofScalars(['1', [], new \StdClass], [['string']]);
            $this->fail();
        } catch (FilterException $e) {
            $expected = <<<TXT
Field '1' with value 'array (
)' failed filtering, message 'Value 'array (
)' is not a string'
Field '2' with value 'stdClass::__set_state(array(
))' failed filtering, message 'Value 'stdClass::__set_state(array(
))' is not a string'
TXT;
            $this->assertSame($expected, $e->getMessage());
        }
    }

    /**
     * @test
     * @covers ::ofArrays
     */
    public function ofArrays()
    {
        $expected = [['key' => 1], ['key' => 2]];
        $this->assertSame($expected, Filterer::ofArrays([['key' => '1'], ['key' => '2']], ['key' => [['uint']]]));
    }

    /**
     * @test
     * @covers ::ofArrays
     */
    public function ofArraysChained()
    {
        $expected = [['key' => 3.3], ['key' => 5.5]];
        $spec = ['key' => [['trim', 'a'], ['floatval']]];
        $this->assertSame($expected, Filterer::ofArrays([['key' => 'a3.3'], ['key' => 'a5.5']], $spec));
    }

    /**
     * @test
     * @covers ::ofArrays
     */
    public function ofArraysRequiredAndUnknown()
    {
        try {
            Filterer::ofArrays([['key' => '1'], ['key2' => '2']], ['key' => ['required' => true, ['uint']]]);
            $this->fail();
        } catch (Exception $e) {
            $expected = "Field 'key' was required and not present\nField 'key2' with value '2' is unknown";
            $this->assertSame($expected, $e->getMessage());
        }
    }

    /**
     * @test
     * @covers ::ofArrays
     */
    public function ofArraysFail()
    {
        try {
            Filterer::ofArrays(
                [['key' => new \StdClass], ['key' => []], ['key' => null], 'key'],
                ['key' => [['string']]]
            );
            $this->fail();
        } catch (FilterException $e) {
            $expected = <<<TXT
Field 'key' with value 'stdClass::__set_state(array(
))' failed filtering, message 'Value 'stdClass::__set_state(array(
))' is not a string'
Field 'key' with value 'array (
)' failed filtering, message 'Value 'array (
)' is not a string'
Field 'key' with value 'NULL' failed filtering, message 'Value failed filtering, \$allowNull is set to false'
Value at position '3' was not an array
TXT;
            $this->assertSame($expected, $e->getMessage());
        }
    }

    /**
     * @test
     * @covers ::ofArray
     */
    public function ofArray()
    {
        $expected = ['key1' => 1, 'key2' => 2];
        $spec = ['key1' => [['uint']], 'key2' => [['uint']]];
        $this->assertSame($expected, Filterer::ofArray(['key1' => '1', 'key2' => '2'], $spec));
    }

    /**
     * @test
     * @covers ::ofArray
     */
    public function ofArrayChained()
    {
        $expected = ['key' => 3.3];
        $spec = ['key' => [['trim', 'a'], ['floatval']]];
        $this->assertSame($expected, Filterer::ofArray(['key' => 'a3.3'], $spec));
    }

    /**
     * @test
     * @covers ::ofArray
     */
    public function ofArrayRequiredSuccess()
    {
        $expected = ['key2' => 2];
        $spec = ['key1' => [['uint']], 'key2' => ['required' => true, ['uint']]];
        $this->assertSame($expected, Filterer::ofArray(['key2' => '2'], $spec));
    }

    /**
     * @test
     * @covers ::ofArray
     */
    public function ofArrayRequiredFail()
    {
        try {
            Filterer::ofArray(['key1' => '1'], ['key1' => [['uint']], 'key2' => ['required' => true, ['uint']]]);
            $this->fail();
        } catch (FilterException $e) {
            $expected = "Field 'key2' was required and not present";
            $this->assertSame($expected, $e->getMessage());
        }
    }

    /**
     * @test
     * @covers ::ofArray
     */
    public function ofArrayUnknown()
    {
        try {
            Filterer::ofArray(['key' => '1'], ['key2' => [['uint']]]);
            $this->fail();
        } catch (FilterException $e) {
            $expected = "Field 'key' with value '1' is unknown";
            $this->assertSame($expected, $e->getMessage());
        }
    }
}
