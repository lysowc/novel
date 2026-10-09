<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { computed } from "vue"
import { SwitchRoot, SwitchThumb } from "reka-ui"
import { cn } from "@/lib/utils"

const props = withDefaults(defineProps<{
  /** 受控状态（外部用 :checked / v-model:checked 绑定） */
  checked?: boolean
  defaultChecked?: boolean
  disabled?: boolean
  class?: HTMLAttributes["class"]
}>(), {
  checked: undefined,
  defaultChecked: false,
  disabled: false,
})

const emit = defineEmits<{
  (e: "update:checked", value: boolean): void
}>()

// reka-ui 的 SwitchRoot 用 modelValue 受控，这里桥接成 radix 风格的 checked 对外
const modelValue = computed({
  get: () => (props.checked !== undefined ? props.checked : props.defaultChecked),
  set: (v: boolean) => emit("update:checked", v),
})
</script>

<template>
  <SwitchRoot
    v-slot="slotProps"
    data-slot="switch"
    v-model="modelValue"
    :disabled="props.disabled"
    :class="cn(
      'peer data-[state=checked]:bg-primary data-[state=unchecked]:bg-input focus-visible:border-ring focus-visible:ring-ring/50 dark:data-[state=unchecked]:bg-input/80 inline-flex h-[1.15rem] w-8 shrink-0 items-center rounded-full border border-transparent shadow-xs transition-all outline-none focus-visible:ring-3 disabled:cursor-not-allowed disabled:opacity-50',
      props.class,
    )"
  >
    <SwitchThumb
      data-slot="switch-thumb"
      :class="cn('bg-background dark:data-[state=unchecked]:bg-foreground dark:data-[state=checked]:bg-primary-foreground pointer-events-none block size-4 rounded-full ring-0 transition-transform data-[state=checked]:translate-x-[calc(100%-2px)] data-[state=unchecked]:translate-x-0')"
    >
      <slot name="thumb" v-bind="slotProps" />
    </SwitchThumb>
  </SwitchRoot>
</template>
