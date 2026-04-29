@props(['motorcycles'])

@if($motorcycles->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">Brak opublikowanych motocykli.</p>
@else
    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm text-gray-900 dark:text-gray-100">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="text-left p-2 font-semibold">Motocykl</th>
                    <th class="text-right p-2 font-semibold">Dzień</th>
                    <th class="text-right p-2 font-semibold">Tydzień</th>
                    <th class="text-right p-2 font-semibold">Miesiąc</th>
                    <th class="text-right p-2 font-semibold">Kaucja</th>
                </tr>
            </thead>
            <tbody>
                @foreach($motorcycles as $moto)
                    <tr class="border-t border-gray-200 dark:border-gray-700">
                        <td class="p-2 font-medium">{{ $moto->name }}</td>
                        <td class="p-2 text-right tabular-nums">{{ number_format((float) $moto->price_per_day, 2, ',', ' ') }} zł</td>
                        <td class="p-2 text-right tabular-nums">{{ number_format((float) $moto->price_per_week, 2, ',', ' ') }} zł</td>
                        <td class="p-2 text-right tabular-nums">{{ number_format((float) $moto->price_per_month, 2, ',', ' ') }} zł</td>
                        <td class="p-2 text-right tabular-nums">{{ number_format((float) $moto->deposit, 2, ',', ' ') }} zł</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
