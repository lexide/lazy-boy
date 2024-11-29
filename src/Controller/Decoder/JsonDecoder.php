<?php

namespace Lexide\LazyBoy\Controller\Decoder;

use Lexide\LazyBoy\Exception\DecodeException;
use Psr\Http\Message\ServerRequestInterface;

class JsonDecoder implements DecoderInterface
{
    /**
     * {@inheritDoc}
     */
    public function handles(string $mimeType): bool
    {
        return $mimeType == "application/json";
    }

    /**
     * {@inheritDoc}
     */
    public function decode(ServerRequestInterface $request): mixed
    {
        try {
            return json_decode($request->getBody()->getContents(), true, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new DecodeException("JSON decoding failed", previous: $e);
        }
    }

}