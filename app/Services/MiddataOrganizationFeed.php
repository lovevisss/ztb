<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MiddataOrganizationFeed
{
    /** @return array<string, mixed> */
    public function read(): array
    {
        $source = DB::connection('school_source');
        $departments = $source->table('t_cx_xyxx')->get(['dwbm', 'dwmc', 'lsdwh']);
        $people = $source->table('t_cx_zzqxryxx')->whereIn('rylx', ['1', '2'])
            ->get(['xgh', 'xm', 'rylx', 'dwbm', 'dzyx']);

        if ($departments->isEmpty() || $people->isEmpty()) {
            throw ValidationException::withMessages(['source' => '来源部门或人员数据为空，已取消同步。']);
        }

        $departmentCodes = [];
        $departmentFeed = [];
        foreach ($departments as $department) {
            $code = trim((string) $department->dwbm);
            $name = trim((string) $department->dwmc);
            if ($code === '' || $name === '' || isset($departmentCodes[$code])) {
                throw ValidationException::withMessages(['source' => '来源院系编码或名称无效，或编码重复。']);
            }
            $departmentCodes[$code] = true;
            $departmentFeed[] = ['code' => $code, 'name' => $name, 'parent_code' => null, 'active' => true];
        }
        if (! in_array('900', array_column($departmentFeed, 'code'), true)) {
            $departmentCodes['900'] = true;
            $departmentFeed[] = ['code' => '900', 'name' => '未分配院系', 'parent_code' => null, 'active' => true];
        }

        $emailCounts = [];
        foreach ($people as $person) {
            $email = strtolower(trim((string) $person->dzyx));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emailCounts[$email] = ($emailCounts[$email] ?? 0) + 1;
            }
        }

        $accounts = [];
        $personFeed = [];
        foreach ($people as $person) {
            $account = trim((string) $person->xgh);
            $name = trim((string) $person->xm);
            $departmentCode = trim((string) $person->dwbm);
            if ($account === '' || $name === '' || isset($accounts[$account]) || ! isset($departmentCodes[$departmentCode])) {
                throw ValidationException::withMessages(['source' => '来源人员账号、姓名或院系关联无效，或账号重复。']);
            }
            $accounts[$account] = true;
            $email = strtolower(trim((string) $person->dzyx));
            $personFeed[] = [
                'account' => $account,
                'name' => $name,
                'email' => filter_var($email, FILTER_VALIDATE_EMAIL) && $emailCounts[$email] === 1
                    ? $email : hash('sha256', $account).'@school.invalid',
                'department_code' => $departmentCode,
                'position_code' => 'PERSON_TYPE_'.$person->rylx,
                'active' => true,
            ];
        }

        return [
            'complete_snapshot' => true,
            'departments' => $departmentFeed,
            'positions' => [
                ['code' => 'PERSON_TYPE_1', 'name' => '教职工'],
                ['code' => 'PERSON_TYPE_2', 'name' => '非编教职工'],
            ],
            'people' => $personFeed,
        ];
    }
}
