<?php
/**
 * Simple Dependency Injection Container for NHK Framework
 * 
 * This container provides basic dependency injection functionality
 * without external dependencies, following WordPress best practices.
 * 
 * @package NHK\Framework\Container
 * @since 1.0.0
 */

namespace NHK\Framework\Container;

use ReflectionClass;
use ReflectionException;
use InvalidArgumentException;

/**
 * Simple service container for dependency injection
 * 
 * Provides methods for:
 * - Registering services as singletons or factories
 * - Resolving dependencies automatically
 * - Managing service instances
 */
class Container {
    
    /**
     * Registered services
     * 
     * @var array
     */
    protected array $services = [];
    
    /**
     * Service instances (for singletons)
     * 
     * @var array
     */
    protected array $instances = [];
    
    /**
     * Service factories
     * 
     * @var array
     */
    protected array $factories = [];
    
    /**
     * Register a service as a singleton
     * 
     * @param string $abstract Service identifier (usually class name)
     * @param callable|string|null $concrete Service implementation
     * @return void
     */
    public function singleton(string $abstract, $concrete = null): void {
        $this->services[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'singleton' => true
        ];
    }
    
    /**
     * Register a service as a factory (new instance each time)
     * 
     * @param string $abstract Service identifier
     * @param callable|string|null $concrete Service implementation
     * @return void
     */
    public function factory(string $abstract, $concrete = null): void {
        $this->services[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'singleton' => false
        ];
    }
    
    /**
     * Register a factory function
     * 
     * @param string $abstract Service identifier
     * @param callable $factory Factory function
     * @return void
     */
    public function register_factory(string $abstract, callable $factory): void {
        $this->factories[$abstract] = $factory;
    }
    
    /**
     * Get a service from the container
     * 
     * @param string $abstract Service identifier
     * @return mixed
     * @throws InvalidArgumentException If service cannot be resolved
     */
    public function get(string $abstract) {
        // Check if we have a factory function
        if (isset($this->factories[$abstract])) {
            return call_user_func($this->factories[$abstract], $this);
        }
        
        // Check if it's a singleton and already instantiated
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }
        
        // Get service configuration
        $service = $this->services[$abstract] ?? null;
        
        if (!$service) {
            // Try to auto-resolve if it's a class
            if (class_exists($abstract)) {
                $service = [
                    'concrete' => $abstract,
                    'singleton' => false
                ];
            } else {
                throw new InvalidArgumentException("Service {$abstract} not found in container");
            }
        }
        
        // Resolve the service
        $instance = $this->resolve($service['concrete']);
        
        // Store as singleton if needed
        if ($service['singleton']) {
            $this->instances[$abstract] = $instance;
        }
        
        return $instance;
    }
    
    /**
     * Check if a service is registered
     * 
     * @param string $abstract Service identifier
     * @return bool
     */
    public function has(string $abstract): bool {
        return isset($this->services[$abstract]) || 
               isset($this->factories[$abstract]) || 
               class_exists($abstract);
    }
    
    /**
     * Resolve a service
     * 
     * @param mixed $concrete Service implementation
     * @return mixed
     * @throws InvalidArgumentException If service cannot be resolved
     */
    protected function resolve($concrete) {
        // If it's a callable, call it
        if (is_callable($concrete)) {
            return call_user_func($concrete, $this);
        }
        
        // If it's a string, assume it's a class name
        if (is_string($concrete)) {
            return $this->build($concrete);
        }
        
        // Return as-is
        return $concrete;
    }
    
    /**
     * Build a class instance with dependency injection
     * 
     * @param string $class Class name
     * @return object
     * @throws InvalidArgumentException If class cannot be built
     */
    protected function build(string $class): object {
        try {
            $reflection = new ReflectionClass($class);
        } catch (ReflectionException $e) {
            throw new InvalidArgumentException("Class {$class} does not exist");
        }
        
        // Check if class is instantiable
        if (!$reflection->isInstantiable()) {
            throw new InvalidArgumentException("Class {$class} is not instantiable");
        }
        
        $constructor = $reflection->getConstructor();
        
        // If no constructor, just create instance
        if (!$constructor) {
            return new $class();
        }
        
        // Get constructor parameters
        $parameters = $constructor->getParameters();
        $dependencies = [];
        
        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            
            if (!$type || $type->isBuiltin()) {
                // Can't resolve built-in types automatically
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new InvalidArgumentException(
                        "Cannot resolve parameter {$parameter->getName()} for class {$class}"
                    );
                }
            } else {
                // Try to resolve the dependency
                $dependency_class = $type->getName();
                $dependencies[] = $this->get($dependency_class);
            }
        }
        
        return $reflection->newInstanceArgs($dependencies);
    }
    
    /**
     * Clear all instances (useful for testing)
     * 
     * @return void
     */
    public function clear_instances(): void {
        $this->instances = [];
    }
    
    /**
     * Get all registered services
     * 
     * @return array
     */
    public function get_services(): array {
        return $this->services;
    }
}
