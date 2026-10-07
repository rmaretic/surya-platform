<?php

namespace App;

final readonly class PublicStudioProfile
{
    public function __construct(
        public string $name,
        public ?string $shortDescription,
        public ?string $email,
        public ?string $phone,
        public ?string $address,
    ) {}

    /** @return array{name: string, short_description: ?string, email: ?string, phone: ?string, address: ?string} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'short_description' => $this->shortDescription,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
        ];
    }
}
