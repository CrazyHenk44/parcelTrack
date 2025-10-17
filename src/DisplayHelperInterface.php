<?php

namespace ParcelTrack;

use ParcelTrack\DhlTranslationService;
use ParcelTrack\TrackingResult;

interface DisplayHelperInterface
{
    public function getDisplayData(): array;
}
