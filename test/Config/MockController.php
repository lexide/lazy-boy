<?php

namespace Lexide\LazyBoy\Test\Config;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class MockController
{

    protected array $callCount = [];

    public function callFoo(): void
    {
        $this->recordCall(__FUNCTION__);
    }

    public function callBar(): void
    {
        $this->recordCall(__FUNCTION__);
    }

    public function callBaz(): void
    {
        $this->recordCall(__FUNCTION__);
    }

    public function addRequest($request): void
    {
        $this->recordCall(__FUNCTION__);
    }

    public function addRequestInterface(RequestInterface $req): void
    {
        $this->recordCall(__FUNCTION__);
    }

    public function addResponse($response): void
    {
        $this->recordCall(__FUNCTION__);
    }

    public function addResponseInterface(ResponseInterface $resp): void
    {
        $this->recordCall(__FUNCTION__);
    }

    public function addArg(string $foo): void
    {
        $this->recordCall(__FUNCTION__);
    }

    public function addOptionalArg(string $foo = ""): void
    {
        $this->recordCall(__FUNCTION__);
    }

    public function addAll(RequestInterface $request, string $foo, int $bar, ResponseInterface $response): void
    {
        $this->recordCall(__FUNCTION__);
    }

    protected function recordCall(string $function): void
    {
        $this->callCount[$function] = $this->callCount[$function] ?? 0;
        ++$this->callCount[$function];
    }

    public function getCallCount(): array
    {
        return $this->callCount;
    }

}