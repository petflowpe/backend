<?php

namespace App\Console\Commands;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;

/**
 * Re-sincroniza permisos/roles del código (p. ej. appointments.*) sin tocar usuarios.
 */
class SyncSystemPermissionsCommand extends Command
{
    protected $signature = 'permissions:sync-system';

    protected $description = 'Actualiza permisos y roles de sistema desde Permission/Role::getSystem* (sin recrear usuarios)';

    public function handle(): int
    {
        $this->info('Sincronizando permisos y roles de sistema...');
        $seeder = new RolesAndPermissionsSeeder();
        $seeder->setCommand($this);
        $seeder->runPermissionsAndRolesOnly();
        $this->info('Listo. Los roles de sistema quedaron alineados con el código.');

        return self::SUCCESS;
    }
}
