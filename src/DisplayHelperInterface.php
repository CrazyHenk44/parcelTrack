<?php

namespace ParcelTrack;

use ParcelTrack\DhlTranslationService;
use ParcelTrack\TrackingResult;

interface DisplayHelperInterface
{
    public function __construct(TrackingResult $package, array $config, Logger $logger, DhlTranslationService $dhlTranslationService = null);

    public function getDisplayData(): array;
}
