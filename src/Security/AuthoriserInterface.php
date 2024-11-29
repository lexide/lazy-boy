<?php

namespace Lexide\LazyBoy\Security;

use Psr\Http\Message\ServerRequestInterface;

interface AuthoriserInterface
{

    /**
     * Return true if authorised
     *
     * @param ServerRequestInterface $request
     * @param array $securityContext
     * @return bool
     */
    public function checkAuthorisation(ServerRequestInterface $request, array $securityContext): bool;

}