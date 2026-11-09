<?php

namespace App\Models;

use App\Enums\AiProvider;
use Carbon\CarbonImmutable;
use Database\Factories\AiSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * AI provider and API key set by an administrator on the System page
 * (owner approval 2026-11-09). Singleton: the only row has id
 * {@see self::SINGLETON_ID}. The key is encrypted with APP_KEY, hidden from
 * serialization and never sent to the browser. Without a provider and key
 * the AI_PROVIDER and provider key environment values apply. Attributes are
 * written by UpdateAiSettings only (no mass assignment).
 *
 * @property int $id
 * @property AiProvider|null $provider
 * @property string|null $api_key
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class AiSetting extends Model
{
    /** @use HasFactory<AiSettingFactory> */
    use HasFactory;

    public const int SINGLETON_ID = 1;

    public $incrementing = false;

    protected $keyType = 'int';

    /**
     * @var list<string>
     */
    protected $hidden = ['api_key'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'provider' => AiProvider::class,
            'api_key' => 'encrypted',
            'updated_by' => 'integer',
        ];
    }

    /**
     * Whether the panel sets both the provider and its key.
     */
    public function isConfigured(): bool
    {
        return $this->provider !== null && filled($this->api_key);
    }
}
