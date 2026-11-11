<?php

namespace Simon\VentasPedidos\UI;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Simon\InventarioTrazabilidad\Application\CatalogVariants;
use Simon\VentasPedidos\Domain\OrderLines;

final class OrderLinesEditor extends Component
{
    use AuthorizesOrderEditor;

    /** @var list<array{variant_id:string,quantity:string}> */
    public array $lines = [];

    #[Locked]
    public ?int $orderId = null;

    /** @param list<array{variant_id:string,quantity:string}> $lines */
    public function mount(array $lines, ?int $orderId = null): void
    {
        $this->lines = $lines;
        $this->orderId = $orderId;
    }

    public function addLine(): void
    {
        $this->authorizeEditor();
        if (count($this->lines) < OrderLines::MAX_LINES) {
            $this->lines[] = ['variant_id' => '', 'quantity' => ''];
            $this->dispatch('order-line-added');
        }
    }

    public function removeLine(int $index): void
    {
        $this->authorizeEditor();
        if (count($this->lines) > 1 && array_key_exists($index, $this->lines)) {
            array_splice($this->lines, $index, 1);
            $this->dispatch('order-line-removed');
        }
    }

    public function render(CatalogVariants $catalog): View
    {
        $this->authorizeEditor();

        return view('ventas::order-lines-editor', ['variants' => $catalog->options()]);
    }
}
