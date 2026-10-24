<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Kuesioner Kepuasan Pasien</x-slot>
        <x-slot name="description">
            Hasil kuesioner per layanan. Setiap layanan memiliki ringkasan sendiri.
        </x-slot>

        <div x-data="{ tab: '{{ $groups[0]['type'] ?? 'igd' }}' }">
            <div class="flex flex-wrap gap-2 mb-4" role="tablist">
                @foreach ($groups as $group)
                    <button
                        type="button"
                        role="tab"
                        class="rounded-full border px-4 py-1.5 text-sm font-medium"
                        :class="tab === '{{ $group['type'] }}' ? 'bg-amber-500 border-amber-500 text-white' : 'border-gray-300 text-gray-700 dark:text-gray-200'"
                        @click="tab = '{{ $group['type'] }}'"
                    >{{ $group['label'] }} ({{ $group['total'] }})</button>
                @endforeach
            </div>

            @foreach ($groups as $group)
                <div x-show="tab === '{{ $group['type'] }}'" x-cloak role="tabpanel" data-section="questionnaire-{{ $group['type'] }}">
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:1rem; margin-bottom:1rem;">
                        <div><p class="text-sm text-gray-500">Total pengisian</p><p class="text-2xl font-semibold">{{ $group['total'] }}</p></div>
                        <div><p class="text-sm text-gray-500">Tingkat kepuasan</p><p class="text-2xl font-semibold text-emerald-600">{{ $group['satisfaction'] }}%</p></div>
                    </div>

                    <p class="text-sm font-semibold mb-2">Berdasarkan kelompok usia</p>
                    @if (count($group['by_age']) === 0)
                        <p class="text-sm text-gray-500 mb-4">Belum ada pengisian.</p>
                    @else
                        <ul class="mb-4 text-sm list-disc pl-5">
                            @foreach ($group['by_age'] as $age => $count)
                                <li>{{ $age }}: {{ $count }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <p class="text-sm font-semibold mb-2">Pengisian terbaru</p>
                    @if (count($group['latest']) === 0)
                        <p class="text-sm text-gray-500">Belum ada pengisian.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="border-b">
                                    <tr>
                                        <th class="py-2 pr-4">Usia</th>
                                        <th class="py-2 pr-4">Jenis kelamin</th>
                                        <th class="py-2 pr-4">Cara bayar</th>
                                        <th class="py-2">Waktu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($group['latest'] as $row)
                                        <tr class="border-b">
                                            <td class="py-2 pr-4">{{ $row['age'] }}</td>
                                            <td class="py-2 pr-4">{{ $row['gender'] }}</td>
                                            <td class="py-2 pr-4">{{ $row['payment'] }}</td>
                                            <td class="py-2 whitespace-nowrap">{{ $row['submitted'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
