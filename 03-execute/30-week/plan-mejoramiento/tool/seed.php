<?php

/**
 * T-21 · Sembrador de demostración POR LA API: 40 expedientes, 10 funcionarios, 15 préstamos y 3 vencidos.
 * Los 3 vencidos se registran por una segunda instancia de la API con el reloj 6 días atrás (plan D-15).
 */

require __DIR__.'/ApiClient.php';

const CATEGORIES = [
    'Contratos' => '11111111-1111-4111-8111-111111111111',
    'Personal' => '22222222-2222-4222-8222-222222222222',
    'Predial' => '33333333-3333-4333-8333-333333333333',
    'Actas' => '44444444-4444-4444-8444-444444444444',
    'Jurídico' => '55555555-5555-4555-8555-555555555555',
];

const SUBJECTS = [
    'Contratos' => ['Contrato de obra pública', 'Contrato de suministro', 'Contrato de consultoría', 'Convenio interadministrativo'],
    'Personal' => ['Hoja de vida', 'Acto de nombramiento', 'Liquidación de prestaciones', 'Evaluación de desempeño'],
    'Predial' => ['Avalúo catastral', 'Paz y salvo predial', 'Mutación de propietario', 'Acuerdo de pago predial'],
    'Actas' => ['Acta de concejo', 'Acta de consejo de gobierno', 'Acta de comité de contratación', 'Acta de junta de obra'],
    'Jurídico' => ['Acción de tutela', 'Derecho de petición', 'Proceso contencioso', 'Concepto jurídico'],
];

const OFFICES = [
    'Contratos' => 'Secretaría de Infraestructura',
    'Personal' => 'Oficina de Talento Humano',
    'Predial' => 'Secretaría de Hacienda',
    'Actas' => 'Secretaría General',
    'Jurídico' => 'Oficina Jurídica',
];

const OFFICIALS = [
    'Andrea Rojas Medina', 'Luis Fernando Trujillo', 'Paola Andrea Vargas', 'Jorge Iván Polanía', 'Claudia Perdomo Silva',
    'Hernán Darío Cabrera', 'Diana Marcela Losada', 'Óscar Javier Motta', 'Yuli Tatiana Ramírez', 'Fabio Nelson Charry',
];

$admin = requiredEnv('ADMIN_USERNAME');
$password = requiredEnv('ADMIN_PASSWORD');
$api = new ApiClient(getenv('API_URL') ?: 'http://api:8000/api/v1');
$pastApi = new ApiClient(getenv('API_PAST_URL') ?: 'http://api-past:8000/api/v1');

try {
    $api->login($admin, $password);
    // El token se valida con el reloj de cada instancia: la atrasada necesita su propio inicio de sesión.
    $pastApi->login($admin, $password);

    if ($api->expect(200, 'GET', '/dossiers?q=2024-100001')['total'] > 0) {
        echo "La base ya tiene los datos de demostración. Para sembrar de nuevo: docker compose down -v\n";
        exit(0);
    }

    $dossiers = [];
    $number = 100001;
    foreach (CATEGORIES as $name => $categoryId) {
        foreach (range(1, 8) as $n) {
            $subject = SUBJECTS[$name][($n - 1) % 4];
            $dossiers[] = $api->expect(201, 'POST', '/dossiers', [
                'title' => sprintf('%s %03d de 2024', $subject, $n),
                'producing_office' => OFFICES[$name],
                'filing_number' => '2024-'.$number++,
                'category_id' => $categoryId,
                'total_units' => ($n % 3) + 1 + 2,
            ]);
        }
    }
    echo count($dossiers)." expedientes creados.\n";

    $officials = [];
    foreach (OFFICIALS as $index => $fullName) {
        $officials[] = $api->expect(201, 'POST', '/officials', [
            'full_name' => $fullName,
            'document_number' => (string) (1075200000 + $index),
        ]);
    }
    echo count($officials)." funcionarios creados.\n";

    $lend = fn (ApiClient $client, array $official, array $picked) => $client->expect(201, 'POST', '/loans', [
        'official_id' => $official['id'],
        'items' => array_map(fn (int $i) => ['dossier_id' => $dossiers[$i]['id']], $picked),
    ]);

    // 3 préstamos vencidos: nacen hace 6 días y vencieron hace 1. Cada uno con un funcionario distinto.
    foreach ([0, 9, 17] as $position => $dossierIndex) {
        $lend($pastApi, $officials[$position], [$dossierIndex]);
    }

    // 12 préstamos al día con los otros 7 funcionarios. Los primeros expedientes se repiten para el reporte.
    $picks = [0, 0, 8, 8, 8, 16, 16, 24, 32, 1, 9, 25];
    $loans = [];
    foreach ($picks as $position => $dossierIndex) {
        $loans[] = $lend($api, $officials[3 + ($position % 7)], [$dossierIndex]);
    }

    // Dos préstamos quedan cerrados para mostrar los tres estados.
    foreach (array_slice($loans, 0, 2) as $loan) {
        $api->expect(200, 'POST', "/loans/{$loan['id']}/items/{$loan['items'][0]['id']}/return");
    }
    echo (3 + count($loans))." préstamos registrados (2 cerrados).\n";

    $overdue = $api->expect(200, 'GET', '/loans/overdue')['items'];
    $today = gmdate('Y-m-d');
    $from = gmdate('Y-m-d', strtotime('-7 days'));
    $rows = $api->expect(200, 'GET', "/reports/most-requested?from={$from}&to={$today}")['rows'];
    if (count($overdue) !== 3 || $rows === []) {
        throw new RuntimeException('Comprobación fallida: se esperaban 3 vencidos y filas en el reporte.');
    }
    echo "Comprobado: 3 líneas vencidas y ".count($rows)." filas en el reporte.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: '.$e->getMessage()."\n");
    exit(1);
}
