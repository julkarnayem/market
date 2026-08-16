<x-layouts.admin title="Add Staff" heading="Add Staff Account">
    <x-breadcrumb :items="[['label'=>'Staff','url'=>route('admin.staff')],['label'=>'Add Staff']]" />
    <div class="max-w-lg">
        <x-card>
            <form method="POST" action="{{ route('admin.staff.store') }}" class="vstack gap-3">
                @csrf
                <x-input name="name" label="Full Name" required />
                <x-input name="email" type="email" label="Email Address" required />
                <div>
                    <label class="label">Role <span class="text-danger">*</span></label>
                    <select name="role_id" class="select" required>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}">{{ $role->display_name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-input name="password" type="password" label="Password (min 10 chars)" required />
                <x-input name="password_confirmation" type="password" label="Confirm Password" required />
                <div class="d-flex gap-3">
                    <x-button type="submit">Create staff account</x-button>
                    <x-button variant="outline" :href="route('admin.staff')">Cancel</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.admin>
