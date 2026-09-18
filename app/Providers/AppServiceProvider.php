<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
          🔴 ตัวแบ่งหน้ามาตรฐานของ Laravel เขียนด้วยคลาส Tailwind ซึ่งโปรเจคนี้ไม่ได้ใช้
             ปุ่มจึงกลายเป็นข้อความเปล่าๆ และเป็นภาษาอังกฤษล้วน (ผิดกฎ 2 ภาษา)
             ตั้งเป็นของระบบเองที่นี่ที่เดียว ทุกหน้าที่แบ่งหน้าได้เหมือนกันหมด
        */
        Paginator::defaultView('vendor.pagination.bms');
        Paginator::defaultSimpleView('vendor.pagination.bms');
    }
}
