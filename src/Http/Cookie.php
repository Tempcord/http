<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Http;

/**
 * A cookie on the way out.
 *
 * The defaults are the safe ones rather than PHP's: HttpOnly so script cannot
 * read it, SameSite=Lax so it is not sent along with a cross-site request, and
 * a path of "/". Secure is left to the caller, since a bot served over plain
 * HTTP behind a proxy would otherwise set a cookie the browser never returns.
 */
final readonly class Cookie
{
    public function __construct(
        public string $name,
        public string $value,
        public ?int $expiresAt = null,
        public string $path = '/',
        public ?string $domain = null,
        public bool $secure = false,
        public bool $httpOnly = true,
        public SameSite $sameSite = SameSite::Lax,
    ) {}

    /**
     * A cookie that tells the browser to forget the one of the same name.
     */
    public static function forget(string $name, string $path = '/'): self
    {
        return new self($name, '', expiresAt: 0, path: $path);
    }

    public function header(): string
    {
        /*
         * rawurlencode, not urlencode: the latter writes a space as "+", which
         * a browser hands back verbatim rather than as a space.
         */
        $parts = [$this->name . '=' . rawurlencode($this->value)];

        if ($this->expiresAt !== null) {
            $parts[] = 'Expires=' . gmdate('D, d M Y H:i:s \G\M\T', $this->expiresAt);
            $parts[] = 'Max-Age=' . max(0, $this->expiresAt - time());
        }

        $parts[] = 'Path=' . $this->path;

        if ($this->domain !== null) {
            $parts[] = 'Domain=' . $this->domain;
        }

        if ($this->secure) {
            $parts[] = 'Secure';
        }

        if ($this->httpOnly) {
            $parts[] = 'HttpOnly';
        }

        $parts[] = 'SameSite=' . $this->sameSite->value;

        return implode('; ', $parts);
    }
}
