<x-layouts.public title="Contact">
    <div class="mx-auto max-w-xl px-3 px-sm-4 px-lg-4 py-4">
        <h1 class="font-display fs-2 fw-bold text-dark">Contact us</h1>
        <p class="fs-sm text-muted mt-2">Questions about buying, selling, or your wallet? Send a message.</p>
        <x-card class="mt-4">
            {{-- Wire-up (mail/ticket creation) lands in a later Part. --}}
            <form class="vstack gap-3" method="POST" action="{{ route('contact.submit') }}">
                @csrf
                <x-input name="name" label="Name" required />
                <x-input name="email" type="email" label="Email" required />
                <div>
                    <label class="label">Message</label>
                    <textarea name="message" rows="5" class="textarea" required></textarea>
                </div>
                <x-button type="submit">Send message</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.public>
