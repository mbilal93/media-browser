<?php
declare(strict_types=1);
namespace MediaBrowser;

final class Url
{
    public static function query(string $url, array $values): string
    {
        [$base, $fragment] = array_pad(explode('#', $url, 2), 2, null);
        [$path, $query] = array_pad(explode('?', $base, 2), 2, '');
        parse_str($query, $params);
        return $path . '?' . http_build_query(array_replace($params, $values), '', '&', PHP_QUERY_RFC3986)
            . ($fragment === null ? '' : '#' . $fragment);
    }
}
