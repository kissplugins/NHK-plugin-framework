<?php
/**
 * Minimal Dependency Injection Container
 * 
 * A simple service container for dependency injection.
 * This is a minimal implementation for demonstration purposes.
 * 
 * @package NHK\Framework\Container
 * @since 1.0.0
 */

namespace NHK\Framework\Container;

use ReflectionClass;
use ReflectionParameter;
use Exception;

/**
 * Container Class
 * 
 * Provides dependency injection and service management.
 */
class Container {
    
    /**
     * Registered services
     * 
     * @var array
     */
    protected array $services = [];
    
    /**
     * Service instances (singletons)
     * 
     * @var array
     */
    protected array $instances = [];
    
    /**
     * Register a service
     * 
     * @param string $abstract Service identifier
     * @param callable|string|null $concrete Service implementation
     * @return void
     */
    public function bind(string $abstract, $concrete = null): void {
        if ($concrete === null) {
            $concrete = $abstract;
        }
        
        $this->services[$abstract] = $concrete;
    }
    
    /**
     * Register a singleton service
     * 
     * @param string $abstract Service identifier
     * @param callable|string|null $concrete Service implementation
     * @return void
     */
    public function singleton(string $abstract, $concrete = null): void {
        $this->bind($abstract, $concrete);
        $this->instances[$abstract] = null;
    }
    
    /**
     * Get a service from the container
     * 
     * @param string $abstract Service identifier
     * @return mixed Service instance
     * @throws Exception If service cannot be resolved
     */
    public function get(string $abstract) {
        // Check if it's a singleton and already instantiated
        if (array_key_exists($abstract, $this->instances)) {
            if ($this->instances[$abstract] !== null) {
                return $this->instances[$abstract];
            }
        }
        
        // Resolve the service
        $instance = $this->resolve($abstract);
        
        // Store singleton instance
        if (array_key_exists($abstract, $this->instances)) {
            $this->instances[$abstract] = $instance;
        }
        
        return $instance;
    }
    
    /**
     * Resolve a service
     * 
     * @param string $abstract Service identifier
     * @return mixed Service instance
     * @throws Exception If service cannot be resolved
     */
    protected function resolve(string $abstract) {
        // Check if service is registered
        if (isset($this->services[$abstract])) {
            $concrete = $this->services[$abstract];
            
            // If it's a callable, call it
            if (is_callable($concrete)) {
                return $concrete($this);
            }
            
            // If it's a string, resolve it
            if (is_string($concrete)) {
                return $this->build($concrete);
            }
            
            return $concrete;
        }
        
        // Try to auto-resolve the class
        return $this->build($abstract);
    }
    
    /**
     * Build a class instance with dependency injection
     * 
     * @param string $class Class name
     * @return mixed Class instance
     * @throws Exception If class cannot be built
     */
    protected function build(string $class) {
        if (!class_exists($class)) {
            throw new Exception("Class {$class} does not exist");
        }
        
        $reflection = new ReflectionClass($class);
        
        if (!$reflection->isInstantiable()) {
            throw new Exception("Class {$class} is not instantiable");
        }
        
        $constructor = $reflection->getConstructor();
        
        if ($constructor === null) {
            return new $class;
        }
        
        $parameters = $constructor->getParameters();
        $dependencies = $this->resolveDependencies($parameters);
        
        return $reflection->newInstanceArgs($dependencies);
    }
    
    /**
     * Resolve constructor dependencies
     * 
     * @param ReflectionParameter[] $parameters Constructor parameters
     * @return array Resolved dependencies
     * @throws Exception If dependency cannot be resolved
     */
    protected function resolveDependencies(array $parameters): array {
        $dependencies = [];
        
        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            
            if ($type === null) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new Exception("Cannot resolve parameter {$parameter->getName()}");
                }
            } elseif ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->get($type->getName());
            } else {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new Exception("Cannot resolve parameter {$parameter->getName()}");
                }
            }
        }
        
        return $dependencies;
    }
    
    /**
     * Check if a service is registered
     * 
     * @param string $abstract Service identifier
     * @return bool
     */
    public function has(string $abstract): bool {
        return isset($this->services[$abstract]) || class_exists($abstract);
    }
    
    /**
     * Remove a service from the container
     * 
     * @param string $abstract Service identifier
     * @return void
     */
    public function forget(string $abstract): void {
        unset($this->services[$abstract], $this->instances[$abstract]);
    }
    
    /**
     * Get all registered services
     * 
     * @return array
     */
    public function getServices(): array {
        return $this->services;
    }
    
    /**
     * Clear all services and instances
     * 
     * @return void
     */
    public function flush(): void {
        $this->services = [];
        $this->instances = [];
    }
}
