<?php

use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;

test('preload link header is capped for split bundles', function () {
    $this->swap(Vite::class, new class extends Vite
    {
        public function __invoke($entrypoints, $buildDirectory = null): HtmlString
        {
            return new HtmlString('');
        }

        /** @return array<string, array<int, string>> */
        public function preloadedAssets(): array
        {
            return collect(range(1, 45))
                ->mapWithKeys(fn (int $index): array => [
                    "/build/assets/chunk-{$index}.js" => ['rel="modulepreload"', 'as="script"'],
                ])
                ->all();
        }
    });

    $response = $this->get(route('home'));

    $response->assertOk();

    $links = explode(', ', (string) $response->headers->get('Link'));

    expect($links)->toHaveCount(20)
        ->and($links[0])->toBe('</build/assets/chunk-1.js>; rel="modulepreload"; as="script"');
});
