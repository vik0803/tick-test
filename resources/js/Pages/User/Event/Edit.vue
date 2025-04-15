<template>
    <App>
        <Head :title="'Edit Event'" />
        <EventForm :event="event" :settings="settings" />
    </App>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import App from '@/Pages/User/Layout/App.vue'
import EventForm from '@/Components/Event/EventForm.vue'

defineProps({
    event: Object,
    settings: Object
})
</script> 