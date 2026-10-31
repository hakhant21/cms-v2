@php
    // A view that extends the layout can hand the <head> its own metadata.
    // Missing pages are never indexed, but the page is worth following.
    $seo = [
        'title' => __('ui.errors.not_found_title').' | '.config('app.name'),
        'description' => __('ui.errors.not_found_body'),
        'image' => null,
        'canonical' => url()->current(),
        'type' => 'website',
        'site_name' => (string) config('app.name'),
        'robots' => 'noindex,follow',
    ];
@endphp

@extends('layouts.site')

@section('content')
    <x-site.error-page
        code="404"
        :title="__('ui.errors.not_found_title')"
        :body="__('ui.errors.not_found_body')"
    />
@endsection
