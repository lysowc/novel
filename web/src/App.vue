<script setup lang="ts">
import { onMounted } from 'vue'
import { useThemeStore } from '@/stores/theme'
import { useReaderStore } from '@/stores/reader'

const theme = useThemeStore()
const reader = useReaderStore()

onMounted(() => {
  theme.init()
  reader.init()
})
</script>

<template>
  <RouterView v-slot="{ Component }">
    <Transition name="fade" mode="out-in">
      <component :is="Component" />
    </Transition>
  </RouterView>
  <Toaster
    position="top-center"
    :toast-options="{
      class: '!rounded-xl',
      style: {
        fontFamily: 'inherit',
      },
    }"
    rich-colors
  />
</template>
