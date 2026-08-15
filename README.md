# taqlyn/sdk-php

**Full guide:** [PHP](../../apps/docs/content/server/php.md) on the docs site.

Taqlyn server SDK for PHP 8.2+. It creates short links with Ed25519-signed
requests. This package is for server-side use only; it does not include mobile
Match or resolve flows.

## Install

```bash
composer require taqlyn/sdk-php
```

The SDK uses PHP's Sodium extension to sign requests.

## Quickstart

```php
<?php

use Taqlyn\Client;

require __DIR__ . '/vendor/autoload.php';

$client = new Client(
    baseUrl: getenv('TAQLYN_BASE_URL'),
    clientId: getenv('TAQLYN_CLIENT_ID'),
    privateKeyPem: getenv('TAQLYN_PRIVATE_KEY'),
);

$link = $client->createShortLink([
    'destinationWeb' => 'https://example.com/offer',
    'mode' => 'web_only',
]);

echo $link['shortUrl'];
```

`TAQLYN_PRIVATE_KEY` must contain the PKCS#8 PEM issued when credentials are
created. Literal `\n` sequences in an environment variable are supported.
An `sk_test_*` or `sk_live_*` value is only a credential handle and cannot sign
requests.

## Signing

Privileged requests include:

- `X-Taqlyn-Client-Id`
- `X-Taqlyn-Timestamp` (Unix seconds)
- `X-Taqlyn-Signature` (standard base64 Ed25519 signature)

The canonical message has no trailing newline:

```text
taqlyn-v1
{METHOD}
{PATH}
{unixTimestamp}
{clientId}
{hex(sha256(body))}
```

## Tests

The smoke test verifies the shared golden signature, a mocked create request,
and rejection of an `sk_*` handle:

```bash
composer test
# or, without installing Composer dependencies:
php tests/smoke.php
```

## License

MIT — see [LICENSE](./LICENSE).
