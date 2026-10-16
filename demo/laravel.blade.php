{{-- Include in an authenticated Blade page. The host form owns saving and validation. --}}
<link rel="stylesheet" href="{{ asset('vendor/media-browser/media-browser.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/media-browser/media-field.css') }}">
@php
    echo media_browser([
        'name' => 'gallery_ids', 'multiple' => true, 'sortable' => true, 'max_items' => 12,
        'endpoint' => route('media-browser.api'), 'profile' => 'product_gallery',
        'config' => \MediaBrowser\Configuration::profile(config('media-browser'), 'product_gallery'),
        'value' => $savedImageIds ?? [],
    ]);
@endphp
<script src="{{ asset('vendor/media-browser/media-browser.js') }}" defer></script>
<script src="{{ asset('vendor/media-browser/media-field.js') }}" defer></script>
