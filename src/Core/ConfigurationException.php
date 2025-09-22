<?php
namespace Bamboo\Core;

use RuntimeException;

class ConfigurationException extends RuntimeException
{
    /** @var list<string> */
    private array $errors;

    /**
     * @param list<string> $errors
     */
    public function __construct(array $errors)
    {
        $this->errors = array_values($errors);
        parent::__construct($this->buildMessage());
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    private function buildMessage(): string
    {
        if ($this->errors === []) {
            return 'Configuration validation failed with unknown errors.';
        }

        $lines = array_map(static fn(string $error): string => "- {$error}", $this->errors);

        return "Configuration validation failed:\n" . implode("\n", $lines);
    }
}
