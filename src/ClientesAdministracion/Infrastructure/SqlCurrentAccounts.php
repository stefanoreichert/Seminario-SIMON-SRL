<?php

namespace Simon\ClientesAdministracion\Infrastructure;

use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Simon\ClientesAdministracion\Application\CurrentAccountRecords;
use Simon\Shared\Domain\BusinessRuleViolation;

final class SqlCurrentAccounts implements CurrentAccountRecords
{
    public function run(Closure $operation): mixed
    {
        try {
            return DB::transaction($operation, 5);
        } catch (UniqueConstraintViolationException $exception) {
            throw new BusinessRuleViolation('La operación o la apertura de esta cuenta ya fue registrada. Actualizá la consulta antes de repetir.', previous: $exception);
        }
    }

    public function locked(int $customerId, string $currency): ?array
    {
        $account = DB::table('customer_current_accounts')->where('customer_id', $customerId)->where('currency', $currency)->lockForUpdate()->first();

        return $account === null ? null : $this->describe($account);
    }

    /** @return array{id:int,customer_id:int,currency:string,minor_scale:int,balance_minor:int,initialized:bool} */
    private function describe(object $account): array
    {
        $last = DB::table('customer_current_account_entries')->where('account_id', $account->id)->orderByDesc('id')->first(['balance_after_minor']);

        return ['id' => (int) $account->id, 'customer_id' => (int) $account->customer_id, 'currency' => $account->currency,
            'minor_scale' => (int) $account->minor_scale, 'balance_minor' => (int) ($last->balance_after_minor ?? 0), 'initialized' => $last !== null];
    }

    public function create(int $customerId, string $currency, int $scale): int
    {
        return DB::table('customer_current_accounts')->insertGetId(['customer_id' => $customerId, 'currency' => $currency, 'minor_scale' => $scale, 'opened_at' => now()->utc()->format('Y-m-d H:i:s')]);
    }

    public function replay(string $operationId, int $actorId, string $digest): ?int
    {
        $entry = DB::table('customer_current_account_entries')->where('operation_id', $operationId)->first(['id', 'actor_id', 'request_digest']);

        if ($entry === null) {
            return null;
        }

        if ((int) $entry->actor_id !== $actorId || ! hash_equals($entry->request_digest, $digest)) {
            throw new BusinessRuleViolation('La identificación ya corresponde a otro movimiento de cuenta corriente.');
        }

        return (int) $entry->id;
    }

    public function append(int $accountId, int $actorId, string $operationId, string $kind, int $deltaMinor, int $balanceMinor, string $source, string $note, string $digest): int
    {
        return DB::table('customer_current_account_entries')->insertGetId([
            'account_id' => $accountId, 'actor_id' => $actorId, 'operation_id' => $operationId, 'kind' => $kind,
            'amount_minor' => $deltaMinor, 'balance_after_minor' => $balanceMinor, 'source' => $source, 'note' => $note,
            'request_digest' => $digest, 'recorded_at' => now()->utc()->format('Y-m-d H:i:s'),
        ]);
    }

    public function report(int $customerId, string $currency, int $page): array
    {
        return DB::transaction(function () use ($customerId, $currency, $page): array {
            $account = DB::table('customer_current_accounts')->where('customer_id', $customerId)->where('currency', $currency)->sharedLock()->first();

            if ($account === null) {
                return ['account' => null, 'entries' => ['items' => [], 'page' => 1, 'pages' => 1, 'total' => 0]];
            }

            $entries = DB::table('customer_current_account_entries')->where('account_id', $account->id);
            $total = (clone $entries)->count();
            $pages = max(1, (int) ceil($total / 25));
            $page = min($page, $pages);

            return ['account' => $this->describe($account), 'entries' => [
                'items' => (clone $entries)->orderByDesc('id')->offset(($page - 1) * 25)->limit(25)->get()->map(fn (object $entry): array => (array) $entry)->all(),
                'page' => $page, 'pages' => $pages, 'total' => $total,
            ]];
        });
    }
}
