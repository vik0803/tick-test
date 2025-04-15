<template>
  <label class="block text-sm font-medium text-gray-700 mb-1">
    <slot />
  </label>
</template>

<script setup>
defineProps({
  required: {
    type: Boolean,
    default: false
  }
});
</script> 