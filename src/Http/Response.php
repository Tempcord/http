<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Http;

use React\Http\Message\Response as ReactResponse;

/**
 * What to answer with.
 *
 * Named constructors rather than a builder: an answer is nearly always one of
 * a handful of shapes, and the ones that are not can be built from the react
 * response directly.
 */
final readonly class Response
{
    private function __construct(
        public int $status,
        public string $body,
        /** @var array<string, string> */
        public array $headers,
    ) {}

    /**
     * @param array<string, string> $headers
     */
    public static function text(string $body, int $status = 200, array $headers = []): self
    {
        return new self($status, $body, ['Content-Type' => 'text/plain; charset=utf-8', ...$headers]);
    }

    /**
     * @param array<mixed> $body
     * @param array<string, string> $headers
     */
    public static function json(array $body, int $status = 200, array $headers = []): self
    {
        return new self(
            $status,
            json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ['Content-Type' => 'application/json; charset=utf-8', ...$headers],
        );
    }

    public static function noContent(): self
    {
        return new self(204, '', []);
    }

    public static function notFound(string $message = 'Not Found'): self
    {
        return self::json(['error' => $message], 404);
    }

    public static function badRequest(string $message = 'Bad Request'): self
    {
        return self::json(['error' => $message], 400);
    }

    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return self::json(['error' => $message], 401);
    }

    public function toReact(): ReactResponse
    {
        return new ReactResponse($this->status, $this->headers, $this->body);
    }
}
