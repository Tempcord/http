<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use React\Http\Message\ServerRequest;
use Tempcord\Plugins\Http\Http\Request;
use Tempcord\Plugins\Http\Http\Response;

#[CoversClass(Request::class)]
#[CoversClass(Response::class)]
final class RequestResponseTest extends TestCase
{
    private function request(string $url = 'http://bot.test/health', string $body = '', array $headers = []): Request
    {
        return new Request(new ServerRequest('POST', $url, $headers, $body), ['guild' => '123']);
    }

    public function test_it_reads_the_basics(): void
    {
        $request = $this->request();

        $this->assertSame('POST', $request->method());
        $this->assertSame('/health', $request->path());
        $this->assertSame('123', $request->parameter('guild'));
        $this->assertNull($request->parameter('nothing'));
    }

    public function test_it_reads_the_query_string(): void
    {
        $request = $this->request('http://bot.test/health?since=yesterday');

        $this->assertSame('yesterday', $request->query('since'));
        $this->assertNull($request->query('until'));
    }

    public function test_it_reads_a_header(): void
    {
        $request = $this->request(headers: ['X-Signature' => 'abc']);

        $this->assertSame('abc', $request->header('X-Signature'));
        $this->assertNull($request->header('X-Nothing'));
    }

    public function test_it_reads_a_json_body(): void
    {
        $this->assertSame(['a' => 1], $this->request(body: '{"a":1}')->json());
    }

    /**
     * A body that is not what was expected is an ordinary thing to receive from
     * the open internet, and a handler answering 400 reads better than one
     * wrapped in a try.
     */
    public function test_a_body_that_is_not_json_reads_as_nothing(): void
    {
        $this->assertNull($this->request(body: 'not json')->json());
        $this->assertNull($this->request(body: '')->json());
        $this->assertNull($this->request(body: '"a string"')->json());
    }

    public function test_json_answers_carry_the_right_content_type(): void
    {
        $response = Response::json(['ok' => true]);

        $this->assertSame(200, $response->status);
        $this->assertSame('application/json; charset=utf-8', $response->headers['Content-Type']);
    }

    /**
     * Ukrainian in a body should be readable in a log and in a browser, not a
     * wall of escapes.
     */
    public function test_json_is_not_escaped_into_illegibility(): void
    {
        $this->assertSame('{"текст":"привіт"}', Response::json(['текст' => 'привіт'])->body);
    }

    public function test_the_refusals_carry_their_own_status(): void
    {
        $this->assertSame(404, Response::notFound()->status);
        $this->assertSame(400, Response::badRequest()->status);
        $this->assertSame(401, Response::unauthorized()->status);
        $this->assertSame(204, Response::noContent()->status);
    }

    public function test_an_answer_becomes_a_react_response(): void
    {
        $react = Response::json(['ok' => true], 201, ['X-Trace' => 'abc'])->toReact();

        $this->assertSame(201, $react->getStatusCode());
        $this->assertSame('abc', $react->getHeaderLine('X-Trace'));
        $this->assertSame('{"ok":true}', (string) $react->getBody());
    }

    public function test_text_answers_are_plain(): void
    {
        $response = Response::text('mine');

        $this->assertSame('mine', $response->body);
        $this->assertStringStartsWith('text/plain', $response->headers['Content-Type']);
    }
}
