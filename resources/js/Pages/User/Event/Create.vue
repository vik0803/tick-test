<template>
    <App>
        <div class="flex items-center justify-between px-4 py-2 bg-white border-b">
            <h2 class="text-xl font-semibold text-gray-800">{{ title }}</h2>
        </div>
        
        <EventForm
            :settings="settings"
            :event="{
                event_name: '',
                event_date: '',
                event_time: '',
                location: '',
                ticket_prefix: ''
            }"
        />
    </App>
</template>

<script setup>
import EventForm from '@/Components/Event/EventForm.vue'
import App from '@/Pages/User/Layout/App.vue'

defineProps({
    title: String,
    settings: Object
})
</script> 