<template>
    <div class="py-1">
        <Link href="/contacts" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
            <span>{{ $t('Contacts') }}</span>
        </Link>
        <Link href="/events" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
            <span>{{ $t('Events') }}</span>
        </Link>
        <Link href="/campaigns" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
            <span>{{ $t('Campaigns') }}</span>
        </Link>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
</script> 