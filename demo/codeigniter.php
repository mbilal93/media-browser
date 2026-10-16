<?php // Include from an authenticated CI4 view after publishing the package assets.
helper('url'); ?>
<link rel="stylesheet" href="<?= esc(base_url('vendor/media-browser/media-browser.css'), 'attr') ?>">
<link rel="stylesheet" href="<?= esc(base_url('vendor/media-browser/media-field.css'), 'attr') ?>">
<?php echo media_browser([
    'name' => 'gallery_ids', 'multiple' => true, 'sortable' => true, 'max_items' => 12,
    'endpoint' => site_url('media-browser/api'), 'profile' => 'product_gallery',
    'config' => \MediaBrowser\Configuration::profile(config('MediaBrowser')->options(), 'product_gallery'),
    'value' => $savedImageIds ?? [],
]); ?>
<script src="<?= esc(base_url('vendor/media-browser/media-browser.js'), 'attr') ?>" defer></script>
<script src="<?= esc(base_url('vendor/media-browser/media-field.js'), 'attr') ?>" defer></script>
