<x-layouts.admin :title="'Ticket '.$ticket->reference" heading="Support Ticket">
    <x-breadcrumb :items="[['label'=>'Tickets','url'=>route('admin.tickets')],['label'=>$ticket->reference]]" />
    <div class="grid-cols-[1fr_20rem] gap-4">
        {{-- Main thread --}}
        <div class="vstack gap-3">
            <x-card>
                <h2 class="fw-semibold text-dark mb-1">{{ $ticket->subject }}</h2>
                <div class="d-flex flex-wrap align-items-center gap-2 fs-sm text-muted">
                    <span>{{ $ticket->user->name }}</span>·
                    <span>{{ $ticket->category }}</span>·
                    <span class="badge-{{ $ticket->priorityColor() }} text-xs">{{ ucfirst($ticket->priority) }}</span>·
                    <x-status-badge :status="$ticket->status->value" />
                </div>
            </x-card>

            <div class="vstack gap-2">
                @foreach($ticket->messages as $msg)
                    <div class="card-p {{ $msg->is_internal_note ? 'bg-amber-50 ring-1 ring-amber-200' : ($msg->is_staff_reply ? 'bg-brand-50 ring-1 ring-brand-100' : '') }}">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="grid h-7 w-7 place-items-center rounded-full text-xs font-bold
                                    {{ $msg->is_staff_reply ? 'bg-brand-600 text-white' : 'bg-slate-200 text-slate-700' }}">
                                    {{ strtoupper(substr($msg->user->name,0,1)) }}
                                </span>
                                <span class="text-sm font-medium {{ $msg->is_staff_reply ? 'text-brand-800' : 'text-slate-700' }}">
                                    {{ $msg->user->name }}
                                    @if($msg->is_staff_reply)<span class="badge-brand ms-1">Staff</span>@endif
                                    @if($msg->is_internal_note ?? false)<span class="badge-amber ms-1">INTERNAL</span>@endif
                                </span>
                            </div>
                            <span class="fs-xs text-secondary">{{ $msg->created_at->format('d M Y, H:i') }}</span>
                        </div>
                        <p class="fs-sm text-dark">{{ $msg->body }}</p>
                        @if($msg->hasAttachment())<p class="fs-xs text-primary mt-2">📎 {{ $msg->attachment_name }}</p>@endif
                    </div>
                @endforeach
            </div>

            {{-- Staff reply --}}
            <x-card>
                <h2 class="section-title mb-2">Staff Reply</h2>
                <form method="POST" action="{{ route('admin.tickets.reply',$ticket) }}" class="vstack gap-2">
                    @csrf
                    <textarea name="body" rows="4" class="textarea" required placeholder="Type your reply to the user…"></textarea>
                    <input type="file" name="attachment" class="input fs-sm" accept=".jpg,.jpeg,.png,.pdf,.txt">
                    <x-button type="submit" variant="brand">Send reply to user</x-button>
            </form>

            <form method="POST" action="{{ route('admin.tickets.note',$ticket) }}" class="mt-3 pt-3 border-top border-light vstack gap-2">
                @csrf
                <p class="fs-sm fw-semibold text-dark">🔒 Internal Note (staff only — user cannot see this)</p>
                <textarea name="body" rows="3" class="textarea fs-sm" placeholder="Internal notes, escalation info, finance queries…" required></textarea>
                <x-button type="submit" variant="outline" size="sm">Add internal note</x-button>
                </form>
            </x-card>
        </div>

        {{-- Sidebar controls --}}
        <div class="vstack gap-3">
            {{-- Assign --}}
            <x-card>
                <h2 class="section-title mb-2">Assignment</h2>
                <form method="POST" action="{{ route('admin.tickets.assign',$ticket) }}" class="vstack gap-2">
                    @csrf
                    <select name="assigned_to" class="select fs-sm">
                        <option value="">Unassigned</option>
                        @foreach($staffList as $s)
                            <option value="{{ $s->id }}" @selected($ticket->assigned_to===$s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                    <x-button type="submit" variant="outline" class="w-100" size="sm">Update assignment</x-button>
                </form>
            </x-card>

            {{-- Status --}}
            <x-card>
                <h2 class="section-title mb-2">Status</h2>
                <form method="POST" action="{{ route('admin.tickets.status',$ticket) }}" class="vstack gap-2">
                    @csrf
                    @method('PATCH')
                    <select name="status" class="select fs-sm">
                        @foreach(['open','in_progress','waiting_for_user','resolved','closed'] as $s)
                            <option value="{{ $s }}" @selected($ticket->status->value===$s)>{{ ucwords(str_replace('_',' ',$s)) }}</option>
                        @endforeach
                    </select>
                    <input name="reason" placeholder="Reason (optional)" class="input fs-sm">
                    <x-button type="submit" variant="outline" class="w-100" size="sm">Change status</x-button>
                </form>
            </x-card>

            {{-- Priority --}}
            <x-card>
                <h2 class="section-title mb-2">Priority</h2>
                <form method="POST" action="{{ route('admin.tickets.priority',$ticket) }}" class="vstack gap-2">
                    @csrf
                    @method('PATCH')
                    <select name="priority" class="select fs-sm">
                        @foreach(['low','normal','high','urgent'] as $p)
                            <option value="{{ $p }}" @selected($ticket->priority===$p)>{{ ucfirst($p) }}</option>
                        @endforeach
                    </select>
                    <x-button type="submit" variant="outline" class="w-100" size="sm">Set priority</x-button>
                </form>
            </x-card>

            {{-- User info --}}
            <x-card>
                <h2 class="section-title mb-2">User</h2>
                <p class="fs-sm fw-semibold text-dark">{{ $ticket->user->name }}</p>
                <p class="fs-xs text-muted">{{ $ticket->user->email }}</p>
                <a href="{{ route('admin.users.show',$ticket->user) }}" class="btn-ghost btn-sm mt-2 d-inline-block">View user →</a>
            </x-card>
        </div>
    </div>
</x-layouts.admin>
