<x-layouts.dashboard title="Leave a Review" heading="Leave a Review">
<div class="max-w-lg vstack gap-3">
    <x-card>
        <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
            <div class="h-12 w-12 rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="background:#ECFDF5">
                <span class="fs-3">⭐</span>
            </div>
            <div>
                <p class="fw-semibold text-dark">{{ $order->asset->title }}</p>
                <p class="fs-sm text-muted">Seller: {{ $order->seller->name }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('dashboard.orders.review.store', $order) }}" x-data="{ rating: 0, hover: 0 }">
            @csrf

            {{-- Star rating --}}
            <div class="mb-3">
                <label class="d-block fs-sm fw-medium text-dark mb-2">Your Rating</label>
                <div class="d-flex gap-2">
                    @for($i = 1; $i <= 5; $i++)
                    <button type="button"
                        @click="rating = {{ $i }}"
                        @mouseenter="hover = {{ $i }}"
                        @mouseleave="hover = 0"
                        class="fs-1"
                        :class="(hover || rating) >= {{ $i }} ? 'opacity-100' : 'opacity-30'"
                        style="color:#F59E0B">★</button>
                    @endfor
                </div>
                <input type="hidden" name="rating" :value="rating" required>
                <p class="fs-xs text-secondary mt-2" x-text="['','Poor','Fair','Good','Very Good','Excellent'][rating] || 'Click to rate'"></p>
                @error('rating') <p class="text-danger fs-xs mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Comment --}}
            <div class="mb-3">
                <label class="d-block fs-sm fw-medium text-dark mb-1">
                    Comment <span class="text-secondary fw-normal">(optional)</span>
                </label>
                <textarea name="comment" rows="4"
                    class="w-100 border border-secondary border-opacity-25 rounded-3 px-2 py-2 fs-sm text-dark"
                    style="focus:border-color:#10B981"
                    placeholder="Share your experience with this seller and asset…"
                    maxlength="1000">{{ old('comment') }}</textarea>
                @error('comment') <p class="text-danger fs-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="d-flex gap-3">
                <button type="submit"
                    x-bind:disabled="rating === 0"
                    class="flex-grow-1 py-2 fs-sm fw-semibold text-white rounded-3"
                    style="background:#10B981"
                    onmouseover="if(!this.disabled)this.style.background='#059669'"
                    onmouseout="this.style.background='#10B981'">
                    Submit Review
                </button>
                <a href="{{ route('dashboard.orders.show', $order) }}"
                   class="px-3 py-2 fs-sm fw-semibold text-dark border border-secondary border-opacity-25 rounded-3">
                    Cancel
                </a>
            </div>
        </form>
    </x-card>
</div>
</x-layouts.dashboard>
