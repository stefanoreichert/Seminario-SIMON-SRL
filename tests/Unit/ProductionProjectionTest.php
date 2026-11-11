<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Simon\InventarioTrazabilidad\Domain\ProductionProjection;
use Simon\Shared\Domain\BusinessRuleViolation;

class ProductionProjectionTest extends TestCase
{
    #[DataProvider('balances')]
    public function test_projection_preserves_the_sign_and_converts_only_a_deficit_to_injection(int $available, int $inProcess, int $pending, int $balance, int $injection): void
    {
        $projection = new ProductionProjection($available, $inProcess, $pending);

        $this->assertSame($balance, $projection->balance);
        $this->assertSame($injection, $projection->injectionNeeded);
    }

    /** @return array<string,array{int,int,int,int,int}> */
    public static function balances(): array
    {
        return [
            'faltante' => [10, 4, 20, -6, 6],
            'cubierto' => [10, 4, 14, 0, 0],
            'excedente' => [10, 4, 3, 11, 0],
            'sin actividad' => [0, 0, 0, 0, 0],
            'solo demanda' => [0, 0, 20, -20, 20],
        ];
    }

    #[DataProvider('invalidSources')]
    public function test_negative_source_quantities_are_rejected(int $available, int $inProcess, int $pending): void
    {
        $this->expectException(BusinessRuleViolation::class);

        new ProductionProjection($available, $inProcess, $pending);
    }

    /** @return list<array{int,int,int}> */
    public static function invalidSources(): array
    {
        return [[-1, 0, 0], [0, -1, 0], [0, 0, -1]];
    }
}
