<?php

namespace Simon\InventarioTrazabilidad\Application;

use Simon\ClientesAdministracion\Application\CustomerDirectory;
use Simon\ClientesAdministracion\Application\DeliveryAddresses;
use Simon\InventarioTrazabilidad\Domain\MovementRules;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\Application\UnitOfWork;
use Simon\Shared\Domain\BusinessRuleViolation;

final readonly class MoveStock
{
    public function __construct(
        private AccessPolicy $access,
        private UnitOfWork $transactions,
        private InventoryStore $inventory,
        private OrderFulfillment $orders,
        private DispatchAuthorization $authorizations,
        private DeliveryAddresses $addresses,
        private CustomerDirectory $customers,
        private CatalogVariants $catalog,
        private PairUnits $pairs,
    ) {}

    public function receive(int $actorId, string $operationId, int $productionId, int $quantity): int
    {
        $actor = $this->access->require($actorId, 'inventory.receive');
        MovementRules::operationId($operationId);
        MovementRules::quantity($quantity);
        $request = ['kind' => 'receipt', 'production_id' => $productionId, 'quantity' => $quantity];

        return $this->transactions->run(function () use ($actor, $operationId, $productionId, $quantity, $request): int {
            $previous = $this->inventory->replay($operationId, $actor->id, $request);

            if ($previous !== null) {
                return $previous;
            }

            $production = $this->inventory->production($productionId);
            MovementRules::batch($production['lot'], $production['manufactured_on']);
            $batchId = $this->inventory->receive($productionId, $quantity);
            $this->pairs->increaseIfTracked($batchId, $quantity);

            return $this->inventory->record($operationId, $actor, 'receipt', $quantity, $batchId, null, $request);
        });
    }

    public function opening(int $actorId, string $operationId, int $variantId, string $lot, string $manufacturedOn, int $quantity): int
    {
        $actor = $this->access->require($actorId, 'inventory.opening');
        MovementRules::operationId($operationId);
        MovementRules::quantity($quantity);
        MovementRules::batch($lot, $manufacturedOn);
        $request = ['kind' => 'opening', 'variant_id' => $variantId, 'lot' => trim($lot), 'manufactured_on' => $manufacturedOn, 'quantity' => $quantity];

        return $this->transactions->run(function () use ($actor, $operationId, $variantId, $lot, $manufacturedOn, $quantity, $request): int {
            $previous = $this->inventory->replay($operationId, $actor->id, $request);

            if ($previous !== null) {
                return $previous;
            }

            $batchId = $this->inventory->opening($variantId, trim($lot), $manufacturedOn, $quantity);
            $this->pairs->increaseIfTracked($batchId, $quantity);

            return $this->inventory->record($operationId, $actor, 'opening', $quantity, $batchId, null, $request);
        });
    }

    /** @param list<string> $pairCodes */
    public function dispatch(int $actorId, string $operationId, int $orderLineId, int $batchId, int $quantity, bool $ownerWithdrawal = false, ?int $deliveryAddressId = null, array $pairCodes = []): int
    {
        $actor = $this->access->require($actorId, 'inventory.dispatch');
        MovementRules::operationId($operationId);
        MovementRules::quantity($quantity);
        $request = ['kind' => 'dispatch', 'order_line_id' => $orderLineId, 'batch_id' => $batchId, 'quantity' => $quantity, 'owner_withdrawal' => $ownerWithdrawal];

        if ($pairCodes !== []) {
            $pairCodes = array_map(trim(...), $pairCodes);
            sort($pairCodes);
            $request['pair_codes'] = implode(',', $pairCodes);
        }

        if ($deliveryAddressId !== null) {
            if ($deliveryAddressId < 1) {
                throw new BusinessRuleViolation('La dirección de entrega no es válida.');
            }

            $request['delivery_address_id'] = $deliveryAddressId;
        }

        return $this->transactions->run(function () use ($actor, $operationId, $orderLineId, $batchId, $quantity, $ownerWithdrawal, $request, $deliveryAddressId, $pairCodes): int {
            $previous = $this->inventory->replay($operationId, $actor->id, $request);

            if ($previous !== null) {
                return $previous;
            }

            $line = $this->orders->line($orderLineId);

            if ($pairCodes === [] && $quantity > $line['quantity'] - $line['dispatched'] - $this->pairs->pickedCount($orderLineId)) {
                throw new BusinessRuleViolation('Hay pares seleccionados para esta línea. Confirmá su picking o liberá la selección antes de despachar otros pares.');
            }

            $destination = $deliveryAddressId === null ? $line['delivery_destination'] : $this->addresses->snapshot($line['customer_id'], $deliveryAddressId);

            if ($destination === null && ! $ownerWithdrawal) {
                throw new BusinessRuleViolation('Seleccioná una dirección de entrega para este pedido antes de despachar.');
            }

            $authorization = $this->authorizations->assertAuthorized($line['order_id']);
            $stock = $this->inventory->stock($batchId);
            MovementRules::batch($stock['lot'], $stock['manufactured_on']);

            if ($this->pairs->tracked($batchId) && count($pairCodes) !== $quantity) {
                throw new BusinessRuleViolation('Este lote usa identificación individual. Seleccioná sus pares desde Picking antes de confirmar la salida.');
            }

            if (count(array_unique($pairCodes)) !== count($pairCodes) || ($pairCodes !== [] && count($pairCodes) !== $quantity)) {
                throw new BusinessRuleViolation('Cada par despachado requiere un código distinto.');
            }

            $units = array_map(fn (string $code) => $this->pairs->get($code), $pairCodes);

            foreach ($units as $unit) {
                $unit->assertSelected($orderLineId, $batchId);
            }

            if ($stock['variant_id'] !== $line['variant_id']) {
                throw new BusinessRuleViolation('El lote no corresponde al artículo del pedido.');
            }

            $customer = $this->customers->identified([$line['customer_id']])[0] ?? null;
            $article = $this->catalog->identified([$line['variant_id']])[0] ?? null;

            if ($customer === null || $article === null) {
                throw new BusinessRuleViolation('No fue posible identificar el cliente y el artículo para conservar la trazabilidad.');
            }

            $shipment = [
                'order' => ['id' => $line['order_id'], 'number' => $line['order_number'], 'origin' => $line['order_origin']],
                'customer' => $customer, 'article' => $article,
            ];

            if ($pairCodes !== []) {
                $shipment['pair_codes'] = $pairCodes;
            }

            $this->orders->dispatch($orderLineId, $quantity);
            $this->inventory->decrease($batchId, $quantity);

            $movementId = $this->inventory->record($operationId, $actor, $ownerWithdrawal ? 'owner_withdrawal' : 'dispatch', $quantity, $batchId, $orderLineId, $request, $authorization, $destination ?? [], $shipment);

            foreach ($units as $unit) {
                $unit->dispatch($orderLineId, $batchId);
                $this->pairs->save($unit, $actor->id, $movementId);
            }

            return $movementId;
        });
    }
}
