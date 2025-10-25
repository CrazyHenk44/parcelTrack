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
        $appriseUrl = property_exists($data, "appriseUrl") ? $data->appriseUrl : '';
        if (empty($appriseUrl)) {
            $appriseUrl = (new \ParcelTrack\Helpers\Config())->appriseUrl;
        }

        return new self(
            customName: $data->customName ?? null,
            status: isset($data->status) ? PackageStatus::from($data->status) : PackageStatus::Active,
            appriseUrl: $appriseUrl
        );
    }
}
