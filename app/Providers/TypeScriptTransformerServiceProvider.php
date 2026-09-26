<?php

namespace App\Providers;

use App\Support\TypeScript\VitePlusFormatter;
use Spatie\LaravelTypeScriptTransformer\LaravelData\LaravelDataTypeScriptTransformerExtension;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider;
use Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;

/**
 * Generates `resources/js/types/generated.d.ts` (`composer types:generate`)
 * from Laravel Data classes, `#[TypeScript]` classes and backed enums in app/.
 *
 * spatie/laravel-typescript-transformer is a dev dependency, so this provider
 * is registered by AppServiceProvider only when the package is installed.
 * The output file is committed and must not be edited by hand.
 */
class TypeScriptTransformerServiceProvider extends TypeScriptTransformerApplicationServiceProvider
{
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        $config
            ->extension(new LaravelDataTypeScriptTransformerExtension)
            ->transformer(AttributedClassTransformer::class)
            ->transformer(EnumTransformer::class)
            ->transformDirectories(app_path())
            ->outputDirectory(resource_path('js/types'))
            ->writer(new GlobalNamespaceWriter('generated.d.ts'))
            ->formatter(VitePlusFormatter::class)
            ->withoutManifest();
    }
}
