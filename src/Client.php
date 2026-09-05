<?php

declare(strict_types=1);

namespace Taqlyn;

use Closure;
use JsonException;
use RuntimeException;

final class Client
{
    private const CREATE_SHORT_LINK_PATH = '/v1/short-links';

    public const DEFAULT_API_BASE_URL = 'https://api.taqlyn.com';

    private readonly string $baseUrl;
    private readonly Signer $signer;

    /** @var Closure(string, string, array<string, string>, string): array{status: int, body: string} */
    private readonly Closure $transport;

    /** @var Closure(): int */
    private readonly Closure $clock;

    /**
     * @param ?string $baseUrl Control-plane base URL. Defaults to TAQLYN_BASE_URL or https://api.taqlyn.com.
     * @param null|callable(string, string, array<string, string>, string): array{status: int, body: string} $transport
     * @param null|callable(): int $clock
     */
    public function __construct(
        ?string $baseUrl = null,
        string $clientId = '',
        string $privateKeyPem = '',
        ?callable $transport = null,
        ?callable $clock = null,
    ) {
        $envBaseUrl = getenv('TAQLYN_BASE_URL') ?: getenv('TAQLYN_API_URL');
        $rawUrl = ($baseUrl !== null && trim($baseUrl) !== '')
            ? $baseUrl
            : ($envBaseUrl !== false && trim((string) $envBaseUrl) !== '' ? $envBaseUrl : self::DEFAULT_API_BASE_URL);
        $this->baseUrl = rtrim($rawUrl, '/');
        $this->signer = new Signer($clientId, $privateKeyPem);
        $this->transport = $transport === null
            ? $this->defaultTransport(...)
            : Closure::fromCallable($transport);
        $this->clock = $clock === null
            ? time(...)
            : Closure::fromCallable($clock);
    }

    /**
     * Creates a server-side short link.
     *
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function createShortLink(array $request): array
    {
        try {
            $body = json_encode($request, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode the short-link request.', 0, $exception);
        }

        $headers = $this->signer->headers(
            'POST',
            self::CREATE_SHORT_LINK_PATH,
            $body,
            ($this->clock)(),
        );
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';

        $response = ($this->transport)(
            'POST',
            rtrim($this->baseUrl, '/') . self::CREATE_SHORT_LINK_PATH,
            $headers,
            $body,
        );

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new ApiException($response['status'], $response['body']);
        }

        try {
            $decoded = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Taqlyn API returned invalid JSON.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Taqlyn API returned an unexpected response.');
        }

        return $decoded;
    }

    /**
     * @param array<string, string> $headers
     * @return array{status: int, body: string}
     */
    private function defaultTransport(
        string $method,
        string $url,
        array $headers,
        string $body,
    ): array {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = "{$name}: {$value}";
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerLines),
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => 30,
            ],
        ]);

        $responseBody = @file_get_contents($url, false, $context);
        if ($responseBody === false) {
            throw new RuntimeException("Unable to connect to the Taqlyn API at {$url}.");
        }

        $status = 0;
        foreach ($http_response_header ?? [] as $headerLine) {
            if (preg_match('/\AHTTP\/\S+\s+(\d{3})/', $headerLine, $matches)) {
                $status = (int) $matches[1];
                break;
            }
        }

        if ($status === 0) {
            throw new RuntimeException('Taqlyn API returned no HTTP status.');
        }

        return ['status' => $status, 'body' => $responseBody];
    }
}
