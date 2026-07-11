<?php
/**
 * HTTP smoke checks for the Mi Pilates portal and PWA endpoints.
 *
 * Usage: TP_PORTAL_URL=https://example.com/mi-pilates php tests/portal-pwa-smoke.php
 *
 * @package TatiPilates
 */

$portal_url = getenv('TP_PORTAL_URL');

if (!$portal_url) {
    fwrite(STDERR, "Define TP_PORTAL_URL con la URL absoluta del portal /mi-pilates.\n");
    exit(1);
}

$portal_url = rtrim($portal_url, '/');
$base_url   = preg_replace('#/mi-pilates$#', '', $portal_url);

/**
 * Fails the smoke test.
 *
 * @param string $message Failure message.
 * @return void
 */
function tp_portal_smoke_fail($message) {
    fwrite(STDERR, 'FAIL ' . $message . PHP_EOL);
    exit(1);
}

/**
 * Performs one HTTP GET request.
 *
 * @param string $url URL.
 * @param string $accept Accept header.
 * @return array{status:int,body:string,headers:string}
 */
function tp_portal_smoke_get($url, $accept = 'text/html') {
    $headers = array(
        'Accept: ' . $accept,
        'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
    );

    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array(
            $curl,
            array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER         => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_HTTPHEADER     => $headers,
            )
        );
        $response = curl_exec($curl);

        if (false === $response) {
            $error = curl_error($curl);
            tp_portal_smoke_fail($url . ' no respondio: ' . $error);
        }

        $status      = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $header_size = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);

        return array(
            'status'  => $status,
            'headers' => substr((string) $response, 0, $header_size),
            'body'    => substr((string) $response, $header_size),
        );
    }

    $context = stream_context_create(
        array(
            'http' => array(
                'method'  => 'GET',
                'header'  => implode("\r\n", $headers),
                'timeout' => 20,
            ),
        )
    );
    $body = file_get_contents($url, false, $context);

    if (false === $body) {
        tp_portal_smoke_fail($url . ' no respondio.');
    }

    $status = 0;
    $headers_list = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : array();
    $raw_headers = is_array($headers_list) ? implode("\n", $headers_list) : '';

    if (preg_match('#HTTP/\S+\s+(\d+)#', $raw_headers, $matches)) {
        $status = (int) $matches[1];
    }

    return array(
        'status'  => $status,
        'headers' => $raw_headers,
        'body'    => $body,
    );
}

/**
 * Asserts an HTTP 2xx response.
 *
 * @param array<string,mixed> $response Response.
 * @param string             $label Label.
 * @return void
 */
function tp_portal_smoke_assert_ok($response, $label) {
    $status = (int) ($response['status'] ?? 0);

    if ($status < 200 || $status >= 300) {
        tp_portal_smoke_fail($label . ' respondio HTTP ' . $status);
    }
}

$portal = tp_portal_smoke_get($portal_url);
tp_portal_smoke_assert_ok($portal, 'Portal');

foreach (array('rel="manifest"', 'apple-mobile-web-app-capable', 'theme-color', 'tp-portal') as $needle) {
    if (false === strpos($portal['body'], $needle)) {
        tp_portal_smoke_fail('Portal no contiene ' . $needle);
    }
}

$manifest = tp_portal_smoke_get($base_url . '/?tp_portal_manifest=1', 'application/manifest+json,application/json');
tp_portal_smoke_assert_ok($manifest, 'Manifest');
$manifest_json = json_decode($manifest['body'], true);

if (!is_array($manifest_json) || empty($manifest_json['name']) || empty($manifest_json['icons'])) {
    tp_portal_smoke_fail('Manifest no contiene name/icons validos.');
}

$worker = tp_portal_smoke_get($base_url . '/?tp_portal_sw=1', 'application/javascript,text/javascript');
tp_portal_smoke_assert_ok($worker, 'Service worker');

foreach (array('install', 'fetch', 'tp_portal_offline') as $needle) {
    if (false === strpos($worker['body'], $needle)) {
        tp_portal_smoke_fail('Service worker no contiene ' . $needle);
    }
}

$offline = tp_portal_smoke_get($base_url . '/?tp_portal_offline=1');
tp_portal_smoke_assert_ok($offline, 'Offline');

if (false === strpos($offline['body'], 'Mi Pilates')) {
    tp_portal_smoke_fail('Offline shell no contiene Mi Pilates.');
}

echo 'PASS portal-pwa-smoke ' . $portal_url . PHP_EOL;
