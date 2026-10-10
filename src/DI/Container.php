<?php

declare(strict_types=1);

namespace SulCompany\Router\DI;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use SulCompany\Router\DI\Exceptions\ContainerException;
use SulCompany\Router\DI\Exceptions\NotFoundException;

class Container implements ContainerInterface
{
    /**
     * @var array<string, array{
     *     concrete: callable|object,
     *     singleton: bool
     * }>
     */
    protected array $definitions = [];

    /**
     * @var array<string, object>
     */
    protected array $instances = [];

    /**
     * @var array<string, bool>
     */
    protected array $resolving = [];

    /**
     * Registra uma definição transient (nova instância a cada get).
     */
    public function bind(string $id, callable|object $concrete): void
    {
        $this->definitions[$id] = [
            'concrete' => $concrete,
            'singleton' => false,
        ];
    }

    /**
     * Registra uma definição shared (mesma instância sempre).
     */
    public function singleton(string $id, callable|object $concrete): void
    {
        $this->definitions[$id] = [
            'concrete' => $concrete,
            'singleton' => true,
        ];
    }

    /**
     * Registra uma instância já criada (sempre shared).
     */
    public function instance(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
    }

    /**
     * Resolve uma entrada do container.
     *
     * @throws NotFoundException  quando não há definição e não é autowireable
     * @throws ContainerException quando a resolução falha (circular, etc)
     */
    public function get(string $id): mixed
    {
        // 1. Instância já resolvida?
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // 2. Definição registrada?
        if (isset($this->definitions[$id])) {
            return $this->resolveDefinition($id);
        }

        // 3. Autowiring implícito
        if (class_exists($id)) {
            return $this->resolveClass($id);
        }

        throw new NotFoundException(
            "Serviço ou classe '{$id}' não encontrado no container."
        );
    }

    /**
     * Verifica se há uma definição registrada.
     *
     * Não considera autowiring: retorna true apenas para bind/singleton/instance.
     */
    public function has(string $id): bool
    {
        return isset($this->definitions[$id])
            || isset($this->instances[$id]);
    }

    protected function resolveDefinition(string $id): object
    {
        $definition = $this->definitions[$id];
        $concrete = $definition['concrete'];

        $object = is_callable($concrete)
            ? $concrete($this)
            : $concrete;

        if (!is_object($object)) {
            throw new ContainerException(
                "A definição '{$id}' deve resolver para um objeto."
            );
        }

        if ($definition['singleton']) {
            $this->instances[$id] = $object;
        }

        return $object;
    }

    protected function resolveClass(string $class): object
    {
        if (isset($this->resolving[$class])) {
            throw new ContainerException(
                "Detecção de dependência circular ao resolver '{$class}'."
            );
        }

        $this->resolving[$class] = true;

        try {
            $reflection = new ReflectionClass($class);

            if (!$reflection->isInstantiable()) {
                throw new ContainerException(
                    "A classe '{$class}' não pode ser instanciada."
                );
            }

            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                return new $class();
            }

            $args = [];

            foreach ($constructor->getParameters() as $parameter) {
                $args[] = $this->resolveParameter($parameter);
            }

            return $reflection->newInstanceArgs($args);

        } finally {
            unset($this->resolving[$class]);
        }
    }

    protected function resolveParameter(ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $name = $type->getName();

            if ($this->has($name)) {
                return $this->get($name);
            }

            if (class_exists($name)) {
                return $this->resolveClass($name);
            }

            throw new ContainerException(
                "Não foi possível resolver o tipo '{$name}' "
                . "para o parâmetro \${$parameter->getName()}."
            );
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->allowsNull()) {
            return null;
        }

        $declaring = $parameter->getDeclaringClass()?->getName() ?? 'desconhecida';

        throw new ContainerException(
            "Não foi possível resolver o parâmetro "
            . "\${$parameter->getName()} em {$declaring}."
        );
    }
}