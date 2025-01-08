<?php

namespace Lexide\LazyBoy\Test\Security;

use Lexide\LazyBoy\Security\AuthoriserContainer;
use Lexide\LazyBoy\Security\AuthoriserInterface;
use Lexide\LazyBoy\Security\AuthoriserResponse;
use Lexide\LazyBoy\Security\AuthoriserResponseFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;

class AuthoriserContainerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @dataProvider resultProvider
     *
     * @param bool $requireAll
     * @param array $resultSequence
     * @param bool $expectedResult
     */
    public function testAuthorising(bool $requireAll, array $resultSequence, bool $expectedResult, int $expectedCode = 0)
    {
        $authoriserCount = count($resultSequence);

        $defaultResponse = \Mockery::mock(AuthoriserResponse::class);
        $defaultResponse->shouldIgnoreMissing(0);
        $authoriserResponseFactory = \Mockery::mock(AuthoriserResponseFactory::class);
        $authoriserResponseFactory->shouldReceive("create")->with($requireAll)->once()->andReturn($defaultResponse);

        $responses = [];
        foreach ($resultSequence as $result) {
            $response = \Mockery::mock(AuthoriserResponse::class);
            $response->shouldReceive("getSuccess")->andReturn($result === true);
            $response->shouldReceive("getErrorPriority")->andReturn($result[0] ?? 0);
            $response->shouldReceive("getErrorResponseCode")->andReturn($result[1] ?? 0);
            $responses[] = $response;
        }
        $authoriser = \Mockery::mock(AuthoriserInterface::class);
        $authoriser->shouldReceive("checkAuthorisation")->andReturnValues($responses);
        $request = \Mockery::mock(ServerRequestInterface::class);

        $authorisers = array_fill(0, $authoriserCount, $authoriser);

        $container = new AuthoriserContainer($authorisers, $authoriserResponseFactory, $requireAll);

        $actual = $container->checkAuthorisation($request, []);

        $this->assertSame($expectedResult, $actual->getSuccess());
        $this->assertSame($expectedCode, $actual->getErrorResponseCode());
    }

    public function resultProvider(): array
    {
        return [
            "require all, passing" => [
                true,
                [true, true, true, true],
                true
            ],
            "require all, failing" => [
                true,
                [true, true, false, true],
                false
            ],
            "require all, error code" => [
                true,
                [true, true, true, [0, 10]],
                false,
                10
            ],
            "require one, passing" => [
                false,
                [false, false, false, true],
                true
            ],
            "require one, failing" => [
                false,
                [false, false, false, false],
                false
            ],
            "require one, failing, use last error" => [
                false,
                [false, false, false, [0, 10]],
                false,
                10
            ],
            "require one, failing, use priority error" => [
                false,
                [[1, 10], [0, 20], [3, 30], [2, 40]],
                false,
                30
            ],
            "require one, failing, use last error of highest priority" => [
                false,
                [[2, 10], [0, 20], [2, 30], [2, 40], [1, 50]],
                false,
                40
            ]
        ];
    }

}
