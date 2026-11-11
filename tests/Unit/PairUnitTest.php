<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Simon\InventarioTrazabilidad\Domain\PairUnit;
use Simon\Shared\Domain\BusinessRuleViolation;

class PairUnitTest extends TestCase
{
    public function test_identity_survives_selection_release_and_dispatch(): void
    {
        $unit = new PairUnit('PAR-'.str_repeat('A', 24), 1, 2);
        $unit->pick(3, 2);
        $this->assertSame('picked', $unit->state());
        $unit->release(3);
        $this->assertNull($unit->orderLineId());
        $unit->pick(4, 2);
        $unit->dispatch(4, 1);
        $this->assertSame('dispatched', $unit->state());
        $this->assertSame('PAR-'.str_repeat('A', 24), $unit->code);
        $this->expectException(BusinessRuleViolation::class);
        $unit->pick(4, 2);
    }

    public function test_duplicate_scan_is_rejected(): void
    {
        $unit = new PairUnit('PAR-'.str_repeat('B', 24), 1, 2);
        $unit->pick(3, 2);
        $this->expectException(BusinessRuleViolation::class);
        $unit->pick(3, 2);
    }

    public function test_another_variant_cannot_be_selected(): void
    {
        $unit = new PairUnit('PAR-'.str_repeat('C', 24), 1, 2);
        $this->expectException(BusinessRuleViolation::class);
        $unit->pick(3, 4);
    }

    public function test_a_selection_cannot_be_dispatched_for_another_line_or_batch(): void
    {
        $unit = new PairUnit('PAR-'.str_repeat('D', 24), 1, 2);
        $unit->pick(3, 2);
        $this->expectException(BusinessRuleViolation::class);
        $unit->dispatch(4, 1);
    }
}
