<?php

namespace Lexide\LazyBoy\Security;

use Psr\Http\Message\ServerRequestInterface;

class PublicRouteAuthoriser implements AuthoriserInterface
{
    /**
     * {@inheritDoc}
     */
    public function checkAuthorisation(ServerRequestInterface $request, array $securityContext): bool
    {
        return $securityContext["public"] ?? false;
    }

}