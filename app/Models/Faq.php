<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\FaqFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Faq managed in the administration panel.
 *
 * @property int $id
 * @property string $question
 * @property string $answer
 * @property string|null $locale Null: shown in every public locale.
 * @property int|null $position
 * @property bool $published
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['question', 'answer', 'locale', 'position', 'published'])]
class Faq extends Model
{
    /** @use HasFactory<FaqFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'published' => 'boolean',
        ];
    }
}
