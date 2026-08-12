<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    require dirname(__DIR__) . '/src/ApiException.php';
    require dirname(__DIR__) . '/src/Signer.php';
    require dirname(__DIR__) . '/src/Client.php';
}

use Taqlyn\Client;
use Taqlyn\Signer;

const PRIVATE_KEY = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MC4CAQAwBQYDK2VwBCIEIAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8g
-----END PRIVATE KEY-----
PEM;

const EXPECTED_SIGNATURE = 'zTe0VimeWAe6dzpPxAIn+DDR46E58G63ypiSTkXd1jT1o3oxYJ4jzAof05lf3s/8sbZ7l46VjDh8ohtB+NISAA==';
const BODY = '{"destinationWeb":"https://example.com/offer","mode":"web_only"}';

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(
            STDERR,
            "{$message}\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true) . "\n",
        );
        exit(1);
    }
}

$signer = new Signer('app_test_abc', PRIVATE_KEY);
$headers = $signer->headers('POST', '/v1/short-links', BODY, 1700000000);
assertSameValue(EXPECTED_SIGNATURE, $headers['X-Taqlyn-Signature'], 'Golden signature differs.');
assertSameValue('app_test_abc', $headers['X-Taqlyn-Client-Id'], 'Client ID header differs.');
assertSameValue('1700000000', $headers['X-Taqlyn-Timestamp'], 'Timestamp header differs.');

$transportCalled = false;
$transport = static function (
    string $method,
    string $url,
    array $requestHeaders,
    string $body,
) use (&$transportCalled): array {
    $transportCalled = true;
    assertSameValue('POST', $method, 'Create method differs.');
    assertSameValue('https://api.taqlyn.test/v1/short-links', $url, 'Create URL differs.');
    assertSameValue(BODY, $body, 'Create JSON body differs.');
    assertSameValue(EXPECTED_SIGNATURE, $requestHeaders['X-Taqlyn-Signature'], 'Create signature differs.');
    assertSameValue('application/json', $requestHeaders['Content-Type'], 'Content-Type differs.');

    return [
        'status' => 201,
        'body' => '{"id":"sl_test_123","shortUrl":"https://go.example/Ab12Cd"}',
    ];
};

$client = new Client(
    'https://api.taqlyn.test/',
    'app_test_abc',
    PRIVATE_KEY,
    $transport,
    static fn (): int => 1700000000,
);
$link = $client->createShortLink([
    'destinationWeb' => 'https://example.com/offer',
    'mode' => 'web_only',
]);

assertSameValue(true, $transportCalled, 'Mock transport was not called.');
assertSameValue('sl_test_123', $link['id'], 'Create response ID differs.');

try {
    new Signer('app_test_abc', 'sk_test_not_a_private_key');
    fwrite(STDERR, "Signer accepted a sk_* credential handle.\n");
    exit(1);
} catch (InvalidArgumentException) {
    // Expected: signing requires the issued PKCS#8 PEM.
}

fwrite(STDOUT, "PASS golden signature\nPASS mocked create\nPASS sk_* rejection\n");
