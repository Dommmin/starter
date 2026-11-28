<?php

namespace App\Http\Controllers\Admin\Quality;

use App\Http\Controllers\Controller;
use App\Services\Content\ContentQualityReport;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only content checklist for every panel user; fixing happens in the
 * editors, which check their own abilities.
 */
class ContentQualityController extends Controller
{
    public function __invoke(ContentQualityReport $report): Response
    {
        return Inertia::render('admin/quality/index', $report->build());
    }
}
