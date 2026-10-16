<?php
declare(strict_types=1);

namespace MediaBrowser;

use PDO;
use RuntimeException;
use InvalidArgumentException;
use OutOfBoundsException;
use Throwable;
use finfo;

final class MediaStore
{
    private PDO $db;
    private string $files;
    private string $table;
    private string $driver;

    public function __construct(private array $config, ?PDO $connection = null)
    {
        if (($config['page_size'] ?? 0) < 1 || ($config['max_bytes'] ?? 0) < 1) {
            throw new InvalidArgumentException('Page size and maximum bytes must be positive.');
        }
        $database = $config['database'] ?? [];
        $tableName = $database['table'] ?? 'media';
        $this->table = Database::table($tableName);
        $root = $config['storage'];
        $this->files = $root . '/files';
        if (!is_dir($this->files) && !mkdir($this->files, 0700, true) && !is_dir($this->files)) {
            throw new RuntimeException('Cannot create storage directory.');
        }
        $this->db = $connection ?? Database::connect($database, $root);
        $this->driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!in_array($this->driver, ['sqlite', 'mysql'], true)) throw new InvalidArgumentException('Unsupported PDO connection driver.');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($this->driver === 'mysql') $this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        else $this->db->exec('PRAGMA busy_timeout = 5000');
        // Never run MySQL DDL inside a host transaction: it can implicitly commit it.
        if (($database['auto_create'] ?? true) && !$this->db->inTransaction()) $this->db->exec(Database::schema($this->driver, $tableName));
    }

    public function listing(string $search = '', int $page = 1, string $type = ''): array
    {
        $where = "name LIKE :search ESCAPE '!'";
        $params = [':search' => '%' . strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%'];
        $mimeParameters = [];
        foreach (array_keys($this->config['types']) as $index => $mime) {
            $key = ':mime' . $index;
            $mimeParameters[] = $key;
            $params[$key] = $mime;
        }
        $where .= ' AND mime IN (' . ($mimeParameters ? implode(',', $mimeParameters) : "''") . ') AND size <= :max_bytes';
        $params[':max_bytes'] = $this->config['max_bytes'];
        if ($type === 'image' || $type === 'video') {
            $where .= ' AND mime LIKE :type';
            $params[':type'] = $type . '/%';
        } elseif ($type === 'document') {
            $where .= " AND mime = 'application/pdf'";
        }
        $count = $this->db->prepare('SELECT COUNT(*) FROM ' . $this->table . ' WHERE ' . $where);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $this->config['page_size']));
        $page = max(1, min($page, $pages));
        $query = $this->db->prepare('SELECT * FROM ' . $this->table . ' WHERE ' . $where . ' ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) {
            $query->bindValue($key, $value);
        }
        $query->bindValue(':limit', $this->config['page_size'], PDO::PARAM_INT);
        $query->bindValue(':offset', ($page - 1) * $this->config['page_size'], PDO::PARAM_INT);
        $query->execute();
        return ['items' => array_map([$this, 'present'], $query->fetchAll(PDO::FETCH_ASSOC)), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public function profile(string $name): self
    {
        return $name === '' ? $this : new self(Configuration::profile($this->config, $name), $this->db);
    }

    public function limits(): array
    {
        return ['maxBytes' => $this->config['max_bytes'], 'pageSize' => $this->config['page_size'], 'types' => array_keys($this->config['types'])];
    }

    public function get(string $id): array
    {
        return $this->fetch($id);
    }

    public function findByName(string $name): ?array
    {
        $query = $this->db->prepare('SELECT * FROM ' . $this->table . ' WHERE name = ? ORDER BY created_at, id LIMIT 1');
        $query->execute([$name]);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function fetch(string $id, bool $lock = false): array
    {
        $query = $this->db->prepare('SELECT * FROM ' . $this->table . ' WHERE id = ?' . ($lock && $this->driver === 'mysql' ? ' FOR UPDATE' : ''));
        $query->execute([$id]);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new OutOfBoundsException('Media not found.');
        }
        return $row;
    }

    public function present(array $row): array
    {
        $row['size'] = (int) $row['size'];
        return $row;
    }

    public function path(array $row): string
    {
        // Both components come from records created here, never from client paths.
        return $this->files . '/' . $row['id'] . '.' . $row['extension'];
    }

    public function add(string $temporaryPath, string $name, bool $httpUpload = true): array
    {
        if (!is_file($temporaryPath) || !is_readable($temporaryPath)) {
            throw new InvalidArgumentException('Uploaded file is unavailable.');
        }
        $size = filesize($temporaryPath);
        if ($size === false || $size < 1 || $size > $this->config['max_bytes']) {
            throw new InvalidArgumentException('File must be nonempty and no larger than ' . $this->config['max_bytes'] . ' bytes.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        $extension = $this->config['types'][$mime] ?? null;
        if (!$extension) {
            throw new InvalidArgumentException('Unsupported file type. Use JPG, PNG, GIF, WebP, PDF, MP4 or WebM.');
        }
        if (str_starts_with($mime, 'image/') && @getimagesize($temporaryPath) === false) {
            throw new InvalidArgumentException('The image is invalid.');
        }
        $row = ['id' => bin2hex(random_bytes(16)), 'name' => $this->cleanName($name), 'mime' => $mime,
            'extension' => $extension, 'size' => $size, 'created_at' => gmdate('Y-m-d\TH:i:s\Z')];
        $path = $this->path($row);
        $moved = $httpUpload ? move_uploaded_file($temporaryPath, $path) : copy($temporaryPath, $path);
        if (!$moved) {
            throw new RuntimeException('Could not store the upload.');
        }
        try {
            $insert = $this->db->prepare('INSERT INTO ' . $this->table . ' (id, name, mime, extension, size, created_at) VALUES (:id, :name, :mime, :extension, :size, :created_at)');
            $insert->execute($row);
        } catch (Throwable $error) {
            unlink($path);
            throw $error;
        }
        return $this->present($row);
    }

    public function rename(string $id, string $name): array
    {
        $this->get($id);
        $query = $this->db->prepare('UPDATE ' . $this->table . ' SET name = ? WHERE id = ?');
        $query->execute([$this->cleanName($name), $id]);
        return $this->present($this->get($id));
    }

    public function delete(string $id): void
    {
        $this->db->beginTransaction();
        $path = null;
        $tombstone = null;
        try {
            $path = $this->path($this->fetch($id, true));
            $tombstone = $path . '.deleting';
            if (is_file($path) && !rename($path, $tombstone)) {
                throw new RuntimeException('Could not delete file.');
            }
            $query = $this->db->prepare('DELETE FROM ' . $this->table . ' WHERE id = ?');
            $query->execute([$id]);
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($tombstone && is_file($tombstone)) {
                rename($tombstone, $path);
            }
            throw $error;
        }
        if ($tombstone && is_file($tombstone)) {
            unlink($tombstone);
        }
    }

    private function cleanName(string $name): string
    {
        $name = trim(preg_replace('/[\x00-\x1f\x7f]/u', '', $name) ?? '');
        if ($name === '' || strlen($name) > 240 || strpbrk($name, '/\\') !== false) {
            throw new InvalidArgumentException('Use a display name of 1–240 bytes without path separators.');
        }
        return $name;
    }
}
