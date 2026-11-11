<?php

namespace Simon\InventarioTrazabilidad\Application;

use Simon\InventarioTrazabilidad\Domain\MovementRules;
use Simon\InventarioTrazabilidad\Domain\SupplyExpiry;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\Application\UnitOfWork;
use Simon\Shared\Domain\BusinessRuleViolation;

final readonly class ManageSupplies
{
    public function __construct(private AccessPolicy $access, private UnitOfWork $transactions, private SupplyLots $supplies) {}

    public function configure(int $actorId, int $anticipationDays): void
    {
        $this->access->require($actorId, 'production.manage');

        if ($anticipationDays < 0 || $anticipationDays > 3650) {
            throw new BusinessRuleViolation('La anticipación debe estar entre 0 y 3650 días.');
        }

        $this->transactions->run(fn () => $this->supplies->configure($anticipationDays));
    }

    public function register(int $actorId, string $operationId, string $name, string $lot, string $expiresOn, ?int $productionId = null): int
    {
        $this->access->require($actorId, 'production.manage');
        MovementRules::operationId($operationId);
        $expiry = new SupplyExpiry($expiresOn);

        if (trim($name) === '' || mb_strlen($name) > 150 || trim($lot) === '' || mb_strlen($lot) > 100 || ($productionId !== null && $productionId < 1)) {
            throw new BusinessRuleViolation('Completá el insumo, lote y una tanda válida si corresponde.');
        }

        return $this->transactions->run(fn (): int => $this->supplies->register($operationId, $actorId, trim($name), trim($lot), $expiry->date, $productionId));
    }

    /** @return array<string,mixed> */
    public function overview(int $actorId, int $page = 1): array
    {
        $permission = $this->access->allows($actorId, 'production.manage') ? 'production.manage' : 'inventory.view';
        $this->access->require($actorId, $permission);

        if ($page < 1) {
            throw new BusinessRuleViolation('La página no es válida.');
        }

        $report = $this->supplies->overview($page);
        $report['items'] = array_map(fn (array $lot): array => [...$lot, 'warning' => $report['anticipation_days'] === null && $lot['expires_on'] > $report['today'] ? 'Configurar anticipación' : (new SupplyExpiry($lot['expires_on']))->warning($report['today'], $report['anticipation_days'] ?? 0)], $report['items']);

        return $report;
    }
}
