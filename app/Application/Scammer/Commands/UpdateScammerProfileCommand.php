<?php

namespace App\Application\Scammer\Commands;

final readonly class UpdateScammerProfileCommand
{
    public function __construct(
        public int $scammerId,
        public string $profilePicture,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(int $scammerId, array $data): self
    {
        return new self(
            scammerId: $scammerId,
            profilePicture: (string) $data['profile_picture'],
        );
    }
}
