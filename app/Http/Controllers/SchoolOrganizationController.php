<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SchoolOrganizationSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SchoolOrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasAnyRole(['system_admin']), 403);

        return Inertia::render('admin/SchoolOrganization', [
            'departments' => DB::table('departments')->whereNotNull('external_code')->orderBy('external_code')
                ->get(['external_code', 'name', 'parent_id', 'is_active']),
            'positions' => DB::table('school_positions')->orderBy('code')->get(['id', 'code', 'name']),
            'positionRoles' => DB::table('school_position_roles')->join('roles', 'roles.id', '=', 'school_position_roles.role_id')
                ->join('school_positions', 'school_positions.id', '=', 'school_position_roles.school_position_id')
                ->get(['school_positions.code as position_code', 'roles.code as role_code']),
            'roles' => DB::table('roles')->orderBy('name')->get(['code', 'name']),
            'runs' => DB::table('organization_sync_runs')->orderByDesc('id')->limit(10)->get(),
        ]);
    }

    public function sync(Request $request, SchoolOrganizationSync $sync): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['system_admin']), 403);
        $data = $request->validate([
            'complete_snapshot' => ['required', 'boolean'],
            'departments' => ['required', 'array', 'min:1', 'max:10000'],
            'departments.*.code' => ['required', 'string', 'max:64', 'distinct'],
            'departments.*.name' => ['required', 'string', 'max:255'],
            'departments.*.parent_code' => ['nullable', 'string', 'max:64'],
            'departments.*.active' => ['sometimes', 'boolean'],
            'positions' => ['required', 'array', 'min:1', 'max:10000'],
            'positions.*.code' => ['required', 'string', 'max:64', 'distinct'],
            'positions.*.name' => ['required', 'string', 'max:255'],
            'people' => ['required', 'array', 'min:1', 'max:50000'],
            'people.*.account' => ['required', 'string', 'max:128', 'distinct'],
            'people.*.name' => ['required', 'string', 'max:255'],
            'people.*.email' => ['nullable', 'email', 'max:255', 'distinct'],
            'people.*.department_code' => ['required', 'string', 'max:64'],
            'people.*.position_code' => ['required', 'string', 'max:64'],
            'people.*.active' => ['required', 'boolean'],
        ]);

        $result = $sync->sync($data, $request->user());

        return $request->header('X-Inertia')
            ? to_route('organization.index')->with('status', "已同步 {$result['users_created']} 位新人员，更新 {$result['users_updated']} 位人员。")
            : response()->json($result);
    }

    public function updatePositionRoles(Request $request, string $code, SchoolOrganizationSync $sync): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['system_admin']), 403);
        $position = DB::table('school_positions')->where('code', $code)->first();
        abort_if($position === null, 404);
        $data = $request->validate([
            'role_codes' => ['present', 'array'],
            'role_codes.*' => ['string', 'distinct', Rule::exists('roles', 'code')],
        ]);
        DB::transaction(function () use ($position, $data, $sync) {
            DB::table('school_position_roles')->where('school_position_id', $position->id)->delete();
            foreach ($data['role_codes'] as $code) {
                DB::table('school_position_roles')->insert([
                    'school_position_id' => $position->id,
                    'role_id' => DB::table('roles')->where('code', $code)->value('id'),
                ]);
            }
            foreach (User::query()->where('school_position_id', $position->id)->get() as $user) {
                $sync->syncRoles($user, $position->id);
            }
        });

        return $request->header('X-Inertia')
            ? to_route('organization.index')->with('status', '岗位角色映射已更新。')
            : response()->json(['updated' => true]);
    }
}
