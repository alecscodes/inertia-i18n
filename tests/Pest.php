<?php

use AlecsCodes\InertiaI18n\Tests\TestCase;

// Bind only the standard suite files; CustomPropsTest has its own TestCase.
uses(TestCase::class)->in(__DIR__.'/TranslationLoaderTest.php', __DIR__.'/ServiceProviderTest.php');

function withLangFiles(array $structure): string
{
    $base = sys_get_temp_dir().'/inertia-i18n-tests-'.uniqid();
    mkdir($base, 0755, true);
    foreach ($structure as $path => $content) {
        $full = $base.'/'.$path;
        $dir = dirname($full);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (str_ends_with($path, '.json')) {
            file_put_contents($full, json_encode($content, JSON_PRETTY_PRINT));
        } else {
            file_put_contents($full, "<?php\nreturn ".var_export($content, true).';');
        }
    }
    app()->useLangPath($base);
    return $base;
}

function inertiaRequest(array $headers = []): \Illuminate\Http\Request
{
    return \Illuminate\Http\Request::create('/', 'GET', server: array_merge([
        'HTTP_X_INERTIA' => 'true',
        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
    ], $headers));
}

function inertiaProps(\Inertia\Response $response, array $headers = [], ?\Illuminate\Http\Request $request = null): array
{
    $request ??= inertiaRequest($headers);
    app()->instance('request', $request);
    $payload = json_decode($response->toResponse($request)->getContent(), true);
    return is_array($payload) && isset($payload['props']) && is_array($payload['props']) ? $payload['props'] : [];
}

function rawInertiaProps(\Inertia\Response $response, ?\Illuminate\Http\Request $request = null): \stdClass
{
    $request ??= inertiaRequest();
    app()->instance('request', $request);
    return json_decode($response->toResponse($request)->getContent(), false, 512, JSON_THROW_ON_ERROR)->props;
}
