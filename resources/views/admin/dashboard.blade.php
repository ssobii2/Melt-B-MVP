@extends('admin.layouts.app')

@section('title', 'MELT-B Admin Dashboard')

@section('content_header')
<h1>
    <i class="fas fa-thermometer-half text-primary"></i>
    MELT-B Admin Dashboard
    <small class="text-muted">Thermal Data Management System</small>
</h1>
@stop

@section('content')
<div class="row">
    <!-- Statistics Cards -->
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3>{{ $stats['total_users'] }}</h3>
                <p>Total Users</p>
            </div>
            <div class="icon">
                <i class="fas fa-users"></i>
            </div>
            <a href="{{ route('admin.users') }}" class="small-box-footer">
                View Details <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3>{{ $stats['total_datasets'] }}</h3>
                <p>Datasets</p>
            </div>
            <div class="icon">
                <i class="fas fa-database"></i>
            </div>
            <a href="{{ route('admin.datasets') }}" class="small-box-footer">
                Manage Datasets <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>{{ $stats['total_buildings'] }}</h3>
                <p>Buildings</p>
            </div>
            <div class="icon">
                <i class="fas fa-building"></i>
            </div>
            <a href="{{ route('admin.buildings') }}" class="small-box-footer">
                View Buildings <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box bg-danger">
            <div class="inner">
                <h3>{{ $stats['total_entitlements'] }}</h3>
                <p>Entitlements</p>
            </div>
            <div class="icon">
                <i class="fas fa-key"></i>
            </div>
            <a href="{{ route('admin.entitlements') }}" class="small-box-footer">
                Manage Access <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
</div>

<div class="row">
    <!-- Users by Role Chart -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user-tag"></i>
                    Users by Role
                </h3>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Count</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($stats['users_by_role'] as $role => $count)
                        <tr>
                            <td>
                                <span class="badge badge-primary">{{ ucfirst($role) }}</span>
                            </td>
                            <td>{{ $count }}</td>
                            <td>
                                @php
                                $percentage = round(($count / $stats['total_users']) * 100, 1);
                                @endphp
                                <div class="progress progress-xs">
                                    <div class="progress-bar bg-primary" style="width: {{ $percentage }}%"></div>
                                </div>
                                {{ $percentage }}%
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Registrations -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user-plus"></i>
                    Recent Registrations
                </h3>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stats['recent_registrations'] as $user)
                        <tr>
                            <td>
                                <strong>{{ $user->name }}</strong>
                                <br>
                                <small class="text-muted">{{ $user->email }}</small>
                            </td>
                            <td>
                                <span class="badge badge-info">{{ ucfirst($user->role) }}</span>
                            </td>
                            <td>
                                <small>{{ $user->created_at->diffForHumans() }}</small>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted">
                                No recent registrations
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Activity Log -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-history"></i>
                    Recent Activity
                </h3>
                <div class="card-tools">
                    <a href="{{ route('admin.audit-logs') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-eye"></i> View All Logs
                    </a>
                </div>
            </div>
            <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                <table class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Action</th>
                            <th>Target</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stats['recent_audit_logs'] as $log)
                        <tr>
                            <td>
                                <strong>{{ $log->user->name ?? 'System' }}</strong>
                                <br>
                                <small class="text-muted">{{ $log->user->email ?? 'system@admin' }}</small>
                            </td>
                            <td>
                                <span class="badge badge-secondary">{{ str_replace('_', ' ', ucfirst($log->action)) }}</span>
                            </td>
                            <td>
                                @if($log->target_type)
                                {{ ucfirst($log->target_type) }} #{{ $log->target_id }}
                                @else
                                -
                                @endif
                            </td>
                            <td>
                                <small>{{ $log->created_at->diffForHumans() }}</small>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">
                                No recent activity
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@stop

@push('js')
<script>
    console.log('MELT-B Admin Dashboard loaded');

    // Auto-refresh dashboard every 5 minutes
    setTimeout(function() {
        location.reload();
    }, 300000);
</script>
@endpush