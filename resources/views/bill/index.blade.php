<x-layout>
    <div class="max-w-xl mx-auto mt-12 space-y-4">
        <h1 class="text-2xl font-bold">Bill Summarizer</h1>
        <form method="POST" action="{{ route('bill.analyze') }}" class="space-y-4">
            @csrf
            <input name="bill_number" placeholder="Bill Number (e.g. AB1234)" class="w-full p-2 border rounded">
            <select name="session_id" class="w-full p-2 border rounded">
                @foreach($sessions as $session)
                    <option value="{{ $session['session_id'] }}">
                        {{ $session['year_start'] }}–{{ $session['year_end'] }}
                    </option>
                @endforeach
            </select>
            <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Fetch & Summarize</button>
        </form>
    </div>
</x-layout>
