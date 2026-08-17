<script setup lang="ts">
/**
 * Single-input 6-digit OTP field, shared by the signup and password-reset
 * verify steps. Both post to endpoints validating `size:6|regex:/^\d{6}$/`.
 */
const model = defineModel<string>({ required: true });

const props = defineProps<{
    /** Server-side validation message for `otp`, if the last submit failed. */
    error?: string;
}>();

/** Digits only, hard-capped at 6 — applies to pasted codes too. */
function onInput(event: Event) {
    const el = event.target as HTMLInputElement;
    const digits = el.value.replace(/\D/g, '').slice(0, 6);
    if (el.value !== digits) el.value = digits;
    model.value = digits;
}
</script>

<template>
    <div>
        <label for="otp" class="label text-center">Enter 6-digit OTP</label>

        <!-- indent offsets the trailing letter-space so the digits look optically centred -->
        <input
            id="otp"
            :value="model"
            type="text"
            name="otp"
            inputmode="numeric"
            autocomplete="one-time-code"
            maxlength="6"
            pattern="[0-9]{6}"
            placeholder="••••••"
            required
            autofocus
            class="input py-4 text-center indent-[0.5em] font-mono text-2xl font-bold tracking-[0.5em]"
            :class="props.error && 'input-error'"
            :aria-invalid="Boolean(props.error)"
            :aria-describedby="props.error ? 'otp-error' : undefined"
            @input="onInput"
        />

        <p v-if="props.error" id="otp-error" class="field-error justify-center text-center">{{ props.error }}</p>
    </div>
</template>
