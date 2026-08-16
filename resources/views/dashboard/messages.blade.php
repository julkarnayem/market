<x-layouts.dashboard title="Messages" heading="Messages">
<div class="grid-cols-[22rem_1fr] gap-0 rounded-4 overflow-hidden shadow-sm"
     x-data="chatApp({{ $selectedConversation?->id ?? 'null' }}, {{ $isRealtimeReady ? 'true' : 'false' }})">

    {{-- Conversation List --}}
    <div class="bg-white border-r border-slate-200 flex flex-col {{ $selectedConversation ? 'hidden lg:flex' : 'flex' }}">
        <div class="px-3 py-2 border-bottom border-light">
            <h2 class="fw-semibold text-dark fs-sm">Order Messages</h2>
        </div>
        <div class="flex-grow-1 overflow-y-auto divide-y">
            @forelse($conversations as $conv)
                @php
                    $other   = $conv->participants->first();
                    $unread  = $unreadMap[$conv->id] ?? 0;
                    $asset   = $conv->order?->asset;
                    $isActive= $selectedConversation?->id === $conv->id;
                @endphp
                <a href="{{ route('dashboard.messages',['conversation'=>$conv->id]) }}"
                   class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors {{ $isActive ? 'bg-brand-50' : '' }}">
                    <span class="h-10 w-10 flex-shrink-0 d-grid place-items-center rounded-pill bg-primary bg-opacity-25 text-primary fw-semibold fs-sm">
                        {{ strtoupper(substr($other?->name ?? '?', 0, 1)) }}
                    </span>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center justify-content-between gap-1">
                            <p class="fs-sm fw-semibold text-dark text-truncate">{{ $other?->name ?? 'Unknown' }}</p>
                            <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                @if($unread > 0)
                                    <span class="h-5 min-w-[20px] d-grid place-items-center rounded-pill bg-primary fw-bold text-white px-1">{{ $unread }}</span>
                                @endif
                                <span class="text-secondary">{{ $conv->last_message_at?->diffForHumans(null,true) }}</span>
                            </div>
                        </div>
                        <p class="fs-xs text-muted text-truncate">{{ $asset?->title ?? 'Order #'.$conv->order_id }}</p>
                        <span class="badge-{{ $conv->order?->status?->value === 'completed' ? 'mint' : 'slate' }} text-[10px] mt-0.5 inline-flex">
                            {{ ucwords(str_replace('_',' ',$conv->order?->status?->value ?? '')) }}
                        </span>
                    </div>
                </a>
            @empty
                <div class="d-flex flex-column align-items-center justify-content-center py-5 text-center px-3">
                    <span class="fs-2 mb-2">✉️</span>
                    <p class="fs-sm fw-semibold text-dark">No messages yet</p>
                    <p class="fs-xs text-secondary mt-1">Messages appear here when orders are created.</p>
                </div>
            @endforelse
        </div>
        {{ $conversations->links() }}
    </div>

    {{-- Message area --}}
    <div class="bg-slate-50 flex flex-col {{ !$selectedConversation ? 'hidden lg:flex' : '' }}">
        @if($selectedConversation)
            @php
                $order   = $selectedConversation->order;
                $isbuyer = $order?->buyer_user_id === auth()->id();
                $other   = $selectedConversation->participants->firstWhere('id', '!=', auth()->id());
            @endphp
            {{-- Order context header --}}
            <div class="bg-white border-bottom border-secondary border-opacity-25 px-3 py-2 d-flex align-items-center gap-3">
                <a href="{{ route('dashboard.messages') }}" class="d-lg-none btn-ghost btn-sm p-1">←</a>
                <span class="h-9 w-9 d-grid place-items-center rounded-pill bg-primary bg-opacity-25 text-primary fw-bold fs-sm flex-shrink-0">
                    {{ strtoupper(substr($other?->name ?? '?', 0, 1)) }}
                </span>
                <div class="flex-grow-1">
                    <p class="fw-semibold text-dark fs-sm text-truncate">{{ $other?->name ?? 'Unknown' }}</p>
                    <p class="fs-xs text-muted text-truncate">{{ $order?->asset?->title ?? '' }} · {{ $order?->order_number }}</p>
                </div>
                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                    <x-status-badge :status="$order?->status?->value ?? 'unknown'" />
                    <a href="{{ route('dashboard.orders.show', $order) }}" class="btn-ghost btn-sm fs-xs">View order →</a>
                </div>
            </div>

            {{-- Messages --}}
            <div class="flex-grow-1 overflow-y-auto p-3 vstack gap-2" id="msg-area"
                 @if(!$isRealtimeReady) x-init="startPolling({{ $selectedConversation->id }})" @endif>

                @foreach($messages as $msg)
                    @php $mine = $msg->sender_user_id === auth()->id(); @endphp
                    <div class="flex {{ $mine ? 'justify-end' : 'items-end gap-2' }}" id="msg-{{ $msg->id }}">
                        @if(!$mine)
                            <span class="h-7 w-7 flex-shrink-0 d-grid place-items-center rounded-pill bg-slate-300 text-dark fs-xs fw-bold mb-1">
                                {{ strtoupper(substr($msg->sender?->name ?? '?', 0, 1)) }}
                            </span>
                        @endif
                        <div class="max-w-[75%]">
                            @if($msg->is_system)
                                <div class="text-center my-1">
                                    <span class="bg-secondary bg-opacity-25 text-muted fs-xs px-2 py-1 rounded-pill">{{ e($msg->body) }}</span>
                                </div>
                            @else
                                @if($msg->trashed())
                                    <div class="rounded-4 px-3 py-2 bg-light text-secondary fs-sm fst-italic">This message was deleted.</div>
                                @else
                                    @if($msg->replyTo)
                                        <div class="rounded-t-xl bg-slate-200/70 px-2 py-1 fs-xs text-muted mb-1">
                                            <span class="fw-semibold">{{ $msg->replyTo->sender?->name }}</span>: {{ Str::limit(e($msg->replyTo->body), 80) }}
                                        </div>
                                    @endif
                                    <div class="rounded-2xl px-4 py-2.5 {{ $mine ? 'bg-brand-600 text-white rounded-tr-sm' : 'bg-white text-slate-800 shadow-sm rounded-tl-sm' }}">
                                        <p class="fs-sm">{{ e($msg->body) }}</p>
                                        @if($msg->hasAttachment())
                                            <a href="{{ route('messages.attachment', $msg) }}" class="flex items-center gap-1.5 mt-2 text-xs {{ $mine ? 'text-brand-100' : 'text-brand-700' }} hover:underline">
                                                📎 {{ $msg->attachment_name ?? 'Attachment' }}
                                            </a>
                                        @endif
                                        <div class="d-flex align-items-center justify-content-between gap-3 mt-1">
                                            <span class="">{{ $msg->created_at->format('H:i') }}</span>
                                            @if(!$mine)
                                                <button onclick="reportMsg({{ $msg->id }})" class="">Report</button>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach

                {{-- Live messages injected here by Alpine/polling --}}
                <template x-for="msg in liveMessages" :key="msg.id">
                    <div :class="msg.sender_id === {{ auth()->id() }} ? 'flex justify-end' : 'flex items-end gap-2'">
                        <div :class="msg.sender_id === {{ auth()->id() }} ? 'max-w-[75%] bg-brand-600 text-white rounded-2xl rounded-tr-sm px-4 py-2.5' : 'max-w-[75%] bg-white text-slate-800 shadow-sm rounded-2xl rounded-tl-sm px-4 py-2.5'">
                            <p class="fs-sm" x-text="msg.body"></p>
                            <span class="d-block mt-1" x-text="msg.time_label"></span>
                        </div>
                    </div>
                </template>

                <div id="msg-bottom"></div>
            </div>

            {{-- Composer --}}
            <div class="bg-white border-top border-secondary border-opacity-25 px-3 py-2">
                <form id="msg-form" method="POST" action="{{ route('dashboard.messages.send', $selectedConversation) }}"
                      enctype="multipart/form-data" class="d-flex gap-2 align-items-end"
                      x-on:submit="onSubmit">
                    @csrf
                    <input type="hidden" name="client_message_id" id="cmid" value="">
                    <div class="flex-grow-1">
                        <textarea name="body" rows="1" id="msg-input"
                            placeholder="Type a message…"
                            class="w-100 rounded-3 border border-secondary border-opacity-25 px-2 py-2 fs-sm max-h-32 overflow-y-auto"
                            maxlength="5000"
                            x-on:keydown.enter.prevent="if(!$event.shiftKey) { $el.closest('form').requestSubmit(); }"
                            x-on:input="autoResize($el)"></textarea>
                    </div>
                    <label class="btn-ghost btn-icon flex-shrink-0" title="Attach file">
                        📎
                        <input type="file" name="attachment" class="d-none" accept=".jpg,.jpeg,.png,.webp,.pdf,.txt"
                               x-on:change="hasAttach = $el.files.length > 0">
                    </label>
                    <button type="submit" class="btn-primary btn-sm px-3 flex-shrink-0" :disabled="sending" x-bind:class="sending ? 'opacity-60' : ''">
                        <span x-show="!sending">Send</span>
                        <span x-show="sending" class="">…</span>
                    </button>
                </form>
                <p class="text-secondary mt-1">Enter to send · Shift+Enter for new line · Max 5000 chars</p>
                @if(!$isRealtimeReady)
                    <p class="text-warning mt-1">⚡ Auto-refresh every 5s (real-time not configured)</p>
                @endif
            </div>
        @else
            <div class="flex-grow-1 d-flex flex-column align-items-center justify-content-center text-center p-4">
                <span class="display-4 mb-3">✉️</span>
                <p class="fw-semibold text-dark">Select a conversation</p>
                <p class="fs-sm text-secondary mt-1">Choose an order conversation from the left.</p>
            </div>
        @endif
    </div>
