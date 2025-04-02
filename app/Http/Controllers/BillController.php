<?php

namespace App\Http\Controllers;

use App\Services\LegiScanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\PdfToText\Pdf;
use OpenAI\Laravel\Facades\OpenAI;

class BillController extends Controller
{
    public function index(LegiScanService $legiscan)
    {
        $sessions = $legiscan->getSessions();
        return view('bill.index', compact('sessions'));
    }

    public function analyze(Request $request, LegiScanService $legiscan)
    {
        $validated = $request->validate([
            'bill_number' => 'required|string',
            'session_id' => 'required|integer',
        ]);

        $billNumber = strtoupper(preg_replace('/\s+/', '', $validated['bill_number']));
        $sessionId = $validated['session_id'];

        $cacheKey = "bill_{$billNumber}_session_{$sessionId}";

        $result = Cache::remember($cacheKey, now()->addHours(6), function () use ($billNumber, $sessionId, $legiscan) {
            // Try to find the bill from the master list
            $billData = $legiscan->findBillByNumber($billNumber, $sessionId);

            if (!$billData) {
                return ['error' => 'Bill not found for the selected session.'];
            }

            $billId = $billData['bill_id'];

            // Fetch full bill data
            $billDetailsResponse = Http::get("https://api.legiscan.com/", [
                'key' => config('services.legiscan.key'),
                'op' => 'getBill',
                'id' => $billId,
            ]);

            if (!$billDetailsResponse->ok()) {
                return ['error' => 'Failed to retrieve full bill details.'];
            }

            $billDetails = $billDetailsResponse->json()['bill'];
            $billText = $billData['title'] . "\n\n" . ($billData['summary'] ?? '[No summary provided]');

            $billSummary = OpenAI::chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => 'Summarize this California bill in clear, concise language for a government finance team.'],
                    ['role' => 'user', 'content' => $billText],
                ],
            ])['choices'][0]['message']['content'];

            // Check for local PDF analysis
            $pdfPath = storage_path("app/analyses/{$billNumber}.pdf");
            $analysisSummary = null;

            if (file_exists($pdfPath)) {
                $pdfText = Pdf::getText($pdfPath);
                $analysisSummary = OpenAI::chat()->create([
                    'model' => 'gpt-4o',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Summarize this financial analysis document for an internal government audience.'],
                        ['role' => 'user', 'content' => $pdfText],
                    ],
                ])['choices'][0]['message']['content'];
            }

            return [
                'bill_summary' => $billSummary,
                'analysis_summary' => $analysisSummary,
                'link' => $billDetails['url'],
            ];
        });

        return view('bill.result', ['result' => $result]);
    }

    public function search(Request $request, LegiScanService $legiscan)
    {
        $query = $request->input('query');
        $sessionId = $request->input('session_id');

        if (!$query || !$sessionId) {
            return response()->json([]);
        }

        $results = $legiscan->searchBillsFromSession($query, $sessionId);

        foreach ($results as &$bill) {
            if (!isset($bill['number'])) {
                \Log::warning('Missing bill number in result:', $bill);
                $bill['has_analysis'] = false;
                continue;
            }

            $billNumber = strtoupper(preg_replace('/\s+/', '', $bill['number']));
            $pdfPath = storage_path("app/analyses/{$billNumber}.pdf");

            $bill['has_analysis'] = file_exists($pdfPath);

            if ($bill['has_analysis']) {
                \Log::info("Found analysis for: {$billNumber}");
            }
        }

        return response()->json($results);
    }
}
