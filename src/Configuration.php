<?php
declare(strict_types=1);
namespace MediaBrowser;

final class Configuration
{
    public static function resolve(array $overrides = [], string $profile = ''): array
    {
        $options = self::merge(require dirname(__DIR__) . '/config.php', $overrides);
        return self::merge(self::profile($options, $profile), $overrides);
    }

    public static function profile(array $options, string $profile): array
    {
        if ($profile === '') return $options;
        $settings = $options['profiles'][$profile] ?? null;
        if (!is_array($settings)) throw new \InvalidArgumentException('Unknown media profile.');
        return self::merge($options, $settings);
    }

    private static function merge(array $defaults, array $overrides): array
    {
        foreach (['field', 'profiles', 'database'] as $key) {
            if (isset($overrides[$key]) && is_array($overrides[$key])) $overrides[$key] = array_replace($defaults[$key] ?? [], $overrides[$key]);
        }
        return array_replace($defaults, $overrides);
    }

    public static function defaults(string $storage): array
    {
        $options = require dirname(__DIR__) . '/config.php';
        $options['storage'] = $storage;
        unset($options['password']); // Host frameworks own authentication.
        return $options;
    }
}
