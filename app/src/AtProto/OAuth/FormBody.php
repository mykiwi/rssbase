<?php

declare(strict_types=1);

namespace App\AtProto\OAuth;

/**
 * The fields of a request to an authorization server, sent as a form.
 */
final readonly class FormBody
{
    /**
     * @param array<string, string> $fields
     */
    private function __construct(
        #[\SensitiveParameter]
        private array $fields,
    ) {}

    /**
     * The fields by name: FormBody::of(grant_type: 'refresh_token', refresh_token: $token).
     */
    public static function of(#[\SensitiveParameter] string ...$fields): self
    {
        return new self(self::named($fields));
    }

    public function with(#[\SensitiveParameter] string ...$fields): self
    {
        return new self([...$this->fields, ...self::named($fields)]);
    }

    /**
     * @return array<string, string>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    /**
     * @param array<array-key, string> $fields
     *
     * @return array<string, string>
     */
    private static function named(array $fields): array
    {
        $named = [];
        foreach ($fields as $name => $value) {
            if (!\is_string($name)) {
                continue;
            }

            $named[$name] = $value;
        }

        return $named;
    }

    /**
     * Keeps the codes, verifiers and tokens of the fields out of dumps.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['fields' => array_keys($this->fields)];
    }
}
