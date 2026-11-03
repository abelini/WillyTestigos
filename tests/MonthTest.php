<?php

declare(strict_types=1);

namespace Willy\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Willy\Domain\Month;
use Willy\Exception\ValidationException;

final class MonthTest extends TestCase
{
    public function testDetectReturnsPreviousMonthOnDayOne(): void
    {
        $now = new DateTimeImmutable('2026-11-01 10:00:00', $this->zone());

        self::assertSame(Month::Octubre, Month::detect($now));
    }

    public function testDetectReturnsPreviousMonthOnDayTwoAtTheEndOfTheDay(): void
    {
        $now = new DateTimeImmutable('2026-11-02 23:59:59', $this->zone());

        self::assertSame(Month::Octubre, Month::detect($now));
    }

    public function testDetectReturnsCurrentMonthOnDayThree(): void
    {
        $now = new DateTimeImmutable('2026-11-03 00:00:00', $this->zone());

        self::assertSame(Month::Noviembre, Month::detect($now));
    }

    public function testDetectJanuaryFirstReturnsDecember(): void
    {
        $now = new DateTimeImmutable('2027-01-01 10:00:00', $this->zone());

        self::assertSame(Month::Diciembre, Month::detect($now));
    }

    public function testTitleAndFileName(): void
    {
        self::assertSame('TESTIGOS DEL MES DE NOVIEMBRE', Month::Noviembre->title());
        self::assertSame('TESTIGOS DEL MES DE NOVIEMBRE.pdf', Month::Noviembre->fileName());
    }

    public function testFromNameRejectsUnknownMonth(): void
    {
        $this->expectException(ValidationException::class);

        Month::fromName('MES INVENTADO');
    }

    private function zone(): DateTimeZone
    {
        return new DateTimeZone('America/Mazatlan');
    }
}
