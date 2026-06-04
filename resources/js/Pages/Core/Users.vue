<script setup>
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'; import EnterpriseTable from '@/Components/EnterpriseTable.vue'
const rows = [{code:'SV00001', full_name:'Học viên 1', email:'sv1@vabis.edu.vn', user_type:'student', status:'active'}]
</script><template><EraLmsLayout><EnterpriseTable title="Quản lý người dùng" :columns="['code','full_name','email','user_type','status']" :rows="rows" :filters="['Loại người dùng','Trạng thái','Campus']" /></EraLmsLayout></template>
