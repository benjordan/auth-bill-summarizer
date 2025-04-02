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
            'state'  => $this->state,
        ]);

        if (!$response->ok()) {
            logger()->error('MasterList failed', ['status' => $response->status()]);
            return null;
        }

        $data = $response->json();
        logger()->info('MasterList fetched', ['session_id' => $sessionId, 'total_bills' => count($data['masterlist'] ?? [])]);

        $bills = $data['masterlist'] ?? [];
        unset($bills['session']);

        return collect($bills)->first(function ($bill) use ($billNumber) {
            $input = strtoupper(preg_replace('/\s+/', '', $billNumber));
            $target = strtoupper(preg_replace('/\s+/', '', $bill['number']));

            return $input === $target;
        });
    }

    public function searchBillsFromSession($query, $sessionId)
    {
        logger()->info('Searching bills via MasterList', [
            'query' => $query,
            'session_id' => $sessionId
        ]);

        $response = Http::get("https://api.legiscan.com/", [
            'key' => $this->apiKey,
            'op' => 'getMasterList',
            'id' => $sessionId,
            'state'  => $this->state,
        ]);

        if (!$response->ok()) {
            logger()->error('MasterList failed in searchBillsFromSession', ['status' => $response->status()]);
            return [];
        }

        $data = $response->json();
        $bills = $data['masterlist'] ?? [];
        unset($bills['session']);

        $queryUpper = strtoupper(preg_replace('/\s+/', '', $query));

        return collect($bills)->filter(function ($bill) use ($queryUpper) {
            if (!isset($bill['number'], $bill['bill_id'], $bill['title'])) {
                return false;
            }

            $billNumber = strtoupper(preg_replace('/\s+/', '', $bill['number']));
            return str_starts_with($billNumber, $queryUpper);
        })->map(fn($bill) => [
            'bill_id' => $bill['bill_id'],
            'number' => $bill['number'],
            'title' => $bill['title'],
        ])->take(10)->values()->all();
    }
}
