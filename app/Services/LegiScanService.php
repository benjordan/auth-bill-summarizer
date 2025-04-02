<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LegiScanService
{
    protected string $apiKey;
    protected string $state = 'ca';

    public function __construct()
    {
        $this->apiKey = config('services.legiscan.key');
    }

    public function getSessions()
    {
        return Cache::remember('legiscan_sessions_ca', now()->addHours(6), function () {
            $response = Http::get("https://api.legiscan.com/", [
                'key' => $this->apiKey,
                'op' => 'getSessionList',
                'state' => $this->state
            ]);

            if (!$response->ok()) {
                throw new \Exception('Failed to fetch session list from LegiScan');
            }

            // 💀 STOP FILTERING BY 'state' HERE — it's not present in the response
            return collect($response->json()['sessions'] ?? [])
                ->sortByDesc('year_start') // just sort
                ->values();
        });
    }

    public function findSessionIdByYearRange($start, $end)
    {
        return $this->getSessions()->firstWhere(function ($session) use ($start, $end) {
            return $session['year_start'] == $start && $session['year_end'] == $end;
        })['session_id'] ?? null;
    }

    public function findBillByNumber($billNumber, $sessionId)
    {
        $response = Http::get("https://api.legiscan.com/", [
            'key' => $this->apiKey,
            'op' => 'getMasterList',
            'session_id' => $sessionId,
            'state' => $this->state,
        ]);

        if (!$response->ok()) {
            logger()->error('MasterList failed', ['status' => $response->status()]);
            return null;
        }

        $data = $response->json();
        logger()->info('MasterList fetched', ['session_id' => $sessionId, 'total_bills' => count($data['masterlist'] ?? [])]);

        $bills = $data['masterlist'] ?? [];
        unset($bills['session']);

        // Dump bill numbers for sanity check
        $billNumbers = collect($bills)->pluck('number')->map(fn($n) => str_replace(' ', '', strtoupper($n)));
        logger()->info('Available bills', $billNumbers->take(10)->toArray());

        $normalizedInput = strtoupper(preg_replace('/\s+/', '', $billNumber));
        logger()->info('Normalized input', ['input' => $normalizedInput]);

        return collect($bills)->first(function ($bill) use ($normalizedInput) {
            $billNum = strtoupper(preg_replace('/\s+/', '', $bill['number']));
            return $billNum === $normalizedInput;
        });
    }
}
