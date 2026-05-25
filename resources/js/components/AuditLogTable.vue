<template>
  <div class="audited-log-table space-y-4">

    <!-- Filters -->
    <div class="audited-log-table-filters rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <!-- Search -->
        <div class="relative sm:col-span-1">
          <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
          </div>
          <input
            v-model="search"
            type="text"
            placeholder="Search user, description, IP address..."
            class="audited-log-table-search block w-full rounded-md border-0 py-2 pl-9 pr-3 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600 dark:placeholder:text-gray-500"
          />
        </div>

        <select v-model="selectedAction" class="audited-log-table-select rounded-md border-0 py-2 pl-3 pr-8 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-blue-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
          <option value="">All Actions</option>
          <option v-for="a in displayActions" :key="a" :value="a">{{ formatAction(a) }}</option>
        </select>

        <select v-model="selectedModule" class="audited-log-table-select rounded-md border-0 py-2 pl-3 pr-8 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-blue-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
          <option value="">All Modules</option>
          <option v-for="m in displayModules" :key="m" :value="m">{{ m }}</option>
        </select>
      </div>

      <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-4">
        <select v-model="selectedLevel" class="audited-log-table-select rounded-md border-0 py-2 pl-3 pr-8 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-blue-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
          <option value="">All Levels</option>
          <option v-for="l in displayLevels" :key="l" :value="l">{{ l }}</option>
        </select>

        <select v-model="selectedPlatform" class="audited-log-table-select rounded-md border-0 py-2 pl-3 pr-8 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-blue-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600">
          <option value="">All Platforms</option>
          <option value="web">Web</option>
          <option value="mobile">Mobile</option>
          <option value="cli">CLI</option>
        </select>

        <input v-model="dateFrom" type="date" class="audited-log-table-date rounded-md border-0 py-2 pl-3 pr-3 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-blue-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600" />
        <input v-model="dateTo"   type="date" class="audited-log-table-date rounded-md border-0 py-2 pl-3 pr-3 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-blue-500 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-600" />
      </div>

      <div v-if="hasActiveFilters" class="mt-3">
        <button @click="clearFilters" class="audited-log-table-clear inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
          <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
          </svg>
          Clear Filters
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm dark:border-gray-700">
      <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
          <tr>
            <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Date &amp; Time</th>
            <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">User</th>
            <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Level</th>
            <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Action</th>
            <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Module</th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Description</th>
            <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Platform</th>
            <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">IP Address</th>
            <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Device</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>

        <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-700/50 dark:bg-gray-900">
          <!-- Loading skeleton -->
          <tr v-if="loading">
            <td colspan="10" class="px-4 py-10 text-center text-sm text-gray-400 dark:text-gray-500">
              Loading…
            </td>
          </tr>

          <!-- Empty state -->
          <tr v-else-if="!rows.length">
            <td colspan="10" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
              No audit logs found.
            </td>
          </tr>

          <template v-else v-for="log in rows" :key="log.id">
            <!-- Main row -->
            <tr
              class="audited-log-table-row hover:bg-gray-50 dark:hover:bg-gray-800/50"
              :class="{ 'audited-log-table-row--expanded bg-blue-50 dark:bg-blue-900/10': expandedId === log.id }"
            >
              <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                <div>{{ formatDate(log.created_at) }}</div>
                <div class="text-xs text-gray-400 dark:text-gray-500">{{ formatTime(log.created_at) }}</div>
              </td>

              <td class="px-4 py-3">
                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ log.user_name ?? '—' }}</div>
                <span v-if="log.causer_type && log.causer_type !== 'user'" class="audited-causer-badge inline-flex items-center rounded px-1 py-0.5 text-xs font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                  {{ log.causer_type }}
                </span>
              </td>

              <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ log.user_level ?? '—' }}</td>

              <td class="whitespace-nowrap px-4 py-3">
                <span class="audited-action-badge inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :class="log.action_badge_color">
                  {{ log.action_label }}
                </span>
              </td>

              <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ log.module }}</td>

              <td class="max-w-xs px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ log.description }}</td>

              <td class="whitespace-nowrap px-4 py-3">
                <span v-if="log.platform === 'web'" class="audited-platform-badge audited-platform-badge--web inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                  <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2H7.414l-1.707 1.707A1 1 0 014 16v-1H3a2 2 0 01-2-2V5zm5 3a1 1 0 000 2h4a1 1 0 100-2H8z" clip-rule="evenodd"/></svg>
                  Web
                </span>
                <span v-else-if="log.platform === 'mobile'" class="audited-platform-badge audited-platform-badge--mobile inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                  <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM3 4a1 1 0 011-1h8a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm2 1v8h6V5H5z"/></svg>
                  Mobile
                </span>
                <span v-else-if="log.platform === 'cli'" class="audited-platform-badge audited-platform-badge--cli inline-flex items-center gap-1 rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-600 dark:bg-orange-900/30 dark:text-orange-400">
                  <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2 5a2 2 0 012-2h12a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5zm3.293 1.293a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 01-1.414-1.414L7.586 10 5.293 7.707a1 1 0 010-1.414zM11 12a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>
                  CLI
                </span>
                <span v-else class="text-sm text-gray-400">—</span>
              </td>

              <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ log.ip_address ?? '—' }}</td>

              <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-500 dark:text-gray-400" :title="log.user_agent">
                {{ log.user_agent ? log.user_agent.slice(0, 28) + (log.user_agent.length > 28 ? '…' : '') : '—' }}
              </td>

              <td class="whitespace-nowrap px-4 py-3 text-right">
                <button
                  @click="toggleExpand(log.id)"
                  class="audited-log-table-toggle rounded border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
                >
                  {{ expandedId === log.id ? 'Close' : 'View' }}
                </button>
              </td>
            </tr>

            <!-- Expanded detail row -->
            <tr v-if="expandedId === log.id" class="audited-log-table-detail bg-blue-50/50 dark:bg-blue-900/10">
              <td colspan="10" class="px-6 py-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                  <!-- Request context -->
                  <div class="audited-log-table-detail-context space-y-1.5">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Request Context</h4>
                    <dl class="space-y-1 text-sm">
                      <div v-if="log.request_id" class="flex gap-2">
                        <dt class="w-24 shrink-0 text-gray-500 dark:text-gray-400">Request ID</dt>
                        <dd class="font-mono text-xs text-gray-700 dark:text-gray-300">{{ log.request_id }}</dd>
                      </div>
                      <div v-if="log.route_name" class="flex gap-2">
                        <dt class="w-24 shrink-0 text-gray-500 dark:text-gray-400">Route</dt>
                        <dd class="font-mono text-xs text-gray-700 dark:text-gray-300">{{ log.route_name }}</dd>
                      </div>
                      <div v-if="log.url" class="flex gap-2">
                        <dt class="w-24 shrink-0 text-gray-500 dark:text-gray-400">URL</dt>
                        <dd class="break-all text-xs text-gray-700 dark:text-gray-300">{{ log.http_method }} {{ log.url }}</dd>
                      </div>
                      <div v-if="log.auth_guard" class="flex gap-2">
                        <dt class="w-24 shrink-0 text-gray-500 dark:text-gray-400">Guard</dt>
                        <dd class="text-gray-700 dark:text-gray-300">{{ log.auth_guard }}</dd>
                      </div>
                      <div v-if="log.causer_type" class="flex gap-2">
                        <dt class="w-24 shrink-0 text-gray-500 dark:text-gray-400">Actor type</dt>
                        <dd class="text-gray-700 dark:text-gray-300">{{ log.causer_type }}</dd>
                      </div>
                      <div v-if="log.user_agent" class="flex gap-2">
                        <dt class="w-24 shrink-0 text-gray-500 dark:text-gray-400">User agent</dt>
                        <dd class="break-all text-xs text-gray-600 dark:text-gray-400">{{ log.user_agent }}</dd>
                      </div>
                    </dl>
                  </div>

                  <!-- Tags -->
                  <div v-if="log.tags && Object.keys(log.tags).length" class="audited-log-table-detail-tags space-y-1.5">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tags</h4>
                    <dl class="space-y-1 text-sm">
                      <div v-for="(value, key) in log.tags" :key="key" class="flex gap-2">
                        <dt class="shrink-0 font-mono text-xs text-gray-500 dark:text-gray-400">{{ key }}</dt>
                        <dd class="font-mono text-xs text-gray-700 dark:text-gray-300">{{ value }}</dd>
                      </div>
                    </dl>
                  </div>

                  <!-- Old / New values diff -->
                  <div v-if="log.old_values || log.new_values" class="audited-values-diff space-y-1.5 sm:col-span-2">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Changes</h4>
                    <div class="overflow-hidden rounded border border-gray-200 dark:border-gray-700">
                      <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                          <tr>
                            <th class="px-3 py-1.5 text-left font-medium text-gray-500 dark:text-gray-400">Field</th>
                            <th class="px-3 py-1.5 text-left font-medium text-gray-500 dark:text-gray-400">Before</th>
                            <th class="px-3 py-1.5 text-left font-medium text-gray-500 dark:text-gray-400">After</th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                          <tr v-for="field in diffFields(log)" :key="field">
                            <td class="audited-values-diff-field px-3 py-1.5 font-mono text-gray-600 dark:text-gray-400">{{ field }}</td>
                            <td class="audited-values-diff-before px-3 py-1.5 text-red-600 dark:text-red-400">{{ log.old_values?.[field] ?? '—' }}</td>
                            <td class="audited-values-diff-after px-3 py-1.5 text-green-600 dark:text-green-400">{{ log.new_values?.[field] ?? '—' }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>

                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="pagination && pagination.last_page > 1" class="flex items-center justify-between">
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }} entries
      </p>
      <div class="flex gap-1">
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
    </div>

  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'

