<script setup>
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'; import EnterpriseTable from '@/Components/EnterpriseTable.vue'
const rows = [{full_name:'Học viên 1', status:'in_progress', progress_percent:'35%', risk_level:'medium'}]
</script><template><EraLmsLayout><EnterpriseTable title="Theo dõi tiến độ lớp" :columns="['full_name','status','progress_percent','risk_level']" :rows="rows" :filters="['Chưa bắt đầu','Đang học','Chậm tiến độ','Hoàn thành','Bị kẹt']" /><div class="mt-3 rounded-lg border bg-white p-3 text-sm">Drawer hành trình học, timeline event summary, duyệt hoàn thành thủ công và nút recalculation.</div></EraLmsLayout></template>
