<?php

namespace Lexide\LazyBoy\Security;

use Psr\Http\Message\ServerRequestInterface;

class AuthoriserContainer implements AuthoriserInterface
{

    /**
     * @var AuthoriserInterface[]
     */
    protected array $authorisers;
    protected AuthoriserResponseFactory $authoriserResponseFactory;
    protected bool $requireAll;

    /**
     * If $requireAll is true, all authorisers must pass
     * If $requireAll is false, only one authoriser must pass
     *
     * @param AuthoriserInterface[] $authorisers
     * @param AuthoriserResponseFactory $authoriserResponseFactory
     * @param bool $requireAll
     */
    public function __construct(array $authorisers, AuthoriserResponseFactory $authoriserResponseFactory, bool $requireAll)
    {
        $this->authorisers = $authorisers;
        $this->authoriserResponseFactory = $authoriserResponseFactory;
        $this->requireAll = $requireAll;
    }

    /**
     * {@inheritDoc}
     */
    public function checkAuthorisation(ServerRequestInterface $request, array $securityContext): AuthoriserResponse
    {
        ksort($this->authorisers);

        $bestResponse = $this->authoriserResponseFactory->create($this->requireAll);
        foreach ($this->authorisers as $authoriser) {
            $response = $authoriser->checkAuthorisation($request, $securityContext);
            if ($response->getSuccess() != $this->requireAll) {
                return $response;
            }
            if ($response->getErrorPriority() >= $bestResponse->getErrorPriority()) {
                $bestResponse = $response;
            }
        }
        return $bestResponse;
    }
}