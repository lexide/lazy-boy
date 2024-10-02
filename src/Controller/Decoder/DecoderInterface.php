<?php

namespace Lexide\LazyBoy\Controller\Decoder;

use Lexide\LazyBoy\Exception\DecodeException;
use Psr\Http\Message\RequestInterface;

interface DecoderInterface
{

    /**
     * @param string $mimeType
     * @return bool
     */
    public function handles(string $mimeType): bool;

    /**
     * @param RequestInterface $request
     * @return mixed
     * @throws DecodeException
     */
    public function decode(RequestInterface $request): mixed;

}