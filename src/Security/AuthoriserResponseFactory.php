<?php

namespace Lexide\LazyBoy\Security;

class AuthoriserResponseFactory
{

    /**
     * @param bool $success
     * @param int $errorResponseCode
     * @param int $errorPriority
     * @param string $errorMessage
     * @return AuthoriserResponse
     */
    public function create(
        bool $success,
        int $errorResponseCode = 0,
        int $errorPriority = 0,
        string $errorMessage = ""
    ): AuthoriserResponse {
        return new AuthoriserResponse($success, $errorResponseCode, $errorPriority, $errorMessage);
    }

}