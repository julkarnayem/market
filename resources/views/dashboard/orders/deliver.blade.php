<x-layouts.dashboard title="Deliver Asset" heading="Deliver Asset">
    <x-breadcrumb :items="[['label'=>'Orders','url'=>route('dashboard.orders',['role'=>'seller'])],['label'=>$order->order_number,'url'=>route('dashboard.orders.show',$order)],['label'=>'Deliver']]" />
    <div class="max-w-xl">
        <x-alert type="warning" class="mb-3">
            <div><p class="fw-semibold">Delivery is final and visible to the buyer.</p>
            <p class="fs-sm mt-1">Do not include passwords or sensitive data in the note unless the buyer specifically needs it. Use secure channels where possible.</p></div>
        </x-alert>
        <x-card>
            <h2 class="section-title mb-3">Submit Delivery for Order {{ $order->order_number }}</h2>
            <div class="mb-3 p-2 rounded-3 bg-light fs-sm"><span class="fw-medium">Asset:</span> {{ $order->asset->title }}</div>

            <form method="POST" action="{{ route('dashboard.orders.deliver.submit',$order) }}" enctype="multipart/form-data" class="vstack gap-3">
                @csrf
                <div>
                    <label class="label">Delivery message / credentials <span class="text-danger">*</span></label>
                    <textarea name="delivery_note" rows="6" class="textarea {{ $errors->has('delivery_note')?'input-error':'' }}"
                        required minlength="10" placeholder="Provide clear delivery instructions, transfer details, or credentials needed for the buyer to access the asset…">{{ old('delivery_note') }}</textarea>
                    <p class="field-hint">Min 10 characters. This will only be visible to the buyer and authorized support staff.</p>
                    @error('delivery_note')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label">Attachment (optional)</label>
                    <input type="file" name="attachment" class="input" accept=".pdf,.zip,.txt,.jpg,.jpeg,.png">
                    <p class="field-hint">Max 20MB. Stored securely — never publicly accessible.</p>
                </div>
                <div class="d-flex gap-3">
                    <x-button type="submit" variant="success" size="lg">Confirm delivery →</x-button>
                    <x-button variant="outline" :href="route('dashboard.orders.show',$order)">Cancel</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.dashboard>
