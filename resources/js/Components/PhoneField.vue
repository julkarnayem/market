<script setup lang="ts">
/**
 * Bangladeshi mobile-number field with a static +880 prefix.
 * Shared by the signup and password-reset step 1 forms, which both post to
 * endpoints validating `regex:/^01[3-9]\d{8}$/`.
 */
const model = defineModel<string>({ required: true });

const props = defineProps<{
    /** Server-side validation message for `phone`, if the last submit failed. */
    error?: string;
    label?: string;
    hint?: string;
}>();

/** The server rule is digits-only, so strip anything else as it is typed. */
function onInput(event: Event) {
    const el = event.target as HTMLInputElement;
    const digits = el.value.replace(/\D/g, '').slice(0, 11);
    if (el.value !== digits) el.value = digits;
    model.value = digits;
}
</script>

<template>
    <div>
        <label for="phone" class="label">{{ props.label ?? 'Mobile number' }}</label>

        <div class="relative">
            <span
                class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-sm font-medium text-slate-500"
                aria-hidden="true"
            >
                +880
            </span>
            <input
                id="phone"
                :value="model"
                type="tel"
                name="phone"
                inputmode="numeric"
                autocomplete="tel-national"
                maxlength="11"
                pattern="01[3-9][0-9]{8}"
                placeholder="01711234567"
                title="Enter a valid Bangladeshi mobile number, e.g. 01711234567"
                required
                autofocus
                class="input ps-14"
                :class="props.error && 'input-error'"
                :aria-invalid="Boolean(props.error)"
                :aria-describedby="props.error ? 'phone-error' : 'phone-hint'"
                @input="onInput"
            />
        </div>

        <p v-if="props.error" id="phone-error" class="field-error">{{ props.error }}</p>
        <p v-else-if="props.hint" id="phone-hint" class="field-hint">{{ props.hint }}</p>
    </div>
</template>
