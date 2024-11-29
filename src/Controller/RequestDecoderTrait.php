<?php

namespace Lexide\LazyBoy\Controller;

use Lexide\LazyBoy\Controller\Decoder\DecoderInterface;
use Lexide\LazyBoy\Exception\DecodeException;
use Psr\Http\Message\ServerRequestInterface;

trait RequestDecoderTrait
{

    protected ?DecoderInterface $defaultDecoder = null;

    /**
     * @var DecoderInterface[]
     */
    protected array $decoders = [];

    /**
     * @param DecoderInterface $decoder
     */
    public function setDefaultDecoder(DecoderInterface $decoder): void
    {
        $this->defaultDecoder = $decoder;
    }

    /**
     * @param array $decoders
     */
    public function setDecoders(array $decoders): void
    {
        $this->decoders = [];
        foreach ($decoders as $decoder) {
            $this->addDecoder($decoder);
        }
    }

    /**
     * @param DecoderInterface $decoder
     */
    public function addDecoder(DecoderInterface $decoder): void
    {
        $this->decoders[] = $decoder;
    }

    /**
     * @param ServerRequestInterface $request
     * @return mixed
     * @throws DecodeException
     */
    protected function decodeRequest(ServerRequestInterface $request): mixed
    {
        $mimeType = $request->getHeader("Content-Type")[0] ?? null;
        if (!empty($mimeType)) {
            foreach ($this->decoders as $decoder) {
                if ($decoder->handles($mimeType)) {
                    return $decoder->decode($request);
                }
            }
        }
        return $this->defaultDecoder->decode($request);
    }

}