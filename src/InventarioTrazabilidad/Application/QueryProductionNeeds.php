<?php

namespace Simon\InventarioTrazabilidad\Application;

use Simon\InventarioTrazabilidad\Domain\ProductionProjection;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\Application\ReadSourceUnavailable;
use Simon\Shared\Domain\BusinessRuleViolation;
use Simon\VentasPedidos\Application\PendingDemand;

final readonly class QueryProductionNeeds
{
    public function __construct(private AccessPolicy $access, private ProductionPlanningQueries $planning, private PendingDemand $demand) {}

    /** @return array{items:list<array<string,mixed>>,page:int,pages:int,total:int} */
    public function handle(int $actorId, int $page = 1): array
    {
        $permission = $this->access->allows($actorId, 'production.manage') ? 'production.manage' : 'inventory.view';
        $this->access->require($actorId, $permission);

        if ($page < 1) {
            throw new BusinessRuleViolation('La página de necesidades de producción no es válida.');
        }

        return $this->planning->snapshot(function () use ($actorId, $page): array {
            $report = $this->planning->page($page);
            $pending = $this->demand->quantities($actorId, array_column($report['items'], 'id'));
            $report['items'] = array_map(function (array $article) use ($pending): array {
                if (! array_key_exists($article['id'], $pending)) {
                    throw new ReadSourceUnavailable('No se pudo obtener la demanda de todos los artículos de esta página.');
                }

                $projection = new ProductionProjection($article['available'], $article['in_process'], $pending[$article['id']]);

                return [...$article, 'pending' => $projection->pending, 'balance' => $projection->balance, 'injection_needed' => $projection->injectionNeeded];
            }, $report['items']);

            return $report;
        });
    }
}
