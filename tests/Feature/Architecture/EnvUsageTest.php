<?php

declare(strict_types=1);

// env() tylko w plikach config/: po `php artisan config:cache` env() poza nimi zwraca null.
arch('kod aplikacji czyta konfigurację przez config(), nie env()')
    ->expect('App')
    ->not->toUse('env');
