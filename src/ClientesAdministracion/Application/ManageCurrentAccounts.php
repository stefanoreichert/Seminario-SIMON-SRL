<?php

namespace Simon\ClientesAdministracion\Application;

use Simon\ClientesAdministracion\Domain\AccountEntryKind;
use Simon\ClientesAdministracion\Domain\CurrentAccount;
use Simon\ClientesAdministracion\Domain\Money;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\Domain\BusinessRuleViolation;

final readonly class ManageCurrentAccounts
{
    public function __construct(private AccessPolicy $access, private CurrentAccountRecords $accounts, private CustomerRecords $customers, private CustomerDirectory $directory) {}

    public function post(int $actorId, string $operationId, int $customerId, string $kind, string $amount, string $source, string $note = '', string $currency = 'ARS'): int
    {
        $this->access->require($actorId, 'finance.manage');
        $entryKind = AccountEntryKind::tryFrom($kind);

        if ($entryKind === null || $currency !== 'ARS' || ! preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i', $operationId)) {
            throw new BusinessRuleViolation('Revisá la operación y la moneda habilitada: pesos argentinos (ARS).');
        }

        $money = Money::parse($amount, $currency);
        $source = trim($source);
        $note = trim($note);

        if ($source === '' || mb_strlen($source) > 255 || mb_strlen($note) > 2000 || ($entryKind === AccountEntryKind::Adjustment && $note === '')) {
            throw new BusinessRuleViolation('Indicá la fuente del importe y, para ajustes, el motivo.');
        }

        $digest = hash('sha256', json_encode([$customerId, $currency, $kind, $money->minor, $source, $note], JSON_THROW_ON_ERROR));

        return $this->accounts->run(function () use ($actorId, $operationId, $customerId, $entryKind, $money, $source, $note, $currency, $digest): int {
            $stored = $this->accounts->locked($customerId, $currency);
            $previous = $this->accounts->replay($operationId, $actorId, $digest);

            if ($previous !== null) {
                return $previous;
            }

            if ($this->directory->identified([$customerId]) === []) {
                throw new BusinessRuleViolation('El cliente no existe.');
            }

            $account = new CurrentAccount($customerId, new Money($stored['balance_minor'] ?? 0, $currency, $stored['minor_scale'] ?? 2), $stored['initialized'] ?? false);
            $delta = $account->post($entryKind, $money);
            $id = $stored['id'] ?? $this->accounts->create($customerId, $currency, $money->scale);

            return $this->accounts->append($id, $actorId, $operationId, $entryKind->value, $delta->minor, $account->balance()->minor, $source, $note, $digest);
        });
    }

    /** @return array<string,mixed> */
    public function overview(int $actorId, ?int $customerId = null, string $query = '', int $customerPage = 1, int $entryPage = 1): array
    {
        $permission = $this->access->allows($actorId, 'finance.manage') ? 'finance.manage' : 'finance.view';
        $this->access->require($actorId, $permission);

        if ($customerPage < 1 || $entryPage < 1 || mb_strlen($query) > 255) {
            throw new BusinessRuleViolation('La búsqueda o página no es válida.');
        }

        $customer = $customerId === null ? null : ($this->directory->identified([$customerId])[0] ?? null);
        $report = $customerId === null ? ['account' => null, 'entries' => ['items' => [], 'page' => 1, 'pages' => 1, 'total' => 0]] : $this->accounts->report($customerId, 'ARS', $entryPage);

        return [...$report, 'customer' => $customer, 'customers' => $this->customers->search(trim($query), $customerPage)];
    }
}
