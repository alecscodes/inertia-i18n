<?php

use AlecsCodes\InertiaI18n\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Response;

uses(TestCase::class)
    ->afterEach(function () {
        if (isset($this->langBase) && is_dir($this->langBase)) {
            File::deleteDirectory($this->langBase);
        }
    })
    ->in(__DIR__);

/**
 * Create a temporary lang directory and point lang_path() to it.
 * PHP files get var_export()'ed arrays, .json files get json_encode()'d
 * values, and string contents are written verbatim.
 */
function withLangFiles(array $structure): string
{
    $base = sys_get_temp_dir().'/inertia-i18n-tests-'.uniqid();
    mkdir($base, 0755, true);

    foreach ($structure as $path => $content) {
        $full = "{$base}/{$path}";

        if (! is_dir(dirname($full))) {
            mkdir(dirname($full), 0755, true);
        }

        file_put_contents($full, match (true) {
            is_string($content) => $content,
            str_ends_with($path, '.json') => json_encode($content),
            default => "<?php\nreturn ".var_export($content, true).';',
        });
    }

    app()->useLangPath($base);

    return $base;
}

/** Render an Inertia response as an Inertia XHR request and return its props. */
function inertiaProps(Response $response, array $headers = [], bool $assoc = true): mixed
{
    $request = Request::create('/', 'GET', server: [
        'HTTP_X_INERTIA' => 'true',
        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ...$headers,
    ]);

    app()->instance('request', $request);

    $payload = json_decode($response->toResponse($request)->getContent(), $assoc, 512, JSON_THROW_ON_ERROR);

    return $assoc ? $payload['props'] : $payload->props;
}
