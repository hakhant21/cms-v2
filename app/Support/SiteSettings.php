<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The single catalogue of editable site settings.
 *
 * Each entry declares the storage key, the editor control and the label, so
 * adding a setting means adding one line here — the admin form, the typed
 * reads and the frontend all follow automatically.
 */
final class SiteSettings
{
    public const TYPE_TEXT = 'text';

    public const TYPE_TEXTAREA = 'textarea';

    public const TYPE_BOOL = 'bool';

    public const TYPE_COLOR = 'color';

    public const TYPE_SELECT = 'select';

    public const TYPE_OFFICES = 'offices';

    /**
     * @return array<string, array{label: string, description: string, fields: array<string, array{label: string, type: string, help?: string, options?: array<string, string>}>}>
     */
    public static function groups(): array
    {
        return [
            'company' => [
                'label' => 'Company',
                'description' => 'Shown in the header, the footer and on the contact page.',
                'fields' => [
                    'company.name' => ['label' => 'Company name', 'type' => self::TYPE_TEXT],
                    'company.tagline' => ['label' => 'Tagline', 'type' => self::TYPE_TEXT, 'help' => 'A short line under the name in the footer.'],
                    'company.email' => ['label' => 'Email', 'type' => self::TYPE_TEXT],
                    'company.phone' => ['label' => 'Phone', 'type' => self::TYPE_TEXT],
                    'company.phone_secondary' => ['label' => 'Second phone', 'type' => self::TYPE_TEXT],
                    'company.address' => ['label' => 'Address', 'type' => self::TYPE_TEXTAREA],
                    'company.latitude' => ['label' => 'Latitude', 'type' => self::TYPE_TEXT, 'help' => 'Decimal degrees, e.g. 19.7616782. The map is centred here.'],
                    'company.longitude' => ['label' => 'Longitude', 'type' => self::TYPE_TEXT, 'help' => 'Decimal degrees, e.g. 96.2196115.'],
                    'company.country' => ['label' => 'Country code', 'type' => self::TYPE_TEXT, 'help' => 'Two letters, e.g. MM. Decides which region the map labels are drawn for.'],
                    'company.map_place_id' => ['label' => 'Google Maps Place ID', 'type' => self::TYPE_TEXT, 'help' => 'The 0x…:0x… id from Google Maps “Share → Embed a map”. With it the map shows your own listing — name, address, hours — instead of a bare pin.'],
                    'company.map_query' => ['label' => 'Map address', 'type' => self::TYPE_TEXT, 'help' => 'Where “Open in Google Maps” points, and the pin used when no Place ID is set.'],
                    'company.map_zoom' => [
                        'label' => 'Map zoom',
                        'type' => self::TYPE_SELECT,
                        'options' => ['12' => 'District', '14' => 'Neighbourhood', '16' => 'Street', '18' => 'Building'],
                    ],
                    'company.office_hours' => ['label' => 'Office hours', 'type' => self::TYPE_TEXT],
                    'company.offices' => [
                        'label' => 'Offices',
                        'type' => self::TYPE_OFFICES,
                        'help' => 'Every office, entered once. The contact page shows them in all languages — the labels translate themselves, so an address change is a single edit here.',
                    ],
                ],
            ],
            'contact' => [
                'label' => 'Contact form',
                'description' => 'Where enquiries are delivered and what visitors are told.',
                'fields' => [
                    'contact.notify_email' => ['label' => 'Send enquiries to', 'type' => self::TYPE_TEXT, 'help' => 'Leave empty to use the company email.'],
                    'contact.success_message' => ['label' => 'Thank-you message', 'type' => self::TYPE_TEXTAREA],
                    'contact.form_enabled' => ['label' => 'Accept new messages', 'type' => self::TYPE_BOOL],
                ],
            ],
            'chat' => [
                'label' => 'Live chat',
                'description' => 'The bubble in the corner of every page, and the inbox in the admin.',
                'fields' => [
                    'chat.enabled' => ['label' => 'Show the chat bubble', 'type' => self::TYPE_BOOL],
                    'chat.greeting' => [
                        'label' => 'Opening line',
                        'type' => self::TYPE_TEXTAREA,
                        'help' => 'Written into a brand-new conversation before the visitor\'s first message. Leave empty for no line.',
                    ],
                ],
            ],
            'theme' => [
                'label' => 'Look and feel',
                'description' => 'Colours and motion for the public site.',
                'fields' => [
                    'theme.primary' => ['label' => 'Primary colour', 'type' => self::TYPE_COLOR],
                    'theme.accent' => ['label' => 'Accent colour', 'type' => self::TYPE_COLOR],
                    'theme.surface' => ['label' => 'Page background', 'type' => self::TYPE_COLOR],
                    'theme.text' => ['label' => 'Body text', 'type' => self::TYPE_COLOR],
                    'theme.animation' => [
                        'label' => 'Animation',
                        'type' => self::TYPE_SELECT,
                        'options' => ['dynamic' => 'Dynamic', 'calm' => 'Calm', 'none' => 'None'],
                    ],
                    'theme.animation_duration' => ['label' => 'Animation speed', 'type' => self::TYPE_TEXT, 'help' => 'A CSS duration such as 900ms or 1.2s.'],
                ],
            ],
            'site' => [
                'label' => 'Site',
                'description' => 'Which page the home URL shows, and the small print under every page.',
                'fields' => [
                    'site.home_slug' => [
                        'label' => 'Home page',
                        'type' => self::TYPE_TEXT,
                        'help' => 'The address of the page shown at /. Leave as home unless you renamed it.',
                    ],
                    'site.footer_note' => [
                        'label' => 'Footer note',
                        'type' => self::TYPE_TEXTAREA,
                        'help' => 'Optional line under the contact details, e.g. a registration number.',
                    ],
                ],
            ],
            'social' => [
                'label' => 'Social links',
                'description' => 'Leave a field empty to hide that icon in the footer.',
                'fields' => [
                    'social.facebook' => ['label' => 'Facebook URL', 'type' => self::TYPE_TEXT],
                    'social.instagram' => ['label' => 'Instagram URL', 'type' => self::TYPE_TEXT],
                    'social.youtube' => ['label' => 'YouTube URL', 'type' => self::TYPE_TEXT],
                    'social.tiktok' => ['label' => 'TikTok URL', 'type' => self::TYPE_TEXT],
                ],
            ],
        ];
    }

    /**
     * The form field name for a storage key. Dots would nest the Livewire
     * state, so they become underscores and are mapped back on save.
     */
    public static function fieldName(string $key): string
    {
        return str_replace(['.', '-'], '_', $key);
    }

    /**
     * Every key with its declared control type.
     *
     * @return array<string, array{label: string, type: string, help?: string, options?: array<string, string>}>
     */
    public static function fields(): array
    {
        $fields = [];

        foreach (self::groups() as $group) {
            $fields += $group['fields'];
        }

        return $fields;
    }

    /**
     * The category a key is stored under.
     */
    public static function categoryOf(string $key): string
    {
        foreach (self::groups() as $category => $group) {
            if (array_key_exists($key, $group['fields'])) {
                return $category;
            }
        }

        return 'general';
    }

    /**
     * Current typed values, keyed by storage key.
     *
     * @return array<string, mixed>
     */
    public static function values(): array
    {
        $values = [];

        foreach (self::fields() as $key => $field) {
            $values[$key] = match ($field['type']) {
                self::TYPE_BOOL => Setting::bool($key, true),
                self::TYPE_OFFICES => Setting::json($key),
                default => Setting::get($key),
            };
        }

        return $values;
    }
}
