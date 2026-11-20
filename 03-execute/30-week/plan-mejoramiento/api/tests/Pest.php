<?php

/*
| Las pruebas de característica (Feature) arrancan Laravel y usan PostgreSQL real (base archive_test).
| Las pruebas unitarias del dominio y de la aplicación son PHP puro: no arrancan Laravel.
*/

require_once __DIR__.'/Fakes/InMemory.php';

pest()->extend(Tests\TestCase::class)->in('Feature');
