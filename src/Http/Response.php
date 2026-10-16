<?php
declare(strict_types=1);
namespace MediaBrowser\Http;

final class Response
{
    public function __construct(public int $status = 200, public array $data = [], public array $headers = [], public ?string $file = null) {}

    /** Only the core PHP front controller emits. Framework bridges return native responses. */
    public function emit(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) header($name . ': ' . $value);
        if ($this->file !== null) {
            if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
            readfile($this->file);
        } else {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($this->data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }
    }
}
