<script setup>
import { computed, ref } from 'vue'
import Login from './Pages/Auth/Login.vue'
import AdminSystemCheck from './Pages/Admin/SystemCheck.vue'
import AdminActionCheck from './Pages/Admin/ActionCheck.vue'
import AdminOperationsPlaceholder from './Pages/Admin/OperationsPlaceholder.vue'
import AdminMoodleParity from './Pages/Admin/MoodleParity.vue'
import Dashboard from './Pages/Core/Dashboard.vue'
import AiLearningPlatform from './Pages/AI/LearningPlatform.vue'
import AnalyticsDashboard from './Pages/Analytics/Dashboard.vue'
import AssignmentsManagement from './Pages/Assignments/Management.vue'
import AssignmentsStudentSubmission from './Pages/Assignments/StudentSubmission.vue'
import AssignmentsTeacherGrading from './Pages/Assignments/TeacherGrading.vue'
import AttendanceEligibility from './Pages/Attendance/Eligibility.vue'
import AttendanceLiveSessions from './Pages/Attendance/LiveSessions.vue'
import AttendanceSessions from './Pages/Attendance/Sessions.vue'
import AttendanceStudentCheckin from './Pages/Attendance/StudentCheckin.vue'
import AttendanceTeacherBoard from './Pages/Attendance/TeacherBoard.vue'
import CareerEmployerPortal from './Pages/Career/EmployerPortal.vue'
import CareerPortfolioDashboard from './Pages/Career/PortfolioDashboard.vue'
import CareerPublicPortfolio from './Pages/Career/PublicPortfolio.vue'
import CommunityHub from './Pages/Community/Hub.vue'
import CoreAcademicUnits from './Pages/Core/AcademicUnits.vue'
import CoreAuditLogs from './Pages/Core/AuditLogs.vue'
import CoreCampuses from './Pages/Core/Campuses.vue'
import CoreRolesPermissions from './Pages/Core/RolesPermissions.vue'
import CoreTenants from './Pages/Core/Tenants.vue'
import CoreUsers from './Pages/Core/Users.vue'
import CoreWhiteLabel from './Pages/Core/WhiteLabel.vue'
import CoursesIndex from './Pages/Courses/Index.vue'
import CoursesRepository from './Pages/Courses/Repository.vue'
import CoursesStudio from './Pages/Courses/Studio.vue'
import CoursesLearn from './Pages/Courses/Learn.vue'
import CredentialsBadgeBuilder from './Pages/Credentials/BadgeBuilder.vue'
import CredentialsCertificateBuilder from './Pages/Credentials/CertificateBuilder.vue'
import CredentialsVerifyPortal from './Pages/Credentials/VerifyPortal.vue'
import CredentialsWallet from './Pages/Credentials/Wallet.vue'
import EnrollmentManagement from './Pages/Enrollment/Management.vue'
import ExamAssign from './Pages/Exam/Assign.vue'
import ExamBuilder from './Pages/Exam/Builder.vue'
import ExamManagement from './Pages/Exam/Management.vue'
import ExamManualGrading from './Pages/Exam/ManualGrading.vue'
import ExamResults from './Pages/Exam/Results.vue'
import ExamTake from './Pages/Exam/Take.vue'
import GradebookApproval from './Pages/Gradebook/Approval.vue'
import GradebookBuilder from './Pages/Gradebook/Builder.vue'
import GradebookMatrix from './Pages/Gradebook/Matrix.vue'
import GradebookStudentView from './Pages/Gradebook/StudentView.vue'
import IntegrationsDashboard from './Pages/Integrations/Dashboard.vue'
import IntegrationsEventLogs from './Pages/Integrations/EventLogs.vue'
import IntegrationsMappingCenter from './Pages/Integrations/MappingCenter.vue'
import IntegrationsSyncJobs from './Pages/Integrations/SyncJobs.vue'
import IntegrationsSystemConfig from './Pages/Integrations/SystemConfig.vue'
import LearningPathBuilder from './Pages/LearningPath/Builder.vue'
import LearningPathClassProgress from './Pages/LearningPath/ClassProgress.vue'
import LearningPathLearnerProgress from './Pages/LearningPath/LearnerProgress.vue'
import MobileLearning from './Pages/Mobile/MobileLearning.vue'
import ObeAccreditationReports from './Pages/OBE/AccreditationReports.vue'
import ObeAchievementDashboard from './Pages/OBE/AchievementDashboard.vue'
import ObeCompetencyFramework from './Pages/OBE/CompetencyFramework.vue'
import ObeCoverageAnalysis from './Pages/OBE/CoverageAnalysis.vue'
import ObeOutcomeManagement from './Pages/OBE/OutcomeManagement.vue'
import ObeOutcomeMatrix from './Pages/OBE/OutcomeMatrix.vue'
import QuestionBankBlueprint from './Pages/QuestionBank/Blueprint.vue'
import QuestionBankCategoryTree from './Pages/QuestionBank/CategoryTree.vue'
import QuestionBankEditor from './Pages/QuestionBank/Editor.vue'
import QuestionBankImport from './Pages/QuestionBank/Import.vue'
import QuestionBankIndex from './Pages/QuestionBank/Index.vue'
import QuestionBankOutcomeMatrix from './Pages/QuestionBank/OutcomeMatrix.vue'
import ScormManager from './Pages/Standards/ScormManager.vue'
import SurveyBuilder from './Pages/Survey/Builder.vue'
import SurveyDashboard from './Pages/Survey/Dashboard.vue'
import VideoAnalytics from './Pages/Video/Analytics.vue'
import VideoLessonVideo from './Pages/Video/LessonVideo.vue'
import VideoManager from './Pages/Video/Manager.vue'
import XapiExplorer from './Pages/Standards/XapiExplorer.vue'
import LtiRegistry from './Pages/Standards/LtiRegistry.vue'
import ExternalToolCenter from './Pages/Standards/ExternalToolCenter.vue'

