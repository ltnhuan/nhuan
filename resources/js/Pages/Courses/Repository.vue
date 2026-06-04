<script setup>
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'
import EnterpriseTable from '@/Components/EnterpriseTable.vue'

const folders = ['Môn chung','Khoa Công nghệ','Khoa Du lịch','Văn hóa 9+','Ngoại ngữ','Doanh nghiệp']
const rows = [
  { title: 'Slide bài giảng', item_type: 'pptx', visibility: 'faculty', status: 'draft' },
  { title: 'Video nhập môn', item_type: 'video', visibility: 'tenant', status: 'approved' },
]
</script>

<template>
  <EraLmsLayout>
    <div class="grid grid-cols-[260px_1fr] gap-3">
      <aside class="rounded-lg border bg-white p-3">
        <button class="mb-2 rounded bg-blue-900 px-3 py-2 text-xs text-white">+ Tạo folder</button>
        <button v-for="folder in folders" :key="folder" class="block w-full rounded px-2 py-2 text-left text-sm hover:bg-blue-50">{{ folder }}</button>
      </aside>
      <main>
        <EnterpriseTable title="Kho học liệu" :columns="['title','item_type','visibility','status']" :rows="rows" :filters="['Loại item','Trạng thái','Phạm vi chia sẻ']" />
        <div class="mt-3 grid grid-cols-4 gap-3 text-sm">
          <div class="rounded-lg border bg-white p-3">Upload file + checksum chống trùng</div>
          <div class="rounded-lg border bg-white p-3">Move/copy/share theo tenant/faculty/course</div>
          <div class="rounded-lg border bg-white p-3">Version history + change note</div>
          <div class="rounded-lg border bg-white p-3">Preview/download URL không trả binary qua JSON</div>
        </div>
      </main>
    </div>
  </EraLmsLayout>
</template>
