<?php
declare(strict_types=1);

/**
 * One argument: return picker HTML (use echo); set 'value' for saved image IDs.
 * Two arguments: return validated submitted images as an array without output.
 * Host authentication/CSRF checks must run before calling this function on POST.
 */
function media_browser(array $arguments = [], array|string|null $selection = null): string|array
{
    // Retain the old explicit render option for existing integrations.
    if (array_key_exists('render', $arguments)) return \MediaBrowser\ImageField::renderAndResolve($arguments, $selection ?? ($arguments['value'] ?? null));
    if (func_num_args() > 1) return \MediaBrowser\ImageField::renderAndResolve($arguments + ['render' => false], $selection);
    ob_start();
    try {
        \MediaBrowser\ImageField::renderAndResolve($arguments + ['render' => true], $arguments['value'] ?? null);
        return ob_get_contents();
    } finally {
        ob_end_clean();
    }
}
