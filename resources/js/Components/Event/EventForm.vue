<template>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form @submit.prevent="submit">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ trans('Event Name') }}</label>
                                <FormInput
                                    v-model="form.event_name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <div v-if="form.errors.event_name" class="text-sm text-red-600">{{ form.errors.event_name }}</div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ trans('Event Date') }}</label>
                                <FormInput
                                    v-model="form.event_date"
                                    type="date"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <div v-if="form.errors.event_date" class="text-sm text-red-600">{{ form.errors.event_date }}</div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ trans('Event Time') }}</label>
                                <FormInput
                                    v-model="form.event_time"
                                    type="time"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <div v-if="form.errors.event_time" class="text-sm text-red-600">{{ form.errors.event_time }}</div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ trans('Location') }}</label>
                                <FormInput
                                    v-model="form.location"
                                    type="text"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <div v-if="form.errors.location" class="text-sm text-red-600">{{ form.errors.location }}</div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ trans('Ticket Prefix') }}</label>
                                <FormInput
                                    v-model="form.ticket_prefix"
                                    type="text"
                                    class="mt-1 block w-full"
                                    maxlength="4"
                                    required
                                />
                                <div v-if="form.errors.ticket_prefix" class="text-sm text-red-600">{{ form.errors.ticket_prefix }}</div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <Link href="/events" class="mr-3">
                                {{ trans('Cancel') }}
                            </Link>
                            <button
                                type="submit"
                                class="inline-flex items-center px-4 py-2 bg-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-secondary focus:bg-secondary active:bg-secondary focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                :disabled="form.processing"
                            >
                                {{ trans(event.uuid ? 'Update' : 'Create') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useForm, usePage } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'
import { router } from '@inertiajs/vue3'
import FormInput from '@/Components/FormInput.vue'
import { trans } from 'laravel-vue-i18n'
import { computed } from 'vue'

const props = defineProps({
    event: {
        type: Object,
        required: true
    },
    settings: {
        type: Object,
        required: true
    }
})

const page = usePage()
const auth = computed(() => page.props.auth)
const organization = computed(() => page.props.organization)

const form = useForm({
    event_name: props.event.event_name || '',
    event_date: props.event.event_date || '',
    event_time: props.event.event_time || '',
    location: props.event.location || '',
    ticket_prefix: props.event.ticket_prefix || ''
})

const submit = () => {
    if (!auth.value.user) {
        console.error('User not authenticated')
        return
    }

    if (!organization.value) {
        console.error('No organization selected')
        return
    }

    if (props.event && props.event.event_id) {
        router.put(`/events/${props.event.event_id}`, form)
    } else {
        router.post('/events', form)
    }
}
</script> 