<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;

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
            'id'  => $sessionId, // not 'session_id' because... why be consistent?
            'state' => $this->state,
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

    public function getBillDetails($billId)
    {
        $cacheKey = "legiscan_bill_{$billId}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($billId) {
            $response = Http::get("https://api.legiscan.com/", [
                'key' => $this->apiKey,
                'op' => 'getBill',
                'id' => $billId,
            ]);

            if (!$response->ok()) {
                logger()->error('getBill failed', ['status' => $response->status()]);
                return null;
            }

            $data = $response->json();
            $bill = $data['bill'] ?? null;

            if (!$bill || !is_array($bill)) {
                logger()->warning('Bill details missing or malformed', ['bill_id' => $billId]);
                return null;
            }

            return $bill;
        });
    }

    public function getBillSummary(string $billId, string $title, ?string $summary): string
    {
        $cacheKey = "bill_summary_{$billId}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($title, $summary) {
            $billText = $title . "\n\n" . ($summary ?? '[No summary provided]');

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => 'Write a one-paragraph summary of this California bill.
                        Your summary must:
                            •	Begin with “This bill…”
                            •	Clearly and factually describe what the bill requires, authorizes, or prohibits
                            •	Identify any state or local agencies, programs, or stakeholder groups responsible for implementation
                            •	Include any deadlines, operative dates, or reporting requirements
                            •	Mention if the bill contains an urgency clause or effective date
                        Do not include:
                            •	Fiscal analysis or cost estimates
                            •	Opinions, justifications, or policy commentary
                            •	References to Legislative Counsel digests
                        Stick to a neutral, objective tone. The goal is to summarize only the substantive actions or directives in the bill.
                        Also, Analyze the bill and extract the key components in bullet format to support drafting a formal bill summary.
                        Organize the information under the following labeled sections:
                            •	What the Bill Does:
                        Bullet out the major requirements, authorizations, prohibitions, or procedural changes introduced by the bill. Focus on what the bill does, not why it does it.
                            •	Stakeholders / Implementing Agencies:
                        List any agencies, departments, boards, commissions, or stakeholder groups responsible for or affected by the bill.
                            •	Key Dates and Deadlines:
                        Note any operative dates, implementation deadlines, report due dates, or annual election/notice periods.
                            •	Procedural Notes (if applicable):
                        Call out anything the analyst should watch for—such as references to existing law, interplay with other bills, or conditions for activation (e.g., election by a manufacturer or opt-in clauses).
                            •	Urgency Clause / Effective Date:
                        If the bill includes language that makes it take effect immediately, or specifies an exact effective date, record it here.
                        :warning: Do not include fiscal impacts, policy analysis, or Legislative Counsel digest references.
                        '],
                    ['role' => 'user', 'content' => $billText],
                ],
            ]);

            return $response['choices'][0]['message']['content'] ?? '[Failed to summarize]';
        });
    }

    public function getAnalysisSummary(string $billNumber, string $pdfText): ?string
    {
        $cacheKey = "analysis_summary_{$billNumber}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($pdfText) {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => 'Summarize this financial analysis document for an internal government audience.'],
                    ['role' => 'user', 'content' => $pdfText],
                ],
            ]);

            return $response['choices'][0]['message']['content'] ?? null;
        });
    }
}
