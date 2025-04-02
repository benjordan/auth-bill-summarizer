<x-layout>
    <div class="max-w-7xl mx-auto py-8 grid grid-cols-1 md:grid-cols-3 gap-8">
        <div class="col-span-1 space-y-4">
            <h1 class="text-2xl font-bold">{{ $bill['bill_number'] }}</h1>
            <p><strong>Title:</strong> {{ $bill['title'] }}</p>
            <p class="text-gray-800 mt-2"><strong>Summary:</strong> {{ $bill['description'] }}</p>
            <p><strong>Status:</strong> {{ $bill['status'] }}</p>
            <p><strong>Session:</strong> {{ $bill['session']['name'] ?? 'N/A' }}</p>
            <p><strong>Introduced:</strong> {{ $bill['introduced_date'] ?? 'N/A' }}</p>
            <p><strong>Last Action:</strong> {{ $bill['last_action'] ?? 'N/A' }} on {{ $bill['last_action_date'] ?? 'N/A' }}</p>

            @if(!empty($bill['sponsors']))
                <div>
                    <h2 class="font-semibold">Sponsors</h2>
                    <ul class="list-disc list-inside">
                        @foreach($bill['sponsors'] as $sponsor)
                            <li>{{ $sponsor['name'] }} ({{ $sponsor['party'] }})</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(!empty($bill['subjects']))
                <div>
                    <h2 class="font-semibold">Subjects</h2>
                    <ul class="list-disc list-inside">
                        @foreach($bill['subjects'] as $subject)
                            <li>{{ $subject }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div>
                <a href="https://legiscan.com/CA/bill/{{ $bill['bill_number'] }}/{{ $bill['session']['year_start'] }}" target="_blank" class="text-blue-600 hover:underline">View on LegiScan</a>
            </div>
        </div>

        <div class="col-span-2 space-y-6">
            <div>

                <h2 class="text-xl font-bold">Bill Summary</h2>
                <div class="prose max-w-none bg-gray-100 p-4 rounded">
                    {!! \Illuminate\Support\Str::markdown($bill_summary) !!}
                </div>

                <h2 class="text-xl font-bold">Previous Analysis</h2>
                @if($analysis_summary)
                    <p class="bg-gray-100 p-4 rounded">{{ $analysis_summary }}</p>
                @else
                    <p class="italic text-gray-500">No previous analysis found.</p>
                @endif

                @if(!empty($bill['summaries']))
                    <div class="mt-4">
                        <h3 class="font-semibold">Additional Summaries</h3>
                        <ul class="list-disc list-inside mt-2 space-y-2">
                            @foreach($bill['summaries'] as $summary)
                                <li>
                                    <div class="text-sm text-gray-500">{{ $summary['date'] }} - {{ $summary['type'] }}</div>
                                    <div class="text-gray-800">{{ $summary['text'] }}</div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            @if(!empty($bill['history']))
                <div>
                    <h2 class="text-xl font-semibold">History</h2>
                    <table class="w-full text-left border mt-2">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="p-2">Date</th>
                                <th class="p-2">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bill['history'] as $event)
                                <tr>
                                    <td class="p-2 border-t">{{ $event['date'] }}</td>
                                    <td class="p-2 border-t">{{ $event['action'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if(!empty($bill['roll_calls']))
                <div>
                    <h2 class="text-xl font-semibold">Roll Calls</h2>
                    <table class="w-full text-left border mt-2">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="p-2">Date</th>
                                <th class="p-2">Motion</th>
                                <th class="p-2">Yea</th>
                                <th class="p-2">Nay</th>
                                <th class="p-2">Absent</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bill['roll_calls'] as $call)
                                <tr>
                                    <td class="p-2 border-t">{{ $call['date'] }}</td>
                                    <td class="p-2 border-t">{{ $call['motion'] }}</td>
                                    <td class="p-2 border-t">{{ $call['yea'] }}</td>
                                    <td class="p-2 border-t">{{ $call['nay'] }}</td>
                                    <td class="p-2 border-t">{{ $call['absent'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-layout>
