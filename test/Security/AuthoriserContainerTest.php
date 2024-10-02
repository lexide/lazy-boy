<?php

namespace Lexide\LazyBoy\Test\Security;

use Lexide\LazyBoy\Security\AuthoriserContainer;
use Lexide\LazyBoy\Security\AuthoriserInterface;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

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
    public function testAuthorising(bool $requireAll, array $resultSequence, bool $expectedResult)
    {
        $authoriserCount = count($resultSequence);

        $authoriser = \Mockery::mock(AuthoriserInterface::class);
        $authoriser->shouldReceive("checkAuthorisation")->andReturnValues($resultSequence);
        $request = \Mockery::mock(RequestInterface::class);

        $authorisers = array_fill(0, $authoriserCount, $authoriser);

        $container = new AuthoriserContainer($authorisers, $requireAll);

        $this->assertSame($expectedResult, $container->checkAuthorisation($request, []));
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
            "require one, passing" => [
                false,
                [false, false, false, true],
                true
            ],
            "require one, failing" => [
                false,
                [false, false, false, false],
                false
            ]
        ];
    }

}
