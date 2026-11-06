<?php

namespace App\Application\Report\Commands;

final readonly class StoreReportForScammerCommand
{
    /**
     * @param  list<string>  $proofs
     * @param  list<string>  $productNames
     */
    public function __construct(
        public int $scammerId,
        public string $title,
        public string $description,
        public string $email,
        public array $proofs,
        public array $productNames,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromValidated(int $scammerId, array $input): self
    {
        return new self(
            scammerId: $scammerId,
            title: (string) ($input['title'] ?? ''),
            description: is_string($input['description'] ?? null) ? $input['description'] : '',
            email: (string) ($input['email'] ?? ''),
            proofs: self::proofsFrom($input['proofs'] ?? []),
            productNames: self::productNamesFrom($input['products'] ?? []),
        );
    }

    /**
     * @return list<string>
     */
    private static function proofsFrom(mixed $proofs): array
    {
        if (! is_array($proofs)) {
            return [];
        }

        $paths = [];

        foreach ($proofs as $url) {
            $paths[] = is_string($url) ? $url : '';
        }

        return $paths;
    }

    /**
     * @return list<string>
     */
    private static function productNamesFrom(mixed $products): array
    {
        if (! is_array($products)) {
            return [];
        }

        $names = [];

        foreach ($products as $name) {
            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }
}
