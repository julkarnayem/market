<x-layouts.dashboard :title="'Edit: '.$listing->title">
    <x-breadcrumb :items="[['label'=>'My Listings','url'=>route('dashboard.listings')],['label'=>$listing->title,'url'=>route('dashboard.listings.show',$listing)],['label'=>'Edit']]" />

    @if($listing->status->value==='published')
        <x-alert type="info" class="mb-3">Your live listing stays public while your edit is under review. Changes go live only after admin approval.</x-alert>
    @endif

    <div class="max-w-3xl vstack gap-3" x-data="{
        priceBdt: '{{ \App\Support\Money::toBdt($listing->price) }}',
        earning:{price:'',fee_amount:'',earning:'',fee_percent:'{{ number_format($feeBp/100,2) }}'},
        async calcFee(){
            if(!this.priceBdt) return;
            const r=await fetch('{{ route("dashboard.listings.fee-preview") }}?price_bdt='+this.priceBdt);
            this.earning=await r.json();
        }
    }">
        <form method="POST" action="{{ route('dashboard.listings.update',$listing) }}" enctype="multipart/form-data">
            @csrf @method('PATCH')

            <x-card>
                <h2 class="section-title mb-3">Basic Information</h2>
                <div class="vstack gap-3">
                    <x-input name="title" label="Title" :value="old('title',$listing->title)" required />
                    <div>
                        <label class="label">Description</label>
                        <textarea name="description" rows="6" class="textarea">{{ old('description',$listing->description) }}</textarea>
                        @error('description')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </x-card>

            {{-- Attributes --}}
            @if($listing->category->attributes->where('is_active',true)->isNotEmpty())
                <x-card>
                    <h2 class="section-title mb-3">Attributes</h2>
                    <div class="vstack gap-3">
                        @foreach($listing->category->attributes->where('is_active',true)->sortBy('position') as $attr)
                            @php $current = $listing->attributeValues->firstWhere('category_attribute_id',$attr->id); @endphp
                            <div>
                                <label class="label">{{ $attr->label }}@if($attr->is_required)<span class="text-danger"> *</span>@endif
                                    @if($attr->unit)<span class="fs-xs text-secondary ms-1">({{ $attr->unit }})</span>@endif
                                </label>
                                @if($attr->type==='select')
                                    <select name="attributes[{{ $attr->id }}]" class="select">
                                        <option value="">Select…</option>
                                        @foreach($attr->safeOptions() as $opt)
                                            <option value="{{ $opt }}" @selected(old("attributes.{$attr->id}",$current?->value)===$opt)>{{ $opt }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="{{ $attr->type==='number'?'number':'text' }}" name="attributes[{{ $attr->id }}]"
                                           class="input" value="{{ old("attributes.{$attr->id}",$current?->value) }}"
                                           placeholder="{{ $attr->placeholder??'' }}">
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif

            <x-card>
                <h2 class="section-title mb-3">Price &amp; Quantity</h2>
                <div class="row row-cols-2 gap-3">
                    <div>
                        <label class="label">Selling Price (৳) <span class="text-danger">*</span></label>
                        @if($listing->isPriceLocked())
                            <div class="input bg-light text-muted money">{{ \App\Support\Money::format($listing->price) }}</div>
                            <input type="hidden" name="price_bdt" value="{{ \App\Support\Money::toBdt($listing->price) }}">
                            <p class="field-hint text-warning">⚠ Price locked while an offer is pending.</p>
                        @else
                            <div class="position-relative"><span class="position-absolute text-muted font-mono">৳</span>
                            <input type="number" name="price_bdt" class="input ps-4" x-model="priceBdt" @input.debounce.400ms="calcFee()"
                                   value="{{ old('price_bdt',\App\Support\Money::toBdt($listing->price)) }}" min="1" required></div>
                        @endif
                    </div>
                    <x-input name="quantity" label="Quantity" type="number" :value="old('quantity',$listing->quantity)" min="1" max="9999" required />
                </div>
                <div class="mt-2 rounded-3 bg-success bg-opacity-10 p-3 fs-sm vstack gap-1">
                    <div class="d-flex justify-content-between"><span class="text-muted">Platform Fee (<span x-text="earning.fee_percent??'{{ number_format($feeBp/100,2) }}'"></span>%)</span><span class="money text-danger" x-text="earning.fee_amount||'—'"></span></div>
                    <div class="d-flex justify-content-between fw-bold"><span class="text-success">You Will Receive</span><span class="money text-success" x-text="earning.earning||'—'"></span></div>
                </div>
            </x-card>

            <x-card>
                <label class="label">Reason for edit (optional)</label>
                <input type="text" name="edit_reason" class="input" placeholder="e.g. Updated subscriber count" value="{{ old('edit_reason') }}">
            </x-card>

            <div class="d-flex gap-3">
                <x-button type="submit" size="lg">{{ $listing->status->value==='draft'?'Save changes':'Submit edit for review' }}</x-button>
                <x-button variant="outline" :href="route('dashboard.listings.show',$listing)">Cancel</x-button>
            </div>
        </form>
    </div>
</x-layouts.dashboard>
