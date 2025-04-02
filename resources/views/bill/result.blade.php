<x-layout>
    <div class="max-w-3xl mx-auto mt-10 space-y-6">
        <a href="/" class="text-blue-500">&larr; Back</a>

        @if(isset($result['error']))
            <p class="text-red-600">{{ $result['error'] }}</p>
        @else
            <div class="space-y-4">
                <h2 class="text-xl font-bold">Bill Summary</h2>
                <p class="bg-gray-100 p-4 rounded">{{ $result['bill_summary'] }}</p>

                <h2 class="text-xl font-bold">Previous Analysis</h2>
                @if($result['analysis_summary'])
                    <p class="bg-gray-100 p-4 rounded">{{ $result['analysis_summary'] }}</p>
                @else
                    <p class="italic text-gray-500">No previous analysis found.</p>
                @endif

                <a href="{{ $result['link'] }}" target="_blank" class="text-blue-600 underline">View Full Bill on LegiScan</a>
            </div>
        @endif
    </div>
</x-layout>
