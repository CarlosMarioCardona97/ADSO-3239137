<?php

/**
 * RNF-2 · Prueba concurrente real: en cada ronda, dos peticiones SIMULTÁNEAS piden la última unidad
 * del mismo expediente. Exactamente una debe recibir 201 y la otra 409.
 */

require __DIR__.'/ApiClient.php';

$rounds = (int) ($argv[1] ?? 10);
$api = new ApiClient(getenv('API_URL') ?: 'http://api:8000/api/v1');
$prefix = random_int(100000, 899999);
$failures = 0;

try {
    $api->login(requiredEnv('ADMIN_USERNAME'), requiredEnv('ADMIN_PASSWORD'));

    for ($round = 0; $round < $rounds; $round++) {
        $dossier = $api->expect(201, 'POST', '/dossiers', [
            'title' => "Prueba de concurrencia {$prefix}-{$round}",
            'producing_office' => 'Prueba',
            'filing_number' => sprintf('2091-%06d', $prefix + $round),
            'category_id' => '44444444-4444-4444-8444-444444444444',
            'total_units' => 1,
        ]);
        $handles = [];
        $multi = curl_multi_init();
        foreach ([1, 2] as $operator) {
            $official = $api->expect(201, 'POST', '/officials', [
                'full_name' => "Funcionario de prueba {$operator}",
                'document_number' => "P{$prefix}-{$round}-{$operator}",
            ]);
            $handle = $api->handle('POST', '/loans', [
                'official_id' => $official['id'],
                'items' => [['dossier_id' => $dossier['id']]],
            ]);
            curl_multi_add_handle($multi, $handle);
            $handles[] = $handle;
        }

        do {
            curl_multi_exec($multi, $running);
            curl_multi_select($multi, 0.05);
        } while ($running > 0);

        $statuses = array_map(fn ($h) => (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), $handles);
        foreach ($handles as $handle) {
            curl_multi_remove_handle($multi, $handle);
        }
        curl_multi_close($multi);
        sort($statuses);

        $available = $api->expect(200, 'GET', "/dossiers/{$dossier['id']}")['available_units'];
        $ok = $statuses === [201, 409] && $available === 0;
        $failures += $ok ? 0 : 1;
        printf("Ronda %02d: %s, disponibles %d → %s\n", $round + 1, implode(' y ', $statuses), $available, $ok ? 'bien' : 'FALLA');
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: '.$e->getMessage()."\n");
    exit(1);
}

echo $failures === 0 ? "RNF-2 cumplido en {$rounds} rondas.\n" : "RNF-2 NO cumplido: {$failures} rondas fallaron.\n";
exit($failures === 0 ? 0 : 1);
