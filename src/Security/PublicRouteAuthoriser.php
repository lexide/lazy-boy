<?php

namespace Lexide\LazyBoy\Security;

use Psr\Http\Message\ServerRequestInterface;

class PublicRouteAuthoriser implements AuthoriserInterface
{

    protected AuthoriserResponseFactory $authoriserResponseFactory;

    /**
     * @param AuthoriserResponseFactory $authoriserResponseFactory
     */
    public function __construct(AuthoriserResponseFactory $authoriserResponseFactory)
    {
        $this->authoriserResponseFactory = $authoriserResponseFactory;
    }

    /**
     * {@inheritDoc}
     */
    public function checkAuthorisation(ServerRequestInterface $request, array $securityContext): AuthoriserResponse
    {
        return $this->authoriserResponseFactory->create($securityContext["public"] ?? false);
    }

}