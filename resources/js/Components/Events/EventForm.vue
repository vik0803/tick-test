<template>
  <div class="w-full" :class="loading ? 'opacity-75' : ''">
    <div class="mb-4">
      <FormLabel required>{{ $t('Event Name') }}</FormLabel>
      <FormInput v-model="formData.event_name" :placeholder="$t('Enter event name')" />
      <FormError :error="errors.event_name" />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
      <div>
        <FormLabel required>{{ $t('Event Date') }}</FormLabel>
        <FormDate v-model="formData.event_date" :placeholder="$t('Select date')" />
        <FormError :error="errors.event_date" />
      </div>
      <div>
        <FormLabel required>{{ $t('Event Time') }}</FormLabel>
        <FormTime v-model="formData.event_time" :placeholder="$t('Select time')" />
        <FormError :error="errors.event_time" />
      </div>
    </div>

    <div class="mb-4">
      <FormLabel required>{{ $t('Location') }}</FormLabel>
      <FormInput v-model="formData.location" :placeholder="$t('Enter event location')" />
      <FormError :error="errors.location" />
    </div>

    <div class="mb-4">
      <FormLabel>{{ $t('Description') }}</FormLabel>
      <FormTextarea v-model="formData.description" :placeholder="$t('Enter event description')" rows="3" />
      <FormError :error="errors.description" />
    </div>

    <div class="mb-4">
      <FormLabel>{{ $t('Ticket Prefix') }}</FormLabel>
      <FormInput v-model="formData.ticket_prefix" :placeholder="$t('e.g. EVT2024')" />
      <p class="text-sm text-gray-500 mt-1">{{ $t('Used for generating unique ticket numbers') }}</p>
      <FormError :error="errors.ticket_prefix" />
    </div>

    <div class="mb-4">
      <FormLabel>{{ $t('Maximum Attendees') }}</FormLabel>
      <FormInput 
        v-model="formData.max_attendees" 
        type="number" 
        :placeholder="$t('Leave empty for unlimited')" 
      />
      <FormError :error="errors.max_attendees" />
    </div>

    <div class="mb-4">
      <FormLabel>{{ $t('Event Status') }}</FormLabel>
      <FormSelect
        v-model="formData.status"
        :options="[
          { value: 'draft', label: $t('Draft') },
          { value: 'published', label: $t('Published') },
          { value: 'cancelled', label: $t('Cancelled') }
        ]"
      />
      <FormError :error="errors.status" />
    </div>

    <div class="flex justify-end mt-6">
      <FormCancel @click="$emit('cancel')" />
      <PrimaryButton 
        type="button" 
        @click="submitForm"
        :disabled="loading"
        class="ml-2"
      >
        {{ loading ? $t('Saving...') : $t('Save Event') }}
      </PrimaryButton>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import FormInput from '@/Components/FormInput.vue';
import FormTextarea from '@/Components/FormTextArea.vue';
import FormSelect from '@/Components/FormSelect.vue';
import FormLabel from '@/Components/Form/Label.vue';
import FormError from '@/Components/Form/Error.vue';
import FormDate from '@/Components/Form/Date.vue';
import FormTime from '@/Components/Form/Time.vue';
import FormCancel from '@/Components/Form/Cancel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
  event: {
    type: Object,
    default: () => ({
      id: null,
      event_name: '',
      event_date: '',
      event_time: '',
      location: '',
      description: '',
      ticket_prefix: '',
      max_attendees: '',
      status: 'draft'
    })
  },
  submitUrl: {
    type: String,
    required: true
  },
  method: {
    type: String,
    default: 'post'
  }
});

const emit = defineEmits(['submit', 'cancel']);

const loading = ref(false);
const formData = useForm({
  id: props.event.id,
  event_name: props.event.event_name,
  event_date: props.event.event_date,
  event_time: props.event.event_time,
  location: props.event.location,
  description: props.event.description,
  ticket_prefix: props.event.ticket_prefix,
  max_attendees: props.event.max_attendees,
  status: props.event.status || 'draft'
});

// Update form if props change
watch(() => props.event, (newEvent) => {
  formData.id = newEvent.id;
  formData.event_name = newEvent.event_name;
  formData.event_date = newEvent.event_date;
  formData.event_time = newEvent.event_time;
  formData.location = newEvent.location;
  formData.description = newEvent.description;
  formData.ticket_prefix = newEvent.ticket_prefix;
  formData.max_attendees = newEvent.max_attendees;
  formData.status = newEvent.status || 'draft';
}, { deep: true });

const errors = computed(() => formData.errors);

const submitForm = () => {
  loading.value = true;
  
  formData[props.method](props.submitUrl, {
    onSuccess: () => {
      loading.value = false;
      emit('submit');
    },
    onError: () => {
      loading.value = false;
    }
  });
};
</script> 