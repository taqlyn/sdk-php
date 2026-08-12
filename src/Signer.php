<?php

declare(strict_types=1);

namespace Taqlyn;

use InvalidArgumentException;
use RuntimeException;

final class Signer
{
    private const PKCS8_ED25519_PREFIX = "\x30\x2e\x02\x01\x00\x30\x05\x06\x03\x2b\x65\x70\x04\x22\x04\x20";

    public function __construct(
        private readonly string $clientId,
        string $privateKeyPem,
    ) {
        $this->secretKey = self::loadSecretKey($privateKeyPem);
    }

    private readonly string $secretKey;

    public function canonicalMessage(
        string $method,
        string $path,
        int $timestamp,
        string $body,
    ): string {
        return implode("\n", [
            'taqlyn-v1',
            strtoupper($method),
            $path,
            (string) $timestamp,
            $this->clientId,
            hash('sha256', $body),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function headers(
        string $method,
        string $path,
        string $body,
        ?int $timestamp = null,
    ): array {
        $timestamp ??= time();
        $message = $this->canonicalMessage($method, $path, $timestamp, $body);
        $signature = sodium_crypto_sign_detached($message, $this->secretKey);

        return [
            'X-Taqlyn-Client-Id' => $this->clientId,
            'X-Taqlyn-Timestamp' => (string) $timestamp,
            'X-Taqlyn-Signature' => base64_encode($signature),
        ];
    }

    private static function loadSecretKey(string $privateKeyPem): string
    {
        $privateKeyPem = str_replace('\n', "\n", trim($privateKeyPem));

        if (str_starts_with($privateKeyPem, 'sk_')) {
            throw new InvalidArgumentException(
                'A sk_* credential handle cannot sign requests; provide the issued PKCS#8 PEM private key.',
            );
        }

        if (!preg_match(
            '/\A-----BEGIN PRIVATE KEY-----\s*(.*?)\s*-----END PRIVATE KEY-----\z/s',
            $privateKeyPem,
            $matches,
        )) {
            throw new InvalidArgumentException('Private key must be a PKCS#8 PEM.');
        }

        $der = base64_decode(preg_replace('/\s+/', '', $matches[1]) ?? '', true);
        if ($der === false || !str_starts_with($der, self::PKCS8_ED25519_PREFIX)) {
            throw new InvalidArgumentException('Private key must contain an Ed25519 PKCS#8 key.');
        }

        $seed = substr($der, strlen(self::PKCS8_ED25519_PREFIX));
        if (strlen($seed) !== SODIUM_CRYPTO_SIGN_SEEDBYTES) {
            throw new InvalidArgumentException('Invalid Ed25519 PKCS#8 private key length.');
        }

        $keypair = sodium_crypto_sign_seed_keypair($seed);
        $secretKey = sodium_crypto_sign_secretkey($keypair);
        if (strlen($secretKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new RuntimeException('Unable to load the Ed25519 private key.');
        }

        return $secretKey;
    }
}
