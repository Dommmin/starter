<?php

use App\Enums\AccentColor;

/**
 * Accent presets live in resources/css/app.css as `--accent-hue` / `--accent-l`
 * overrides. These tests resolve the token values from the stylesheet, so a
 * new preset or a token edit that drops below WCAG AA (4.5:1 for text) fails.
 */
function accentCss(): string
{
    return file_get_contents(__DIR__.'/../../resources/css/app.css');
}

/**
 * Declarations of the first block whose selector line matches the pattern.
 *
 * @return array<string, string>
 */
function accentBlock(string $selectorPattern): array
{
    preg_match('/^'.$selectorPattern.'\s*\{(.*?)^\}/ms', accentCss(), $match);
    preg_match_all('/^\s*(--[a-z-]+):\s*(.+?);$/ms', $match[1] ?? '', $declarations, PREG_SET_ORDER);

    return array_map(
        fn (string $value): string => preg_replace(['/\s+/', '/\( /', '/ \)/'], [' ', '(', ')'], trim($value)),
        array_column($declarations, 2, 1),
    );
}

/**
 * @param  array<string, string>  $variables
 */
function accentResolve(string $value, array $variables): float
{
    $value = preg_replace_callback('/var\((--[a-z-]+)\)/', fn (array $m): string => $variables[$m[1]], $value);

    while (preg_match('/calc\(([^()]*)\)/', $value, $m)) {
        preg_match('/^\s*(-?[\d.]+)\s*([+-])\s*(-?[\d.]+)\s*$/', $m[1], $parts);
        $result = $parts[2] === '+' ? $parts[1] + $parts[3] : $parts[1] - $parts[3];
        $value = str_replace($m[0], (string) $result, $value);
    }

    return (float) $value;
}

/**
 * @param  array<string, string>  $variables
 * @return array{0: float, 1: float, 2: float}
 */
function accentColor(string $declaration, array $variables): array
{
    $atom = 'calc\((?:[^()]|\([^()]*\))*\)|var\([^)]*\)|[^\s)]+';
    preg_match("/^oklch\\(\\s*({$atom})\\s+({$atom})\\s+({$atom})/", $declaration, $m);
    [$lightness, $chroma, $hue] = [accentResolve($m[1], $variables), accentResolve($m[2], $variables), accentResolve($m[3], $variables)];

    $a = $chroma * cos(deg2rad($hue));
    $b = $chroma * sin(deg2rad($hue));
    $l = ($lightness + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
    $m = ($lightness - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
    $s = ($lightness - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

    return array_map(
        fn (float $channel): float => min(1.0, max(0.0, $channel)),
        [
            4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
            -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
            -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
        ],
    );
}

/**
 * @param  array{0: float, 1: float, 2: float}  $a
 * @param  array{0: float, 1: float, 2: float}  $b
 */
function accentContrast(array $a, array $b): float
{
    $luminance = fn (array $c): float => 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    [$high, $low] = [max($luminance($a), $luminance($b)), min($luminance($a), $luminance($b))];

    return ($high + 0.05) / ($low + 0.05);
}

/**
 * Surfaces × modes that carry the accent, with the selectors of their token blocks.
 */
dataset('accent surfaces', [
    'public light' => [[':root'], ':root'],
    'public dark' => [['\.dark'], '\.dark'],
    'admin light' => [[':root', "\[data-surface='admin'\]"], "\[data-surface='admin'\]"],
    'admin dark' => [[':root', "\[data-surface='admin'\]", '\.dark'], "\.dark\[data-surface='admin'\],\n\.dark \[data-surface='admin'\]"],
]);

$presets = array_map(fn (AccentColor $accent): string => $accent->value, AccentColor::cases());

test('every accent preset has a stylesheet block and the other way round', function () use ($presets) {
    preg_match_all("/^\[data-accent='([a-z]+)'\]/m", accentCss(), $found);

    expect($found[1])->toEqualCanonicalizing(array_values(array_diff($presets, ['default'])));
});

test('accent buttons and links keep AA contrast in every surface and mode', function (array $bases, string $selector, string $preset) {
    $variables = accentBlock(':root');
    $variables = [
        '--accent-hue' => $variables['--accent-hue'],
        '--accent-l' => $variables['--accent-l'],
    ];
    if ($preset !== 'default') {
        $variables = array_merge($variables, accentBlock("\[data-accent='{$preset}'\]"));
    }

    $tokens = [];
    foreach ($bases as $base) {
        $tokens = array_merge($tokens, accentBlock($base));
    }
    $tokens = array_merge($tokens, accentBlock($selector));

    $button = accentContrast(accentColor($tokens['--primary'], $variables), accentColor($tokens['--primary-foreground'], $variables));
    $link = accentContrast(accentColor($tokens['--brand-text'], $variables), accentColor($tokens['--background'], $variables));

    expect($button)->toBeGreaterThanOrEqual(4.5, "{$preset} primary button")
        ->and($link)->toBeGreaterThanOrEqual(4.5, "{$preset} brand text");
})->with('accent surfaces')
    ->with(array_map(fn (AccentColor $accent): string => $accent->value, AccentColor::cases()));
