<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TenderNumber
{
    public function next(): string
    {
        $day = now()->format('Y-m-d');
        DB::table('procurement_number_sequences')->insertOrIgnore(['day' => $day, 'last_number' => 0]);
        $row = DB::table('procurement_number_sequences')->where('day', $day)->lockForUpdate()->first();
        $number = $row->last_number + 1;
        if ($number > 9999) {
            throw ValidationException::withMessages(['project_number' => '当日申请编号已用完，请联系管理员。']);
        }
        DB::table('procurement_number_sequences')->where('day', $day)->update(['last_number' => $number]);

        return 'CGSQ-'.now()->format('Ymd').'-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
