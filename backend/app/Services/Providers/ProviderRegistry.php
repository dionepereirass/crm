<?php

namespace App\Services\Providers;

use App\Services\Providers\Contracts\MessageProviderInterface;
use App\Services\Providers\Drivers\BrevoEmailProvider;
use App\Services\Providers\Drivers\FakeEmailProvider;
use App\Services\Providers\Drivers\FakeSmsProvider;
use App\Services\Providers\Drivers\ZenviaSmsProvider;
use InvalidArgumentException;

class ProviderRegistry
{
    /**
     * Mapa de drivers registrados para suas respectivas classes implementadoras.
     */
    protected static array $drivers = [
        'fake_email' => FakeEmailProvider::class,
        'fake_sms' => FakeSmsProvider::class,
        'brevo' => BrevoEmailProvider::class,
        'zenvia' => ZenviaSmsProvider::class,
    ];

    /**
     * Registra um novo driver de provedor.
     */
    public static function register(string $driver, string $class): void
    {
        if (!is_subclass_of($class, MessageProviderInterface::class)) {
            throw new InvalidArgumentException("A classe [{$class}] deve implementar MessageProviderInterface.");
        }

        self::$drivers[strtolower($driver)] = $class;
    }

    /**
     * Verifica se o driver está registrado.
     */
    public static function has(string $driver): bool
    {
        return isset(self::$drivers[strtolower($driver)]);
    }

    /**
     * Obtém a classe do driver registrado.
     */
    public static function get(string $driver): ?string
    {
        return self::$drivers[strtolower($driver)] ?? null;
    }

    /**
     * Retorna todos os drivers registrados.
     */
    public static function all(): array
    {
        return self::$drivers;
    }

    /**
     * Instancia o driver com credenciais e configurações fornecidas.
     */
    public static function make(string $driver, array $credentials = [], array $configuration = []): MessageProviderInterface
    {
        $normalized = strtolower($driver);
        if (!self::has($normalized)) {
            throw new InvalidArgumentException("O driver [{$driver}] não está registrado no ProviderRegistry.");
        }

        $class = self::get($normalized);
        return new $class($credentials, $configuration);
    }
}
