<?php

namespace Lexide\LazyBoy\Security;

use Psr\Http\Message\ServerRequestInterface;

interface AuthoriserInterface
{

    /**
     * @param ServerRequestInterface $request
     * @param array $securityContext
     * @return AuthoriserResponse
     */
    public function checkAuthorisation(ServerRequestInterface $request, array $securityContext): AuthoriserResponse;

}