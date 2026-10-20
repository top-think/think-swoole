<?php

namespace think\swoole;

use Closure;
use InvalidArgumentException;
use RuntimeException;
use think\swoole\redis\Connection;

class Redis
{
    protected const LEGACY_FIELDS = [
        'host',
        'port',
        'password',
        'database',
        'timeout',
        'options',
    ];

    /**
     * @var string
     */
    protected $default;

    /**
     * @var array
     */
    protected $definitions = [];

    /**
     * @var Connection[]
     */
    protected $connections = [];

    /**
     * @var Closure|null
     */
    protected $logger;

    public function __construct(array $config = [], ?Closure $logger = null)
    {
        $connections = $config['connections'] ?? [];

        if (!is_array($connections)) {
            throw new InvalidArgumentException('swoole.redis.connections must be an array');
        }

        $this->definitions = $connections;
        $this->default     = (string) ($config['default'] ?? 'default');
        $this->logger      = $logger;
    }

    public function getDefaultConnection(): string
    {
        return $this->default;
    }

    public function connection(?string $name = null): Connection
    {
        $name = $name === null ? $this->default : $name;

        if ($name === '') {
            throw new InvalidArgumentException('Redis connection name must not be empty');
        }

        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        if (!isset($this->definitions[$name]) || !is_array($this->definitions[$name])) {
            throw new InvalidArgumentException("Redis connection [{$name}] is not configured");
        }

        $connection = new Connection($name, $this->definitions[$name]);

        return $this->connections[$name] = $connection;
    }

    /**
     * 将模块的字符串/数组配置解析为命名连接
     *
     * @internal
     */
    public function connectionFor($config, string $legacyName): Connection
    {
        if (is_string($config) && $config !== '') {
            return $this->connection($config);
        }

        if (is_array($config)) {
            if (isset($config['connection'])) {
                if (!is_string($config['connection']) || $config['connection'] === '') {
                    throw new InvalidArgumentException("Redis connection for [{$legacyName}] must be a non-empty string");
                }

                return $this->connection($config['connection']);
            }

            if ($this->hasLegacyFields($config)) {
                return $this->connection($this->registerLegacy($legacyName, $config));
            }
        }

        return $this->connection();
    }

    protected function hasLegacyFields(array $config): bool
    {
        foreach (self::LEGACY_FIELDS as $field) {
            if (array_key_exists($field, $config)) {
                return true;
            }
        }

        return false;
    }

    protected function registerLegacy(string $module, array $config): string
    {
        $name = 'legacy.' . $module;
        $config += [
            'host'    => '127.0.0.1',
            'port'    => 6379,
            'timeout' => 10,
        ];

        if (isset($this->definitions[$name])) {
            if ($this->definitions[$name] !== $config) {
                throw new RuntimeException("Redis legacy connection [{$name}] has conflicting configurations");
            }

            return $name;
        }

        $this->definitions[$name] = $config;

        if ($this->logger) {
            ($this->logger)("Redis config for [{$module}] is deprecated; use swoole.redis.connections and a connection name");
        }

        return $name;
    }
}
