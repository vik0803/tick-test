<template>
    <AppLayout>
        <div class="md:flex md:flex-col bg-white border-l py-4 text-[#000] overflow-y-hidden">
            <div class="flex justify-between px-8 border-b pb-2 capitalize">
                <div>
                    <h2 class="text-xl mb-1">New campaign</h2>
                    <p class="flex items-center text-sm leading-6 text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                        <span class="ml-1 mt-1">Create campaign</span>
                    </p>
                </div>
                <div class="space-x-2 flex items-center">
                    <Link href="/campaigns" class="rounded-md bg-black px-3 py-2 text-sm text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Back</Link>
                </div>
            </div>
            <CampaignForm :templates="templates" :contactGroups="contactGroups" :settings="settings" :events="events"/>
        </div>
    </AppLayout>
</template>
<script setup>
    import axios from "axios";
    import AppLayout from "./../Layout/App.vue";
    import CampaignForm from '@/Components/CampaignForm.vue';
    import WhatsappTemplate from '@/Components/WhatsappTemplate.vue';
    import { ref, computed, onMounted } from 'vue';
    import { Link, useForm } from "@inertiajs/vue3";
    import 'vue3-toastify/dist/index.css';
    import { trans } from 'laravel-vue-i18n';

    const props = defineProps(['templates', 'contactGroups', 'settings', 'events', 'title']);
    
    onMounted(() => {
        console.log('Create Campaign Component mounted');
        console.log('Events prop received in Create.vue:', props.events);
        console.log('Events type in Create.vue:', typeof props.events, Array.isArray(props.events));
        if (props.events) {
            console.log('Events count in Create.vue:', props.events.length);
            console.log('First event in Create.vue:', props.events.length > 0 ? props.events[0] : null);
        } else {
            console.warn('No events received in Create.vue');
        }
    });
</script>