<?php
declare(strict_types=1);
// These trusted arguments are reused when rendering AND resolving the POST.
return [
    'cover' => ['name' => 'cover_ids', 'multiple' => false, 'sortable' => false, 'button_text' => 'Choose cover', 'profile' => 'product_gallery'],
    'gallery' => ['name' => 'gallery_ids', 'multiple' => true, 'sortable' => true, 'max_items' => 12, 'button_text' => 'Choose gallery images', 'profile' => 'product_gallery'],
];
