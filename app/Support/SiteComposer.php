<?php

namespace App\Support;

use App\Models\Language;
use App\Models\NavigationItem;
use App\Models\Setting;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * Supplies the data every public page needs: theme tokens, the language list,
 * both navigation trees and the social links.
 *
 * The layout never reads a model directly, so the admin screens stay the only
 * place that has to know how content is stored.
 */
final class SiteComposer
{
    /**
     * @return array<string, mixed>
     */
    public static function data(): array
    {
        $codes = Language::activeCodes();

        $languages = Language::query()
            ->active()
            ->get()
            ->filter(fn (Language $language): bool => in_array($language->code, $codes, true))
            ->values();

        $current = app()->getLocale();
        $currentLanguage = $languages->firstWhere('code', $current);

        return [
            'siteName' => (string) Setting::get('company.name', config('app.name')),
            'tagline' => (string) Setting::get('company.tagline', ''),
            'animation' => self::animation(),
            'themeStyle' => self::themeStyle(),
            'direction' => $currentLanguage?->direction === 'rtl' ? 'rtl' : 'ltr',
            'languages' => $languages,
            'currentLanguage' => $currentLanguage,
            'headerNav' => Route::has('home') ? NavigationItem::tree(NavigationItem::PLACEMENT_HEADER) : collect(),
            'footerNav' => Route::has('home') ? NavigationItem::tree(NavigationItem::PLACEMENT_FOOTER) : collect(),
            'socialLinks' => self::socialLinks(),
            'currentPath' => '/'.ltrim(request()->path(), '/'),
        ];
    }

    public function compose(View $view): void
    {
        try {
            $data = self::data();
        } catch (\Throwable) {
            // The most common reason an error page is shown is a database
            // outage. Reading settings and menus there would fail a second
            // time, so fall back to the bare chrome.
            $data = self::fallback();
        }

        $existing = $view->getData();

        // Defaults only: a page template that already received its own model
        // and SEO payload must not have them replaced by null.
        if (! array_key_exists('page', $existing)) {
            $data['page'] = null;
        }

        if (! array_key_exists('seo', $existing)) {
            $data['seo'] = null;
        }

        $view->with($data);
    }

    /**
     * The minimum the layout needs to render without touching the database.
     *
     * @return array<string, mixed>
     */
    private static function fallback(): array
    {
        return [
            'siteName' => (string) config('app.name'),
            'tagline' => '',
            'animation' => 'dynamic',
            'themeStyle' => '',
            'direction' => 'ltr',
            'languages' => collect(),
            'currentLanguage' => null,
            'headerNav' => collect(),
            'footerNav' => collect(),
            'socialLinks' => [],
            'currentPath' => '/'.ltrim(request()->path(), '/'),
        ];
    }

    /** The motion preset an editor picked, translated to a body class suffix. */
    private static function animation(): string
    {
        $mode = (string) Setting::get('theme.animation', 'dynamic');

        return in_array($mode, ['dynamic', 'calm', 'none'], true) ? $mode : 'dynamic';
    }

    /**
     * Inline custom properties: the whole design system derives from these, so
     * changing a colour in the admin repaints the site without a rebuild.
     */
    private static function themeStyle(): string
    {
        $map = [
            '--site-primary' => Setting::get('theme.primary', '#166534'),
            '--site-accent' => Setting::get('theme.accent', '#bef264'),
            '--site-surface' => Setting::get('theme.surface', '#fafaf9'),
            '--site-text' => Setting::get('theme.text', '#292524'),
            '--site-animation-duration' => Setting::get('theme.animation_duration', '1200ms'),
        ];

        $declarations = [];

        foreach ($map as $property => $value) {
            if (is_string($value) && $value !== '') {
                $declarations[] = $property.': '.$value.';';
            }
        }

        return implode(' ', $declarations);
    }

    /**
     * Only networks that have a URL are returned, so the footer hides icons
     * nobody filled in.
     *
     * @return array<string, string>
     */
    private static function socialLinks(): array
    {
        $networks = ['facebook', 'instagram', 'youtube', 'tiktok'];
        $links = [];

        foreach ($networks as $network) {
            $url = Setting::get('social.'.$network);

            if (is_string($url) && trim($url) !== '') {
                $links[$network] = $url;
            }
        }

        return $links;
    }
}
