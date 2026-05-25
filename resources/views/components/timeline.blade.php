<div class="audited-timeline flow-root">
    @if($logs->isEmpty())
        <p class="audited-timeline-empty text-sm text-gray-500 dark:text-gray-400">No audit history found.</p>
    @else
        <ul role="list" class="-mb-8">
            @foreach($logs as $log)
                <li class="audited-timeline-entry">
                    <div class="relative pb-8">
                        @unless($loop->last)
                            <span class="audited-timeline-connector absolute left-4 top-4 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-700" aria-hidden="true"></span>
                        @endunless

                        <div class="relative flex space-x-3">
                            <div>
                                <span class="audited-timeline-dot flex h-8 w-8 items-center justify-center rounded-full ring-8 ring-white dark:ring-gray-900 bg-gray-50 dark:bg-gray-800">
                                    <span class="h-2 w-2 rounded-full bg-gray-400 dark:bg-gray-500"></span>
                                </span>
                            </div>

                            <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                <div class="min-w-0">
                                    <span class="audited-action-badge inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $log->action_badge_color }}">
                                        {{ $log->action_label }}
                                    </span>
                                    <span class="audited-module-label ml-1 text-xs text-gray-500 dark:text-gray-400">{{ $log->module }}</span>

                                    <p class="audited-timeline-description mt-0.5 text-sm text-gray-900 dark:text-gray-100">
                                        {{ $log->description }}
                                    </p>

                                    @if($log->user_name)
                                        <p class="audited-timeline-actor mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $log->user_name }}
                                            @if($log->causer_type && $log->causer_type !== 'user')
                                                <span class="audited-causer-badge inline-flex items-center rounded px-1 py-0.5 text-xs font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                                                    {{ $log->causer_type }}
                                                </span>
                                            @endif
                                        </p>
                                    @endif

                                    @if($showValues && ($log->old_values || $log->new_values))
                                        <div class="audited-values-diff mt-2 overflow-hidden rounded border border-gray-200 dark:border-gray-700">
                                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs">
                                                <thead class="bg-gray-50 dark:bg-gray-800">
                                                    <tr>
                                                        <th class="px-2 py-1 text-left font-medium text-gray-500 dark:text-gray-400">Field</th>
                                                        <th class="px-2 py-1 text-left font-medium text-gray-500 dark:text-gray-400">Before</th>
                                                        <th class="px-2 py-1 text-left font-medium text-gray-500 dark:text-gray-400">After</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                                                    @foreach(array_unique(array_merge(array_keys($log->old_values ?? []), array_keys($log->new_values ?? []))) as $field)
                                                        <tr>
                                                            <td class="audited-values-diff-field px-2 py-1 font-mono text-gray-600 dark:text-gray-400">{{ $field }}</td>
                                                            <td class="audited-values-diff-before px-2 py-1 text-red-600 dark:text-red-400">{{ $log->old_values[$field] ?? '—' }}</td>
                                                            <td class="audited-values-diff-after px-2 py-1 text-green-600 dark:text-green-400">{{ $log->new_values[$field] ?? '—' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </div>

                                <div class="shrink-0 whitespace-nowrap text-right text-xs text-gray-500 dark:text-gray-400">
                                    <time class="audited-timestamp"
                                          datetime="{{ $log->created_at->toIso8601String() }}"
                                          title="{{ $log->created_at->format('M j, Y g:i A') }}">
                                        {{ $log->created_at->diffForHumans() }}
                                    </time>
                                </div>
                            </div>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
