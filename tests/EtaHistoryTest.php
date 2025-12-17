<?php

use PHPUnit\Framework\TestCase;
use ParcelTrack\TrackingResult;
use ParcelTrack\Event;
use ParcelTrack\Helpers\DateHelper;

class EtaHistoryTest extends TestCase
{
    private function createResult($code, $start = null, $end = null) {
        $r = new TrackingResult([
            'trackingCode' => $code,
            'shipper' => 'test',
            'packageStatus' => 'active',
            'rawResponse' => ''
        ]);
        $r->etaStart = $start;
        $r->etaEnd = $end;
        return $r;
    }

    /* Logic extracted from cron.php to be testable here, or simulating the logic */
    private function runLogic(TrackingResult $old = null, TrackingResult $new): void
    {
        $previousEtaStart = $old ? $old->etaStart : null;
        $previousEtaEnd   = $old ? $old->etaEnd   : null;
        $newEtaStart      = $new->etaStart;
        $newEtaEnd        = $new->etaEnd;

        $hasNewEta = ($newEtaStart !== null);
        
        if ($hasNewEta) {
            $isInitial = ($previousEtaStart === null);
            $isChange  = ($previousEtaStart !== $newEtaStart || $previousEtaEnd !== $newEtaEnd);

            if ($isInitial || $isChange) {
                $etaDescription = DateHelper::formatAbsoluteDutchDateRange($newEtaStart, $newEtaEnd);
                $msgPrefix = $isInitial ? "Geplande bezorging: " : "Geplande bezorging gewijzigd naar: ";

                $new->addEvent(new Event('now', $msgPrefix . $etaDescription, null, true));
            }
        }
    }

    public function testInitialEta()
    {
        $new = $this->createResult('123', '2025-10-27T10:00:00', '2025-10-27T12:00:00');
        // Old is null (or same as brand new package)
        $this->runLogic(null, $new);

        $this->assertCount(1, $new->events);
        $this->assertTrue($new->events[0]->isInternal);
        $this->assertStringContainsString('Geplande bezorging:', $new->events[0]->description);
        $this->assertStringNotContainsString('gewijzigd naar', $new->events[0]->description);
    }

    public function testEtaChange()
    {
        $old = $this->createResult('123', '2025-10-27T10:00:00');
        $new = $this->createResult('123', '2025-10-28T14:00:00');

        $this->runLogic($old, $new);

        $this->assertCount(1, $new->events);
        $this->assertStringContainsString('Geplande bezorging gewijzigd naar:', $new->events[0]->description);
    }

    public function testNoChange()
    {
        $old = $this->createResult('123', '2025-10-27T10:00:00', '2025-10-27T12:00:00');
        $new = $this->createResult('123', '2025-10-27T10:00:00', '2025-10-27T12:00:00');

        $this->runLogic($old, $new);

        $this->assertCount(0, $new->events);
    }

    public function testNullEndDateHelper()
    {
        // Test DateHelper formatting directly
        $date = '2025-10-27T10:00:00';
        $formatted = DateHelper::formatAbsoluteDutchDateRange($date, null);
        
        // Expected: Maandag 27 okt, 10:00
        $this->assertStringContainsString('Maandag 27 okt, 10:00', $formatted);
        $this->assertStringNotContainsString('-', $formatted); // No dash range
    }
}