const props = defineProps({
  csrfToken: { type: String, required: true },
  isAuthenticated: { type: Boolean, default: false },
  demoUserEmail: { type: String, default: null },
  user: { type: Object, default: null },
  tenant: { type: Object, default: null },
  demoCredentials: { type: Object, required: true },
})

const session = ref({
  isAuthenticated: props.isAuthenticated,
  user: props.user,
  demoUserEmail: props.demoUserEmail,
})

const headers = computed(() => ({
  'Content-Type': 'application/json',
  'X-CSRF-TOKEN': props.csrfToken,
  'X-Tenant-Code': props.tenant?.code || 'VABIS',
  ...(session.value.demoUserEmail ? { 'X-Demo-User-Email': session.value.demoUserEmail } : {}),
}))

const currentPage = computed(() => {
  const path = window.location.pathname

  if (path.startsWith('/admin/lms/system-check')) {
    return AdminSystemCheck
  }

  if (path.startsWith('/admin/lms/action-check')) {
    return AdminActionCheck
  }

  if (path.startsWith('/courses/learn')) {
    return CoursesLearn
  }

  if (path.startsWith('/courses/studio')) {
    return CoursesStudio
  }

  if (path.startsWith('/courses')) {
    return CoursesIndex
  }

  if (path.startsWith('/repository')) {
    return CoursesRepository
  }

  if (path.startsWith('/learning-path')) {
    if (path.startsWith('/learning-path/class-progress')) {
      return LearningPathClassProgress
    }

    if (path.startsWith('/learning-path/learner-progress')) {
      return LearningPathLearnerProgress
    }

    return LearningPathBuilder
  }

  if (path.startsWith('/enrollment')) {
    return EnrollmentManagement
  }

  if (path.startsWith('/videos')) {
    if (path.startsWith('/videos/analytics')) {
      return VideoAnalytics
    }

    if (path.startsWith('/videos/lesson')) {
      return VideoLessonVideo
    }

    return VideoManager
  }

  if (path.startsWith('/question-banks')) {
    if (path.startsWith('/question-banks/categories')) {
      return QuestionBankCategoryTree
    }

    if (path.startsWith('/question-banks/editor')) {
      return QuestionBankEditor
    }

    if (path.startsWith('/question-banks/import')) {
      return QuestionBankImport
    }

    if (path.startsWith('/question-banks/outcomes')) {
      return QuestionBankOutcomeMatrix
    }

    if (path.startsWith('/question-banks/blueprints')) {
      return QuestionBankBlueprint
    }

    return QuestionBankIndex
  }

  if (path.startsWith('/exams')) {
    if (path.startsWith('/exams/builder')) {
      return ExamBuilder
    }

    if (path.startsWith('/exams/assign')) {
      return ExamAssign
    }

    if (path.startsWith('/exams/take')) {
      return ExamTake
    }

    if (path.startsWith('/exams/results')) {
      return ExamResults
    }

    if (path.startsWith('/exams/manual-grading')) {
      return ExamManualGrading
    }

    return ExamManagement
  }

  if (path.startsWith('/assignments')) {
    if (path.startsWith('/assignments/submission')) {
      return AssignmentsStudentSubmission
    }

    if (path.startsWith('/assignments/grading')) {
      return AssignmentsTeacherGrading
    }

    return AssignmentsManagement
  }

  if (path.startsWith('/community')) {
    return CommunityHub
  }

  if (path.startsWith('/gradebook')) {
    if (path.startsWith('/gradebook/builder')) {
      return GradebookBuilder
    }

    if (path.startsWith('/gradebook/approval')) {
      return GradebookApproval
    }

    if (path.startsWith('/gradebook/student')) {
      return GradebookStudentView
    }

    return GradebookMatrix
  }

  if (path.startsWith('/attendance')) {
    if (path.startsWith('/attendance/live')) {
      return AttendanceLiveSessions
    }

    if (path.startsWith('/attendance/checkin')) {
      return AttendanceStudentCheckin
    }

    if (path.startsWith('/attendance/teacher')) {
      return AttendanceTeacherBoard
    }

    if (path.startsWith('/attendance/eligibility')) {
      return AttendanceEligibility
    }

    return AttendanceSessions
  }

  if (path.startsWith('/surveys')) {
    if (path.startsWith('/surveys/builder')) {
      return SurveyBuilder
    }

    return SurveyDashboard
  }

  if (path.startsWith('/credentials')) {
    if (path.startsWith('/credentials/certificates')) {
      return CredentialsCertificateBuilder
    }

    if (path.startsWith('/credentials/wallet')) {
      return CredentialsWallet
    }

    if (path.startsWith('/credentials/verify')) {
      return CredentialsVerifyPortal
    }

    return CredentialsBadgeBuilder
  }

  if (path.startsWith('/career/employer')) {
    return CareerEmployerPortal
  }

  if (path.startsWith('/career/public')) {
    return CareerPublicPortfolio
  }

  if (path.startsWith('/career')) {
    return CareerPortfolioDashboard
  }

  if (path.startsWith('/sis')) {
    if (path.startsWith('/sis/mapping')) {
      return IntegrationsMappingCenter
    }

    if (path.startsWith('/sis/sync-jobs')) {
      return IntegrationsSyncJobs
    }

    if (path.startsWith('/sis/events')) {
      return IntegrationsEventLogs
    }

    if (path.startsWith('/sis/systems')) {
      return IntegrationsSystemConfig
    }

    return IntegrationsDashboard
  }

  if (path.startsWith('/reports')) {
    return AnalyticsDashboard
  }

  if (path.startsWith('/settings')) {
    if (path.startsWith('/settings/tenants')) {
      return CoreTenants
    }

    if (path.startsWith('/settings/campuses')) {
      return CoreCampuses
    }

    if (path.startsWith('/settings/academic-units')) {
      return CoreAcademicUnits
    }

    if (path.startsWith('/settings/roles')) {
      return CoreRolesPermissions
    }

    if (path.startsWith('/settings/white-label')) {
      return CoreWhiteLabel
    }

    if (path.startsWith('/settings/audit-logs')) {
      return CoreAuditLogs
    }

    return CoreUsers
  }

  if (path.startsWith('/obe')) {
    if (path.startsWith('/obe/outcome-matrix')) {
      return ObeOutcomeMatrix
    }

    if (path.startsWith('/obe/competency-framework')) {
      return ObeCompetencyFramework
    }

    if (path.startsWith('/obe/coverage')) {
      return ObeCoverageAnalysis
    }

    if (path.startsWith('/obe/accreditation')) {
      return ObeAccreditationReports
    }

    if (path.startsWith('/obe/achievement')) {
      return ObeAchievementDashboard
    }

    return ObeOutcomeManagement
  }

  if (path.startsWith('/standards/scorm')) {
    return ScormManager
  }

  if (path.startsWith('/standards/xapi')) {
    return XapiExplorer
  }

  if (path.startsWith('/standards/lti')) {
    return LtiRegistry
  }

  if (path.startsWith('/standards/tools')) {
    return ExternalToolCenter
  }

  if (path.startsWith('/mobile')) {
    return MobileLearning
  }

  if (path.startsWith('/ai')) {
    return AiLearningPlatform
  }

  if (path.startsWith('/analytics')) {
    return AnalyticsDashboard
  }

  if (path.startsWith('/moodle-parity')) {
    return AdminMoodleParity
  }

  if (path.startsWith('/security') || path.startsWith('/plugins') || path.startsWith('/backup') || path.startsWith('/uat')) {
    return AdminOperationsPlaceholder
  }

  return Dashboard
})

