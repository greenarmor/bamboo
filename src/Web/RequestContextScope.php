<?php

namespace Bamboo\Web;

use ArrayAccess;

class RequestContextScope
{
    private const CONTEXT_KEY = 'bamboo.request_context';

    private ?RequestContext $fallbackContext = null;

    public function set(RequestContext $context): void
    {
        $store = $this->fetchCoroutineContext();
        if ($store !== null) {
            $store[self::CONTEXT_KEY] = $context;
            return;
        }

        $this->fallbackContext = $context;
    }

    public function get(): ?RequestContext
    {
        $store = $this->fetchCoroutineContext();
        if ($store !== null) {
            return $this->getFromStore($store);
        }

        return $this->fallbackContext;
    }

    public function getOrCreate(): RequestContext
    {
        $context = $this->get();
        if ($context instanceof RequestContext) {
            return $context;
        }

        $context = new RequestContext();
        $this->set($context);

        return $context;
    }

    public function has(): bool
    {
        $store = $this->fetchCoroutineContext();
        if ($store !== null) {
            return $this->getFromStore($store) instanceof RequestContext;
        }

        return $this->fallbackContext instanceof RequestContext;
    }

    public function clear(): void
    {
        $store = $this->fetchCoroutineContext();
        if ($store !== null) {
            if ($store instanceof ArrayAccess && $store->offsetExists(self::CONTEXT_KEY)) {
                $store->offsetUnset(self::CONTEXT_KEY);
            }
            return;
        }

        $this->fallbackContext = null;
    }
    
    private function getFromStore(ArrayAccess $store): ?RequestContext
    {
        if (!$store->offsetExists(self::CONTEXT_KEY)) {
            return null;
        }

        $value = $store->offsetGet(self::CONTEXT_KEY);
        return $value instanceof RequestContext ? $value : null;
    }

private function fetchCoroutineContext(): ?ArrayAccess<string, mixed>
    {
        $class = $this->resolveCoroutineClass();
        if ($class === null) {
            return null;
        }

        try {
            $context = $class::getContext();
        } catch (\Throwable) {
            return null;
        }

        return $context instanceof ArrayAccess ? $context : null;
    }

    private function resolveCoroutineClass(): ?string
    {
        if (class_exists('\\OpenSwoole\\Coroutine')) {
            return '\\OpenSwoole\\Coroutine';
        }

        if (class_exists('\\Swoole\\Coroutine')) {
            return '\\Swoole\\Coroutine';
        }

        return null;
    }
}
