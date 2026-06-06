<script setup>
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'
</script>
<template>
  <EraLmsLayout><template #breadcrumb>verify.eralms.vn / certificate</template>
    <section class="mx-auto max-w-4xl px-6 py-8">
      <div class="border bg-white p-6">
        <div class="flex items-start justify-between gap-6">
          <div><div class="text-sm font-semibold text-emerald-700">VALID</div><h1 class="mt-2 text-2xl font-semibold">Chứng chỉ hoàn thành khóa học</h1><p class="mt-2 text-sm text-slate-600">Mã xác thực: CERT-DEMO-00001</p></div>
          <div class="grid h-32 w-32 place-items-center border bg-slate-50 text-xs font-semibold">QR</div>
        </div>
        <dl class="mt-6 grid gap-3 md:grid-cols-2">
          <div class="border p-3"><dt class="text-xs text-slate-500">Người học</dt><dd class="mt-1 font-medium">Sinh viên Demo</dd></div>
          <div class="border p-3"><dt class="text-xs text-slate-500">Ngày cấp</dt><dd class="mt-1 font-medium">2026-06-05</dd></div>
          <div class="border p-3"><dt class="text-xs text-slate-500">Trạng thái</dt><dd class="mt-1 font-medium text-emerald-700">Issued</dd></div>
          <div class="border p-3"><dt class="text-xs text-slate-500">Hash Verify</dt><dd class="mt-1 break-all font-mono text-xs">sha256: blockchain-ready-anchor</dd></div>
        </dl>
      </div>
    </section>
  </EraLmsLayout>
</template>
