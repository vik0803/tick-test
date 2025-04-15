<template>
  <Head :title="$t('Create Event')" />
  <UserLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ $t('Create Event') }}
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 bg-white border-b border-gray-200">
            <EventForm 
              :submit-url="route('events.store')" 
              method="post"
              @submit="onEventCreated"
              @cancel="$inertia.visit(route('events.index'))"
            />
          </div>
        </div>
      </div>
    </div>
  </UserLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import EventForm from '@/Components/Events/EventForm.vue';
import Layout from '@/Layouts/AppLayout.vue';

const onEventCreated = () => {
  // Optionally show success toast or other UI feedback
  window.location.href = route('events.index');
};
</script> 