</div>

{{-- Report message modal --}}
<div id="report-modal" class="d-none position-fixed top-0 start-0 end-0 bottom-0 d-flex align-items-center justify-content-center" x-data>
    <div class="position-absolute top-0 start-0 end-0 bottom-0 bg-slate-900/50" onclick="document.getElementById('report-modal').classList.add('hidden')"></div>
    <div class="position-relative bg-white rounded-4 p-3 w-100 max-w-sm shadow-xl mx-3">
        <h3 class="fw-semibold text-dark mb-2">Report message</h3>
        <form method="POST" id="report-form" class="vstack gap-2">
            @csrf
            <select name="reason" class="select fs-sm">
                @foreach(['scam'=>'Scam','abuse'=>'Abuse','threat'=>'Threat','spam'=>'Spam','prohibited'=>'Prohibited content','other'=>'Other'] as $v=>$l)
                    <option value="{{ $v }}">{{ $l }}</option>
                @endforeach
            </select>
            <textarea name="description" rows="3" class="textarea fs-sm" placeholder="Additional details (optional)"></textarea>
            <div class="d-flex gap-2">
                <x-button type="submit" variant="danger" class="flex-grow-1">Submit report</x-button>
                <x-button type="button" variant="outline" onclick="document.getElementById('report-modal').classList.add('hidden')">Cancel</x-button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function reportMsg(msgId) {
    document.getElementById('report-form').action = `/messages/${msgId}/report`;
    document.getElementById('report-modal').classList.remove('hidden');
}
function chatApp(activeConvId, isRealtime) {
    return {
        sending: false, hasAttach: false, liveMessages: [], pollTimer: null,
        lastMsgTime: null,

        onSubmit(e) {
            if (this.sending) { e.preventDefault(); return; }
            document.getElementById('cmid').value = crypto.randomUUID ? crypto.randomUUID() : Date.now().toString();
            this.sending = true;
            // Allow normal form submission; re-enable after 2s
            setTimeout(() => { this.sending = false; }, 2000);
        },

        autoResize(el) { el.style.height = 'auto'; el.style.height = el.scrollHeight + 'px'; },

        startPolling(convId) {
            if (!convId || isRealtime) return;
            this.pollTimer = setInterval(async () => {
                if (document.hidden) return;
                const url = `/api/conversations/${convId}/poll` + (this.lastMsgTime ? `?since=${this.lastMsgTime}` : '');
                try {
                    const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!r.ok) return;
                    const msgs = await r.json();
                    if (msgs.length) {
                        const myId = {{ auth()->id() }};
                        msgs.forEach(m => {
                            if (!this.liveMessages.find(x => x.id === m.id) && m.sender_id !== myId) {
                                this.liveMessages.push(m);
                            }
                            this.lastMsgTime = m.created_at;
                        });
                        this.$nextTick(() => document.getElementById('msg-bottom')?.scrollIntoView({ behavior: 'smooth' }));
                    }
                } catch(e) { /* network error — silent */ }
            }, 5000);
        },

        destroy() { if (this.pollTimer) clearInterval(this.pollTimer); }
    };
}
window.addEventListener('DOMContentLoaded', () => {
    document.getElementById('msg-bottom')?.scrollIntoView();
});
</script>
@endpush
</x-layouts.dashboard>
