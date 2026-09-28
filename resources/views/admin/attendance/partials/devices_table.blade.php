<div class="table-responsive">
    <table class="table table-hover align-middle mb-0 text-nowrap">
        <thead class="table-light">
            <tr>
                <th class="ps-3" style="width: 50px;">Status</th>
                <th>Device SN</th>
                <th>Device Name / Model</th>
                <th>Location / Site Link</th>
                <th>Last Heartbeat</th>
                <th>Total Punches</th>
                <th class="pe-3 text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($deviceList as $device)
            <tr>
                <td class="ps-3">
                    @if($device->isOnline())
                        <span class="badge bg-success-subtle text-success border border-success d-inline-flex align-items-center gap-1" title="Heartbeat received within 2 minutes">
                            <i class="fa-solid fa-circle text-success fa-beat-fade" style="font-size: 7px;"></i>Online
                        </span>
                    @elseif($device->isRecent())
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning d-inline-flex align-items-center gap-1" title="Heartbeat received within 10 minutes">
                            <i class="fa-solid fa-circle text-warning" style="font-size: 7px;"></i>Recent
                        </span>
                    @else
                        <span class="badge bg-secondary-subtle text-muted border d-inline-flex align-items-center gap-1" title="No heartbeat recently">
                            <i class="fa-solid fa-circle text-secondary" style="font-size: 7px;"></i>Offline
                        </span>
                    @endif
                </td>
                <td>
                    <code class="fw-bold fs-6 text-dark bg-light px-2 py-1 rounded border">{{ $device->serial_number }}</code>
                    @if(!$device->is_active)
                        <span class="badge bg-danger-subtle text-danger ms-1">Disabled</span>
                    @endif
                </td>
                <td>
                    <div class="fw-semibold text-dark">{{ $device->name ?: 'Unnamed Device' }}</div>
                    <small class="text-muted">{{ $device->model_name ?: 'ZKTeco ADMS' }} @if($device->ip_address)({{ $device->ip_address }}:{{ $device->port ?: 80 }})@endif</small>
                </td>
                <td>
                    @if($device->device_type === 'site')
                        <span class="badge bg-success text-white py-1 px-2 shadow-xs">
                            <i class="fa-solid fa-helmet-safety me-1"></i>Construction Site
                        </span>
                        <div class="fw-semibold text-success small mt-1">
                            <i class="fa-solid fa-building-flag me-1"></i>{{ $device->project->name ?? 'Unlinked Project' }}
                        </div>
                        @if($device->location)
                            <div class="text-muted small"><i class="fa-solid fa-location-dot me-1"></i>{{ $device->location }}</div>
                        @endif
                    @else
                        <span class="badge bg-primary text-white py-1 px-2 shadow-xs">
                            <i class="fa-solid fa-building me-1"></i>Head Office
                        </span>
                        @if($device->location)
                            <div class="text-muted small mt-1"><i class="fa-solid fa-location-dot me-1"></i>{{ $device->location }}</div>
                        @else
                            <div class="text-muted small mt-1">Main Headquarters</div>
                        @endif
                    @endif
                </td>
                <td>
                    @if($device->last_seen_at)
                        <span class="fw-semibold text-dark">{{ $device->last_seen_at->diffForHumans() }}</span>
                        <br><small class="text-muted">{{ $device->last_seen_at->format('d M Y, h:i A') }}</small>
                    @else
                        <span class="text-muted">Never connected</span>
                    @endif
                </td>
                <td>
                    <span class="badge bg-info-subtle text-dark border px-2 py-1 fw-bold">
                        <i class="fa-solid fa-fingerprint text-info me-1"></i>{{ number_format($device->punches_count ?? $device->punches()->count() ?? 0) }}
                    </span>
                </td>
                <td class="pe-3 text-end">
                    <div class="btn-group btn-group-sm">
                        <a href="{{ route('admin.attendance.device-logs') }}?device_sn={{ urlencode($device->serial_number) }}"
                           class="btn btn-outline-info"
                           title="Filter Punches From This Device">
                            <i class="fa-solid fa-filter"></i>
                        </a>
                        <button type="button" class="btn btn-outline-primary"
                                title="Edit Device / Change Location Link"
                                onclick="openEditDeviceModal({{ json_encode([
                                    'id'            => $device->id,
                                    'serial_number' => $device->serial_number,
                                    'name'          => $device->name,
                                    'device_type'   => $device->device_type,
                                    'project_id'    => $device->project_id,
                                    'location'      => $device->location,
                                    'model_name'    => $device->model_name,
                                    'ip_address'    => $device->ip_address,
                                    'port'          => $device->port,
                                    'is_active'     => $device->is_active ? 1 : 0,
                                    'notes'         => $device->notes
                                ]) }})">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger"
                                title="Delete Device Registration"
                                onclick="openDeleteDeviceModal({{ $device->id }}, '{{ addslashes($device->name ?: $device->serial_number) }}', '{{ $device->serial_number }}')">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center py-4 text-muted">
                    <i class="fa-solid fa-network-wired fa-2x mb-2 d-block opacity-25"></i>
                    No devices in this category.
                    <br>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="openCreateDeviceModal()">
                        <i class="fa-solid fa-plus me-1"></i>+ Link / Register Device
                    </button>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
