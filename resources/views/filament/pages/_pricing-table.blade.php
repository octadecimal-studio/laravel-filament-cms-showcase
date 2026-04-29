@props(['motorcycles'])

{{-- KML-0049: tabela cennika z inline CSS — Tailwind JIT na TST nie indeksuje
     klas z tego partiala (Filament uzywa precompiled bundle). Skalowanie utility
     class wymagaloby custom theme + npm build, dlatego stosujemy scoped <style>. --}}
<style>
    .pricing-table-wrapper {
        overflow-x: auto;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.5rem;
    }

    .dark .pricing-table-wrapper {
        border-color: rgb(55 65 81);
    }

    .pricing-table {
        width: 100%;
        font-size: 0.875rem;
        line-height: 1.25rem;
        color: rgb(17 24 39);
        border-collapse: collapse;
    }

    .dark .pricing-table {
        color: rgb(243 244 246);
    }

    .pricing-table thead {
        background-color: rgb(249 250 251);
    }

    .dark .pricing-table thead {
        background-color: rgb(31 41 55);
    }

    .pricing-table th {
        padding: 0.5rem 0.75rem;
        font-weight: 600;
        text-align: left;
    }

    .pricing-table th.is-numeric {
        text-align: right;
    }

    .pricing-table tbody tr {
        border-top: 1px solid rgb(229 231 235);
    }

    .dark .pricing-table tbody tr {
        border-top-color: rgb(55 65 81);
    }

    .pricing-table td {
        padding: 0.5rem 0.75rem;
    }

    .pricing-table td.is-name {
        font-weight: 500;
    }

    .pricing-table td.is-numeric {
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .pricing-empty {
        font-size: 0.875rem;
        color: rgb(107 114 128);
    }

    .dark .pricing-empty {
        color: rgb(156 163 175);
    }
</style>

@if($motorcycles->isEmpty())
    <p class="pricing-empty">Brak opublikowanych motocykli.</p>
@else
    <div class="pricing-table-wrapper">
        <table class="pricing-table">
            <thead>
                <tr>
                    <th>Motocykl</th>
                    <th class="is-numeric">Dzień</th>
                    <th class="is-numeric">Tydzień</th>
                    <th class="is-numeric">Miesiąc</th>
                    <th class="is-numeric">Kaucja</th>
                </tr>
            </thead>
            <tbody>
                @foreach($motorcycles as $moto)
                    <tr>
                        <td class="is-name">{{ $moto->name }}</td>
                        <td class="is-numeric">{{ number_format((float) $moto->price_per_day, 2, ',', ' ') }} zł</td>
                        <td class="is-numeric">{{ number_format((float) $moto->price_per_week, 2, ',', ' ') }} zł</td>
                        <td class="is-numeric">{{ number_format((float) $moto->price_per_month, 2, ',', ' ') }} zł</td>
                        <td class="is-numeric">{{ number_format((float) $moto->deposit, 2, ',', ' ') }} zł</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
