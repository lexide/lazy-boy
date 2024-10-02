<?php

namespace Lexide\LazyBoy\Security;

use Psr\Http\Message\RequestInterface;

class PublicRouteAuthoriser implements AuthoriserInterface
{
    /**
     * {@inheritDoc}
     */
    public function checkAuthorisation(RequestInterface $request, array $securityContext): bool
    {
        return $securityContext["public"] ?? false;
    }

}