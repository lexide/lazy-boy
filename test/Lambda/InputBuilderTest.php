<?php

namespace Lexide\LazyBoy\Test\Lambda;

use Lexide\LazyBoy\Exception\InputException;
use Lexide\LazyBoy\Lambda\InputBuilder;
use PHPUnit\Framework\TestCase;

class InputBuilderTest extends TestCase
{

    /**
     * @dataProvider inputProvider
     *
     * @param string $args
     * @param string $options
     * @param array $expectedRegex
     * @throws \Exception
     */
    public function testBuildingInput(string $args = "", string $options = "", array $expectedRegex = [])
    {
        $command = "test:command";

        $event = [
            "foo" => "one",
            "bar" => "two",
            "baz" => "three",
            "blank" => "",
            "false" => false,
            "null" => null
        ];

        $inputBuilder = new InputBuilder();
        $input = $inputBuilder->buildInput($command, $args, $options, $event);

        $expectedRegex[] = "/^.$command.( |$)/";

        // sadly this is the only way to check what was added to the input without binding it to a command definition
        $actual = $input->__toString();

        foreach ($expectedRegex as $regex) {
            $this->assertMatchesRegularExpression($regex, $actual);
        }
    }

    public function testNoCommand()
    {
        $this->expectException(InputException::class);
        $this->expectExceptionMessageMatches("/no command/i");

        $inputBuilder = new InputBuilder();
        $inputBuilder->buildInput("", "", "", []);
    }

    /**
     * @dataProvider invalidInputProvider
     *
     * @param string $args
     * @param string $options
     * @param string $exceptionMessageRegex
     * @throws InputException
     */
    public function testInvalidInputData(string $args, string $options, string $exceptionMessageRegex)
    {
        $this->expectException(InputException::class);
        $this->expectExceptionMessageMatches($exceptionMessageRegex);

        $command = "test:command";

        $event = [
            "foo" => "one",
            "bar" => "two",
            "baz" => "three",
            "null" => null
        ];

        $inputBuilder = new InputBuilder();
        $inputBuilder->buildInput($command, $args, $options, $event);
    }

    public function inputProvider(): array
    {
        return [
            "Command only" => [],
            "With args" => [
                json_encode(["foo" => "bar"]),
                "",
                ["/ bar( |$)/"]
            ],
            "With options" => [
                "",
                json_encode(["--foo" => "bar", "-b" => "baz"]),
                ["/ --foo=bar( |$)/", "/ -b baz( |$)/"]
            ],
            "With unformatted options" => [
                "",
                json_encode(["foo" => "bar", "b" => "baz"]),
                ["/ --foo=bar( |$)/", "/ -b baz( |$)/"]
            ],
            "Args and options" => [
                json_encode(["foo" => "one", "bar" => "two"]),
                json_encode(["--baz" => "three", "-f" => "four"]),
                [
                    "/ one( |$)/",
                    "/ two( |$)/",
                    "/ --baz=three( |$)/",
                    "/ -f four( |$)/"
                ]
            ],
            "Flag options" => [
                "",
                json_encode(["--foo" => "", "-b" => "", "--baz" => "fiz"]),
                [
                    "/ --foo( |$)/",
                    "/ -b( |$)/",
                    "/ --baz=fiz( |$)/"
                ]
            ],
            "Templating event variables into args" => [
                json_encode(["foo" => "{event.foo}", "baz" => "{event.baz}"]),
                "",
                [
                    "/ one( |$)/",
                    "/ three( |$)/",
                ]
            ],
            "Templating empty string event variables into args" => [
                json_encode(["foo" => "{event.blank}"]),
                "",
                [
                    "/ ''$/",
                ]
            ],
            "Templating false event variables into args" => [
                json_encode(["foo" => "{event.false}"]),
                "",
                [
                    "/ ''$/",
                ]
            ],
            "Templating event variables into options" => [
                "",
                json_encode(["--foo" => "{event.foo}", "-b" => "{event.bar}"]),
                [
                    "/ --foo=one( |$)/",
                    "/ -b two( |$)/",
                ]
            ],
            "Templating empty variables into options" => [
                "",
                json_encode(["--foo" => "{event.blank}", "-b" => "{event.null}"]),
                [
                    "/ --foo( |$)/",
                    "/ -b( |$)/",
                ]
            ],
            "Options with missing event variables are skipped" => [
                "",
                json_encode(["--foo" => "{event.null}", "-b" => "{event.missing}"]),
                [
                    "/ --foo$/"
                ]
            ]
        ];
    }

    public function invalidInputProvider(): array
    {
        return [
            "Bad arg json" => [
                json_encode("not an array"),
                "",
                "/not an array/"
            ],
            "Numeric args keys" => [
                json_encode(["foo", "bar"]),
                "",
                "/invalid arg/i"
            ],
            "Null arg event templating" => [
                json_encode(["foo" => "{event.null}"]),
                "",
                "/.null. does not exist/"
            ],
            "Missing arg event templating" => [
                json_encode(["foo" => "{event.missing}"]),
                "",
                "/.missing. does not exist/"
            ],
            "Bad option json" => [
                "",
                json_encode("not an array"),
                "/not an array/"
            ],
            "Numeric option keys" => [
                "",
                json_encode(["foo", "bar"]),
                "/invalid option/i"
            ]
        ];
    }

}
