<x-layouts.dashboard title="Create Listing" heading="Create a listing">
    <div class="max-w-3xl" x-data="{
        step: 1, totalSteps: 5,
        categoryId: '', subcategoryId: '', priceBdt: 0,
        earning: { price:'৳0.00', fee:'৳0.00', earn:'৳0.00', pct: '{{ number_format($feeBp/100,2) }}' },
        imageFiles: [],
        async calcFee() {
            if (!this.priceBdt) return;
            const r = await fetch('{{ route("dashboard.listings.fee-preview") }}?price_bdt='+this.priceBdt);
            const d = await r.json();
            this.earning = {...d, pct: d.fee_percent};
        }
    }">

        {{-- Progress --}}
        <div class="d-flex align-items-center gap-2 mb-4 overflow-x-auto pb-1">
            @foreach(['Category','Details','Attributes','Price & Qty','Images'] as $i => $label)
                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                    <span class="h-7 w-7 d-grid place-items-center rounded-pill fs-xs fw-bold"
                          :class="step > {{ $i+1 }} ? 'bg-mint-500 text-white' : (step === {{ $i+1 }} ? 'bg-brand-600 text-white' : 'bg-slate-200 text-slate-500')">
                        <template x-if="step > {{ $i+1 }}">✓</template>
                        <template x-if="step <= {{ $i+1 }}">{{ $i+1 }}</template>
                    </span>
                    <span class="fs-xs fw-medium d-none d-sm-block" :class="step === {{ $i+1 }} ? 'text-slate-900' : 'text-slate-400'">{{ $label }}</span>
                    @if($i < 4)<span class="text-slate-300 fs-sm">›</span>@endif
                </div>
            @endforeach
        </div>

        <form method="POST" action="{{ route('dashboard.listings.store') }}" enctype="multipart/form-data" id="listing-form" class="vstack gap-3">
            @csrf

            {{-- STEP 1: Category --}}
            <div x-show="step===1" x-transition>
                <x-card>
                    <h2 class="section-title mb-3">Select Category</h2>
                    @if($errors->any() && old('category_id'))<x-alert type="error" class="mb-3">Please correct the errors below.</x-alert>@endif
                    <div class="vstack gap-3">
                        <div>
                            <label class="label">Category <span class="text-danger">*</span></label>
                            <select name="" x-model="categoryId" @change="subcategoryId=''" class="select" required>
                                <option value="">Choose a category…</option>
                                @foreach($categories as $cat)
                                    @if($cat->children->isNotEmpty())
                                        <option value="{{ $cat->id }}" {{ $cat->is_prohibited ? 'disabled' : '' }}>
                                            {{ $cat->icon ?? '' }} {{ $cat->name }}{{ $cat->is_prohibited ? ' (prohibited)' : '' }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div x-show="categoryId" x-transition>
                            <label class="label">Subcategory <span class="text-danger">*</span></label>
                            <select name="category_id" x-model="subcategoryId" class="select {{ $errors->has('category_id')?'input-error':'' }}" required>
                                <option value="">Choose subcategory…</option>
                                @foreach($categories as $cat)
                                    @foreach($cat->children as $sub)
                                        <option value="{{ $sub->id }}"
                                            x-show="categoryId=='{{ $cat->id }}'"
                                            {{ !$sub->isSelectable() ? 'disabled' : '' }}>
                                            {{ $sub->name }}{{ $sub->is_prohibited ? ' (prohibited)' : ($sub->is_restricted ? ' (restricted)' : '') }}
                                        </option>
                                    @endforeach
                                @endforeach
                            </select>
                            @error('category_id')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <x-button @click.prevent="step=2" x-bind:disabled="subcategoryId === ''">Next: Basic details →</x-button>
                    </div>
                </x-card>
            </div>

            {{-- STEP 2: Basic info --}}
            <div x-show="step===2" x-transition>
                <x-card>
                    <h2 class="section-title mb-3">Basic Information</h2>
                    <div class="vstack gap-3">
                        <div>
                            <label class="label">Title <span class="text-danger">*</span></label>
                            <input name="title" type="text" class="input {{ $errors->has('title')?'input-error':'' }}"
                                placeholder="e.g. 50K YouTube Channel – Tech Niche, Monetized" value="{{ old('title') }}" maxlength="255">
                            <p class="field-hint">Min 10 characters. Be specific and descriptive.</p>
                            @error('title')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" rows="8" class="textarea {{ $errors->has('description')?'input-error':'' }}"
                                placeholder="Describe the asset in full detail: age, stats, monetization status, audience demographics, reason for selling…">{{ old('description') }}</textarea>
                            <p class="field-hint">Min 50 characters. Detailed descriptions get approved faster.</p>
                            @error('description')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-3">
                        <x-button variant="ghost" @click.prevent="step=1">← Back</x-button>
                        <x-button @click.prevent="step=3">Next: Attributes →</x-button>
                    </div>
                </x-card>
            </div>

            {{-- STEP 3: Attributes (loaded via Alpine based on category) --}}
            <div x-show="step===3" x-transition>
                <x-card>
                    <h2 class="section-title mb-1">Asset Details</h2>
                    <p class="section-sub mb-3">Fill in specific details about the asset.</p>
                    @foreach($categories as $cat)
                        @foreach($cat->children as $sub)
                            <div x-show="subcategoryId=='{{ $sub->id }}'">
                                @if($sub->attributes->isEmpty())
                                    <p class="fs-sm text-muted fst-italic">No additional attributes for this subcategory.</p>
                                @else
                                    <div class="vstack gap-3">
                                        @foreach($sub->attributes->where('is_active',true)->sortBy('position') as $attr)
                                            <div>
                                                <label class="label">{{ $attr->label }}@if($attr->is_required)<span class="text-danger"> *</span>@endif
                                                    @if($attr->unit)<span class="fs-xs text-secondary ms-1">({{ $attr->unit }})</span>@endif
                                                </label>
                                                @if($attr->type === 'select')
                                                    <select name="attributes[{{ $attr->id }}]" class="select" {{ $attr->is_required?'required':'' }}>
                                                        <option value="">Select…</option>
                                                        @foreach($attr->safeOptions() as $opt)
                                                            <option value="{{ $opt }}" @selected(old("attributes.{$attr->id}")===$opt)>{{ $opt }}</option>
                                                        @endforeach
                                                    </select>
                                                @elseif($attr->type === 'boolean')
                                                    <select name="attributes[{{ $attr->id }}]" class="select">
                                                        <option value="">Select…</option>
                                                        <option value="yes">Yes</option>
                                                        <option value="no">No</option>
                                                    </select>
                                                @elseif($attr->type === 'number' || $attr->type === 'decimal')
                                                    <input type="number" name="attributes[{{ $attr->id }}]" class="input" {{ $attr->is_required?'required':'' }}
                                                        placeholder="{{ $attr->placeholder ?? '' }}" value="{{ old("attributes.{$attr->id}") }}">
                                                @elseif($attr->type === 'date')
                                                    <input type="date" name="attributes[{{ $attr->id }}]" class="input" {{ $attr->is_required?'required':'' }}
                                                        value="{{ old("attributes.{$attr->id}") }}">
                                                @elseif($attr->type === 'url')
                                                    <input type="url" name="attributes[{{ $attr->id }}]" class="input" {{ $attr->is_required?'required':'' }}
                                                        placeholder="{{ $attr->placeholder ?? 'https://' }}" value="{{ old("attributes.{$attr->id}") }}">
                                                @else
                                                    <input type="text" name="attributes[{{ $attr->id }}]" class="input" {{ $attr->is_required?'required':'' }}
                                                        placeholder="{{ $attr->placeholder ?? '' }}" value="{{ old("attributes.{$attr->id}") }}">
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    @endforeach
                    <div class="d-flex justify-content-between mt-3">
                        <x-button variant="ghost" @click.prevent="step=2">← Back</x-button>
                        <x-button @click.prevent="step=4">Next: Price & Quantity →</x-button>
                    </div>
                </x-card>
            </div>

            {{-- STEP 4: Price & Qty + earning summary --}}
            <div x-show="step===4" x-transition>
                <x-card>
                    <h2 class="section-title mb-1">Price &amp; Quantity</h2>
                    <p class="section-sub mb-3">Set your selling price in BDT.</p>
                    <div class="row row-cols-2 gap-3">
                        <div>
                            <label class="label">Selling Price (৳) <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <span class="position-absolute text-muted font-mono fw-bold">৳</span>
                                <input type="number" name="price_bdt" class="input pl-7 {{ $errors->has('price_bdt')?'input-error':'' }}"
                                    x-model="priceBdt" @input.debounce.400ms="calcFee()" min="1" step="1"
                                    value="{{ old('price_bdt') }}" required placeholder="0">
                            </div>
                            @error('price_bdt')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="label">Quantity <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" class="input" min="1" max="9999" value="{{ old('quantity',1) }}" required>
                            <p class="field-hint">Use 1 for unique items.</p>
                            @error('quantity')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    {{-- Earning summary --}}
                    <div class="mt-3 rounded-4 bg-success bg-opacity-10 p-3">
                        <p class="fs-sm fw-semibold text-success mb-2">Your estimated earnings</p>
                        <dl class="vstack gap-2 fs-sm">
                            <div class="d-flex justify-content-between"><dt class="text-muted">Selling Price</dt><dd class="money fw-semibold" x-text="earning.price"></dd></div>
                            <div class="d-flex justify-content-between"><dt class="text-muted">Platform Fee (<span x-text="earning.fee_percent || earning.pct"></span>%)</dt><dd class="money text-danger" x-text="earning.fee_amount"></dd></div>
                            <div class="d-flex justify-content-between pt-2 border-top">
                                <dt class="fw-bold text-success">You Will Receive</dt>
                                <dd class="money fw-bold text-success fs-4" x-text="earning.earning || earning.earn"></dd>
                            </div>
                        </dl>
                        <p class="fs-xs text-secondary mt-2">Earnings are released 8 hours after order completion. Fee is deducted from each sale.</p>
                    </div>

                    <div class="d-flex justify-content-between mt-3">
                        <x-button variant="ghost" @click.prevent="step=3">← Back</x-button>
                        <x-button @click.prevent="step=5">Next: Images →</x-button>
                    </div>
                </x-card>
            </div>

            {{-- STEP 5: Images + Policy + Submit --}}
            <div x-show="step===5" x-transition>
                <x-card>
                    <h2 class="section-title mb-1">Images</h2>
                    <p class="section-sub mb-3">Upload screenshots or proof images. The first image will be the cover.</p>
                    <div>
                        <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp"
                               multiple class="input {{ $errors->has('images.*')?'input-error':'' }}"
                               @change="imageFiles=Array.from($event.target.files)">
                        <p class="field-hint">JPG, PNG, WebP. Max 5MB each. Up to 10 images.</p>
                        @error('images.*')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    {{-- Preview --}}
                    <div class="row row-cols-3 row-cols-5 gap-2 mt-2">
                        <template x-for="(f,i) in imageFiles" :key="i">
                            <div class="position-relative rounded-3 overflow-hidden bg-light">
                                <img :src="URL.createObjectURL(f)" class="h-100 w-100 object-fit-cover">
                                <span x-show="i===0" class="position-absolute badge-mint py-1 px-1">Cover</span>
                            </div>
                        </template>
                    </div>
                </x-card>

                {{-- Policy acceptance --}}
                <x-card class="mt-3">
                    <h2 class="section-title mb-2">Seller Policy</h2>
                    <div class="fs-sm text-muted space-y-1.5">
                        <p>By submitting this listing I confirm that:</p>
                        <ul class="list-disc list-inside vstack gap-1 mt-2">
                            <li>I own or have the right to sell this asset.</li>
                            <li>All information provided is accurate.</li>
                            <li>This asset does not violate the <a href="{{ route('legal','prohibited-assets') }}" class="text-primary" target="_blank">Prohibited Assets Policy</a>.</li>
                            <li>I have read and agree to the <a href="{{ route('legal','seller-policy') }}" class="text-primary" target="_blank">Seller Policy</a>.</li>
                        </ul>
                    </div>
                    <label class="d-flex align-items-start gap-3 mt-3">
                        <input type="checkbox" name="policy_accept" value="1" class="checkbox mt-1" required>
                        <span class="fs-sm text-dark fw-medium">I accept the seller policy and confirm the above statements.</span>
                    </label>
                    @error('policy_accept')<p class="field-error">{{ $message }}</p>@enderror
                </x-card>

                <div class="d-flex align-items-center gap-3 mt-3">
                    <button type="submit" name="save_as_draft" value="1" class="btn-outline">💾 Save as draft</button>
                    <x-button type="submit" size="lg">Submit for review →</x-button>
                    <x-button variant="ghost" @click.prevent="step=4">← Back</x-button>
                </div>
            </div>
        </form>
    </div>
</x-layouts.dashboard>
