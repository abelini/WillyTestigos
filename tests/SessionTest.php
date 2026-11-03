<?php

declare(strict_types=1);

namespace Willy\Tests;

use PHPUnit\Framework\TestCase;
use Willy\Exception\AuthException;
use Willy\Support\Session;

final class SessionTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        TestEnvironment::resetSession();
        TestEnvironment::files()->ensurePaths(TestEnvironment::paths());

        $this->session = Session::start(
            TestEnvironment::config(),
            TestEnvironment::paths(),
            TestEnvironment::clock(),
        );
    }

    public function testItStartsWithAnEmptySession(): void
    {
        self::assertFalse($this->session->has('cualquier-clave'));
    }

    public function testItStoresAndReadsScalars(): void
    {
        $this->session->set('texto', 'captura');
        $this->session->set('entero', 7);
        $this->session->set('bandera', true);

        self::assertSame('captura', $this->session->getString('texto'));
        self::assertSame(7, $this->session->getInt('entero'));
        self::assertTrue($this->session->getBool('bandera'));
    }

    public function testGetStringRejectsAMissingKey(): void
    {
        $this->expectException(AuthException::class);

        $this->session->getString('inexistente');
    }

    public function testGetIntRejectsAMissingKey(): void
    {
        $this->expectException(AuthException::class);

        $this->session->getInt('inexistente');
    }

    public function testGetBoolRejectsAMissingKey(): void
    {
        $this->expectException(AuthException::class);

        $this->session->getBool('inexistente');
    }

    public function testRemoveDeletesTheKey(): void
    {
        $this->session->set('temporal', 'valor');
        $this->session->remove('temporal');

        self::assertFalse($this->session->has('temporal'));
    }

    public function testRemoveIgnoresAMissingKey(): void
    {
        $this->session->remove('inexistente');

        self::assertFalse($this->session->has('inexistente'));
    }

    public function testRegenerateKeepsTheData(): void
    {
        $this->session->set('preclave', 'valor');
        $this->session->regenerate();

        self::assertSame('valor', $this->session->getString('preclave'));
    }

    public function testDestroyClearsTheSession(): void
    {
        $this->session->set('sesion', 'activa');
        $this->session->destroy();

        $fresh = Session::start(TestEnvironment::config(), TestEnvironment::paths(), TestEnvironment::clock());

        self::assertFalse($fresh->has('sesion'));
    }
}