const props = defineProps({
  // --- Props / Inertia mode (pass logs from your controller) ---
  logs:       { type: Object,  default: null },
  allActions: { type: Array,   default: () => [] },
  allModules: { type: Array,   default: () => [] },
  allLevels:  { type: Array,   default: () => [] },
  filters:    { type: Object,  default: () => ({}) },

  // --- Self-fetch mode (leave logs null, component fetches from endpoint) ---
  endpoint:   { type: String,  default: '/audited/api/logs' },
})

const emit = defineEmits(['filter-change', 'page-change'])

// ── State ─────────────────────────────────────────────────────────────────────

const selfFetch      = computed(() => props.logs === null)
const fetchedData    = ref(null)
const fetchedActions = ref([])
const fetchedModules = ref([])
const fetchedLevels  = ref([])
const loading        = ref(false)

const search          = ref(props.filters?.search   ?? '')
const selectedAction  = ref(props.filters?.action   ?? '')
const selectedModule  = ref(props.filters?.module   ?? '')
const selectedLevel   = ref(props.filters?.level    ?? '')
const selectedPlatform= ref(props.filters?.platform ?? '')
const dateFrom        = ref(props.filters?.dateFrom ?? '')
const dateTo          = ref(props.filters?.dateTo   ?? '')
const expandedId      = ref(null)

