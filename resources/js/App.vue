<script setup>
import { computed, defineAsyncComponent, ref } from 'vue'
import Login from './Pages/Auth/Login.vue'

const page = (loader) => defineAsyncComponent(loader)
const AdminSystemCheck = page(() => import('./Pages/Admin/SystemCheck.vue'))
const AdminActionCheck = page(() => import('./Pages/Admin/ActionCheck.vue'))
const AdminOperationsPlaceholder = page(() => import('./Pages/Admin/OperationsPlaceholder.vue'))
const AdminMoodleParity = page(() => import('./Pages/Admin/MoodleParity.vue'))
const Dashboard = page(() => import('./Pages/Core/Dashboard.vue'))
const AiLearningPlatform = page(() => import('./Pages/AI/LearningPlatform.vue'))
const AnalyticsDashboard = page(() => import('./Pages/Analytics/Dashboard.vue'))
const AssignmentsManagement = page(() => import('./Pages/Assignments/Management.vue'))
const AssignmentsStudentSubmission = page(() => import('./Pages/Assignments/StudentSubmission.vue'))
const AssignmentsTeacherGrading = page(() => import('./Pages/Assignments/TeacherGrading.vue'))
const AttendanceEligibility = page(() => import('./Pages/Attendance/Eligibility.vue'))
const AttendanceLiveSessions = page(() => import('./Pages/Attendance/LiveSessions.vue'))
const AttendanceSessions = page(() => import('./Pages/Attendance/Sessions.vue'))
const AttendanceStudentCheckin = page(() => import('./Pages/Attendance/StudentCheckin.vue'))
const AttendanceTeacherBoard = page(() => import('./Pages/Attendance/TeacherBoard.vue'))
const CareerEmployerPortal = page(() => import('./Pages/Career/EmployerPortal.vue'))
const CareerPortfolioDashboard = page(() => import('./Pages/Career/PortfolioDashboard.vue'))
const CareerPublicPortfolio = page(() => import('./Pages/Career/PublicPortfolio.vue'))
const CommunityHub = page(() => import('./Pages/Community/Hub.vue'))
const CoreAcademicUnits = page(() => import('./Pages/Core/AcademicUnits.vue'))
const CoreAuditLogs = page(() => import('./Pages/Core/AuditLogs.vue'))
const CoreCampuses = page(() => import('./Pages/Core/Campuses.vue'))
const CoreRolesPermissions = page(() => import('./Pages/Core/RolesPermissions.vue'))
const CoreTenants = page(() => import('./Pages/Core/Tenants.vue'))
const CoreUsers = page(() => import('./Pages/Core/Users.vue'))
const CoreWhiteLabel = page(() => import('./Pages/Core/WhiteLabel.vue'))
const CoursesIndex = page(() => import('./Pages/Courses/Index.vue'))
const CoursesRepository = page(() => import('./Pages/Courses/Repository.vue'))
const CoursesStudio = page(() => import('./Pages/Courses/Studio.vue'))
const CoursesLearn = page(() => import('./Pages/Courses/Learn.vue'))
const CredentialsBadgeBuilder = page(() => import('./Pages/Credentials/BadgeBuilder.vue'))
const CredentialsCertificateBuilder = page(() => import('./Pages/Credentials/CertificateBuilder.vue'))
const CredentialsVerifyPortal = page(() => import('./Pages/Credentials/VerifyPortal.vue'))
const CredentialsWallet = page(() => import('./Pages/Credentials/Wallet.vue'))
const EnrollmentManagement = page(() => import('./Pages/Enrollment/Management.vue'))
const ExamAssign = page(() => import('./Pages/Exam/Assign.vue'))
const ExamBuilder = page(() => import('./Pages/Exam/Builder.vue'))
const ExamManagement = page(() => import('./Pages/Exam/Management.vue'))
const ExamManualGrading = page(() => import('./Pages/Exam/ManualGrading.vue'))
const ExamResults = page(() => import('./Pages/Exam/Results.vue'))
const ExamTake = page(() => import('./Pages/Exam/Take.vue'))
const GradebookApproval = page(() => import('./Pages/Gradebook/Approval.vue'))
const GradebookBuilder = page(() => import('./Pages/Gradebook/Builder.vue'))
const GradebookMatrix = page(() => import('./Pages/Gradebook/Matrix.vue'))
const GradebookStudentView = page(() => import('./Pages/Gradebook/StudentView.vue'))
const IntegrationsDashboard = page(() => import('./Pages/Integrations/Dashboard.vue'))
const IntegrationsEventLogs = page(() => import('./Pages/Integrations/EventLogs.vue'))
const IntegrationsMappingCenter = page(() => import('./Pages/Integrations/MappingCenter.vue'))
const IntegrationsSyncJobs = page(() => import('./Pages/Integrations/SyncJobs.vue'))
const IntegrationsSystemConfig = page(() => import('./Pages/Integrations/SystemConfig.vue'))
const LearningPathBuilder = page(() => import('./Pages/LearningPath/Builder.vue'))
const LearningPathClassProgress = page(() => import('./Pages/LearningPath/ClassProgress.vue'))
const LearningPathLearnerProgress = page(() => import('./Pages/LearningPath/LearnerProgress.vue'))
const MobileLearning = page(() => import('./Pages/Mobile/MobileLearning.vue'))
const ObeAccreditationReports = page(() => import('./Pages/OBE/AccreditationReports.vue'))
const ObeAchievementDashboard = page(() => import('./Pages/OBE/AchievementDashboard.vue'))
const ObeCompetencyFramework = page(() => import('./Pages/OBE/CompetencyFramework.vue'))
const ObeCoverageAnalysis = page(() => import('./Pages/OBE/CoverageAnalysis.vue'))
const ObeOutcomeManagement = page(() => import('./Pages/OBE/OutcomeManagement.vue'))
const ObeOutcomeMatrix = page(() => import('./Pages/OBE/OutcomeMatrix.vue'))
const QuestionBankBlueprint = page(() => import('./Pages/QuestionBank/Blueprint.vue'))
const QuestionBankCategoryTree = page(() => import('./Pages/QuestionBank/CategoryTree.vue'))
const QuestionBankEditor = page(() => import('./Pages/QuestionBank/Editor.vue'))
const QuestionBankImport = page(() => import('./Pages/QuestionBank/Import.vue'))
const QuestionBankIndex = page(() => import('./Pages/QuestionBank/Index.vue'))
const QuestionBankOutcomeMatrix = page(() => import('./Pages/QuestionBank/OutcomeMatrix.vue'))
const ScormManager = page(() => import('./Pages/Standards/ScormManager.vue'))
const SurveyBuilder = page(() => import('./Pages/Survey/Builder.vue'))
const SurveyDashboard = page(() => import('./Pages/Survey/Dashboard.vue'))
const VideoAnalytics = page(() => import('./Pages/Video/Analytics.vue'))
const VideoLessonVideo = page(() => import('./Pages/Video/LessonVideo.vue'))
const VideoManager = page(() => import('./Pages/Video/Manager.vue'))
const XapiExplorer = page(() => import('./Pages/Standards/XapiExplorer.vue'))
const LtiRegistry = page(() => import('./Pages/Standards/LtiRegistry.vue'))
const ExternalToolCenter = page(() => import('./Pages/Standards/ExternalToolCenter.vue'))

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
