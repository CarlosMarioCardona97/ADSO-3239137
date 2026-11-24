<?php

namespace App\Infrastructure\Http;

use App\Domain\Exception\BusinessRuleViolation;
use Illuminate\Http\Request;

/**
 * Lectura y validación de la entrada HTTP con mensajes en español (RN-6). Junta todos los errores de
 * campo en un único 422 VALIDATION_ERROR con `details` (api-contract §1).
 */
final class Input
{
    /** @var list<array{field: string, issue: string}> */
    private array $errors = [];

    public function __construct(private readonly Request $request) {}

    public function string(string $field, int $max = 250): string
    {
        $value = $this->request->input($field);
        if (! is_string($value) || trim($value) === '') {
            $this->errors[] = ['field' => $field, 'issue' => 'es obligatorio'];

            return '';
        }
        if (mb_strlen(trim($value)) > $max) {
            $this->errors[] = ['field' => $field, 'issue' => "admite máximo {$max} caracteres"];
        }

        return $value;
    }

    public function integer(string $field): int
    {
        $value = $this->request->input($field);
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            $this->errors[] = ['field' => $field, 'issue' => 'debe ser un número entero'];

            return 0;
        }

        return (int) $value;
    }

    public function uuid(string $field): string
    {
        $value = $this->request->input($field);
        if (! is_string($value) || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) !== 1) {
            $this->errors[] = ['field' => $field, 'issue' => 'debe ser un identificador UUID'];

            return '';
        }

        return $value;
    }

    public function optionalQuery(string $field): ?string
    {
        $value = $this->request->query($field);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** @return array{0: int, 1: int} página y tamaño (size máximo 50). */
    public function pagination(): array
    {
        $page = $this->request->query('page', '1');
        $size = $this->request->query('size', '20');
        if (! is_string($page) || ! ctype_digit($page) || (int) $page < 1) {
            $this->errors[] = ['field' => 'page', 'issue' => 'debe ser un entero mayor o igual a 1'];
        }
        if (! is_string($size) || ! ctype_digit($size) || (int) $size < 1 || (int) $size > 50) {
            $this->errors[] = ['field' => 'size', 'issue' => 'debe ser un entero entre 1 y 50'];
        }

        return [max(1, (int) $page), min(50, max(1, (int) $size))];
    }

    public function addError(string $field, string $issue): void
    {
        $this->errors[] = ['field' => $field, 'issue' => $issue];
    }

    /** Lanza un único 422 con todos los campos inválidos, si los hay. */
    public function validate(): void
    {
        if ($this->errors !== []) {
            throw new BusinessRuleViolation('VALIDATION_ERROR', 'Hay datos inválidos en la petición.', $this->errors);
        }
    }
}
