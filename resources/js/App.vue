<script setup>
import { useRoute } from 'vue-router'
import FieldShell from './components/FieldShell.vue'

const route = useRoute()
</script>

<template>
    <FieldShell v-if="route.meta.shell !== false">
        <RouterView :key="route.fullPath" />
    </FieldShell>
    <RouterView v-else :key="route.fullPath" />
</template>
