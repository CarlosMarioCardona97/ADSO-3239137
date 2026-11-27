<?php

/**
 * RNF-1 · La búsqueda responde en menos de 300 ms con 5 000 expedientes.
 * Completa el catálogo por la API hasta 5 000 y mide 50 búsquedas. Reporta la mediana y el percentil 95.
 */

require __DIR__.'/ApiClient.php';

const TARGET = 5000;
const LIMIT_MS = 300.0;

$api = new ApiClient(getenv('API_URL') ?: 'http://api:8000/api/v1');

try {
    $api->login(requiredEnv('ADMIN_USERNAME'), requiredEnv('ADMIN_PASSWORD'));

    $current = $api->expect(200, 'GET', '/dossiers?size=1')['total'];
    $next = 1;
    while ($current < TARGET) {
        [$status] = $api->request('POST', '/dossiers', [
            'title' => sprintf('Expediente de carga %05d', $next),
            'producing_office' => 'Secretaría General',
            'filing_number' => sprintf('2090-%06d', $next),
            'category_id' => '44444444-4444-4444-8444-444444444444',
            'total_units' => 1,
        ]);
        $next++;
        if ($status === 201) {
            $current++;
            if ($current % 500 === 0) {
                echo "{$current} expedientes…\n";
            }
        } elseif ($status !== 409) {
            throw new RuntimeException("No se pudo crear el expediente de carga (estado {$status}).");
        }
    }

    $terms = ['contrato', 'carga 04', 'secretaría', '2090-0031', 'no-existe'];
    $times = [];
    for ($i = 0; $i < 50; $i++) {
        $term = rawurlencode($terms[$i % count($terms)]);
        $start = hrtime(true);
        $api->expect(200, 'GET', "/dossiers?q={$term}&size=20&page=1");
        $times[] = (hrtime(true) - $start) / 1e6;
    }
    sort($times);
    $median = $times[24];
    $p95 = $times[47];
    printf("Catálogo: %d expedientes. Mediana: %.1f ms. Percentil 95: %.1f ms.\n", $current, $median, $p95);
    echo $p95 < LIMIT_MS ? "RNF-1 cumplido.\n" : "RNF-1 NO cumplido.\n";
    exit($p95 < LIMIT_MS ? 0 : 1);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: '.$e->getMessage()."\n");
    exit(1);
}
