<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Kritik & Saran Pasien</x-slot>
        <x-slot name="description">
            Ringkasan dari formulir kritik dan saran di website. Nama dan nomor telepon pasien tidak ditampilkan di sini.
        </x-slot>

        <p class="mb-4"><a class="underline" href="{{ \App\Filament\Resources\Feedbacks\FeedbackResource::getUrl('index') }}">Lihat seluruh kritik dan saran</a></p>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:1rem; margin-bottom:1rem;">
            <div><p class="text-sm text-gray-500">Total</p><p class="text-2xl font-semibold">{{ $summary['total'] }}</p></div>
            <div><p class="text-sm text-gray-500">Baru</p><p class="text-2xl font-semibold text-amber-600">{{ $summary['baru'] }}</p></div>
            <div><p class="text-sm text-gray-500">Diproses</p><p class="text-2xl font-semibold">{{ $summary['diproses'] }}</p></div>
            <div><p class="text-sm text-gray-500">Selesai</p><p class="text-2xl font-semibold text-emerald-600">{{ $summary['selesai'] }}</p></div>
        </div>

        @if (count($latest) === 0)
            <p class="text-sm text-gray-500">Belum ada kritik dan saran masuk.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b">
                        <tr>
                            <th class="py-2 pr-4">Tiket</th>
                            <th class="py-2 pr-4">Kategori</th>
                            <th class="py-2 pr-4">Keluhan</th>
                            <th class="py-2 pr-4">Status</th>
                            <th class="py-2">Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($latest as $row)
                            <tr class="border-b align-top">
                                <td class="py-2 pr-4 font-mono"><a class="underline" href="{{ $row['url'] }}">{{ $row['ticket'] }}</a></td>
                                <td class="py-2 pr-4">{{ $row['categories'] }}</td>
                                <td class="py-2 pr-4 max-w-md">{{ \Illuminate\Support\Str::limit($row['detail'], 140) }}</td>
                                <td class="py-2 pr-4">{{ ucfirst($row['status']) }}</td>
                                <td class="py-2 whitespace-nowrap">{{ $row['submitted'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
