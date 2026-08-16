<x-layouts.dashboard title="Open Dispute" heading="Open a Dispute">
    <x-breadcrumb :items="[['label'=>'Orders','url'=>route('dashboard.orders')],['label'=>$order->order_number,'url'=>route('dashboard.orders.show',$order)],['label'=>'Dispute']]" />
    <div class="max-w-xl">
        <x-alert type="error" class="mb-3">
            <div><p class="fw-semibold">Opening a dispute is a serious action.</p>
            <p class="fs-sm mt-1">Only open a dispute if you genuinely have an issue with the delivery. Admin will review within 24–48 hours.</p></div>
        </x-alert>
        <x-card>
            <h2 class="section-title mb-3">Dispute — Order {{ $order->order_number }}</h2>
            <form method="POST" action="{{ route('dashboard.orders.dispute.submit',$order) }}" class="vstack gap-3">
                @csrf
                <div>
                    <label class="label">Reason for dispute <span class="text-danger">*</span></label>
                    <textarea name="reason" rows="5" class="textarea {{ $errors->has('reason')?'input-error':'' }}"
                        required minlength="20" placeholder="Clearly describe the issue with the delivery. Include specific details of what was promised vs. what was delivered…"></textarea>
                    <p class="field-hint">Min 20 characters.</p>
                    @error('reason')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="d-flex gap-3">
                    <x-button type="submit" variant="danger" size="lg">⚑ Open dispute</x-button>
                    <x-button variant="outline" :href="route('dashboard.orders.show',$order)">Cancel</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.dashboard>
