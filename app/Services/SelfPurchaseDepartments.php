<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SelfPurchaseDepartments
{
    private const NAMES = [
        '科技管理部' => '科技管理部、国际合作交流中心、港澳台工作办公室',
        '资产管理部' => '资产管理部、采购中心、经营发展中心',
        '信息技术部' => '信息技术部、实验室建设管理办公室',
        '后勤基建部' => '后勤基建部',
        '教务管理部' => '教务管理部（实践教学管理中心）、教师教学发展中心、评估中心',
        '图书馆' => '图书馆',
        '人力资源部' => '人力资源部、党委教师工作部',
        '纪委办公室' => '纪委办公室、监察室、审计室',
    ];

    public function options(): array
    {
        $departments = DB::table('departments')->whereIn('name', array_values(self::NAMES))->get(['id', 'name'])->keyBy('name');

        return collect(self::NAMES)->map(fn (string $name, string $label) => [
            'id' => $departments->get($name)?->id,
            'label' => $label,
        ])->values()->all();
    }

    public function validId(int $id): bool
    {
        return DB::table('departments')->where('id', $id)->whereIn('name', array_values(self::NAMES))->exists();
    }
}
