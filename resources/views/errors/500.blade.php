@php
    $seo = [
        'title' => __('ui.errors.server_title').' | '.config('app.name'),
        'description' => __('ui.errors.server_body'),
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
        code="500"
        :title="__('ui.errors.server_title')"
        :body="__('ui.errors.server_body')"
    />
@endsection
