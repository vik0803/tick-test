<template>
    <App>
        <div v-if="can.create" class="flex items-center justify-between px-4 py-2 bg-white border-b">
            <h2 class="text-xl font-semibold text-gray-800">
                Events
            </h2>
            <Link href="/events/create" class="inline-flex items-center px-4 py-2 bg-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-secondary focus:bg-secondary active:bg-secondary focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                Create Event
            </Link>
        </div>

        <div class="py-6 px-4">
            <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <div class="w-1/3">
                        <input v-model="search" type="text" class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm" placeholder="Search events...">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">Event Name</th>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">Ticket Prefix</th>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="event in events.data" :key="event.event_id">
                                <td class="px-6 py-4 whitespace-no-wrap">{{ event.event_name }}</td>
                                <td class="px-6 py-4 whitespace-no-wrap">{{ formatDate(event.event_date) }}</td>
                                <td class="px-6 py-4 whitespace-no-wrap">{{ formatTime(event.event_time) }}</td>
                                <td class="px-6 py-4 whitespace-no-wrap">{{ event.location }}</td>
                                <td class="px-6 py-4 whitespace-no-wrap">{{ event.ticket_prefix }}</td>
                                <td class="px-6 py-4 whitespace-no-wrap text-right text-sm font-medium">
                                    <Link :href="`/events/${event.event_id}/edit`" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</Link>
                                    <a :href="`/debug/events/delete/${event.event_id}`" class="text-red-600 hover:text-red-900">
                                        Delete
                                    </a>
                                </td>
                            </tr>
                            <tr v-if="events.data.length === 0">
                                <td class="px-6 py-4 whitespace-no-wrap text-center" colspan="6">No events found</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination class="mt-6" :pagination="events" />
            </div>
        </div>
    </App>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import App from '@/Pages/User/Layout/App.vue'
import Pagination from '@/Components/Pagination.vue'
import debounce from 'lodash/debounce'

const props = defineProps({
    events: Object,
    filters: Object,
    can: Object
})

const search = ref(props.filters.search)

watch(search, debounce((value) => {
    router.get('/events', { search: value }, {
        preserveState: true,
        preserveScroll: true,
    })
}, 300))

const formatDate = (date) => {
    return new Date(date).toLocaleDateString()
}

const formatTime = (time) => {
    return new Date(`2000-01-01T${time}`).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}
</script> 