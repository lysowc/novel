<script setup lang="ts">import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Switch } from '@/components/ui/switch'
import { Button } from '@/components/ui/button'

import { onMounted, ref } from 'vue'
import { BadgeCheck, Bot, Cable, KeyRound, Pencil, Plus, Server, Star, Trash2, LoaderCircle } from '@lucide/vue'
import { toast } from 'vue-sonner'
import PageHeader from '@/components/common/PageHeader.vue'
import LoadingState from '@/components/common/LoadingState.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import {
  createModel, createProvider, deleteModel, deleteProvider, fetchModels,
  fetchProviders, setModelDefault, setProviderDefault, updateModel, updateProvider,
} from '@/api'
import type { AiModel, AiProvider } from '@/types/api'

const loading = ref(true)
const providers = ref<AiProvider[]>([])
const models = ref<AiModel[]>([])

// Provider 表单
const providerOpen = ref(false)
const providerEditing = ref<AiProvider | null>(null)
const providerSaving = ref(false)
const providerForm = ref({ name: '', base_url: '', api_key: '', status: 1 })

// Model 表单
const modelOpen = ref(false)
const modelEditing = ref<AiModel | null>(null)
const modelSaving = ref(false)
const modelForm = ref({ provider_id: 0, name: '', display_name: '', max_tokens: 8192, temperature: 0.8, status: 1 })

// 删除
const deletingProvider = ref<AiProvider | null>(null)
const deletingModel = ref<AiModel | null>(null)
const deleteLoading = ref(false)

async function load() {
  loading.value = true
  try {
    providers.value = await fetchProviders()
    models.value = await fetchModels()
  } finally {
    loading.value = false
  }
}

// ---- Provider ----
function openProviderCreate() {
  providerEditing.value = null
  providerForm.value = { name: '', base_url: '', api_key: '', status: 1 }
  providerOpen.value = true
}

function openProviderEdit(p: AiProvider) {
  providerEditing.value = p
  providerForm.value = { name: p.name, base_url: p.base_url, api_key: '', status: p.status }
  providerOpen.value = true
}

async function saveProvider() {
  if (!providerForm.value.name.trim() || !providerForm.value.base_url.trim()) {
    toast.error('请填写名称与 Base URL')
    return
  }
  providerSaving.value = true
  try {
    if (providerEditing.value) {
      await updateProvider(providerEditing.value.id, { ...providerForm.value })
      toast.success('Provider 已更新')
    } else {
      await createProvider({ ...providerForm.value })
      toast.success('Provider 已创建')
    }
    providerOpen.value = false
    load()
  } catch {
    // 请求层已提示
  } finally {
    providerSaving.value = false
  }
}

async function toggleProvider(p: AiProvider) {
  try {
    await updateProvider(p.id, { status: p.status === 1 ? 0 : 1 })
    p.status = p.status === 1 ? 0 : 1
    toast.success(p.status ? '已启用' : '已停用')
  } catch {
    // 请求层已提示
  }
}

async function makeProviderDefault(p: AiProvider) {
  try {
    await setProviderDefault(p.id)
    toast.success(`已将 ${p.name} 设为默认`)
    load()
  } catch {
    // 请求层已提示
  }
}

// ---- Model ----
function openModelCreate() {
  modelEditing.value = null
  modelForm.value = {
    provider_id: providers.value[0]?.id ?? 0,
    name: '', display_name: '', max_tokens: 8192, temperature: 0.8, status: 1,
  }
  modelOpen.value = true
}

function openModelEdit(m: AiModel) {
  modelEditing.value = m
  modelForm.value = {
    provider_id: m.provider_id, name: m.name, display_name: m.display_name,
    max_tokens: m.max_tokens, temperature: m.temperature, status: m.status,
  }
  modelOpen.value = true
}

async function saveModel() {
  if (!modelForm.value.name.trim()) {
    toast.error('请填写模型名')
    return
  }
  modelSaving.value = true
  try {
    if (modelEditing.value) {
      await updateModel(modelEditing.value.id, { ...modelForm.value })
      toast.success('模型已更新')
    } else {
      await createModel({ ...modelForm.value })
      toast.success('模型已创建')
    }
    modelOpen.value = false
    load()
  } catch {
    // 请求层已提示
  } finally {
    modelSaving.value = false
  }
}

async function toggleModel(m: AiModel) {
  try {
    await updateModel(m.id, { status: m.status === 1 ? 0 : 1 })
    m.status = m.status === 1 ? 0 : 1
    toast.success(m.status ? '已启用' : '已停用')
  } catch {
    // 请求层已提示
  }
}

async function makeModelDefault(m: AiModel) {
  try {
    await setModelDefault(m.id)
    toast.success(`已将 ${m.display_name || m.name} 设为默认模型`)
    load()
  } catch {
    // 请求层已提示
  }
}

async function confirmDeleteProvider() {
  if (!deletingProvider.value) return
  deleteLoading.value = true
  try {
    await deleteProvider(deletingProvider.value.id)
    toast.success('Provider 已删除')
    deletingProvider.value = null
    load()
  } catch {
    // 请求层已提示
  } finally {
    deleteLoading.value = false
  }
}

