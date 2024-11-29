<?php

namespace Lexide\LazyBoy\Security;

use Psr\Http\Message\ServerRequestInterface;

class AuthoriserContainer implements AuthoriserInterface
{

    /**
     * @var AuthoriserInterface[]
     */
    protected array $authorisers;
    protected bool $requireAll;

    /**
     * If $requireAll is true, all authorisers must pass
     * If $requireAll is false, only one authoriser must pass
     *
     * @param AuthoriserInterface[] $authorisers
     * @param bool $requireAll
     */
    public function __construct(array $authorisers, bool $requireAll)
    {
        $this->authorisers = $authorisers;
        $this->requireAll = $requireAll;
    }

    /**
     * {@inhritDoc}
     */
    public function checkAuthorisation(ServerRequestInterface $request, array $securityContext): bool
    {
        ksort($this->authorisers);
        $defaultResponse = $this->requireAll;
        foreach ($this->authorisers as $authoriser) {
            $response = $authoriser->checkAuthorisation($request, $securityContext);
            if ($response != $defaultResponse) {
                return $response;
            }
        }
        return $defaultResponse;
    }
}