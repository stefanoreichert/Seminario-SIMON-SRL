<?php

namespace Simon\ClientesAdministracion\Domain;

use Simon\Shared\Domain\BusinessRuleViolation;

final readonly class Money
{
    public function __construct(public int $minor, public string $currency = 'ARS', public int $scale = 2)
    {
        if (! preg_match('/\A[A-Z]{3}\z/', $currency) || $scale < 0 || $scale > 4) {
            throw new BusinessRuleViolation('La moneda y su precisión no son válidas.');
        }
    }

    public static function parse(string $value, string $currency = 'ARS', int $scale = 2): self
    {
        new self(0, $currency, $scale);
        $value = trim($value);

        if (! preg_match('/\A(-?)([0-9]{1,12})(?:[.,]([0-9]{1,4}))?\z/', $value, $matches) || strlen($matches[3] ?? '') > $scale) {
            throw new BusinessRuleViolation('Ingresá un importe sin separador de miles y con la cantidad de decimales de la moneda.');
        }

        $whole = (int) $matches[2];
        $fraction = (int) str_pad($matches[3] ?? '', $scale, '0');
        $minor = $whole * (10 ** $scale) + $fraction;

        return new self($matches[1] === '-' ? -$minor : $minor, $currency, $scale);
    }

    public function add(self $other): self
    {
        if ($this->currency !== $other->currency || $this->scale !== $other->scale) {
            throw new BusinessRuleViolation('No se pueden sumar saldos de monedas distintas.');
        }

        if (($other->minor > 0 && $this->minor > PHP_INT_MAX - $other->minor) || ($other->minor < 0 && $this->minor < PHP_INT_MIN - $other->minor)) {
            throw new BusinessRuleViolation('El importe supera el rango que puede registrarse con precisión.');
        }

        return new self($this->minor + $other->minor, $this->currency, $this->scale);
    }

    public function formatted(): string
    {
        $digits = str_pad(ltrim((string) $this->minor, '-'), $this->scale + 1, '0', STR_PAD_LEFT);
        $number = $this->scale === 0 ? $digits : substr($digits, 0, -$this->scale).','.substr($digits, -$this->scale);

        return ($this->minor < 0 ? '-' : '').$number.' '.$this->currency;
    }
}