// ── Computed ──────────────────────────────────────────────────────────────────

const source       = computed(() => selfFetch.value ? fetchedData.value : props.logs)
const rows         = computed(() => source.value?.data ?? [])
const pagination   = computed(() => source.value)
const displayActions = computed(() => selfFetch.value ? fetchedActions.value : props.allActions)
const displayModules = computed(() => selfFetch.value ? fetchedModules.value : props.allModules)
const displayLevels  = computed(() => selfFetch.value ? fetchedLevels.value  : props.allLevels)

const hasActiveFilters = computed(() =>
  search.value || selectedAction.value || selectedModule.value ||
  selectedLevel.value || selectedPlatform.value || dateFrom.value || dateTo.value
)

const currentFilters = computed(() => ({
  search:   search.value,
  action:   selectedAction.value,
  module:   selectedModule.value,
  level:    selectedLevel.value,
  platform: selectedPlatform.value,
  dateFrom: dateFrom.value,
  dateTo:   dateTo.value,
}))

// ── Helpers ───────────────────────────────────────────────────────────────────

function formatAction(value) {
  return value.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

function formatDate(ts) {
  return new Date(ts).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatTime(ts) {
  return new Date(ts).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}

function diffFields(log) {
  return [...new Set([
    ...Object.keys(log.old_values ?? {}),
    ...Object.keys(log.new_values ?? {}),
  ])]
}

function toggleExpand(id) {
  expandedId.value = expandedId.value === id ? null : id
}

function clearFilters() {
  search.value = selectedAction.value = selectedModule.value = ''
  selectedLevel.value = selectedPlatform.value = dateFrom.value = dateTo.value = ''
}

// ── Self-fetch ────────────────────────────────────────────────────────────────

async function doFetch(page = 1) {
  loading.value = true
  const params = new URLSearchParams({ ...currentFilters.value, perPage: 15, page })
  try {
    const res = await fetch(`${props.endpoint}?${params}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
      credentials: 'same-origin',
    })
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const data = await res.json()
    fetchedData.value    = data.logs
    fetchedActions.value = data.allActions ?? []
    fetchedModules.value = data.allModules ?? []
    fetchedLevels.value  = data.allLevels  ?? []
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

// ── Watchers ──────────────────────────────────────────────────────────────────

let searchTimer = null
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    if (selfFetch.value) doFetch(1)
    else emit('filter-change', currentFilters.value)
  }, 300)
})

watch(
  [selectedAction, selectedModule, selectedLevel, selectedPlatform, dateFrom, dateTo],
  () => {
    if (selfFetch.value) doFetch(1)
    else emit('filter-change', currentFilters.value)
  }
)

onMounted(() => { if (selfFetch.value) doFetch() })
</script>
