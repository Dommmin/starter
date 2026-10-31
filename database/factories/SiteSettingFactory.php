<?php

namespace Database\Factories;

use App\Models\SiteSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteSetting>
 */
class SiteSettingFactory extends Factory
{
    /**
     * Define the model's default state: the singleton row with public
     * contact details and no images.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => SiteSetting::SINGLETON_ID,
            'site_name' => fake()->company(),
            'logo_media_id' => null,
            'og_image_media_id' => null,
            'contact_email' => fake()->unique()->safeEmail(),
            'contact_phone' => '+48 123 456 789',
            'address_line' => fake()->streetAddress(),
            'postal_code' => '00-001',
            'city' => fake()->city(),
            'country_code' => 'PL',
            'contact_recipient_email' => null,
            'contact_recipients' => null,
            'contact_auto_reply' => false,
            'social_links' => null,
            'accent' => null,
            'typography' => null,
            'default_theme' => 'system',
            'favicon_media_id' => null,
            'tax_id' => null,
            'map_url' => null,
            'opening_hours' => null,
            'google_site_verification' => null,
            'plausible_domain' => null,
            'coming_soon' => false,
            'noindex' => false,
            'maintenance' => false,
            'maintenance_until' => null,
            'announcement_enabled' => false,
            'announcement_tone' => 'info',
            'announcement_link_url' => null,
            'announcement_starts_at' => null,
            'announcement_ends_at' => null,
            'updated_by' => null,
        ];
    }
}
