<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Simon\InventarioTrazabilidad\Domain\SupplyExpiry;
use Simon\Shared\Domain\BusinessRuleViolation;

class SupplyExpiryTest extends TestCase
{
    #[DataProvider('dates')]
    public function test_warning_boundaries(string $date, int $days, string $warning): void
    {
        $this->assertSame($warning, (new SupplyExpiry($date))->warning('2026-11-11', $days));
    }

    /** @return list<array{string,int,string}> */
    public static function dates(): array
    {
        return [['2026-11-10', 30, 'Vencido'], ['2026-11-11', 30, 'Vence hoy'], ['2026-12-11', 30, 'Próximo a vencer'], ['2026-12-12', 30, 'Vigente'], ['2026-11-12', 0, 'Vigente']];
    }

    public function test_invalid_calendar_dates_are_rejected(): void
    {
        $this->expectException(BusinessRuleViolation::class);
        new SupplyExpiry('2026-02-30');
    }
}
