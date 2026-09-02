<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use React\Http\Message\ServerRequest;
use Tempcord\Plugins\Http\Http\Cookie;
use Tempcord\Plugins\Http\Http\Request;
use Tempcord\Plugins\Http\Http\Response;
use Tempcord\Plugins\Http\Http\SameSite;
use Tempcord\Plugins\Http\Http\Secret;

/**
 * The pieces a handler needs to decide who is asking.
 *
 * All of them stateless on purpose: nothing here remembers anything between
 * requests, which is what makes it safe in a process that serves thousands of
 * them without restarting.
 */
#[CoversClass(Cookie::class)]
#[CoversClass(Secret::class)]
#[CoversClass(Request::class)]
#[CoversClass(Response::class)]
final class AuthPrimitivesTest extends TestCase
{
    private function request(array $headers = []): Request
    {
        return new Request(new ServerRequest('GET', 'http://bot.test/', $headers));
    }

    /**
     * Off this request's own header, never $_COOKIE — in a long-running server
     * the superglobals hold whatever the process started with.
     */
    public function test_a_cookie_is_read_from_the_request(): void
    {
        $request = $this->request(['Cookie' => 'session=abc; theme=dark']);

        $this->assertSame('abc', $request->cookie('session'));
        $this->assertSame('dark', $request->cookie('theme'));
        $this->assertNull($request->cookie('nothing'));
    }

    public function test_a_request_with_no_cookies_reads_none(): void
    {
        $this->assertNull($this->request()->cookie('session'));
    }

    public function test_a_bearer_token_is_read(): void
    {
        $this->assertSame('abc123', $this->request(['Authorization' => 'Bearer abc123'])->bearerToken());
        $this->assertSame('abc123', $this->request(['Authorization' => 'bearer abc123'])->bearerToken());
    }

    /**
     * Another scheme has different rules, and treating it as a token would
     * accept credentials meant for something else entirely.
     */
    #[DataProvider('notBearer')]
    public function test_anything_that_is_not_a_bearer_token_is_not_read(string $header): void
    {
        $this->assertNull($this->request(['Authorization' => $header])->bearerToken());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notBearer(): array
    {
        return [
            'basic auth' => ['Basic dXNlcjpwYXNz'],
            'no scheme' => ['abc123'],
            'empty' => [''],
            'bearer with nothing after it' => ['Bearer'],
            'bearer with a space in the token' => ['Bearer abc 123'],
        ];
    }

    public function test_a_cookie_goes_out_with_safe_defaults(): void
    {
        $header = new Cookie('session', 'abc')->header();

        $this->assertStringContainsString('session=abc', $header);
        $this->assertStringContainsString('HttpOnly', $header);
        $this->assertStringContainsString('SameSite=Lax', $header);
        $this->assertStringContainsString('Path=/', $header);
    }

    /**
     * Left to the caller: a bot behind a plain-HTTP proxy would otherwise set a
     * cookie the browser never sends back, which is a bug that looks like a
     * login loop.
     */
    public function test_secure_is_not_set_unless_asked_for(): void
    {
        $this->assertStringNotContainsString('Secure', new Cookie('a', 'b')->header());
        $this->assertStringContainsString('Secure', new Cookie('a', 'b', secure: true)->header());
    }

    public function test_a_value_is_encoded(): void
    {
        $this->assertStringContainsString('a=b%20c%3Bd', new Cookie('a', 'b c;d')->header());
    }

    public function test_forgetting_a_cookie_expires_it(): void
    {
        $header = Cookie::forget('session')->header();

        $this->assertStringContainsString('session=', $header);
        $this->assertStringContainsString('Max-Age=0', $header);
    }

    public function test_same_site_can_be_tightened(): void
    {
        $this->assertStringContainsString(
            'SameSite=Strict',
            new Cookie('a', 'b', sameSite: SameSite::Strict)->header(),
        );
    }

    /**
     * Set-Cookie is the one header that may legitimately appear more than once.
     */
    public function test_two_cookies_are_sent_as_two_headers(): void
    {
        $react = Response::noContent()
            ->withCookie(new Cookie('a', '1'))
            ->withCookie(new Cookie('b', '2'))
            ->toReact();

        $this->assertCount(2, $react->getHeader('Set-Cookie'));
    }

    public function test_an_answer_can_carry_a_header(): void
    {
        $response = Response::noContent()->withHeader('X-Trace', 'abc');

        $this->assertSame('abc', $response->toReact()->getHeaderLine('X-Trace'));
    }

    public function test_a_matching_secret_is_accepted(): void
    {
        $this->assertTrue(Secret::matches('s3cret', 's3cret'));
        $this->assertFalse(Secret::matches('s3cret', 's3cres'));
    }

    /**
     * An unset secret must never match, or a bot with no secret configured
     * accepts every request that sends an empty one.
     */
    public function test_an_empty_secret_matches_nothing(): void
    {
        $this->assertFalse(Secret::matches('', ''));
        $this->assertFalse(Secret::matches('', 'anything'));
    }

    public function test_a_correctly_signed_body_is_accepted(): void
    {
        $body = '{"event":"ping"}';
        $signature = hash_hmac('sha256', $body, 'shared');

        $this->assertTrue(Secret::signed($body, $signature, 'shared'));
    }

    public function test_a_body_that_was_tampered_with_is_refused(): void
    {
        $signature = hash_hmac('sha256', '{"amount":1}', 'shared');

        $this->assertFalse(Secret::signed('{"amount":1000}', $signature, 'shared'));
    }

    public function test_a_signature_under_another_secret_is_refused(): void
    {
        $body = '{"event":"ping"}';

        $this->assertFalse(Secret::signed($body, hash_hmac('sha256', $body, 'theirs'), 'ours'));
    }

    public function test_a_missing_signature_or_secret_is_refused(): void
    {
        $this->assertFalse(Secret::signed('body', '', 'shared'));
        $this->assertFalse(Secret::signed('body', 'anything', ''));
    }
}
