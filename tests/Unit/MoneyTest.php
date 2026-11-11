<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Simon\ClientesAdministracion\Domain\AccountEntryKind;
use Simon\ClientesAdministracion\Domain\CurrentAccount;
use Simon\ClientesAdministracion\Domain\Money;
use Simon\Shared\Domain\BusinessRuleViolation;

class MoneyTest extends TestCase
{
    #[DataProvider('amounts')]
    public function test_decimal_input_is_stored_and_formatted_without_floating_point(string $input, int $minor, string $formatted): void
    {
        $money = Money::parse($input);
        $this->assertSame($minor, $money->minor);
        $this->assertSame($formatted, $money->formatted());
    }

    /** @return list<array{string,int,string}> */
    public static function amounts(): array
    {
        return [['0', 0, '0,00 ARS'], ['1234,50', 123450, '1234,50 ARS'], ['0.1', 10, '0,10 ARS'], ['-0,10', -10, '-0,10 ARS'], ['999999999999.99', 99999999999999, '999999999999,99 ARS']];
    }

    #[DataProvider('invalidAmounts')]
    public function test_ambiguous_or_overprecise_inputs_are_rejected(string $input): void
    {
        $this->expectException(BusinessRuleViolation::class);
        Money::parse($input);
    }

    /** @return list<array{string}> */
    public static function invalidAmounts(): array
    {
        return [['1.234,50'], ['10,001'], ['1e3'], ['NaN'], [''], ['1000000000000']];
    }

    public function test_mixed_currencies_cannot_be_added(): void
    {
        $this->expectException(BusinessRuleViolation::class);
        Money::parse('1')->add(Money::parse('1', 'USD'));
    }

    public function test_account_lifecycle_preserves_identity_and_requires_explicit_opening(): void
    {
        $account = new CurrentAccount(7, new Money(0), false);
        $account->post(AccountEntryKind::Opening, Money::parse('1000'));
        $account->post(AccountEntryKind::Charge, Money::parse('250,10'));
        $account->post(AccountEntryKind::Payment, Money::parse('200'));
        $account->post(AccountEntryKind::Adjustment, Money::parse('-50,10'));
        $delta = $account->post(AccountEntryKind::Reconciliation, Money::parse('1000'));
        $this->assertSame(0, $delta->minor);
        $this->assertSame(100000, $account->balance()->minor);
        $this->assertSame(7, $account->customerId);
        $this->expectException(BusinessRuleViolation::class);
        $account->post(AccountEntryKind::Opening, Money::parse('0'));
    }

    public function test_a_charge_without_an_opening_balance_is_rejected(): void
    {
        $this->expectException(BusinessRuleViolation::class);
        (new CurrentAccount(1, new Money(0), false))->post(AccountEntryKind::Charge, Money::parse('1'));
    }

    public function test_an_incorrect_reconciliation_cannot_change_the_balance(): void
    {
        $account = new CurrentAccount(1, Money::parse('10'), true);

        try {
            $account->post(AccountEntryKind::Reconciliation, Money::parse('9'));
            $this->fail('Una diferencia exige revisión y ajuste documentado.');
        } catch (BusinessRuleViolation) {
            $this->assertSame(1000, $account->balance()->minor);
        }
    }
}
