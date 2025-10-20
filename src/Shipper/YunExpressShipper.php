<?php

namespace ParcelTrack\Shipper;

use ParcelTrack\Event;
use ParcelTrack\TrackingResult;

class YunExpressShipper implements ShipperInterface
{
    private const POST_URL = 'https://services.yuntrack.com/Track/Query';
    private const SECRET   = 'f3c42837e3b46431ddf5d7db7d67017d';

    public function getRequiredFields(): array
    {
        return [];
    }


    public function fetch(string $trackingCode, array $options = []): ?TrackingResult
    {
        $numbers   = [$trackingCode];
        $timestamp = (int) (microtime(true) * 1000);

        // Build message for HMAC calculation
        $message   = "Timestamp={$timestamp}&NumberList=" . json_encode($numbers, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $message, self::SECRET);

        // Build payload
        $payload = [
            'NumberList'          => $numbers,
            'CaptchaVerification' => '',
            'Timestamp'           => $timestamp,
            'Signature'           => $signature
        ];

        // Build headers
        $headers = [
            'Accept: application/json, text/javascript, */*; q=0.01',
            'Content-Type: application/json;charset=UTF-8',
            'X-Requested-With: XMLHttpRequest',
            'Origin: https://www.yuntrack.com',
            'Referer: https://www.yuntrack.com/Track/Detailing?id=' . $trackingCode,
            'User-Agent: Mozilla/5.0'
        ];

        // Execute HTTP POST
        $ch = curl_init(self::POST_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT        => 15,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response === false) {
            // Handle curl error, maybe log it
            return null;
        }
        $res = $this->getTrackingResult($trackingCode, $response);
        return $res;
    }

    public function getTrackingResult(string $trackingCode, ?string $apiResponse): ?TrackingResult
    {
        if ($apiResponse === null) {
            return null;
        }

        $data = json_decode($apiResponse, true);
        if (!isset($data['ResultList'][0]['TrackInfo']['TrackEventDetails'])) {
            return null;
        }

        $events = [];
        foreach ($data['ResultList'][0]['TrackInfo']['TrackEventDetails'] as $eventData) {
            $events[] = new Event(
                $eventData['CreatedOn'],
                $eventData['ProcessContent'],
                $eventData['ProcessLocation']
            );
        }

        $latestStatus     = 'Unknown';
        $latestStatusDate = null;
        if (!empty($events)) {
            $latestStatus     = $events[count($events) - 1]->description;
            $latestStatusDate = $events[count($events) - 1]->timestamp;
        }

        $tr = new TrackingResult([
            'trackingCode'  => $trackingCode,
            'shipper'       => ShipperConstants::YUNEXPRESS,
            'packageStatus' => $data['ResultList'][0]['TrackData']['TrackStatus'],
            'rawResponse'   => $apiResponse ?? ''
        ]);

        $tr->events = $events;
        return $tr;
    }

    public function getShipperLink(TrackingResult $package): ?string
    {
         $trackingCode = $package->trackingCode ?? '';
        
        if (!$trackingCode) {
            return null;
        }

        return "https://www.yuntrack.com/Track/Detailing?id={$trackingCode}";
    }
}
