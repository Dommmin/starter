import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

// Vitest boots a Vite server only to transform modules, not to serve HMR, so
// laravel-vite-plugin's "no dev server in CI" guard must not abort the tests.
if (process.env.VITEST) {
    process.env.LARAVEL_BYPASS_ENV_CHECK = '1';
}

const vitePort = Number(process.env.VITE_PORT ?? 5173);
const appOrigin = `http://localhost:${process.env.APP_PORT ?? 8080}`;

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                    // latin-ext carries Polish diacritics (ą, ę, ł...); faces are
                    // split by unicode-range, so browsers fetch it only when used.
                    subsets: ['latin', 'latin-ext'],
                    // No preload: laravel-vite-plugin 3.x emits separate woff2 and
                    // woff @font-face rules with identical descriptors, so browsers
                    // use the last (woff) one and preloaded woff2 files are wasted.
                    // Revisit (preload weight 400 only) once the plugin merges
                    // formats into one `src` list.
                    preload: false,
                }),
                // Admin surface only (`--admin-font-*`); faces load on first use.
                bunny('Geist', {
                    weights: [400, 500, 600],
                    subsets: ['latin', 'latin-ext'],
                    preload: false,
                }),
                bunny('Geist Mono', {
                    weights: [400, 500],
                    subsets: ['latin', 'latin-ext'],
                    preload: false,
                }),
            ],
        }),
        inertia(),
        react(),
        babel({
            presets: [reactCompilerPreset()],
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ]),
    build: {
        rolldownOptions: {
            treeshake: {
                // Design-system primitives are pure component modules reached
                // through the `@/design-system/primitives` barrel. Without this
                // hint every re-exported primitive (select, data table, rich
                // text...) is kept on the critical path of each page.
                moduleSideEffects: [
                    {
                        test: /\/resources\/js\/design-system\/primitives\/.+\.tsx?$/,
                        sideEffects: false,
                    },
                ],
            },
        },
    },
    server: {
        ...(process.env.DOCKER_LOCAL === '1'
            ? {
                  host: '0.0.0.0',
                  port: vitePort,
                  strictPort: true,
                  origin: `http://localhost:${vitePort}`,
                  cors: {
                      origin: appOrigin,
                  },
                  hmr: {
                      host: 'localhost',
                      clientPort: vitePort,
                  },
              }
            : {}),
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    test: {
        // Playwright specs run through `make e2e`, never under vitest.
        // `.claude/worktrees/*` are agent checkouts of other branches, not
        // tests of this one.
        exclude: ['**/node_modules/**', 'tests/e2e/**', '.claude/**'],
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            'composer.json',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            entryPoint: 'resources/css/app.css',
        },
    },
});
