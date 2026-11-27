<?php

/** Cliente HTTP mínimo de la API de Archive. Las herramientas solo hablan por la API, nunca por SQL (T-21). */
final class ApiClient
{
    private ?string $token = null;

    public function __construct(private readonly string $baseUrl) {}

    /** Espera a que la API responda e inicia sesión; las migraciones pueden estar corriendo todavía. */
    public function login(string $username, string $password, int $attempts = 60): void
    {
        for ($try = 1; $try <= $attempts; $try++) {
            try {
                [$status, $body] = $this->request('POST', '/auth/login', compact('username', 'password'));
                if ($status === 200) {
                    $this->token = $body['access_token'];

                    return;
                }
                if ($status === 401) {
                    // El admin lo crea el arranque de la API: puede faltar en los primeros segundos.
                    if ($try >= 20) {
                        throw new RuntimeException('Credenciales rechazadas: revise ADMIN_USERNAME y ADMIN_PASSWORD.');
                    }
                }
            } catch (ApiUnavailable) {
                // La API todavía no acepta conexiones.
            }
            sleep(1);
        }
        throw new RuntimeException("La API {$this->baseUrl} no respondió a tiempo.");
    }

    /** Hace la petición y exige el estado esperado. */
    public function expect(int $expected, string $method, string $path, ?array $json = null): array
    {
        [$status, $body] = $this->request($method, $path, $json);
        if ($status !== $expected) {
            $code = $body['code'] ?? 'SIN_CODIGO';
            throw new RuntimeException("{$method} {$path}: se esperaba {$expected} y llegó {$status} ({$code}).");
        }

        return $body;
    }

    /** @return array{0: int, 1: array<mixed>} */
    public function request(string $method, string $path, ?array $json = null): array
    {
        $handle = $this->handle($method, $path, $json);
        $raw = curl_exec($handle);
        if ($raw === false) {
            throw new ApiUnavailable(curl_error($handle));
        }

        return [(int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), json_decode((string) $raw, true) ?? []];
    }

    /** Prepara una petición sin ejecutarla, para poder lanzar varias a la vez con curl_multi. */
    public function handle(string $method, string $path, ?array $json = null): CurlHandle
    {
        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        if ($this->token !== null) {
            $headers[] = 'Authorization: Bearer '.$this->token;
        }
        $handle = curl_init($this->baseUrl.$path);
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 30,
        ]);
        if ($json !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($json, JSON_UNESCAPED_UNICODE));
        }

        return $handle;
    }
}

final class ApiUnavailable extends RuntimeException {}

/** Lee una variable de entorno obligatoria. */
function requiredEnv(string $name): string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        fwrite(STDERR, "Falta la variable de entorno {$name}.\n");
        exit(1);
    }

    return $value;
}
