<?php

// T-19 · Pruebas de arquitectura: verifican los artículos I, II y III de la constitución.
// Si alguien agrega una dependencia prohibida, esta prueba falla.

arch('artículo I: el dominio no depende de Laravel ni de las otras capas')
    ->expect('App\Domain')
    ->not->toUse(['Illuminate', 'App\Application', 'App\Infrastructure', 'App\Providers']);

arch('artículo II: la aplicación solo conoce el dominio y sus propios puertos')
    ->expect('App\Application')
    ->not->toUse(['Illuminate', 'App\Infrastructure', 'App\Providers']);

arch('artículo II: los puertos son interfaces (salvo los objetos de datos que viajan por ellos)')
    ->expect('App\Application\Port')
    ->toBeInterfaces()
    ->ignoring('App\Application\Port\LoanFilter');

arch('artículo III: solo la composición y la propia infraestructura conocen los adaptadores de salida')
    ->expect(['App\Infrastructure\Persistence', 'App\Infrastructure\Security', 'App\Infrastructure\Storage'])
    ->toOnlyBeUsedIn(['App\Infrastructure', 'App\Providers\PortBindingsServiceProvider']);

arch('artículo IV (SRP): un servicio de aplicación por caso de uso, final y con sufijo Service')
    ->expect('App\Application\Service')
    ->toBeFinal()
    ->toHaveSuffix('Service');

arch('no quedan funciones de depuración en el código')
    ->expect(['dd', 'dump', 'var_dump', 'print_r', 'ray'])
    ->not->toBeUsed();
