{{--
  ป้ายผลของ "หนึ่งขั้น" ในสายอนุมัติ

  🔴 ใช้ร่วมกันทั้งตารางเส้นทางเอกสาร (ดูเส้นทาง) และสำเนาเรียนในตัวเอกสาร
     คำและสีจึงตรงกันเสมอ · ตรรกะการแปลผลอยู่ที่ Approval::outcome() ที่เดียว

  🔴 ต้องส่ง $docStatus มาด้วย เพราะผลของขั้นขึ้นกับสถานะของเอกสารทั้งใบ
     เอกสารถูกตีกลับไปแล้ว คนที่ยังไม่ได้เซ็น = "ไม่ได้ดำเนินการ" ไม่ใช่ "รออนุมัติ"

  $step       แถว App\Models\Budget\Approval
  $docStatus  สถานะของเอกสารทั้งใบ (Invest::*)
--}}
@php $outcome = $step->outcome($docStatus ?? null); @endphp

<span class="st {{ $outcome['cls'] }}"
      data-loc-th="{{ $outcome['th'] }}" data-loc-en="{{ $outcome['en'] }}">{{ $outcome['th'] }}</span>
