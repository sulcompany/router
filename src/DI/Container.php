<?php

declare(strict_types=1);

namespace SulCompany\Router\DI;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;
use Exception;

/**
 * Container profissional para injeção de dependências
 * Suporta:
 * - bind() → cria nova instância a cada get()
 * - singleton() → mesma instância em todas as resoluções
 */
class Container implements ContainerInterface
{
    /**
     * @var array<string, array{concrete: callable|object, singleton: bool}>
     */
    protected array $definitions = [];

    /**
     * @var array<string, object> Instâncias singleton
     */
    protected array $instances = [];

    /**
     * Registra um serviço que será instanciado sempre que solicitado
     *
     * @param string $id Nome da classe ou interface
     * @param callable|object $concrete Closure que retorna instância ou objeto
     */
    public function bind(string $id, callable|object $concrete): void
    {
        $this->definitions[$id] = [
            'concrete' => $concrete,
            'singleton' => false,
        ];
    }

    /**
     * Registra um serviço como singleton
     *
     * @param string $id Nome da classe ou interface
     * @param callable|object $concrete Closure que retorna instância ou objeto
     */
    public function singleton(string $id, callable|object $concrete): void
    {
        $this->definitions[$id] = [
            'concrete' => $concrete,
            'singleton' => true,
        ];
    }

    /**
     * Recupera instância do serviço
     *
     * @param string $id
     * @return mixed
     * @throws Exception
     */
    public function get(string $id): mixed
    {
        // Se já existe instância singleton
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // Se foi registrado
        if (isset($this->definitions[$id])) {
            $definition = $this->definitions[$id];
            $concrete = $definition['concrete'];

            $object = is_callable($concrete) ? $concrete($this) : $concrete;

            // Se for singleton, salva a instância
            if ($definition['singleton']) {
                $this->instances[$id] = $object;
            }

            return $object;
        }

        // Se for classe existente, resolve via reflection
        if (class_exists($id)) {
            $object = $this->resolveClass($id);
            // Por padrão, classes resolvidas via reflection são singleton
            $this->instances[$id] = $object;
            return $object;
        }

        throw new Exception("Serviço ou classe '{$id}' não encontrado no container.");
    }

    /**
     * Verifica se o container possui um serviço
     *
     * @param string $id
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->definitions[$id]) || isset($this->instances[$id]) || class_exists($id);
    }

    /**
     * Resolve uma classe automaticamente via Reflection
     *
     * @param string $class
     * @return object
     * @throws Exception
     */
    protected function resolveClass(string $class): object
    {
        $r = new ReflectionClass($class);
        $constructor = $r->getConstructor();

        if (!$constructor) return new $class();

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $args[] = $this->resolveParameter($param);
        }

        return $r->newInstanceArgs($args);
    }

    /**
     * Resolve parâmetro de método ou construtor
     *
     * @param \ReflectionParameter $param
     * @return mixed
     * @throws Exception
     */
    protected function resolveParameter(\ReflectionParameter $param): mixed
    {
        $type = $param->getType();

        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();
            if ($this->has($name)) {
                return $this->get($name);
            }
            if (class_exists($name)) {
                return $this->resolveClass($name);
            }
        }

        // Se tiver valor default, retorna ele
        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        throw new Exception("Não foi possível resolver o parâmetro \${$param->getName()} em {$param->getDeclaringClass()->getName()}");
    }
}
