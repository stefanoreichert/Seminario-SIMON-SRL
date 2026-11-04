<?php

namespace Simon\ClientesAdministracion\Infrastructure;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Simon\ClientesAdministracion\Application\CustomerRecords;
use Simon\ClientesAdministracion\Domain\CustomerProfile;
use Simon\InventarioTrazabilidad\Application\DispatchAuthorization;
use Simon\Shared\Domain\BusinessRuleViolation;

final class SqlCustomers implements CustomerRecords, DispatchAuthorization
{
    public function register(CustomerProfile $profile, string $creditHistory, int $actorId): int
    {
        try {
            return DB::transaction(function () use ($profile, $creditHistory, $actorId): int {
                $id = DB::table('business_customers')->insertGetId([...get_object_vars($profile), 'credit_history' => $creditHistory]);
                DB::table('customer_credit_entries')->insert(['customer_id' => $id, 'actor_id' => $actorId, 'note' => $creditHistory, 'recorded_at' => now()]);

                return $id;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw new BusinessRuleViolation('Ese CUIT ya está registrado.', previous: $exception);
        }
    }

    public function update(int $customerId, CustomerProfile $profile): void
    {
        try {
            DB::transaction(function () use ($customerId, $profile): void {
                $this->requireCustomer($customerId);
                DB::table('business_customers')->where('id', $customerId)->update(get_object_vars($profile));
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            throw new BusinessRuleViolation('Ese CUIT ya está registrado.', previous: $exception);
        }
    }

    public function search(string $query, int $page): array
    {
        $customers = DB::table('business_customers');

        if ($query !== '') {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query).'%';
            $normalizedCuit = preg_replace('/[\s-]+/', '', $query);
            $cuit = $normalizedCuit === '' ? $term : '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $normalizedCuit).'%';
            $customers->where(function (Builder $where) use ($term, $cuit): void {
                $where->whereRaw("name LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("cuit LIKE ? ESCAPE '!'", [$cuit])
                    ->orWhereRaw("phone LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("email LIKE ? ESCAPE '!'", [$term]);
            });
        }

        $total = $customers->count();
        $perPage = 25;
        $page = min($page, max(1, (int) ceil($total / $perPage)));

        return [
            'items' => $customers->select('id', 'name', 'cuit', 'phone', 'email')->orderBy('name')->orderBy('id')
                ->offset(($page - 1) * $perPage)->limit($perPage)->get()->map(fn (object $customer): array => (array) $customer)->all(),
            'total' => $total, 'page' => $page, 'per_page' => $perPage,
        ];
    }

    public function find(int $customerId): ?array
    {
        $customer = DB::table('business_customers')->where('id', $customerId)->first();

        if ($customer === null) {
            return null;
        }

        $entries = DB::table('customer_credit_entries as entries')->join('users', 'users.id', '=', 'entries.actor_id')
            ->where('entries.customer_id', $customerId)->select('entries.id', 'entries.actor_id', 'users.name as actor_name', 'entries.note', 'entries.recorded_at')
            ->orderByDesc('entries.id')->get()->map(fn (object $entry): array => (array) $entry)->all();

        return [...(array) $customer, 'credit_entries' => $entries];
    }

    public function updateCredit(int $customerId, string $creditHistory, int $actorId): void
    {
        DB::transaction(function () use ($customerId, $creditHistory, $actorId): void {
            $this->requireCustomer($customerId);
            DB::table('business_customers')->where('id', $customerId)->update(['credit_history' => $creditHistory]);
            DB::table('customer_credit_entries')->insert(['customer_id' => $customerId, 'actor_id' => $actorId, 'note' => $creditHistory, 'recorded_at' => now()]);
        }, 5);
    }

    private function requireCustomer(int $customerId): void
    {
        if (DB::table('business_customers')->where('id', $customerId)->lockForUpdate()->first(['id']) === null) {
            throw new BusinessRuleViolation('El cliente no existe.');
        }
    }

    public function authorize(int $orderId, int $actorId, bool $billing, bool $credit, string $conditions): void
    {
        DB::table('dispatch_authorizations')->updateOrInsert(['order_id' => $orderId], [
            'authorized_by' => $actorId, 'billing_validated' => $billing,
            'credit_validated' => $credit, 'conditions' => $conditions, 'updated_at' => now(),
        ]);
    }

    /** @return list<object> */
    public function customers(bool $withCredit = false): array
    {
        return DB::table('business_customers')->select($withCredit ? ['*'] : ['id', 'name', 'cuit'])->orderBy('name')->limit(500)->get()->all();
    }

    public function assertAuthorized(int $orderId): array
    {
        $authorization = DB::table('dispatch_authorizations')->where('order_id', $orderId)->lockForUpdate()->first();

        if ($authorization === null || ! $authorization->billing_validated || ! $authorization->credit_validated || trim($authorization->conditions) === '') {
            throw new BusinessRuleViolation('Administración debe validar facturación, crédito y condiciones antes del despacho.');
        }

        return (array) $authorization;
    }
}
