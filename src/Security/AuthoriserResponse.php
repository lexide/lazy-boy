<?php

namespace Lexide\LazyBoy\Security;

class AuthoriserResponse
{

    protected bool $success = false;
    protected int $errorResponseCode;
    protected int $errorPriority;
    protected string $errorMessage;

    /**
     * @param bool $success
     * @param int $errorResponseCode
     * @param int $errorPriority
     * @param string $errorMessage
     */
    public function __construct(
        bool $success,
        int $errorResponseCode = 0,
        int $errorPriority = 0,
        string $errorMessage = ""
    ) {
        $this->success = $success;
        $this->errorResponseCode = $errorResponseCode;
        $this->errorPriority = $errorPriority;
        $this->errorMessage = $errorMessage;
    }

    /**
     * @return bool
     */
    public function getSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @return int
     */
    public function getErrorResponseCode(): int
    {
        return $this->errorResponseCode;
    }

    /**
     * @return int
     */
    public function getErrorPriority(): int
    {
        return $this->errorPriority;
    }

    /**
     * @return string
     */
    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

}