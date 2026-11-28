<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Content\BuildContentExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * JSON download of the site content (administrators, confirmed password).
 */
class ContentExportController extends Controller
{
    public function __invoke(Request $request, BuildContentExport $export): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $export->handle($user);

        return response()->streamDownload(
            function () use ($data): void {
                echo json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            },
            'content-'.now()->format('Y-m-d').'.json',
            ['Content-Type' => 'application/json', 'Cache-Control' => 'private, no-store'],
        );
    }
}
