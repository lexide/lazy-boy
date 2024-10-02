<?php

namespace Lexide\LazyBoy\Security;

use Psr\Http\Message\RequestInterface;

interface AuthoriserInterface
{

    /**
     * Return true if authorised
     *
     * @param RequestInterface $request
     * @param array $securityContext
     * @return bool
     */
    public function checkAuthorisation(RequestInterface $request, array $securityContext): bool;

}