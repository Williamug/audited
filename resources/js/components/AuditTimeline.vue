<template>
  <div class="audited-timeline flow-root">

    <!-- Loading -->
    <p v-if="loading" class="text-sm text-gray-400 dark:text-gray-500">Loading…</p>

    <!-- Error -->
    <p v-else-if="fetchError" class="audited-timeline-error text-sm text-red-500 dark:text-red-400">
      Failed to load timeline ({{ fetchError }}). Check that <code>AUDIT_API_ROUTES=true</code> is set.
    </p>

    <!-- Empty -->
    <p v-else-if="!rows.length" class="audited-timeline-empty text-sm text-gray-500 dark:text-gray-400">
      No audit history found.
    </p>

    <template v-else>
      <ul role="list" class="-mb-8">
        <li v-for="(log, index) in rows" :key="log.id" class="audited-timeline-entry">
          <div class="relative pb-8">
            <span
              v-if="index < rows.length - 1 || (pagination && pagination.next_page_url)"
              class="audited-timeline-connector absolute left-4 top-4 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-700"
              aria-hidden="true"
            ></span>

            <div class="relative flex space-x-3">
              <div>
                <span class="audited-timeline-dot flex h-8 w-8 items-center justify-center rounded-full ring-8 ring-white dark:ring-gray-900 bg-gray-50 dark:bg-gray-800">
                  <span class="h-2 w-2 rounded-full bg-gray-400 dark:bg-gray-500"></span>
                </span>
              </div>

              <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                <div class="min-w-0">
                  <span
                    class="audited-action-badge inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="log.action_badge_color"
                  >
                    {{ log.action_label }}
                  </span>
                  <span class="audited-module-label ml-1 text-xs text-gray-500 dark:text-gray-400">{{ log.module }}</span>

                  <p class="audited-timeline-description mt-0.5 text-sm text-gray-900 dark:text-gray-100">
                    {{ log.description }}
                  </p>

                  <p v-if="log.user_name" class="audited-timeline-actor mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    {{ log.user_name }}
                    <span
                      v-if="log.causer_type && log.causer_type !== 'user'"
                      class="audited-causer-badge inline-flex items-center rounded px-1 py-0.5 text-xs font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400"
                    >
                      {{ log.causer_type }}
                    </span>
                  </p>

                  <!-- Values diff -->
                  <div v-if="showValues && (log.old_values || log.new_values)" class="audited-values-diff mt-2 overflow-hidden rounded border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs">
                      <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                          <th class="px-2 py-1 text-left font-medium text-gray-500 dark:text-gray-400">Field</th>
                          <th class="px-2 py-1 text-left font-medium text-gray-500 dark:text-gray-400">Before</th>
                          <th class="px-2 py-1 text-left font-medium text-gray-500 dark:text-gray-400">After</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        <tr v-for="field in diffFields(log)" :key="field">
                          <td class="audited-values-diff-field px-2 py-1 font-mono text-gray-600 dark:text-gray-400">{{ field }}</td>
                          <td class="audited-values-diff-before px-2 py-1 text-red-600 dark:text-red-400">{{ log.old_values?.[field] ?? '—' }}</td>
                          <td class="audited-values-diff-after px-2 py-1 text-green-600 dark:text-green-400">{{ log.new_values?.[field] ?? '—' }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <div class="shrink-0 whitespace-nowrap text-right text-xs text-gray-500 dark:text-gray-400">
                  <time
                    class="audited-timestamp"
                    :datetime="log.created_at"
                    :title="formatAbsolute(log.created_at)"
                  >
                    {{ formatRelative(log.created_at) }}
                  </time>
                </div>
              </div>
            </div>
          </div>
        </li>
      </ul>

      <!-- Pagination (only in self-fetch or when pagination object is passed) -->
      <div v-if="pagination && pagination.last_page > 1" class="mt-4 flex justify-end gap-1">
        <button
          v-for="link in pagination.links"
          :key="link.label"
          @click="goToPage(link)"
          :disabled="!link.url || link.active"
          class="rounded border px-2.5 py-1 text-xs"
          :class="link.active
            ? 'border-blue-500 bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400'
            : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50 disabled:opacity-40 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400'"
          v-html="link.label"
        />
      </div>
    </template>

  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'

const props = defineProps({
  // --- Props / Inertia mode ---
  // Pass a paginator object (from auditTimelineProps()) or a plain array of log items.
  logs:        { type: [Object, Array], default: null },

  // --- Self-fetch mode ---
  // Provide subjectType + subjectId; the component fetches from endpoint.
  subjectType: { type: String,  default: null },
  subjectId:   { type: [String, Number], default: null },
  endpoint:    { type: String,  default: '/audited/api/timeline' },

  // --- Shared options ---
  showValues:  { type: Boolean, default: false },
  perPage:     { type: Number,  default: 10 },
})

const emit = defineEmits(['page-change'])

// ── State ─────────────────────────────────────────────────────────────────────

const selfFetch   = computed(() => props.logs === null && props.subjectType !== null)
const fetchedData = ref(null)
const loading     = ref(false)
const fetchError  = ref(null)

// ── Computed ──────────────────────────────────────────────────────────────────

// Support both plain array and paginator object from props.logs
const source     = computed(() => selfFetch.value ? fetchedData.value : props.logs)
const rows       = computed(() => {
  if (!source.value) return []
  return Array.isArray(source.value) ? source.value : (source.value.data ?? [])
})
const pagination = computed(() => {
  if (!source.value || Array.isArray(source.value)) return null
  return source.value
})

// ── Helpers ───────────────────────────────────────────────────────────────────

function diffFields(log) {
  return [...new Set([
    ...Object.keys(log.old_values ?? {}),
    ...Object.keys(log.new_values ?? {}),
  ])]
}

function formatRelative(ts) {
  const diff = Math.round((Date.now() - new Date(ts)) / 1000)
  if (diff < 60)   return `${diff}s ago`
  if (diff < 3600) return `${Math.round(diff / 60)}m ago`
  if (diff < 86400)return `${Math.round(diff / 3600)}h ago`
  return `${Math.round(diff / 86400)}d ago`
}

function formatAbsolute(ts) {
  return new Date(ts).toLocaleString('en-GB', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

// ── Self-fetch ────────────────────────────────────────────────────────────────

async function doFetch(page = 1) {
  loading.value = true
  fetchError.value = null
  const params = new URLSearchParams({
    subject_type: props.subjectType,
    subject_id:   props.subjectId,
    perPage:      props.perPage,
    page,
  })
  try {
    const res = await fetch(`${props.endpoint}?${params}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
      credentials: 'same-origin',
    })
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const data = await res.json()
    fetchedData.value = data.logs
  } catch (err) {
    fetchError.value = err.message
  } finally {
    loading.value = false
  }
}

function goToPage(link) {
  if (!link.url || link.active) return
  if (selfFetch.value) {
    const page = new URL(link.url).searchParams.get('page') ?? 1
    doFetch(page)
  } else {
    emit('page-change', link.url)
  }
}

watch(() => [props.subjectType, props.subjectId], () => {
  if (selfFetch.value) doFetch()
})

onMounted(() => { if (selfFetch.value) doFetch() })
</script>
