<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * pawaPay HTTP Message Signature configuration.
 */
const PAWAPAY_KEY_ID = 'pawapay-public';
const PAWAPAY_SIGNATURE_ALGORITHM = 'rsa-v1_5-sha256';
const PAWAPAY_PRIVATE_KEY = __DIR__ . '/pawapay-private.pem';

/**
 * Make a request to the pawaPay API.
 *
 * Financial requests are signed using HTTP Message Signatures.
 */
function pawapayRequest(string $method, string $path, ?array $body = null): array
{
    $config = config();

    if ($config['token'] === '') {
        throw new RuntimeException(
            'PAWAPAY_API_TOKEN is not configured.'
        );
    }

    if (
        strtoupper($method) !== 'GET' &&
        !is_file(PAWAPAY_PRIVATE_KEY)
    ) {
        throw new RuntimeException(
            'pawaPay private key not found: ' . PAWAPAY_PRIVATE_KEY
        );
    }

    $method = strtoupper($method);

    $url = $config['base_url'] . '/' . ltrim($path, '/');

    /*
     * Encode the body ONCE.
     *
     * The exact bytes here must be:
     * 1. hashed for Content-Digest
     * 2. sent to pawaPay as the request body
     */
    $jsonBody = null;

    if ($body !== null) {
        $jsonBody = json_encode(
            $body,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }

    $headers = [
        'Authorization: Bearer ' . $config['token'],
        'Accept: application/json',
        'Content-Type: application/json; charset=UTF-8',
    ];

    /*
     * Sign financial requests.
     */
    if ($body !== null && $method !== 'GET') {
        $signedHeaders = pawapaySignatureHeaders(
            $method,
            $url,
            $jsonBody
        );

        $headers = array_merge($headers, $signedHeaders);
    }

    $ch = curl_init($url);

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_CUSTOMREQUEST => $method,
    ];

    if ($jsonBody !== null) {
        $options[CURLOPT_POSTFIELDS] = $jsonBody;
    }

    curl_setopt_array($ch, $options);

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($raw === false || $curlError !== '') {
        throw new RuntimeException(
            'cURL error: ' . $curlError
        );
    }

    $decoded = json_decode($raw, true);

    return [
        'http_code' => $httpCode,
        'data' => is_array($decoded)
            ? $decoded
            : ['raw' => $raw],
        'raw' => $raw,
    ];
}

/**
 * Generate pawaPay HTTP Message Signature headers.
 *
 * Implements the signature structure documented by pawaPay:
 *
 * @method
 * @authority
 * @path
 * signature-date
 * content-digest
 * content-type
 */
