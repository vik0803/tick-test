<template>
    <input
        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
        ref="input"
    >
</template>

<script setup>
import { ref } from 'vue'

defineProps({
    modelValue: {
        type: [String, Number],
        default: ''
    }
})

defineEmits(['update:modelValue'])

const input = ref(null)

defineExpose({ focus: () => input.value.focus() })
</script> 