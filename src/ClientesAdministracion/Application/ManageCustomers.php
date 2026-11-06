<?php

namespace Simon\ClientesAdministracion\Application;

use Simon\ClientesAdministracion\Domain\CustomerProfile;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\Domain\BusinessRuleViolation;
use Simon\VentasPedidos\Application\DispatchableOrders;

final readonly class ManageCustomers
{
    public function __construct(private AccessPolicy $access, private CustomerRecords $customers, private DispatchableOrders $orders) {}

    public function register(int $actorId, string $name, string $cuit, string $creditHistory, ?string $phone = null, ?string $email = null): int
    {
        $this->access->require($actorId, 'clients.manage');
        $this->validateCredit($creditHistory);

        return $this->customers->register(new CustomerProfile($name, $cuit, $phone, $email), trim($creditHistory), $actorId);
    }

    public function update(int $actorId, int $customerId, string $name, string $cuit, ?string $phone, ?string $email): void
    {
        $this->access->require($actorId, 'clients.manage');
        $this->customers->update($customerId, new CustomerProfile($name, $cuit, $phone, $email));
    }

    /**
     * @return array{items: list<array{id: int, name: string, cuit: string, phone: ?string, email: ?string}>, total: int, page: int, per_page: int}
     */
    public function search(int $actorId, string $query = '', int $page = 1): array
    {
        $this->access->require($actorId, 'clients.manage');

        if ($page < 1 || mb_strlen($query) > 255) {
            throw new BusinessRuleViolation('La búsqueda o la página solicitada no son válidas.');
        }

        return $this->customers->search(trim($query), $page);
    }

    /**
     * @return array{id: int, name: string, cuit: string, phone: ?string, email: ?string, credit_history: string, credit_entries: list<array{id: int, actor_id: int, actor_name: string, note: string, recorded_at: string}>}|null
     */
    public function profile(int $actorId, int $customerId): ?array
    {
        $this->access->require($actorId, 'clients.manage');

        return $this->customers->find($customerId);
    }

    public function updateCredit(int $actorId, int $customerId, string $creditHistory): void
    {
        $this->access->require($actorId, 'clients.manage');
        $this->validateCredit($creditHistory);

        if (trim($creditHistory) === '') {
            throw new BusinessRuleViolation('Ingresá la observación crediticia antes de guardarla.');
        }

        $this->customers->updateCredit($customerId, trim($creditHistory), $actorId);
    }

    public function authorize(int $actorId, int $orderId, bool $billing, bool $credit, string $conditions): void
    {
        $this->access->require($actorId, 'dispatch.authorize');

        if (trim($conditions) === '') {
            throw new BusinessRuleViolation('Administración debe registrar las condiciones del despacho.');
        }

        if (! $this->orders->dispatchable($orderId)) {
            throw new BusinessRuleViolation('Solo se pueden autorizar despachos de pedidos confirmados o parciales.');
        }

        $this->customers->authorize($orderId, $actorId, $billing, $credit, trim($conditions));
    }

    private function validateCredit(string $creditHistory): void
    {
        if (mb_strlen($creditHistory) > 10000) {
            throw new BusinessRuleViolation('El historial crediticio admite hasta 10000 caracteres por observación.');
        }
    }
}
