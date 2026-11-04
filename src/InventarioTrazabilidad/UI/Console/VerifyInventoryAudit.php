<?php

namespace Simon\InventarioTrazabilidad\UI\Console;

use Illuminate\Console\Command;
use Simon\InventarioTrazabilidad\Infrastructure\SqlInventory;
use Throwable;

class VerifyInventoryAudit extends Command
{
    protected $signature = 'inventory:audit-verify';

    protected $description = 'Comprobar firmas, encadenamiento y correspondencia de movimientos sin modificar el historial';

    public function handle(SqlInventory $inventory): int
    {
        try {
            $head = $inventory->verify();
        } catch (Throwable $exception) {
            $this->error('La auditoría no superó la verificación: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->line(json_encode($head, JSON_THROW_ON_ERROR));
        $this->info('Cadena y movimientos verificados. Conservar este cierre fuera del servidor permite contrastarlo ante una recuperación.');

        return self::SUCCESS;
    }
}
