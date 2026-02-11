<?php
declare(strict_types=1);

namespace App\Core;

use ReflectionClass;
use ReflectionException;
use Exception;

/**
 * Dependency Injection Container
 */
class Container
{
    private array $bindings = [];
    private array $instances = [];
    private array $singletons = [];

    /**
     * Bind an abstract to a concrete implementation
     */
    public function bind(string $abstract, callable|string|null $concrete = null): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = $concrete;
    }

    /**
     * Bind a singleton
     */
    public function singleton(string $abstract, callable|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete);
        $this->singletons[$abstract] = true;
    }

    /**
     * Resolve a dependency from the container
     */
    public function get(string $abstract): mixed
    {
        // Return existing singleton instance
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Get concrete implementation
        $concrete = $this->bindings[$abstract] ?? $abstract;

        // Build instance
        $instance = $this->build($concrete);

        // Store singleton
        if (isset($this->singletons[$abstract])) {
            $this->instances[$abstract] = $instance;
        }

        return $instance;
    }

    /**
     * Build an instance of the concrete implementation
     */
    private function build(callable|string $concrete): mixed
    {
        // If callable, execute it
        if (is_callable($concrete)) {
            return $concrete($this);
        }

        try {
            $reflection = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
            throw new Exception("Class {$concrete} does not exist");
        }

        // Check if class is instantiable
        if (!$reflection->isInstantiable()) {
            throw new Exception("Class {$concrete} is not instantiable");
        }

        $constructor = $reflection->getConstructor();

        // No constructor, return new instance
        if ($constructor === null) {
            return new $concrete();
        }

        // Get constructor parameters
        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type === null) {
                // No type hint, check for default value
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new Exception("Cannot resolve parameter {$parameter->getName()}");
                }
            } else {
                // Type hint exists, resolve it
                $typeName = $type->getName();

                if ($type->isBuiltin()) {
                    // Primitive type, use default value if available
                    if ($parameter->isDefaultValueAvailable()) {
                        $dependencies[] = $parameter->getDefaultValue();
                    } else {
                        throw new Exception("Cannot resolve primitive parameter {$parameter->getName()}");
                    }
                } else {
                    // Class/Interface, resolve from container
                    $dependencies[] = $this->get($typeName);
                }
            }
        }

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Check if abstract exists in container
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Set an instance directly
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }
}
