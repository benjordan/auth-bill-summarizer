<x-layout>
    <div class="max-w-xl mx-auto mt-12 space-y-4">
        <h1 class="text-2xl font-bold">Bill Summarizer</h1>
        <form method="POST" action="{{ route('bill.analyze') }}" class="space-y-4">
            @csrf
            <div x-data="billSearch()" class="relative">
                <input
                    x-model="query"
                    @input.debounce.300="search"
                    @keydown.arrow-down.prevent="highlightNext()"
                    @keydown.arrow-up.prevent="highlightPrev()"
                    @keydown.enter.prevent="selectHighlighted()"
                    @click.away="results = []; highlighted = -1"
                    name="bill_number"
                    placeholder="Bill Number (e.g. AB1234)"
                    class="w-full p-2 border rounded"
                    autocomplete="off"
                >

                <div x-show="loading" class="absolute right-2 top-2">
                    <img src="/img/spinner.gif" alt="Loading..." class="h-5 w-5">
                </div>

                <ul
                    x-show="results.length"
                    class="absolute z-10 bg-white border w-full rounded shadow mt-1 max-h-60 overflow-y-auto"
                >
                    <template x-for="(bill, index) in results" :key="bill.bill_id">
                        <li
                            @click="select(bill)"
                            :class="{'bg-gray-100': highlighted === index}"
                            class="p-2 hover:bg-gray-100 cursor-pointer flex items-center justify-between"
                        >
                            <span x-text="bill.number + ' - ' + bill.title"></span>

                            <template x-if="bill.has_analysis">
                                <span title="You've already analyzed this one" class="ml-2 text-green-500">
                                    📜
                                </span>
                            </template>
                        </li>
                    </template>
                </ul>
            </div>

            <select name="session_id" class="w-full p-2 border rounded">
                @foreach($sessions as $session)
                    <option value="{{ $session['session_id'] }}">
                        {{ $session['year_start'] }}&ndash;{{ $session['year_end'] }}
                    </option>
                @endforeach
            </select>

            <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                Fetch & Summarize
            </button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

    <script>
        function billSearch() {
            return {
                query: '',
                results: [],
                highlighted: -1,
                loading: false,
                search() {
                    if (this.query.length < 2) {
                        this.results = [];
                        return;
                    }
                    this.loading = true;
                    const sessionId = document.querySelector('select[name=\"session_id\"]').value;
                    fetch(`/api/search-bills?query=${encodeURIComponent(this.query)}&session_id=${sessionId}`)
                        .then(res => res.json())
                        .then(data => {
                            this.results = data;
                            this.highlighted = -1;
                        })
                        .finally(() => {
                            this.loading = false;
                        });
                },
                select(bill) {
                    this.results = [];
                    this.query = bill.number;
                    this.highlighted = -1;

                    setTimeout(() => {
                        window.location.href = `/bill/${bill.bill_id}`;
                    }, 100);
                },
                highlightNext() {
                    if (this.highlighted < this.results.length - 1) this.highlighted++;
                },
                highlightPrev() {
                    if (this.highlighted > 0) this.highlighted--;
                },
                selectHighlighted() {
                    if (this.highlighted >= 0 && this.results.length > 0) {
                        this.select(this.results[this.highlighted]);
                    }
                }
            }
        }
    </script>
</x-layout>
