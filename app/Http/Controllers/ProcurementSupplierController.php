<?php

namespace App\Http\Controllers;

use App\Models\ProcurementSupplier;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementSupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $search = $request->validate(['search' => ['nullable', 'string', 'max:200']])['search'] ?? '';
        $manager = $this->canManageAll($user);
        $headDepartments = $this->headDepartments($user);
        $suppliers = ProcurementSupplier::query()
            ->leftJoin('departments', 'departments.id', '=', 'procurement_suppliers.department_id')
            ->when($search !== '', fn ($query) => $query->where('procurement_suppliers.name', 'like', '%'.$search.'%'))
            ->orderByDesc('procurement_suppliers.is_active')->orderBy('procurement_suppliers.name')->orderBy('procurement_suppliers.id')
            ->select('procurement_suppliers.*', 'departments.name as department_name')
            ->paginate(20)->withQueryString()
            ->through(fn (ProcurementSupplier $supplier) => [
                ...$supplier->only(['id', 'name', 'contact_name', 'contact_phone', 'department_name', 'is_active']),
                'can_edit' => $this->canEdit($user, $supplier, $manager, $headDepartments),
            ]);

        return Inertia::render('procurement/Suppliers', [
            'suppliers' => $suppliers,
            'search' => $search,
            'canCreate' => $this->canCreate($user),
            'canChangeStatus' => $manager,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canCreate($user), 403);
        $supplier = new ProcurementSupplier(['department_id' => $user->department_id, 'created_by' => $user->id]);
        $this->save($request, $supplier);

        return to_route('procurement.suppliers.index')->with('status', '供应商已新增。');
    }

    public function update(Request $request, ProcurementSupplier $supplier): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canEdit($user, $supplier, $this->canManageAll($user), $this->headDepartments($user)), 403);
        $this->save($request, $supplier);

        return back()->with('status', '供应商已保存。');
    }

    public function status(Request $request, ProcurementSupplier $supplier): RedirectResponse
    {
        abort_unless($this->canManageAll($request->user()), 403);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $supplier->update($data);
        $this->audit($request, $supplier);

        return back()->with('status', $supplier->is_active ? '供应商已启用。' : '供应商已停用。');
    }

    private function save(Request $request, ProcurementSupplier $supplier): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'contact_name' => ['required', 'string', 'max:100'],
            'contact_phone' => ['required', 'string', 'max:30', 'regex:/^[+\d\s().\-]+$/u', 'regex:/\d/u'],
        ]);
        $data['name'] = Str::squish($data['name']);
        if ($data['name'] === '') {
            throw ValidationException::withMessages(['name' => '请填写供应商名称。']);
        }
        $data['name_key'] = hash('sha256', Str::lower($data['name']));
        try {
            $supplier->fill($data)->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => '供应商已存在，请在供应商库中查找。']);
        }
        $this->audit($request, $supplier);
    }

    private function audit(Request $request, ProcurementSupplier $supplier): void
    {
        $request->attributes->set('audit_subject', ['type' => 'supplier', 'id' => (string) $supplier->id, 'label' => $supplier->name]);
    }

    private function canCreate(User $user): bool
    {
        return $user->is_active && DB::table('departments')->where('id', $user->department_id)->where('is_active', true)->exists();
    }

    private function canManageAll(User $user): bool
    {
        return $user->is_active && $user->hasAnyRole(['procurement_manager', 'system_admin']);
    }

    private function canEdit(User $user, ProcurementSupplier $supplier, bool $manager, array $headDepartments): bool
    {
        return $manager || ($user->is_active
            && ((int) $supplier->department_id === (int) $user->department_id && (int) $supplier->created_by === $user->id
                || in_array((int) $supplier->department_id, $headDepartments, true)));
    }

    private function headDepartments(User $user): array
    {
        $assignments = DB::table('department_head_assignments');
        $departments = (clone $assignments)->where('user_id', $user->id)->pluck('department_id')->map(fn ($id) => (int) $id)->all();
        if ($user->department_id !== null && ! $assignments->where('department_id', $user->department_id)->exists()) {
            $head = User::query()->where('department_id', $user->department_id)->where('is_active', true)
                ->whereHas('roles', fn ($roles) => $roles->where('code', 'department_head'))->min('id');
            if ($head !== null && (int) $head === $user->id) {
                $departments[] = (int) $user->department_id;
            }
        }

        return $departments;
    }
}
