<div class="space-y-4">
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.delete-job-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const url = this.getAttribute('data-url');
                    const message = this.getAttribute('data-message');
                    
                    if (confirm(message)) {
                        fetch(url, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            }
                        }).then(response => response.json()).then(data => {
                            if (data.success) {
                                window.location.reload();
                            } else {
                                alert('Błąd: ' + (data.error || 'Nie udało się usunąć joba'));
                            }
                        }).catch(err => {
                            console.error(err);
                            alert('Błąd podczas usuwania joba');
                        });
                    }
                });
            });
        });
    </script>
    @if($jobs->isEmpty())
        <p class="text-gray-500 text-center py-8">Brak aktywnych jobów analizy szablonów.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Kolejka</th>
                        <th class="px-4 py-3">Próby</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Utworzono</th>
                        <th class="px-4 py-3">Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($jobs as $job)
                        <tr class="bg-white border-b hover:bg-gray-50">
                            <td class="px-4 py-3">{{ $job->id }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs font-semibold rounded bg-blue-100 text-blue-800">
                                    {{ $job->queue }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs font-semibold rounded {{ $job->attempts > 1 ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' }}">
                                    {{ $job->attempts }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs font-semibold rounded {{ $job->reserved_at ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $job->reserved_at ? 'W trakcie' : 'Oczekuje' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                {{ $job->created_at ? \Carbon\Carbon::createFromTimestamp($job->created_at)->format('d.m.Y H:i:s') : '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    @if($job->reserved_at)
                                        <button type="button" 
                                                data-action="stop"
                                                data-job-id="{{ $job->id }}"
                                                data-url="{{ route('widgets.template-jobs.delete', $job->id) }}"
                                                data-message="Czy na pewno chcesz przerwać ten job?"
                                                class="px-2 py-1 text-xs font-semibold rounded bg-red-100 text-red-800 hover:bg-red-200 cursor-pointer delete-job-btn">
                                            Stop
                                        </button>
                                    @else
                                        <button type="button" 
                                                data-action="delete"
                                                data-job-id="{{ $job->id }}"
                                                data-url="{{ route('widgets.template-jobs.delete', $job->id) }}"
                                                data-message="Czy na pewno chcesz usunąć ten job z kolejki?"
                                                class="px-2 py-1 text-xs font-semibold rounded bg-red-100 text-red-800 hover:bg-red-200 cursor-pointer delete-job-btn">
                                            Usuń
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
