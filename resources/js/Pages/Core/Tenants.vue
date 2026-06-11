<script setup>
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'; import EnterpriseTable from '@/Components/EnterpriseTable.vue'
const rows = [{code:'VABIS', name:'VABIS LMS', status:'active', domain:'lms.vabis.edu.vn'}]
</script><template><EraLmsLayout><EnterpriseTable title="Quản lý đơn vị" :columns="[{ key: 'code', label: 'Mã' }, { key: 'name', label: 'Tên' }, { key: 'domain', label: 'Tên miền' }, { key: 'status', label: 'Trạng thái' }]" :rows="rows" :filters="['Trạng thái']" /></EraLmsLayout></template>
