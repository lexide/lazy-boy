<?php

namespace Lexide\LazyBoy\Controller\Decoder;

use Lexide\LazyBoy\Exception\DecodeException;
use Psr\Http\Message\ServerRequestInterface;

interface DecoderInterface
{

    /**
     * @param string $mimeType
     * @return bool
     */
    public function handles(string $mimeType): bool;

    /**
     * @param ServerRequestInterface $request
     * @return mixed
     * @throws DecodeException
     */
    public function decode(ServerRequestInterface $request): mixed;

}