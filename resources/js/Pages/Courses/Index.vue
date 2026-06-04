<script setup>
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'; import EnterpriseTable from '@/Components/EnterpriseTable.vue'
const rows = [{code:'COURSE001', title:'Môn chung 1', status:'draft', course_type:'blended', level:'college'}]
</script><template><EraLmsLayout><EnterpriseTable title="Quản trị khóa học" :columns="['code','title','level','course_type','status']" :rows="rows" :filters="['Trạng thái','Khoa','Loại khóa','Hệ đào tạo','Campus']" /><aside class="mt-3 rounded-lg border bg-white p-4 text-sm">Drawer xem nhanh: mô tả, owner, cấu trúc, phiên bản và lịch sử duyệt.</aside></EraLmsLayout></template>