async function confirmDeleteModel() {
  if (!deletingModel.value) return
  deleteLoading.value = true
  try {
    await deleteModel(deletingModel.value.id)
    toast.success('模型已删除')
    deletingModel.value = null
    load()
  } catch {
    // 请求层已提示
  } finally {
    deleteLoading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader title="AI 配置" description="配置模型服务商与可用模型">
      <template #actions>
        <Button variant="outline" class="gap-2" @click="openModelCreate">
          <Bot class="size-4" />
          添加模型
        </Button>
        <Button class="gap-2" @click="openProviderCreate">
          <Plus class="size-4" />
          添加 Provider
        </Button>
      </template>
    </PageHeader>

    <LoadingState v-if="loading" variant="list" :rows="3" />

    <template v-else>
      <!-- Providers -->
      <section class="mb-8">
        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold">
          <Server class="size-4 text-primary" />
          Provider（服务商）
        </h2>
        <div v-if="providers.length === 0" class="rounded-2xl border bg-card">
          <EmptyState title="还没有 Provider" description="添加一个 API 服务商开始使用 AI 能力" />
        </div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <div
            v-for="p in providers"
            :key="p.id"
            class="group relative rounded-2xl border bg-card p-5 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
          >
            <div class="flex items-start justify-between">
              <div class="flex items-center gap-3">
                <span class="flex size-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-md">
                  <Cable class="size-5" />
                </span>
                <div>
                  <p class="flex items-center gap-1.5 font-semibold">
                    {{ p.name }}
                    <span
                      v-if="p.is_default"
                      class="inline-flex items-center gap-0.5 rounded-full bg-primary/10 px-1.5 py-px text-[10px] font-medium text-primary"
                    >
                      <Star class="size-2.5" /> 默认
                    </span>
                  </p>
                  <p class="mt-0.5 text-xs text-muted-foreground">{{ p.status ? '已启用' : '已停用' }}</p>
                </div>
              </div>
              <div class="flex gap-0.5 opacity-100 transition-opacity lg:opacity-0 lg:group-hover:opacity-100">
                <Button variant="ghost" size="icon" class="size-8" title="编辑" @click="openProviderEdit(p)">
                  <Pencil class="size-4" />
                </Button>
                <Button variant="ghost" size="icon" class="size-8 text-destructive" title="删除" @click="deletingProvider = p">
                  <Trash2 class="size-4" />
                </Button>
              </div>
            </div>
            <div class="mt-4 space-y-1.5 text-xs text-muted-foreground">
              <p class="flex items-center gap-1.5 truncate">
                <Cable class="size-3.5 shrink-0" />
                <span class="truncate">{{ p.base_url }}</span>
              </p>
              <p class="flex items-center gap-1.5">
                <KeyRound class="size-3.5 shrink-0" />
                {{ p.api_key || '未设置密钥' }}
              </p>
            </div>
            <div class="mt-4 flex items-center justify-between border-t pt-3">
              <div class="flex items-center gap-2">
                <span class="text-[11px] text-muted-foreground">启用</span>
                <Switch :checked="p.status === 1" @update:checked="toggleProvider(p)" />
              </div>
              <Button
                v-if="!p.is_default"
                variant="ghost"
                size="sm"
                class="gap-1 text-[11px] text-muted-foreground hover:text-primary"
                @click="makeProviderDefault(p)"
              >
                <BadgeCheck class="size-3.5" />
                设为默认
              </Button>
            </div>
          </div>
        </div>
      </section>

      <!-- Models -->
      <section>
        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold">
          <Bot class="size-4 text-primary" />
          模型列表
        </h2>
        <div v-if="models.length === 0" class="rounded-2xl border bg-card">
          <EmptyState title="还没有模型" description="为 Provider 添加可用模型" />
        </div>
        <div v-else class="overflow-hidden rounded-2xl border bg-card shadow-sm">
          <Table>
            <TableHeader>
              <TableRow class="hover:bg-transparent">
                <TableHead>模型名</TableHead>
                <TableHead class="hidden sm:table-cell">Provider</TableHead>
                <TableHead>显示名</TableHead>
                <TableHead class="hidden md:table-cell">max_tokens</TableHead>
                <TableHead class="hidden md:table-cell">temperature</TableHead>
                <TableHead>状态</TableHead>
                <TableHead class="text-right">操作</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="m in models" :key="m.id" class="group">
                <TableCell>
                  <p class="flex items-center gap-1.5 font-medium">
                    {{ m.name }}
                    <span
                      v-if="m.is_default"
                      class="inline-flex items-center gap-0.5 rounded-full bg-primary/10 px-1.5 py-px text-[10px] font-medium text-primary"
                    >
                      <Star class="size-2.5" /> 默认
                    </span>
                  </p>
                </TableCell>
                <TableCell class="hidden sm:table-cell text-xs text-muted-foreground">{{ m.provider_name }}</TableCell>
                <TableCell>{{ m.display_name || '—' }}</TableCell>
                <TableCell class="hidden md:table-cell">{{ m.max_tokens }}</TableCell>
                <TableCell class="hidden md:table-cell">{{ m.temperature }}</TableCell>
                <TableCell>
                  <Switch :checked="m.status === 1" @update:checked="toggleModel(m)" />
                </TableCell>
                <TableCell class="text-right">
                  <div class="flex justify-end gap-1 lg:opacity-0 lg:transition-opacity lg:group-hover:opacity-100">
                    <Button v-if="!m.is_default" variant="ghost" size="icon" class="size-8" title="设为默认" @click="makeModelDefault(m)">
                      <Star class="size-4" />
                    </Button>
                    <Button variant="ghost" size="icon" class="size-8" title="编辑" @click="openModelEdit(m)">
                      <Pencil class="size-4" />
                    </Button>
                    <Button variant="ghost" size="icon" class="size-8 text-destructive" title="删除" @click="deletingModel = m">
                      <Trash2 class="size-4" />
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      </section>
    </template>

    <!-- Provider 表单 -->
    <Dialog v-model:open="providerOpen">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{{ providerEditing ? '编辑 Provider' : '添加 Provider' }}</DialogTitle>
          <DialogDescription>配置 API 服务商信息</DialogDescription>
        </DialogHeader>
        <div class="space-y-4 py-2">
          <div class="space-y-1.5">
            <Label>名称 <span class="text-destructive">*</span></Label>
            <Input v-model="providerForm.name" placeholder="例如：DeepSeek" />
          </div>
          <div class="space-y-1.5">
            <Label>Base URL <span class="text-destructive">*</span></Label>
            <Input v-model="providerForm.base_url" placeholder="https://api.deepseek.com/v1" />
          </div>
          <div class="space-y-1.5">
            <Label>API Key{{ providerEditing ? '（留空表示不变更）' : '' }}</Label>
            <Input v-model="providerForm.api_key" type="password" placeholder="sk-..." />
          </div>
          <div class="flex items-center justify-between rounded-xl border p-3">
            <div>
              <p class="text-sm font-medium">启用</p>
              <p class="text-xs text-muted-foreground">停用后该服务商不可用</p>
            </div>
            <Switch
              :checked="providerForm.status === 1"
              @update:checked="(v: boolean) => (providerForm.status = v ? 1 : 0)"
            />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="providerOpen = false">取消</Button>
          <Button :disabled="providerSaving" @click="saveProvider">
            <LoaderCircle v-if="providerSaving" class="size-4 animate-spin" />
            保存
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Model 表单 -->
    <Dialog v-model:open="modelOpen">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{{ modelEditing ? '编辑模型' : '添加模型' }}</DialogTitle>
          <DialogDescription>为 Provider 添加一个可用模型</DialogDescription>
        </DialogHeader>
        <div class="space-y-4 py-2">
          <div class="space-y-1.5">
            <Label>Provider</Label>
            <Select v-model="modelForm.provider_id">
              <SelectTrigger>
                <SelectValue placeholder="选择服务商" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="p in providers" :key="p.id" :value="p.id">{{ p.name }}</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5">
              <Label>模型名 <span class="text-destructive">*</span></Label>
              <Input v-model="modelForm.name" placeholder="deepseek-chat" />
            </div>
            <div class="space-y-1.5">
              <Label>显示名</Label>
              <Input v-model="modelForm.display_name" placeholder="DeepSeek V3" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5">
              <Label>max_tokens</Label>
              <Input v-model.number="modelForm.max_tokens" type="number" min="256" />
            </div>
            <div class="space-y-1.5">
              <Label>temperature（0-2）</Label>
              <Input v-model.number="modelForm.temperature" type="number" min="0" max="2" step="0.1" />
            </div>
          </div>
          <div class="flex items-center justify-between rounded-xl border p-3">
            <div>
              <p class="text-sm font-medium">启用</p>
              <p class="text-xs text-muted-foreground">停用后该模型不可用</p>
            </div>
            <Switch
              :checked="modelForm.status === 1"
              @update:checked="(v: boolean) => (modelForm.status = v ? 1 : 0)"
            />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="modelOpen = false">取消</Button>
          <Button :disabled="modelSaving" @click="saveModel">
            <LoaderCircle v-if="modelSaving" class="size-4 animate-spin" />
            保存
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <ConfirmDialog
      :open="!!deletingProvider"
      title="删除 Provider"
      :description="`确定要删除「${deletingProvider?.name ?? ''}」吗？其下模型将不可用。`"
      confirm-text="删除"
      :loading="deleteLoading"
      @update:open="(v: boolean) => !v && (deletingProvider = null)"
      @confirm="confirmDeleteProvider"
    />
    <ConfirmDialog
      :open="!!deletingModel"
      title="删除模型"
      :description="`确定要删除「${deletingModel?.name ?? ''}」吗？`"
      confirm-text="删除"
      :loading="deleteLoading"
      @update:open="(v: boolean) => !v && (deletingModel = null)"
      @confirm="confirmDeleteModel"
    />
  </div>
</template>
