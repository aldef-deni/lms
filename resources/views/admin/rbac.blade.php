@extends('layouts.app')
@section('title','Roles & permissions')
@section('content')
<div class="page-head"><div><span class="eyebrow" style="color:#9195aa;font-size:9px">ACCESS CONTROL</span><h1>Roles & permissions</h1><p>Control what each workspace role can access. Super Admin always retains full system access.</p></div><a class="btn secondary" href="/manage/users">Manage users</a></div>
<div class="stack">
@foreach($roles as $role)
<form class="card pad" method="POST" action="/rbac/roles/{{ $role->id }}">@csrf @method('PUT')
<div class="row between" style="margin-bottom:20px"><div><h2 style="font-size:18px">{{ $role->name }}</h2><small>{{ $role->permissions->count() }} permissions assigned</small></div><button class="btn small">Save permissions</button></div>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(230px,1fr));align-items:start">
@foreach($permissions as $group => $items)<div><strong style="display:block;margin-bottom:10px;text-transform:capitalize">{{ str_replace('-',' ',$group) }}</strong><div class="stack" style="gap:8px">@foreach($items as $permission)<label style="font-size:12px"><input type="checkbox" name="permissions[]" value="{{ $permission->name }}" @checked($role->hasPermissionTo($permission))> {{ str_replace(['.','-'],' ',$permission->name) }}</label>@endforeach</div></div>@endforeach
</div></form>
@endforeach
</div>
@endsection
