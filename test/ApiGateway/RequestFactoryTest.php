<?php

namespace Lexide\LazyBoy\Test\ApiGateway;

use Lexide\LazyBoy\ApiGateway\RequestFactory;
use Lexide\LazyBoy\Exception\RequestException;
use PHPUnit\Framework\TestCase;

class RequestFactoryTest extends TestCase
{


    /**
     * @dataProvider eventProvider
     *
     * @param array $event
     * @param array $expectedParams
     * @param string $expectedBody
     * @param array $expectedHeaders
     * @throws RequestException
     */
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

    /**
     * @dataProvider badEventProvider
     *
     * @param array $event
     * @throws RequestException
     */
    public function testBadEvent(array $event)
    {
        $this->expectException(RequestException::class);

        $factory = new RequestFactory();
        $factory->createFromEvent($event);
    }

    public function eventProvider(): array
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
                    "rawQueryString" => http_build_query(["foo" => "one", "bar" => ["two", "three"]])
                ],
                ["foo" => "one", "bar" => ["two", "three"]]
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

    public function badEventProvider(): array
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
