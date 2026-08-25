<script setup lang="ts">import { Button } from '@/components/ui/button'
import { AlertDialog, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'

import { ref, watch } from 'vue'
import { AlertTriangle, LoaderCircle } from '@lucide/vue'

const props = withDefaults(
  defineProps<{
    open: boolean
    title?: string
    description?: string
    confirmText?: string
    cancelText?: string
    loading?: boolean
  }>(),
  {
    title: '确认操作',
    description: '此操作无法撤销，请确认。',
    confirmText: '确认',
    cancelText: '取消',
    loading: false,
  },
)

const emit = defineEmits<{
  (e: 'update:open', v: boolean): void
  (e: 'confirm'): void
}>()

const inner = ref(props.open)
watch(
  () => props.open,
  (v) => (inner.value = v),
)
watch(inner, (v) => emit('update:open', v))
</script>

<template>
  <AlertDialog v-model:open="inner">
    <AlertDialogContent class="max-w-sm">
      <AlertDialogHeader>
        <div class="mb-2 flex size-10 items-center justify-center rounded-full bg-destructive/10 text-destructive">
          <AlertTriangle class="size-5" />
        </div>
        <AlertDialogTitle class="text-base">{{ title }}</AlertDialogTitle>
        <AlertDialogDescription class="text-sm">{{ description }}</AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel :disabled="loading">{{ cancelText }}</AlertDialogCancel>
        <Button variant="destructive" :disabled="loading" @click="emit('confirm')">
          <LoaderCircle v-if="loading" class="size-4 animate-spin" />
          {{ confirmText }}
        </Button>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
