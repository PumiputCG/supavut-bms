<?php

/*
| ไฟล์นี้ทำหน้าที่รวม route ของแต่ละระบบย่อยเท่านั้น
| อย่าประกาศ route ตรงนี้ — ให้ไปเพิ่มในไฟล์ของระบบย่อยที่เกี่ยวข้อง
*/

require __DIR__.'/web/core.php';
require __DIR__.'/web/access.php';
require __DIR__.'/web/master.php';
require __DIR__.'/web/audit.php';
require __DIR__.'/web/budget.php';
