<?php

namespace Lexide\LazyBoy\Test\ApiGateway;

use Lexide\LazyBoy\ApiGateway\RequestFactory;
use Lexide\LazyBoy\Exception\RequestException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RequestFactoryTest extends TestCase
{


    #[DataProvider("eventProvider")]
    public function testCreatingRequest(
        array $event,
        array $expectedParams = [],
        string $expectedBody = "",
        array $expectedHeaders = []
    ) {
        $factory = new RequestFactory();
        $response = $factory->createFromEvent($event);

        $actualParams = $response->getQueryParams();
        $actualBody = $response->getBody()->getContents();
        $actualHeaders = $response->getHeaders();

        $this->assertCount(count($expectedParams), $actualParams);
        foreach ($expectedParams as $param => $value) {
            $this->assertArrayHasKey($param, $actualParams);
            $this->assertSame($value, $actualParams[$param]);
        }

        $this->assertSame($expectedBody, $actualBody);

        $this->assertCount(count($expectedHeaders), $actualHeaders);
        foreach ($expectedHeaders as $header => $value) {
            $this->assertArrayHasKey($header, $actualHeaders);
            $this->assertSame($value, $actualHeaders[$header]);
        }
    }

    #[DataProvider("badEventProvider")]
    public function testBadEvent(array $event)
    {
        $this->expectException(RequestException::class);

        $factory = new RequestFactory();
        $factory->createFromEvent($event);
    }

    public static function eventProvider(): array
    {
        return [
            "basic event" => [
                [
                    "httpMethod" => "get",
                    "path" => "foo"
                ]
            ],
            "queryString" => [
                [
                    "httpMethod" => "get",
                    "path" => "foo",
                    "multiValueQueryStringParameters" => [
                        "foo" => ["one"],
                        "bar" => ["two", "three"],
                        "baz[]" => ["four", "five"]
                    ]
                ],
                ["foo" => "one", "bar" => ["two", "three"], "baz" => ["four", "five"]]
            ],
            "body" => [
                [
                    "httpMethod" => "get",
                    "path" => "foo",
                    "body" => "foo bar baz"
                ],
                [],
                "foo bar baz"
            ],
            "headers" => [
                [
                    "httpMethod" => "get",
                    "path" => "foo",
                    "headers" => [
                        "foo" => "one",
                        "bar" => "two,three"
                    ]
                ],
                [],
                "",
                [
                    "foo" => ["one"],
                    "bar" => ["two", "three"]
                ]
            ]
        ];
    }

    public static function badEventProvider(): array
    {
        return [
            "Empty event" => [
                []
            ],
            "No path" => [
                ["httpMethod" => "foo"]
            ],
            "No method" => [
                ["path" => "foo"]
            ]
        ];
    }

}
