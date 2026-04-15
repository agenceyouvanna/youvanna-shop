<?php
namespace Youvanna\Shop;

defined('ABSPATH') || exit;

final class Container
{
    private array $services = [];
    private array $factories = [];

    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->services[$id]);
    }

    public function get(string $id): object
    {
        if (isset($this->services[$id])) {
            return $this->services[$id];
        }
        if (isset($this->factories[$id])) {
            return $this->services[$id] = ($this->factories[$id])($this);
        }
        if (class_exists($id)) {
            return $this->services[$id] = new $id();
        }
        throw new \RuntimeException("Service {$id} not registered");
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]) || isset($this->factories[$id]) || class_exists($id);
    }
}
