<?php
declare(strict_types=1);
namespace Config;

/** Copy to the host's app/Config/MediaBrowser.php. */
class MediaBrowser extends \CodeIgniter\Config\BaseConfig
{
    public function authorized(): bool
    {
        // Replace with the host application's media-management permission check.
        return false;
    }

    public function options(): array
    {
        return \MediaBrowser\Configuration::defaults(WRITEPATH . 'media-browser');
    }
}
