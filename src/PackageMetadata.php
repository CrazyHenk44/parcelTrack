<?php

namespace ParcelTrack;

class PackageMetadata
{
    public function __construct(
        public ?string $customName = null,
        public PackageStatus $status = PackageStatus::Active,
        public string $appriseUrl = ''
    ) {
    }

    public static function fromObject(\stdClass $data): self
    {
        return new self(
            customName: $data->customName ?? null,
            status: isset($data->status) ? PackageStatus::from($data->status) : PackageStatus::Active,
            appriseUrl: $data->appriseUrl ?? (new \ParcelTrack\Helpers\Config())->appriseUrl ?? ''
        );
    }
}