function pawapaySignatureHeaders(
    string $method,
    string $url,
    string $body
): array {
    /*
     * Parse URL components.
     */
    $parts = parse_url($url);

    if ($parts === false) {
        throw new RuntimeException(
            'Unable to parse pawaPay URL.'
        );
    }

    $scheme = $parts['scheme'] ?? 'https';
    $host = $parts['host'] ?? '';

    if ($host === '') {
        throw new RuntimeException(
            'pawaPay URL does not contain a host.'
        );
    }

    $port = $parts['port'] ?? null;

    /*
     * @authority contains host and, when necessary, port.
     */
    $authority = $host;

    if (
        $port !== null &&
        !(($scheme === 'https' && $port === 443) ||
          ($scheme === 'http' && $port === 80))
    ) {
        $authority .= ':' . $port;
    }

    /*
     * RFC 9421 @path.
     *
     * This must include the path exactly as sent.
     */
    $path = $parts['path'] ?? '/';

    if ($path === '') {
        $path = '/';
    }

    /*
     * Content-Digest.
     *
     * pawaPay accepts SHA-512.
     */
    $digest = base64_encode(
        hash('sha512', $body, true)
    );

    $contentDigest = 'sha-512=:' . $digest . ':';

    /*
     * Signature validity window.
     *
     * pawaPay's examples use created/expires epoch values
     * in Signature-Input and an ISO-8601 Signature-Date header.
     */
    $now = new DateTimeImmutable(
        'now',
        new DateTimeZone('UTC')
    );

    $created = $now->getTimestamp();
    $expires = $created + 60;

    $signatureDate = $now->format(
        'Y-m-d\TH:i:s.v\Z'
    );

    /*
     * This MUST exactly match the Content-Type header
     * that is sent with the request.
     */
    $contentType = 'application/json; charset=UTF-8';

    /*
     * RFC 9421 signature parameters.
     */
    $signatureParams =
        '("@method" "@authority" "@path" ' .
        '"signature-date" "content-digest" "content-type")' .
        ';alg="' . PAWAPAY_SIGNATURE_ALGORITHM . '"' .
        ';keyid="' . PAWAPAY_KEY_ID . '"' .
        ';created=' . $created .
        ';expires=' . $expires;

    /*
     * Construct the signature base exactly as required
     * by pawaPay.
     */
    $signatureBase =
        '"@method": ' . $method . "\n" .
        '"@authority": ' . $authority . "\n" .
        '"@path": ' . $path . "\n" .
        '"signature-date": ' . $signatureDate . "\n" .
        '"content-digest": ' . $contentDigest . "\n" .
        '"content-type": ' . $contentType . "\n" .
        '"@signature-params": ' . $signatureParams;

    /*
     * Load RSA private key.
     */
    $privateKeyPem = file_get_contents(PAWAPAY_PRIVATE_KEY);

    if ($privateKeyPem === false) {
        throw new RuntimeException(
            'Unable to read pawaPay private key.'
        );
    }

    $privateKey = openssl_pkey_get_private(
        $privateKeyPem
    );

    if ($privateKey === false) {
        throw new RuntimeException(
            'Unable to load pawaPay RSA private key: ' .
            openssl_error_string()
        );
    }

    /*
     * rsa-v1_5-sha256
     *
     * PHP's OPENSSL_ALGO_SHA256 uses
     * RSASSA-PKCS1-v1_5 with SHA-256.
     */
    $signatureBinary = '';

    $signed = openssl_sign(
        $signatureBase,
        $signatureBinary,
        $privateKey,
        OPENSSL_ALGO_SHA256
    );

    if (!$signed) {
        throw new RuntimeException(
            'Unable to create pawaPay signature: ' .
            (openssl_error_string() ?: 'unknown OpenSSL error')
        );
    }

    /*
     * Free OpenSSL key resource/object.
     */
    unset($privateKey);

    $signature = base64_encode($signatureBinary);

    return [
        'Content-Digest: ' . $contentDigest,

        'Signature-Date: ' . $signatureDate,

        'Signature: sig-pp=:' . $signature . ':',

        'Signature-Input: sig-pp=' . $signatureParams,

        /*
         * Tell pawaPay which signature and digest
         * algorithms our client supports.
         */
        'Accept-Signature: rsa-pss-sha512,ecdsa-p256-sha256,rsa-v1_5-sha256,ecdsa-p384-sha384',

        'Accept-Digest: sha-256,sha-512',
    ];
}

/**
 * Create a pawaPay deposit.
 */
function createDeposit(array $input): array
{
    $id = uuidV4();

    $payload = [
        'amount' => number_format(
            (float)$input['amount'],
            2,
            '.',
            ''
        ),

        'currency' => config()['currency'],

        'depositId' => $id,

        'payer' => [
            'type' => 'MMO',

            'accountDetails' => [
                'provider' => $input['provider'],

                'phoneNumber' => preg_replace(
                    '/\D+/',
                    '',
                    $input['phone']
                ),
            ],
        ],

        'clientReferenceId' => $input['reference'],

        'customerMessage' => substr(
            $input['message'],
            0,
            22
        ),
    ];

    $result = pawapayRequest(
        'POST',
        '/v2/deposits',
        $payload
    );

    $result['request'] = $payload;

    return $result;
}

/**
 * Get a deposit by ID.
 */
function getDeposit(string $depositId): array
{
    return pawapayRequest(
        'GET',
        '/v2/deposits/' . rawurlencode($depositId)
    );
}

/**
 * Get provider availability.
 */
function getAvailability(): array
{
    $country = rawurlencode(
        config()['country']
    );

    return pawapayRequest(
        'GET',
        '/v2/availability?country=' .
        $country .
        '&operationType=DEPOSIT'
    );
}

/**
 * Get active pawaPay configuration.
 */
function getActiveConfiguration(): array
{
    return pawapayRequest(
        'GET',
        '/v2/active-conf'
    );
}

/**
 * Generate UUID v4.
 */
function uuidV4(): string
{
    $data = random_bytes(16);

    $data[6] = chr(
        (ord($data[6]) & 0x0f) | 0x40
    );

    $data[8] = chr(
        (ord($data[8]) & 0x3f) | 0x80
    );

    return vsprintf(
        '%s%s-%s-%s-%s-%s%s%s',
        str_split(
            bin2hex($data),
            4
        )
    );
}
