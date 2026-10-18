<?php

namespace App\Http\Controllers\Admin\Import;

use App\Actions\Import\ImportCsv;
use App\Contracts\Import\ImportDefinition;
use App\Data\Admin\Import\ImportPreviewData;
use App\Data\Admin\Import\ImportScreenData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Import\CommitImportRequest;
use App\Http\Requests\Admin\Import\PreviewImportRequest;
use App\Models\User;
use App\Support\Import\ImportDefinitions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CSV import of a module (administrators; `import` ability on the module
 * model): upload and check, then confirm the checked rows.
 */
class ImportController extends Controller
{
    public function show(string $module): Response
    {
        return $this->screen($this->definition($module));
    }

    public function preview(PreviewImportRequest $request, string $module, ImportCsv $import): Response
    {
        $definition = $this->definition($module);
        /** @var UploadedFile $file */
        $file = $request->file('file');

        return $this->screen($definition, $import->preview($definition, $file, $this->user($request)));
    }

    public function store(CommitImportRequest $request, string $module, ImportCsv $import): RedirectResponse
    {
        $definition = $this->definition($module);
        $count = $import->commit($definition, $request->string('token')->toString(), $this->user($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.import.imported', ['count' => $count])]);

        return to_route("admin.{$definition->key()}.index");
    }

    private function definition(string $module): ImportDefinition
    {
        $definition = ImportDefinitions::find($module);
        abort_if($definition === null, 404);
        Gate::authorize('import', $definition->model());

        return $definition;
    }

    private function screen(ImportDefinition $definition, ?ImportPreviewData $preview = null): Response
    {
        return Inertia::render('admin/import/show', new ImportScreenData(
            module: $definition->key(),
            columns: $definition->columns(),
            requiredColumns: $definition->requiredColumns(),
            maxRows: ImportCsv::MAX_ROWS,
            maxKilobytes: ImportCsv::MAX_KILOBYTES,
            preview: $preview,
        ));
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