const currentPageProps = computed(() => {
  const path = window.location.pathname

  if (path.startsWith('/security')) {
    return { title: 'Bảo mật', subtitle: 'Quản lý phiên đăng nhập, chính sách khóa tài khoản, audit và kiểm tra quyền truy cập.' }
  }

  if (path.startsWith('/moodle-parity')) {
    return { title: 'Moodle parity', subtitle: 'Đối chiếu chức năng Moodle với module EraLMS hiện có; phần trùng được sync/update qua action registry.' }
  }

  if (path.startsWith('/plugins')) {
    return { title: 'Plugin', subtitle: 'Quản lý plugin, connector, trạng thái cài đặt và đồng bộ extension.' }
  }

  if (path.startsWith('/backup')) {
    return { title: 'Sao lưu', subtitle: 'Quản lý backup dữ liệu, lịch chạy, restore point và kiểm tra khôi phục.' }
  }

  if (path.startsWith('/uat')) {
    return { title: 'UAT', subtitle: 'Theo dõi checklist nghiệm thu, test case và kết quả go-live.' }
  }

  return {}
})

async function login(credentials) {
  const response = await fetch('/login', {
    method: 'POST',
    headers: headers.value,
    body: JSON.stringify(credentials),
  })
  const data = await response.json()

  if (!response.ok) {
    throw new Error(data.message || 'Không thể đăng nhập.')
  }

  session.value = {
    isAuthenticated: true,
    user: data.user,
    demoUserEmail: data.user.email,
  }
  window.location.href = `${data.redirect || '/'}?v=${Date.now()}`
}

async function logout() {
  await fetch('/logout', {
    method: 'POST',
    headers: headers.value,
  })
  session.value = {
    isAuthenticated: false,
    user: null,
    demoUserEmail: null,
  }
  window.history.replaceState({}, '', '/login')
}
</script>

<template>
  <Login
    v-if="!session.isAuthenticated"
    :demo-credentials="demoCredentials"
    :tenant="tenant"
    :login-action="login"
  />
  <component
    :is="currentPage"
    v-else
    :session-user="session.user"
    :api-headers="headers"
    v-bind="currentPageProps"
    @logout="logout"
  />
</template>
