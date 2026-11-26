<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Number every faq in one shared sequence 1..N, in exactly the current
     * public order (records with a position first, by position, then id;
     * records without a position last, by id). Every public locale list is a
     * subset of that order, so none of them changes. Data only, no schema
     * change; idempotent (a second run finds every position already right
     * and writes nothing). Code released before it keeps working, because it
     * already accepts filled positions.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $faqs = DB::table('faqs')
                ->select(['id', 'position'])
                ->orderByRaw('position is null')
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($faqs->values() as $index => $faq) {
                $position = $index + 1;

                if ($faq->position !== null && (int) $faq->position === $position) {
                    continue;
                }

                DB::table('faqs')->where('id', $faq->id)->update(['position' => $position]);
            }
        });
    }

    /**
     * No-op: the previous positions (gaps, duplicates and empty values) are
     * not stored anywhere, so they cannot be restored. The normalized
     * positions are valid for the code released before this migration.
     */
    public function down(): void
    {
        //
    }
};
