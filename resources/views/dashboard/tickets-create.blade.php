<x-layouts.dashboard title="New Ticket" heading="Open a support ticket">
    <div class="max-w-2xl">
        <x-card>
            <form method="POST" action="{{ route('dashboard.tickets.store') }}" class="vstack gap-3">
                @csrf
                <x-input name="subject" label="Subject" required placeholder="Brief summary of your issue" />
                <div>
                    <label class="label">Category</label>
                    <select name="category" class="select">
                        <option value="order">Order issue</option>
                        <option value="payment">Payment / Wallet</option>
                        <option value="listing">Listing</option>
                        <option value="account">Account</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="label">Priority</label>
                    <select name="priority" class="select">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div>
                    <label class="label">Message</label>
                    <textarea name="message" rows="6" class="textarea" required placeholder="Describe your issue in detail…"></textarea>
                    @error('message')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="d-flex gap-3">
                    <x-button type="submit">Submit ticket</x-button>
                    <x-button variant="outline" :href="route('dashboard.tickets')">Cancel</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.dashboard>
