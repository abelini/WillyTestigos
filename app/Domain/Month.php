<?php

declare(strict_types=1);

namespace Willy\Domain;

use DateTimeImmutable;
use Willy\Exception\ValidationException;

use function strtolower;
use function strtoupper;
use function trim;
use function ucfirst;

enum Month: string
{
    case Enero = 'ENERO';
    case Febrero = 'FEBRERO';
    case Marzo = 'MARZO';
    case Abril = 'ABRIL';
    case Mayo = 'MAYO';
    case Junio = 'JUNIO';
    case Julio = 'JULIO';
    case Agosto = 'AGOSTO';
    case Septiembre = 'SEPTIEMBRE';
    case Octubre = 'OCTUBRE';
    case Noviembre = 'NOVIEMBRE';
    case Diciembre = 'DICIEMBRE';

    public static function fromNumber(int $number): self
    {
        return match ($number) {
            1 => self::Enero,
            2 => self::Febrero,
            3 => self::Marzo,
            4 => self::Abril,
            5 => self::Mayo,
            6 => self::Junio,
            7 => self::Julio,
            8 => self::Agosto,
            9 => self::Septiembre,
            10 => self::Octubre,
            11 => self::Noviembre,
            12 => self::Diciembre,
            default => throw new ValidationException('El mes debe estar entre 1 y 12.'),
        };
    }

    public static function fromName(string $name): self
    {
        $normalized = strtoupper(trim($name));

        foreach (self::cases() as $month) {
            if ($month->value === $normalized) {
                return $month;
            }
        }

        throw new ValidationException('El mes indicado no es válido.');
    }

    public static function detect(DateTimeImmutable $now): self
    {
        $month = self::fromNumber((int) $now->format('n'));
        $day = (int) $now->format('j');

        if ($day <= 2) {
            return $month->previous();
        }

        return $month;
    }

    public function number(): int
    {
        return match ($this) {
            self::Enero => 1,
            self::Febrero => 2,
            self::Marzo => 3,
            self::Abril => 4,
            self::Mayo => 5,
            self::Junio => 6,
            self::Julio => 7,
            self::Agosto => 8,
            self::Septiembre => 9,
            self::Octubre => 10,
            self::Noviembre => 11,
            self::Diciembre => 12,
        };
    }

    public function previous(): self
    {
        $number = $this->number();

        return self::fromNumber($number === 1 ? 12 : $number - 1);
    }

    public function title(): string
    {
        return 'TESTIGOS DEL MES DE ' . $this->value;
    }

    public function fileName(): string
    {
        return $this->title() . '.pdf';
    }

    public function label(): string
    {
        return ucfirst(strtolower($this->value));
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * @return array{value: string, label: string, number: int}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
            'number' => $this->number(),
        ];
    }
}
