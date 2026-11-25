<script setup>
defineProps({
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, required: true },
    name: { type: String, required: true },
    error: { type: String, default: '' },
})
defineEmits(['update:modelValue'])
</script>

<template>
    <div class="grid gap-2">
        <label
            v-for="option in options"
            :key="option.value"
            class="flex min-h-14 cursor-pointer items-start gap-3 rounded-2xl border px-4 py-3"
            :class="String(modelValue) === String(option.value) ? 'border-leaf bg-emerald-50' : 'border-line bg-white'"
        >
            <input
                class="mt-1 h-5 w-5 accent-emerald-800"
                type="radio"
                :name="name"
                :value="option.value"
                :checked="String(modelValue) === String(option.value)"
                @change="$emit('update:modelValue', option.value)"
            >
            <span>
                <span class="block font-medium">{{ option.label }}</span>
                <span v-if="option.hint" class="mt-1 block text-sm leading-5 text-stone-600">{{ option.hint }}</span>
            </span>
        </label>
        <p v-if="error" class="text-sm font-medium text-red-700">{{ error }}</p>
    </div>
</template>
