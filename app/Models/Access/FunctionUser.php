<?php

namespace App\Models\Access;

use Illuminate\Database\Eloquent\Model;

/**
 * คนที่เข้าหัวข้อย่อยนั้นได้
 *
 * 🔴 หัวข้อย่อยที่ไม่มีแถวเลย = ทุกคนที่เห็นโมดูลนั้นเข้าได้ (ดูเหตุผลใน migration)
 */
class FunctionUser extends Model
{
    protected $table = 'access_function_users';

    protected $fillable = ['module_id', 'function_key', 'employee_code', 'updated_by'];
}
