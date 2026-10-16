<?php
declare(strict_types=1);
namespace MediaBrowser;

final class ImageField
{
    public static function renderAndResolve(array $arguments, array|string|null $selection): array
    {
        $profile = $arguments['profile'] ?? '';
        if (!is_string($profile)) throw new \InvalidArgumentException('Invalid media profile.');
        $config = Configuration::resolve($arguments['config'] ?? [], $profile);
        $field = array_replace($config['field'], array_intersect_key($arguments, $config['field']));
        if (!is_string($field['name']) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(?:\[(?:[a-zA-Z_][a-zA-Z0-9_]*|[0-9]+)\])*$/D', $field['name'])) throw new \InvalidArgumentException('Use a field name such as gallery_ids or product[images], without empty [] suffixes.');
        if (!is_int($field['max_items']) || $field['max_items'] < 1) throw new \InvalidArgumentException('max_items must be positive.');
        $ids = $selection ?? [];
        if (is_string($ids)) {
            try { $ids = json_decode($ids, true, 512, JSON_THROW_ON_ERROR); }
            catch (\JsonException $error) { throw new \InvalidArgumentException('Image selection must be a JSON array of IDs.', 0, $error); }
        }
        if (!is_array($ids) || !array_is_list($ids)) throw new \InvalidArgumentException('Image selection must be an ordered list.');
        if (count($ids) > ($field['multiple'] ? $field['max_items'] : 1)) throw new \InvalidArgumentException('Too many selected images.');
        if (count(array_unique($ids, SORT_REGULAR)) !== count($ids)) throw new \InvalidArgumentException('Duplicate image IDs are not allowed.');
        $endpoint = $profile === '' ? $field['endpoint'] : Url::query($field['endpoint'], ['profile' => $profile]);
        $images = [];
        $store = $ids ? new MediaStore($config) : null;
        foreach ($ids as $id) {
            if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/D', $id)) throw new \InvalidArgumentException('Invalid image ID.');
            $row = $store->get($id);
            if (!str_starts_with($row['mime'], 'image/') || !isset($config['types'][$row['mime']]) || $row['size'] > $config['max_bytes']) throw new \InvalidArgumentException('Selected file is not an allowed image for this field.');
            if (!is_file($store->path($row))) throw new \OutOfBoundsException('Selected image is unavailable.');
            if (isset($arguments['can_use']) && !$arguments['can_use']($row)) throw new \RuntimeException('You cannot use this image.');
            $images[] = $store->present($row) + ['url' => Url::query($endpoint, ['action' => 'file', 'id' => $id])];
        }
        if ($arguments['render'] ?? true) {
            $client = ['endpoint' => $endpoint, 'multiple' => (bool) $field['multiple'], 'sortable' => (bool) $field['sortable'],
                'maxItems' => $field['max_items'], 'maxBytes' => $config['max_bytes'], 'pageSize' => $config['page_size'],
                'types' => array_keys(array_filter($config['types'], static fn ($extension, $mime): bool => str_starts_with($mime, 'image/'), ARRAY_FILTER_USE_BOTH)),
                'images' => $images];
            $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            echo '<div class="media-image-field" data-media-field="' . $escape(json_encode($client, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)) . '">';
            echo '<input type="hidden" name="' . $escape($field['name']) . '" value="' . $escape(json_encode($ids, JSON_THROW_ON_ERROR)) . '">';
            echo '<ol class="sortable-images" aria-label="Selected images in display order"></ol>';
            echo '<p class="field-status" role="status" aria-live="polite">' . ($field['sortable'] ? 'Drag the handles or use the arrow buttons to reorder images.' : 'Choose images for this field.') . '</p>';
            echo '<button type="button" class="secondary field-choose">' . $escape($field['button_text']) . '</button></div>';
        }
        return $images;
    }
}
