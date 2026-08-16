<x-layouts.dashboard title="Support Tickets" heading="Support Tickets">
    <x-slot:actions><x-button :href="route('dashboard.tickets.create')">+ New Ticket</x-button></x-slot:actions>
    @if($tickets->isEmpty())
        <x-empty-state icon="🎧" title="No support tickets">Need help? Open a ticket and our team will respond shortly.</x-empty-state>
    @else
        <div class="table-wrap d-none d-sm-block">
            <table class="table">
                <thead><tr><th>#</th><th>Subject</th><th>Priority</th><th>Status</th><th>Last updated</th><th></th></tr></thead>
                <tbody>
                @foreach($tickets as $t)
                    <tr>
                        <td class="text-muted">#{{ $t->id }}</td>
                        <td class="fw-medium">{{ $t->subject }}</td>
                        <td><span class="badge-{{ $t->priority==='high'?'rose':($t->priority==='medium'?'amber':'slate') }}">{{ ucfirst($t->priority) }}</span></td>
                        <td><x-status-badge :status="$t->status" /></td>
                        <td class="text-muted fs-xs">{{ $t->updated_at->diffForHumans() }}</td>
                        <td><a href="{{ route('dashboard.tickets.show',$t) }}" class="btn-ghost btn-sm">View</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="d-sm-none vstack gap-2">
        @foreach($tickets as $t)
            <a href="{{ route('dashboard.tickets.show',$t) }}" class="card-p d-block">
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <p class="fw-semibold text-dark">#{{ $t->id }} · {{ $t->subject }}</p>
                    <x-status-badge :status="$t->status" />
                </div>
                <p class="fs-xs text-muted mt-1">{{ $t->updated_at->diffForHumans() }}</p>
            </a>
        @endforeach
        </div>
    @endif
</x-layouts.dashboard>
