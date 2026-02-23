<?php

namespace App\Exceptions;

use Exception;

class ApiException extends Exception
{
    protected int $statusCode;
    protected array $errors;

    public function __construct(string $message = 'API Error', int $statusCode = 500, array $errors = [])
    {
        parent::__construct($message, $statusCode);
        $this->statusCode = $statusCode;
        $this->errors = $errors;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Buat instance dari response HTTP error.
     */
    public static function fromResponse(int $statusCode, ?array $body = null): self
    {
        $message = $body['message'] ?? 'Terjadi kesalahan pada server';
        $errors = $body['errors'] ?? [];

        return new self($message, $statusCode, $errors);
    }
}
