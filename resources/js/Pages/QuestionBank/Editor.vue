<script setup>
import { CheckCircle2, Lightbulb, WandSparkles, XCircle, FileText, ListChecks, MessageSquareText, PencilLine, Plus, RefreshCw, ShieldCheck, Undo2 } from '@lucide/vue'
import { computed, defineAsyncComponent, onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'
import { AI_HUB_SECTIONS, AI_HUB_TEXT } from '@/config/aiHubConfig'

const RichTextEditor = defineAsyncComponent(() => import('@/Components/Lms/RichTextEditor.vue'))

const props = defineProps({
  apiHeaders: { type: Object, default: () => ({}) },
})

const TEXT = {
  vi: {
    breadcrumb: 'Ngân hàng câu hỏi / Soạn câu hỏi',
    title: 'Trình soạn câu hỏi chuyên nghiệp',
    subtitle: 'Giao diện chuẩn soạn thảo nâng cao: tiêu đề, nội dung WYSIWYG lớn, lựa chọn đáp án có feedback, validation chất lượng, và preview trước khi lưu.',
    actions: {
      preview: 'Xem nhanh',
      saveDraft: 'Lưu nháp',
      submitReview: 'Gửi duyệt',
      save: 'Lưu trạng thái hiện tại',
      reset: 'Làm mới form',
      aiSuggestion: 'Gợi ý AI',
      applySuggestion: 'Áp dụng gợi ý',
    },
    sections: {
      questionSetup: 'Thiết lập câu hỏi',
      stem: 'Nội dung câu hỏi',
      answers: 'Câu trả lời / lựa chọn',
      fillBlank: 'Đáp án điền khuyết',
      matching: 'Cặp ghép đôi',
      metadata: 'Thông tin chuẩn hóa',
      quality: 'Kiểm tra chất lượng',
      preview: 'Xem trước',
      quickTools: 'Công cụ nhanh',
      aiCard: 'AI chất lượng câu hỏi',
      advanced: 'Cài đặt nâng cao',
      references: 'Tham chiếu hệ thống',
    },
    labels: {
      bank: 'Ngân hàng',
      category: 'Danh mục',
      status: 'Trạng thái',
      code: 'Mã câu hỏi',
      title: 'Tiêu đề',
      type: 'Loại câu hỏi',
      score: 'Điểm mặc định',
      explanation: 'Giải thích/chấm điểm cộng',
      difficulty: 'Độ khó',
      bloom: 'Bloom',
      noCategory: 'Không gắn danh mục',
      choose: 'Chọn',
      optionCorrect: 'Đúng',
      optionText: 'Nội dung đáp án',
      optionFeedback: 'Feedback',
      add: 'Thêm',
      remove: 'Xóa',
      blankKey: 'Khóa',
      blankAnswer: 'Đáp án',
      left: 'Phần trái',
      right: 'Phần phải',
      tagPlaceholder: 'Tag: Đề giữa kỳ, CLO2, Khoá học... (phân tách bằng dấu phẩy)',
      titlePlaceholder: 'Ví dụ: Câu hỏi về an toàn thông tin',
      stemPlaceholder: 'Nhập nội dung câu hỏi, chèn ảnh, biểu đồ, tài liệu đính kèm...',
      explanationPlaceholder: 'Giải thích chung cho đáp án, quy tắc chấm hoặc căn cứ đánh giá',
      typeCount: 'Số loại',
      editorQuality: 'Chất lượng soạn thảo',
      pass: 'Pass',
      draft: 'Draft',
      templateLabel: 'Template',
      clearStem: 'Xóa khung soạn',
      answerHint: 'Điền nội dung, bật đáp án đúng và chuẩn hóa feedback.',
      fillBlankHint: 'Mỗi blank cần khóa và đáp án.',
      matchingHint: 'Kết nối hai tập nội dung theo cặp.',
      metadataHint: 'Thông tin gợi ý cho phân tích và xuất đề.',
      previewHint: 'Xem nhanh bố cục câu hỏi sau khi soạn.',
      qualityHint: 'Kiểm tra nhanh chất lượng trước khi lưu.',
      aiSourceHint: 'So sánh nhanh với mẫu chuẩn Moodle / Canvas style để chuẩn hóa wording.',
      benchmarkTitle: 'Chuẩn tham chiếu',
      importRelated: 'Import câu hỏi liên quan',
      addBlank: 'Thêm blank',
      addPair: 'Thêm cặp',
      addOptionAction: 'Thêm câu trả lời',
      insertedTemplate: 'Đã chèn mẫu',
      randomizeChoices: 'Trộn đáp án khi hiển thị',
      randomizeChoicesHint: 'Kích hoạt nếu muốn đảo vị trí đáp án khi xuất bài',
      strictBlankMatch: 'Đối chiếu đúng sai phân biệt chữ hoa/thường',
      strictBlankMatchHint: 'Áp dụng khi chấm các câu điền khuyết',
      partialCredit: 'Cho phép cộng điểm một phần',
      partialCreditHint: 'Hỗ trợ cho các đáp án có trọng số khác nhau',
      sourceSystems: 'Mẫu so sánh tham chiếu',
      aiActionWording: 'Gợi ý wording & distractor',
      aiActionClone: 'Tạo 2 câu hỏi tương tự từ cùng CLO',
      advancedSettings: 'Cài đặt nâng cao',
      no: 'Không',
      yes: 'Có',
      ready: 'Sẵn sàng',
      review: 'Duyệt',
      openAiLabel: 'Mở AI Assistant',
      pointPlaceholder: 'Điểm',
      questionSetupHint: 'Thiết lập nhanh thông tin câu hỏi trước khi soạn',
      difficultyEasy: 'Dễ',
      difficultyMedium: 'Trung bình',
      difficultyHard: 'Khó',
      difficultyExpert: 'Chuyên sâu',
      bloomRemember: 'Nhớ',
      bloomUnderstand: 'Hiểu',
      bloomApply: 'Vận dụng',
      bloomAnalyze: 'Phân tích',
      bloomEvaluate: 'Đánh giá',
      bloomCreate: 'Sáng tạo',
      changeNotePrefix: 'Lưu từ editor với trạng thái',
      contentHeader: 'Nội dung',
      missing: 'Thiếu',
      optionLabel: 'Lựa chọn',
      blankLabel: 'Các vị trí trống',
      pairLabel: 'Cặp',
      contextLabel: 'Ngữ cảnh',
    },
    defaults: {
      questionTitle: 'Câu hỏi mới',
      stem: '<p>Nhập nội dung câu hỏi có dấu tiếng Việt đầy đủ.</p>',
      tags: 'Đề giữa kỳ, CLO1',
      options: [
        { content: 'Đáp án A', is_correct: true, feedback: 'Phản hồi cho đáp án A', score_weight: 1 },
        { content: 'Đáp án B', is_correct: false, feedback: 'Phản hồi cho đáp án B', score_weight: 1 },
      ],
      fillBlanks: [{ blank_key: 'blank_1', accepted_answer: 'EraLMS', score_weight: 1 }],
      pairs: [{ left_content: 'CLO', right_content: 'Course Learning Outcome' }],
      aiTemplates: {
        stem: [
          { title: 'Câu trắc nghiệm', content: '<p>Tình huống: ...</p><p>Câu hỏi:</p>' },
          { title: 'Đề cao năng lực ứng dụng', content: '<p>Cho ví dụ thực tế, hãy cho biết</p>' },
          { title: 'Bài đọc ngắn', content: '<blockquote><p>Dựa trên tài liệu dưới đây, hãy trả lời...</p></blockquote>' },
        ],
        option: ['A. ', 'B. ', 'C. ', 'D. '],
      },
    },
    statusLabels: {
      draft: 'Draft',
      review: 'Review',
      approved: 'Approved',
      published: 'Published',
      archived: 'Archived',
    },
    typeLabels: {
      single_choice: 'Một đáp án',
      multiple_choice: 'Nhiều đáp án',
      true_false: 'Đúng/Sai',
      essay: 'Tự luận',
      fill_blank: 'Điền khuyết',
      matching: 'Ghép đôi',
      audio: 'Audio',
      image: 'Hình ảnh',
      video: 'Video',
      ordering: 'Sắp thứ tự',
    },
    hints: {
      questionHint: 'Sử dụng tiêu chuẩn: có tiêu đề, nội dung, đáp án đúng, và ít nhất 1 phương án hợp lệ.',
      statusReady: 'Sẵn sàng lưu',
      statusDraft: 'Cần tối thiểu 1 nội dung mô tả',
      statusReview: 'Phù hợp gửi duyệt',
      statusEmpty: 'Thiếu dữ liệu bắt buộc',
      qualityGood: 'Chất lượng câu hỏi tốt',
      qualityFair: 'Cần hoàn thiện thêm',
      noCategory: 'Không có câu hỏi liên quan CLO/PLO',
      noStem: 'Thiếu nội dung câu hỏi',
      noOptions: 'Chưa có đáp án',
      multipleCorrectRequired: 'Cần ít nhất 1 đáp án đúng',
      blankInvalid: 'Blank có khóa và đáp án',
      matchInvalid: 'Mỗi cặp phải có đủ cả 2 chiều',
      loading: 'Đang tải dữ liệu...',
      loaded: 'Đã tải dữ liệu câu hỏi.',
      successSave: 'Đã lưu câu hỏi',
      errorSave: 'Lỗi khi lưu câu hỏi.',
      noBank: 'Chọn ngân hàng câu hỏi trước khi lưu.',
      noType: 'Chọn loại câu hỏi.',
      noCode: 'Nhập mã hoặc tên câu hỏi.',
      aiHint: 'Mở AI Assistant để xem gợi ý chỉnh sửa câu hỏi theo chuẩn CLO/Bloom.',
      aiSuggestionText: 'Nên làm rõ điều kiện đánh giá và điểm nhấn kiến thức trọng tâm.',
      aiTemplateText: 'Chọn 1 đáp án đúng nhất dựa trên kiến thức trọng tâm.',
      changeNotePrefix: 'Lưu từ editor với trạng thái',
      referenceMoodle: 'Moodle: mô hình bank mapping + phân loại chuẩn',
      referenceCanvas: 'Canvas: preview rõ ràng, nhấn mạnh random hóa',
      referenceQuizizz: 'Quizizz: flow chỉnh sửa nhanh, distractor trực quan',
      referenceSocrative: 'Socrative: nhấn trọng tâm độ khó và điểm chuẩn',
      benchmarkDefault: 'Tối thiểu: stem rõ, distractor hợp lý, feedback cụ thể, metadata đầy đủ',
    },
  },
  en: {
    breadcrumb: 'Ngân hàng câu hỏi / Soạn câu hỏi',
    title: 'Trình soạn câu hỏi chuyên nghiệp',
    subtitle: 'Giao diện chuẩn soạn thảo nâng cao: tiêu đề, nội dung WYSIWYG lớn, lựa chọn đáp án có feedback, validation chất lượng, và preview trước khi lưu.',
    actions: {
      preview: 'Xem nhanh',
      saveDraft: 'Lưu nháp',
      submitReview: 'Gửi duyệt',
      save: 'Lưu trạng thái hiện tại',
      reset: 'Làm mới form',
      aiSuggestion: 'Gợi ý AI',
      applySuggestion: 'Áp dụng gợi ý',
    },
    sections: {
      questionSetup: 'Thiết lập câu hỏi',
      stem: 'Nội dung câu hỏi',
      answers: 'Câu trả lời / lựa chọn',
      fillBlank: 'Đáp án điền khuyết',
      matching: 'Cặp ghép đôi',
      metadata: 'Thông tin chuẩn hóa',
      quality: 'Kiểm tra chất lượng',
      preview: 'Xem trước',
      quickTools: 'Công cụ nhanh',
      aiCard: 'AI chất lượng câu hỏi',
      advanced: 'Cài đặt nâng cao',
      references: 'Tham chiếu hệ thống',
    },
    labels: {
      bank: 'Ngân hàng',
      category: 'Danh mục',
      status: 'Trạng thái',
      code: 'Mã câu hỏi',
      title: 'Tiêu đề',
      type: 'Loại câu hỏi',
      score: 'Điểm mặc định',
      explanation: 'Giải thích/chấm điểm cộng',
      difficulty: 'Difficulty',
      bloom: 'Bloom',
      noCategory: 'Không gắn danh mục',
      choose: 'Chọn',
      optionCorrect: 'Đúng',
      optionText: 'Nội dung đáp án',
      optionFeedback: 'Feedback',
      add: 'Add',
      remove: 'Remove',
      blankKey: 'Blank key',
      blankAnswer: 'Accepted answer',
      left: 'Left',
      right: 'Right',
      tagPlaceholder: 'Tag: Đề giữa kỳ, CLO2, Khoá học... (phân tách bằng dấu phẩy)',
      titlePlaceholder: 'Ví dụ: Câu hỏi về an toàn thông tin',
      stemPlaceholder: 'Nhập nội dung câu hỏi, chèn ảnh, biểu đồ, tài liệu đính kèm...',
      explanationPlaceholder: 'Giải thích chung cho đáp án, quy tắc chấm hoặc căn cứ đánh giá',
      typeCount: 'Số loại',
      editorQuality: 'Chất lượng soạn thảo',
      pass: 'Pass',
      draft: 'Draft',
      templateLabel: 'Template',
      clearStem: 'Xóa khung soạn',
      answerHint: 'Điền nội dung, bật đáp án đúng và chuẩn hóa feedback.',
      fillBlankHint: 'Mỗi blank cần khóa và đáp án.',
      matchingHint: 'Kết nối hai tập nội dung theo cặp.',
      metadataHint: 'Thông tin gợi ý cho phân tích và xuất đề.',
      previewHint: 'Xem nhanh bố cục câu hỏi sau khi soạn.',
      qualityHint: 'Kiểm tra nhanh chất lượng trước khi lưu.',
      aiSourceHint: 'So sánh nhanh với mẫu chuẩn Moodle / Canvas để chuẩn hóa wording.',
      benchmarkTitle: 'Chuẩn tham chiếu',
      importRelated: 'Import câu hỏi liên quan',
      addBlank: 'Thêm blank',
      addPair: 'Thêm cặp',
      addOptionAction: 'Thêm câu trả lời',
      insertedTemplate: 'Đã chèn mẫu',
      randomizeChoices: 'Trộn đáp án khi hiển thị',
      randomizeChoicesHint: 'Kích hoạt nếu muốn đảo vị trí đáp án khi xuất bài',
      strictBlankMatch: 'Đối chiếu đúng sai phân biệt hoa/thường',
      strictBlankMatchHint: 'Áp dụng so sánh chặt cho đáp án điền khuyết',
      partialCredit: 'Cho phép cộng điểm một phần',
      partialCreditHint: 'Hữu ích cho chấm nhiều tiêu chí với điểm con',
      sourceSystems: 'Mẫu so sánh tham chiếu',
      aiActionWording: 'Gợi ý wording & distractor',
      aiActionClone: 'Tạo 2 câu hỏi tương tự từ cùng CLO',
      advancedSettings: 'Cài đặt nâng cao',
      no: 'Không',
      yes: 'Có',
      ready: 'Sẵn sàng',
      review: 'Duyệt',
      openAiLabel: 'Mở AI Assistant',
      pointPlaceholder: 'Điểm',
      questionSetupHint: 'Thiết lập nhanh thông tin câu hỏi trước khi soạn',
      difficultyEasy: 'Dễ',
      difficultyMedium: 'Trung bình',
      difficultyHard: 'Khó',
      difficultyExpert: 'Chuyên sâu',
      bloomRemember: 'Nhớ',
      bloomUnderstand: 'Hiểu',
      bloomApply: 'Vận dụng',
      bloomAnalyze: 'Phân tích',
      bloomEvaluate: 'Đánh giá',
      bloomCreate: 'Sáng tạo',
      contentHeader: 'Nội dung',
      changeNotePrefix: 'Lưu từ editor với trạng thái',
      missing: 'Thiếu',
      optionLabel: 'Lựa chọn',
      blankLabel: 'Các vị trí trống',
      pairLabel: 'Cặp',
      contextLabel: 'Ngữ cảnh',
    },
    defaults: {
      questionTitle: 'Câu hỏi mới',
      stem: '<p>Nhập nội dung câu hỏi có dấu tiếng Việt đầy đủ.</p>',
      tags: 'Đề giữa kỳ, CLO1',
      options: [
        { content: 'Đáp án A', is_correct: true, feedback: 'Phản hồi cho đáp án A', score_weight: 1 },
        { content: 'Đáp án B', is_correct: false, feedback: 'Phản hồi cho đáp án B', score_weight: 1 },
      ],
      fillBlanks: [{ blank_key: 'blank_1', accepted_answer: 'EraLMS', score_weight: 1 }],
      pairs: [{ left_content: 'CLO', right_content: 'Course Learning Outcome' }],
      aiTemplates: {
        stem: [
          { title: 'Câu trắc nghiệm', content: '<p>Tình huống: ...</p><p>Câu hỏi:</p>' },
          { title: 'Ứng dụng', content: '<p>Cho ví dụ thực tế, hãy xác định</p>' },
          { title: 'Đọc ngắn', content: '<blockquote><p>Dựa trên tài liệu dưới đây, hãy trả lời...</p></blockquote>' },
        ],
        option: ['A. ', 'B. ', 'C. ', 'D. '],
      },
    },
    statusLabels: {
      draft: 'Nháp',
      review: 'Duyệt',
      approved: 'Đã duyệt',
      published: 'Đã xuất bản',
      archived: 'Đã lưu trữ',
    },
    typeLabels: {
      single_choice: 'Một đáp án',
      multiple_choice: 'Nhiều đáp án',
      true_false: 'Đúng / Sai',
      essay: 'Tự luận',
      fill_blank: 'Điền khuyết',
      matching: 'Ghép đôi',
      audio: 'Audio',
      image: 'Hình ảnh',
      video: 'Video',
      ordering: 'Sắp thứ tự',
    },
    hints: {
      questionHint: 'Sử dụng quy tắc: có tiêu đề, nội dung câu hỏi, và ít nhất 1 đáp án hợp lệ.',
      statusReady: 'Sẵn sàng lưu',
      statusDraft: 'Cần tối thiểu 1 nội dung mô tả',
      statusReview: 'Phù hợp gửi duyệt',
      statusEmpty: 'Thiếu dữ liệu bắt buộc',
      qualityGood: 'Chất lượng câu hỏi tốt',
      qualityFair: 'Cần cải thiện',
      noCategory: 'Không có CLO/PLO mapping',
      noStem: 'Thiếu nội dung câu hỏi',
      noOptions: 'Chưa có đáp án',
      multipleCorrectRequired: 'Cần ít nhất 1 đáp án đúng',
      blankInvalid: 'Blank cần khóa và đáp án',
      matchInvalid: 'Mỗi cặp phải có đủ hai giá trị',
      loading: 'Đang tải dữ liệu...',
      loaded: 'Đã tải dữ liệu câu hỏi.',
      successSave: 'Đã lưu câu hỏi',
      errorSave: 'Lỗi khi lưu câu hỏi.',
      noBank: 'Chọn ngân hàng câu hỏi trước khi lưu.',
      noType: 'Chọn loại câu hỏi.',
      noCode: 'Nhập mã hoặc tên câu hỏi.',
      aiHint: 'Mở AI Assistant cho gợi ý chỉnh sửa theo CLO/Bloom.',
      aiSuggestionText: 'Làm rõ tiêu chí chấm điểm và nhấn mạnh kiến thức trọng tâm.',
      aiTemplateText: 'Chọn đáp án đúng nhất dựa trên mục tiêu học tập cốt lõi.',
      changeNotePrefix: 'Lưu từ editor với trạng thái',
      referenceMoodle: 'Moodle: ánh xạ ngân hàng và mô hình metadata mạnh',
      referenceCanvas: 'Canvas: câu hỏi rõ, xem trước trước khi đăng',
      referenceQuizizz: 'Quizizz: quy trình chỉnh sửa nhanh, distractor trực quan',
      referenceSocrative: 'Socrative: nhấn mạnh độ khó và nhất quán chấm',
      benchmarkDefault: 'Theo dõi 4 chỉ số: stem rõ, distractor hợp lý, feedback cụ thể, metadata đầy đủ.',
    },
  },
}

const locale = (() => {
  const requested = new URLSearchParams(window.location.search).get('lang')?.toLowerCase()
  return requested === 'en' ? 'en' : 'vi'
})()
const t = computed(() => TEXT[locale])
const aiHubText = computed(() => AI_HUB_TEXT[locale])

const questionId = ref(null)
const banks = ref([])
const categories = ref([])
const selectedBankId = ref(null)
const selectedCategoryId = ref(null)
const code = ref(`Q-${Date.now().toString().slice(-6)}`)
const title = ref('')
const type = ref('single_choice')
const questionStem = ref('')
const message = ref('')
const loading = ref(false)
const saving = ref(false)
const status = ref('draft')
const difficulty = ref('medium')
const bloomLevel = ref('understand')
const defaultScore = ref(1)
const explanation = ref('')
const tags = ref('')
const options = ref([])
const fillBlanks = ref([])
const pairs = ref([])
const aiTemplates = computed(() => t.value.defaults.aiTemplates)

const randomizeChoices = ref(false)
const strictBlankMatch = ref(true)
const partialCredit = ref(false)

const benchmarks = computed(() => [
  { key: 'moodle', label: 'Moodle', note: t.value.hints.referenceMoodle },
  { key: 'canvas', label: 'Canvas', note: t.value.hints.referenceCanvas },
  { key: 'quizizz', label: 'Quizizz', note: t.value.hints.referenceQuizizz },
  { key: 'socrative', label: 'Socrative', note: t.value.hints.referenceSocrative },
])

const aiHubSectionIcons = {
  pipeline: FileText,
  assistant: MessageSquareText,
  generation: WandSparkles,
  insight: Lightbulb,
}

const aiHubSectionCards = computed(() => AI_HUB_SECTIONS.map((section, index) => {
  const sectionText = aiHubText.value.sections?.[section.id] || {}
  return {
    id: section.id,
    href: getAiUrl(section.id),
    order: index + 1,
    icon: aiHubSectionIcons[section.id] || FileText,
    title: sectionText.title || section.id,
    hint: sectionText.hint || '',
    tooltip: sectionText.tooltip || sectionText.hint || '',
  }
}))

function setDefaults() {
  const defaults = t.value.defaults
  title.value = defaults.questionTitle
  questionStem.value = defaults.stem
  tags.value = defaults.tags
  options.value = defaults.options.map((option) => ({ ...option }))
  fillBlanks.value = defaults.fillBlanks.map((item) => ({ ...item }))
  pairs.value = defaults.pairs.map((item) => ({ ...item }))
}

setDefaults()

const typeLabels = computed(() => t.value.typeLabels)
const statusLabels = computed(() => t.value.statusLabels)

const selectedBankCategories = computed(() => categories.value.filter((category) => Number(category.question_bank_id) === Number(selectedBankId.value)))
const activeType = computed(() => type.value)
const hasAnswerSection = computed(() => ['single_choice', 'multiple_choice', 'true_false', 'ordering'].includes(activeType.value))
const hasFillSection = computed(() => activeType.value === 'fill_blank')
const hasMatchingSection = computed(() => activeType.value === 'matching')
const difficultyOptions = computed(() => ([
  { value: 'easy', label: t.value.labels.difficultyEasy },
  { value: 'medium', label: t.value.labels.difficultyMedium },
  { value: 'hard', label: t.value.labels.difficultyHard },
  { value: 'expert', label: t.value.labels.difficultyExpert },
]))
const bloomOptions = computed(() => ([
  { value: 'remember', label: t.value.labels.bloomRemember },
  { value: 'understand', label: t.value.labels.bloomUnderstand },
  { value: 'apply', label: t.value.labels.bloomApply },
  { value: 'analyze', label: t.value.labels.bloomAnalyze },
  { value: 'evaluate', label: t.value.labels.bloomEvaluate },
  { value: 'create', label: t.value.labels.bloomCreate },
]))

const qualityChecks = computed(() => {
  const checks = [
    { key: 'title', label: t.value.labels.title, pass: !!title.value.trim(), critical: true },
    { key: 'code', label: t.value.labels.code, pass: !!code.value.trim(), critical: true },
    { key: 'bank', label: t.value.labels.bank, pass: Boolean(selectedBankId.value), critical: true },
    { key: 'stem', label: t.value.hints.noStem, pass: questionStem.value.trim().length > 18, critical: true },
    { key: 'difficulty', label: t.value.labels.difficulty, pass: Boolean(difficulty.value), critical: false },
    { key: 'bloom', label: t.value.labels.bloom, pass: Boolean(bloomLevel.value), critical: false },
  ]

  if (hasAnswerSection.value) {
    checks.push(
      { key: 'options-count', label: t.value.labels.optionText, pass: options.value.length >= 2, critical: true },
      { key: 'correct-option', label: t.value.hints.multipleCorrectRequired, pass: options.value.some((option) => option.is_correct), critical: true },
    )
  }

  if (hasFillSection.value) {
    const valid = fillBlanks.value.length > 0 && fillBlanks.value.every((item) => item.blank_key.trim() && item.accepted_answer.trim())
    checks.push({ key: 'fillblank', label: t.value.hints.blankInvalid, pass: valid, critical: true })
  }

  if (hasMatchingSection.value) {
    const valid = pairs.value.length > 0 && pairs.value.every((item) => item.left_content.trim() && item.right_content.trim())
    checks.push({ key: 'matching', label: t.value.hints.matchInvalid, pass: valid, critical: true })
  }

  if (activeType.value === 'essay') {
    checks.push({ key: 'essay', label: t.value.hints.questionHint, pass: title.value.trim() && questionStem.value.trim(), critical: true })
  }

  return checks
})

const readyToSave = computed(() => !loading.value && qualityChecks.value.every((check) => check.pass))
const readyForReview = computed(() => readyToSave.value && qualityChecks.value.filter((item) => item.critical).length >= 3)
const qualityRate = computed(() => {
  const passCount = qualityChecks.value.filter((item) => item.pass).length
  return Math.round((passCount / Math.max(1, qualityChecks.value.length)) * 100)
})

const qualityBadge = computed(() => {
  if (qualityRate.value >= 85) return t.value.hints.qualityGood
  if (qualityRate.value >= 60) return t.value.hints.qualityFair
  return t.value.hints.statusEmpty
})

const aiContext = computed(() => {
  if (!questionId.value) return ''
  return `question_id=${questionId.value}&bank_id=${selectedBankId.value || ''}`
})

const previewOptions = computed(() => (hasAnswerSection.value ? options.value.slice(0, 8) : []))
const previewTitle = computed(() => {
  const prefix = t.value.actions.preview
  const currentType = typeLabels.value[type.value] || t.value.typeLabels.single_choice
  return `${prefix}: ${currentType}`
})

onMounted(boot)

async function api(path, options = {}) {
  const response = await fetch(`/api/v1${path}`, {
    ...options,
    headers: { ...props.apiHeaders, ...(options.headers || {}) },
  })
  const data = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(data.message || t.value.hints.errorSave)
  return data.data || data
}

async function boot() {
  loading.value = true
  try {
    const params = new URLSearchParams(window.location.search)
    questionId.value = Number(params.get('question_id')) || null
    await loadBanks()
    if (questionId.value) await loadQuestion(questionId.value)
    else selectedBankId.value ||= banks.value[0]?.id || null
    if (selectedBankId.value) await loadCategories(selectedBankId.value)
    message.value = t.value.hints.loaded
  } catch (error) {
    message.value = error.message
  } finally {
    loading.value = false
  }
}

async function loadBanks() {
  const payload = await api('/question-banks?per_page=100')
  banks.value = payload.data || payload || []
}

async function loadCategories(bankId) {
  if (!bankId) {
    categories.value = []
    return
  }
  categories.value = await api(`/question-categories?question_bank_id=${bankId}`)
}

async function loadQuestion(id) {
  const question = await api(`/questions/${id}`)
  selectedBankId.value = question.question_bank_id
  await loadCategories(selectedBankId.value)
  selectedCategoryId.value = question.category_id || null
  code.value = question.code || code.value
  title.value = question.title || ''
  type.value = question.question_type || 'single_choice'
  questionStem.value = question.stem || ''
  status.value = question.status || 'draft'
  difficulty.value = question.difficulty || 'medium'
  bloomLevel.value = question.bloom_level || 'understand'
  defaultScore.value = Number(question.default_score || 1)
  explanation.value = question.explanation || ''
  tags.value = question.tags?.join(', ') || tags.value
  randomizeChoices.value = Boolean(question.metadata?.advanced_settings?.randomize_choices)
  strictBlankMatch.value = question.metadata?.advanced_settings?.strict_blank_match ?? true
  partialCredit.value = Boolean(question.metadata?.advanced_settings?.allow_partial_credit)
  options.value = (question.options || []).map((option) => ({
    content: option.content || '',
    is_correct: Boolean(option.is_correct),
    feedback: option.feedback || '',
    score_weight: option.score_weight ?? 1,
  }))
  fillBlanks.value = (question.fill_blank_answers || []).map((answer) => ({
    blank_key: answer.blank_key || 'blank_1',
    accepted_answer: answer.accepted_answer || '',
    score_weight: answer.score_weight ?? 1,
  }))
  pairs.value = (question.matching_pairs || []).map((pair) => ({
    left_content: pair.left_content || '',
    right_content: pair.right_content || '',
  }))
  message.value = t.value.hints.loaded
}

function buildPayload(nextStatus = status.value) {
  const stem = type.value === 'fill_blank' && !questionStem.value.includes('{{')
    ? `${questionStem.value}<p>{{blank_1}}</p>`
    : questionStem.value

  const payload = {
    question_bank_id: Number(selectedBankId.value),
    category_id: selectedCategoryId.value ? Number(selectedCategoryId.value) : null,
    code: code.value.trim(),
    title: title.value.trim() || code.value.trim(),
    question_type: type.value,
    stem,
    explanation: explanation.value,
    difficulty: difficulty.value,
    bloom_level: bloomLevel.value,
    default_score: Number(defaultScore.value || 1),
    status: nextStatus,
    change_note: `${t.value.hints.changeNotePrefix} ${nextStatus}`,
    metadata: {
      tags: tags.value
        .split(',')
        .map((tag) => tag.trim())
        .filter(Boolean),
      source: 'editor',
      advanced_settings: {
        randomize_choices: randomizeChoices.value,
        strict_blank_match: strictBlankMatch.value,
        allow_partial_credit: partialCredit.value,
      },
    },
  }

  if (['single_choice', 'multiple_choice', 'true_false', 'ordering'].includes(type.value)) {
    payload.options = options.value
      .filter((option) => option.content.trim())
      .map((option, index) => ({
        option_key: String.fromCharCode(65 + index),
        content: option.content,
        is_correct: Boolean(option.is_correct),
        feedback: option.feedback || '',
        score_weight: option.score_weight ?? 1,
      }))
  }

  if (type.value === 'fill_blank') {
    payload.fill_blank_answers = fillBlanks.value
      .filter((item) => item.blank_key.trim() || item.accepted_answer.trim())
      .map((answer) => ({
        blank_key: answer.blank_key,
        accepted_answer: answer.accepted_answer,
        score_weight: answer.score_weight ?? 1,
      }))
  }

  if (type.value === 'matching') {
    payload.matching_pairs = pairs.value
      .filter((pair) => pair.left_content.trim() || pair.right_content.trim())
      .map((pair) => ({
        left_content: pair.left_content,
        right_content: pair.right_content,
      }))
  }

  if (['audio', 'image', 'video'].includes(type.value)) {
    payload.metadata.media_url = payload.metadata.media_url || 'https://example.edu/media/question-preview'
  }

  return payload
}

function appendTemplate(kind, value) {
  if (kind === 'stem') {
    questionStem.value = `${questionStem.value}<p>${value}</p>`
    message.value = `${t.value.labels.insertedTemplate} "${value.trim()}"`
  }
  if (kind === 'option') {
    options.value.push({ content: value, is_correct: false, feedback: '', score_weight: 1 })
  }
}

function appendOptionTemplate() {
  const label = String.fromCharCode(65 + options.value.length)
  appendTemplate('option', `${label}. `)
}

function addFillBlank() {
  const next = fillBlanks.value.length + 1
  fillBlanks.value.push({ blank_key: `blank_${next}`, accepted_answer: '', score_weight: 1 })
}

function removeFillBlank(index) {
  if (fillBlanks.value.length <= 1) return
  fillBlanks.value.splice(index, 1)
}

function addMatchingPair() {
  pairs.value.push({ left_content: '', right_content: '' })
}

function removeMatchingPair(index) {
  if (pairs.value.length <= 1) return
  pairs.value.splice(index, 1)
}

function addOption() {
  options.value.push({ content: '', is_correct: false, feedback: '', score_weight: 1 })
}

function removeOption(index) {
  if (options.value.length <= 2) return
  options.value.splice(index, 1)
}

function resetForm() {
  selectedCategoryId.value = null
  code.value = `Q-${Date.now().toString().slice(-6)}`
  setDefaults()
  type.value = 'single_choice'
  status.value = 'draft'
  difficulty.value = 'medium'
  bloomLevel.value = 'understand'
  defaultScore.value = 1
  explanation.value = ''
  randomizeChoices.value = false
  strictBlankMatch.value = true
  partialCredit.value = false
  message.value = ''
}

async function saveWithStatus(nextStatus = status.value) {
  if (!selectedBankId.value) {
    message.value = t.value.hints.noBank
    return
  }
  if (!code.value.trim() || !title.value.trim()) {
    message.value = t.value.hints.noCode
    return
  }
  if (!type.value) {
    message.value = t.value.hints.noType
    return
  }
  if (!readyToSave.value) {
    message.value = t.value.hints.statusEmpty
  }

  saving.value = true
  try {
    const payload = buildPayload(nextStatus)
    const saved = questionId.value
      ? await api(`/questions/${questionId.value}`, { method: 'PUT', body: JSON.stringify(payload) })
      : await api('/questions', { method: 'POST', body: JSON.stringify(payload) })
    questionId.value = saved.id
    status.value = saved.status || nextStatus
    message.value = `${t.value.hints.successSave} #${saved.id}. ${questionId.value ? '' : ''}`
  } catch (error) {
    message.value = error.message
  } finally {
    saving.value = false
  }
}

function saveDraft() {
  status.value = 'draft'
  return saveWithStatus('draft')
}

function submitReview() {
  status.value = 'review'
  return saveWithStatus('review')
}

function applyAiSuggestion() {
  if (!type.value) return
  difficulty.value = 'medium'
  defaultScore.value = Math.max(1, defaultScore.value)
  bloomLevel.value = 'understand'
  explanation.value = t.value.hints.aiSuggestionText
  appendTemplate('stem', t.value.hints.aiTemplateText)
  message.value = t.value.hints.aiHint
}

function getAiUrl(tab) {
  const params = new URLSearchParams()
  params.set('v', Date.now().toString())
  params.set('lang', locale)
  if (questionId.value) params.set('question_id', String(questionId.value))
  if (selectedBankId.value) params.set('bank_id', String(selectedBankId.value))
  return `/ai?${params.toString()}#${tab}`
}
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>{{ t.breadcrumb }}</template>

    <section class="space-y-5">
      <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="grid gap-6 bg-slate-950 px-6 py-6 text-white xl:grid-cols-[1fr_420px]">
          <div>
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-200">{{ t.title }}</div>
            <h1 class="mt-2 text-2xl font-bold">{{ t.title }}</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">{{ t.subtitle }}</p>
          </div>
          <div class="grid grid-cols-3 gap-3 text-center">
            <div class="rounded-md bg-white/10 p-4">
              <div class="inline-flex items-center gap-2 text-sm text-slate-200"><FileText class="h-4 w-4" /> {{ Object.keys(typeLabels).length }}</div>
              <div class="mt-1 text-2xl font-bold">{{ t.labels.typeCount }}</div>
            </div>
            <div class="rounded-md bg-white/10 p-4">
              <div class="text-2xl font-bold">{{ qualityRate }}%</div>
              <div class="mt-1 text-xs text-slate-300">{{ t.labels.editorQuality }}</div>
            </div>
            <div class="rounded-md bg-emerald-500/20 p-4">
              <div class="text-2xl font-bold">{{ readyToSave ? t.labels.pass : t.labels.draft }}</div>
              <div class="mt-1 text-xs text-emerald-100">{{ qualityBadge }}</div>
            </div>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-2 border-t border-slate-800/40 px-6 py-3">
          <button class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" :title="t.hints.questionHint" @click="message = `${t.hints.questionHint}`">
            <PencilLine class="h-4 w-4" />
            {{ t.actions.preview }}
          </button>
          <button class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60" :disabled="saving" :title="t.actions.saveDraft" @click="saveDraft">
            <Undo2 class="h-4 w-4" />
            {{ t.actions.saveDraft }}
          </button>
          <button class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60" :disabled="saving" :title="t.actions.submitReview" @click="submitReview">
            <ListChecks class="h-4 w-4" />
            {{ t.actions.submitReview }}
          </button>
          <button class="inline-flex items-center gap-2 rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-60" :disabled="saving" :title="t.actions.save" @click="saveWithStatus(status)">
            <ShieldCheck class="h-4 w-4" />
            {{ t.actions.save }}
          </button>
          <button class="inline-flex items-center gap-2 rounded-md border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-900 hover:bg-emerald-100" :title="t.actions.reset" @click="resetForm">
            <RefreshCw class="h-4 w-4" />
            {{ t.actions.reset }}
          </button>
          <a
            class="ml-auto inline-flex items-center gap-2 rounded-md border border-cyan-300 bg-cyan-950/10 px-3 py-2 text-sm font-semibold text-cyan-100 hover:bg-cyan-950/20"
            :href="getAiUrl('assistant')"
            :title="t.hints.aiHint"
          >
            <WandSparkles class="h-4 w-4 text-cyan-300" />
            {{ t.actions.aiSuggestion }}
          </a>
          <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">{{ typeLabels[type] }} · {{ statusLabels[status] }}</span>
        </div>
      </div>

      <div v-if="loading" class="rounded-md border border-slate-200 bg-white px-4 py-3 text-sm text-slate-500">{{ t.hints.loading }}</div>
      <div v-if="message" class="rounded-md border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-900">{{ message }}</div>

      <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <main class="min-w-0 space-y-5">
          <section class="rounded-lg border border-slate-200 bg-white shadow-sm" :title="t.labels.questionSetupHint || t.labels.qualityHint">
            <div class="border-b border-slate-200 px-5 py-4">
              <h2 class="inline-flex items-center gap-2 text-sm font-bold text-slate-900">
                <FileText class="h-4 w-4 text-slate-700" />
                {{ t.sections.questionSetup }}
              </h2>
              <p class="mt-1 text-xs text-slate-500">{{ t.hints.questionHint }}</p>
            </div>
            <div class="grid gap-4 p-5 md:grid-cols-3">
              <label class="space-y-1">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t.labels.bank }}</span>
                <select
                  v-model="selectedBankId"
                  class="h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                  @change="loadCategories(selectedBankId)"
                >
                  <option v-for="bank in banks" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
                </select>
              </label>
              <label class="space-y-1">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t.labels.category }}</span>
                <select
                  v-model="selectedCategoryId"
                  class="h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                >
                  <option :value="null">{{ t.labels.noCategory }}</option>
                  <option v-for="category in selectedBankCategories" :key="category.id" :value="category.id">{{ category.name }}</option>
                </select>
              </label>
              <label class="space-y-1">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t.labels.status }}</span>
                <select
                  v-model="status"
                  class="h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                >
                  <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
                </select>
              </label>
              <label class="space-y-1">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t.labels.code }}</span>
                <input
                  v-model="code"
                  class="h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                  :placeholder="t.labels.code"
                />
              </label>
              <label class="space-y-1">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t.labels.title }}</span>
                <input
                  v-model="title"
                  class="h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                  :placeholder="t.labels.titlePlaceholder"
                />
              </label>
              <label class="space-y-1">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t.labels.type }}</span>
                <select
                  v-model="type"
                  class="h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                >
                  <option v-for="(label, key) in typeLabels" :key="key" :value="key">{{ label }}</option>
                </select>
              </label>
              <label class="space-y-1">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t.labels.difficulty }}</span>
                <select v-model="difficulty" class="h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100">
                  <option v-for="option in difficultyOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
              </label>
              <label class="space-y-1">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t.labels.bloom }}</span>
                <select v-model="bloomLevel" class="h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100">
                  <option v-for="option in bloomOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
              </label>
            </div>
          </section>

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
               <div class="border-b border-slate-200 px-5 py-4" :title="t.labels.stemPlaceholder">
                <div>
                  <h2 class="inline-flex items-center gap-2 text-sm font-bold text-slate-900">
                    <MessageSquareText class="h-4 w-4 text-slate-700" />
                    {{ t.sections.stem }}
                  </h2>
                  <p class="mt-1 text-xs text-slate-500">{{ t.sections.quickTools }}</p>
                </div>
                <div class="text-xs text-slate-500 inline-flex items-center gap-1">
                  <Lightbulb class="h-4 w-4 text-amber-500" />
                  {{ t.labels.templateLabel }}
                </div>
              </div>
              <div class="mt-3 flex flex-wrap gap-2">
                <button
                  v-for="template in aiTemplates.stem"
                  :key="template.title"
                  class="rounded border px-2 py-1 text-xs"
                  :title="template.title"
                  @click="appendTemplate('stem', template.content)"
                >
                  {{ template.title }}
                </button>
                <button
                  class="rounded border px-2 py-1 text-xs"
                  :title="t.labels.clearStem"
                  @click="questionStem = '<p></p>'"
                >
                  {{ t.labels.clearStem }}
                </button>
              </div>
            <div class="space-y-1 border-t border-slate-200 p-5">
              <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t.labels.contentHeader }}</div>
              <RichTextEditor
                class="question-author-editor"
                v-model="questionStem"
                :api-headers="apiHeaders"
                toolbar-scale="xl"
                min-height="520px"
                :placeholder="t.labels.stemPlaceholder"
              />
            </div>
          </section>

          <section v-if="hasAnswerSection" class="rounded-lg border border-slate-200 bg-white shadow-sm" :title="t.labels.answerHint">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
              <div>
                <h2 class="inline-flex items-center gap-2 text-sm font-bold text-slate-900">
                  <ListChecks class="h-4 w-4 text-slate-700" />
                  {{ t.sections.answers }}
                </h2>
                <p class="mt-1 text-xs text-slate-500">{{ t.labels.answerHint }}</p>
              </div>
              <div class="flex items-center gap-2">
                <button class="inline-flex items-center gap-1 rounded-md border px-3 py-2 text-xs" :title="t.labels.addOptionAction" @click="appendOptionTemplate">
                  <Plus class="h-3.5 w-3.5" />
                  {{ t.labels.add }}
                </button>
                <button class="rounded-md bg-slate-900 px-3 py-2 text-xs text-white" :title="t.labels.addOptionAction" @click="addOption">
                  {{ t.labels.addOptionAction }}
                </button>
              </div>
            </div>
            <div class="space-y-3 p-5">
              <div
                v-for="(option, index) in options"
                :key="index"
                class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3 md:grid-cols-[44px_1fr_1fr_90px_88px]"
              >
                <label class="grid h-11 place-items-center rounded-md bg-white">
                  <input
                    v-model="option.is_correct"
                    type="checkbox"
                    class="h-4 w-4 rounded border-slate-300 text-blue-600"
                    :title="t.labels.optionCorrect"
                  />
                  <span class="mt-1 text-[11px] text-slate-500">{{ t.labels.optionCorrect }}</span>
                </label>
                <input
                  v-model="option.content"
                  class="h-11 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                  :placeholder="`${t.labels.optionText} ${index + 1}`"
                />
                <input
                  v-model="option.feedback"
                  class="h-11 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                  :placeholder="t.labels.optionFeedback"
                />
                <input
                  v-model.number="option.score_weight"
                  type="number"
                  min="0"
                  step="0.25"
                  class="h-11 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                  :placeholder="t.labels.pointPlaceholder"
                />
                <button
                  class="rounded border border-rose-300 bg-white px-2 text-xs text-rose-700"
                  :title="`${t.labels.remove}: ${option.content || `${t.labels.optionLabel} ${index + 1}`}`"
                  @click="removeOption(index)"
                >
                  {{ t.labels.remove }}
                </button>
              </div>
            </div>
          </section>

          <section v-if="hasFillSection" class="rounded-lg border border-slate-200 bg-white shadow-sm" :title="t.labels.fillBlankHint">
            <div class="border-b border-slate-200 px-5 py-4">
              <div class="flex items-center justify-between gap-3">
                <div>
                  <h2 class="inline-flex items-center gap-2 text-sm font-bold text-slate-900">
                    <PencilLine class="h-4 w-4 text-slate-700" />
                    {{ t.sections.fillBlank }}
                  </h2>
                  <p class="mt-1 text-xs text-slate-500">{{ t.labels.fillBlankHint }}</p>
                </div>
                <button
                  class="rounded-md bg-slate-900 px-3 py-2 text-xs text-white"
                  :title="t.labels.addBlank"
                  @click="addFillBlank"
                >
                  {{ t.labels.addBlank }}
                </button>
              </div>
            </div>
            <div class="space-y-3 p-5">
              <div v-for="(blank, index) in fillBlanks" :key="blank.blank_key" class="grid gap-3 md:grid-cols-[110px_1fr_90px_72px]">
                <input
                  v-model="blank.blank_key"
                  class="h-11 rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                  :placeholder="t.labels.blankKey"
                />
                <input
                  v-model="blank.accepted_answer"
                  class="h-11 rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                  :placeholder="t.labels.blankAnswer"
                />
                <input
                  v-model.number="blank.score_weight"
                  type="number"
                  min="0"
                  step="0.25"
                  class="h-11 rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                  :placeholder="t.labels.pointPlaceholder"
                />
                <button
                  class="rounded border border-rose-300 bg-white px-2 text-xs text-rose-700"
                  :title="`${t.labels.remove}: ${blank.blank_key || `${t.labels.blankLabel} ${index + 1}`}`"
                  @click="removeFillBlank(index)"
                >
                  {{ t.labels.remove }}
                </button>
              </div>
            </div>
          </section>

          <section v-if="hasMatchingSection" class="rounded-lg border border-slate-200 bg-white shadow-sm" :title="t.labels.matchingHint">
            <div class="border-b border-slate-200 px-5 py-4">
              <div class="flex items-center justify-between gap-3">
                <div>
                  <h2 class="inline-flex items-center gap-2 text-sm font-bold text-slate-900">
                    <WandSparkles class="h-4 w-4 text-slate-700" />
                    {{ t.sections.matching }}
                  </h2>
                  <p class="mt-1 text-xs text-slate-500">{{ t.labels.matchingHint }}</p>
                </div>
                <button
                  class="rounded-md bg-slate-900 px-3 py-2 text-xs text-white"
                  :title="t.labels.addPair"
                  @click="addMatchingPair"
                >
                  {{ t.labels.addPair }}
                </button>
              </div>
            </div>
            <div class="space-y-3 p-5">
              <div v-for="(pair, index) in pairs" :key="`${pair.left_content}-${index}`" class="grid gap-3 md:grid-cols-5">
                <input
                  v-model="pair.left_content"
                  class="md:col-span-2 h-11 rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                  :placeholder="t.labels.left"
                />
                <input
                  v-model="pair.right_content"
                  class="md:col-span-2 h-11 rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                  :placeholder="t.labels.right"
                />
                <button
                  class="rounded border border-rose-300 bg-white px-2 text-xs text-rose-700"
                  :title="`${t.labels.remove}: ${t.labels.pairLabel} ${index + 1}`"
                  @click="removeMatchingPair(index)"
                >
                  {{ t.labels.remove }}
                </button>
              </div>
            </div>
          </section>
        </main>

        <aside class="min-w-0 space-y-5">
          <section class="rounded-lg border border-slate-200 bg-white shadow-sm" :title="t.labels.qualityHint">
            <div class="border-b border-slate-200 px-5 py-4">
              <div class="inline-flex items-center gap-2 text-sm font-bold text-slate-900">
                <CheckCircle2 class="h-4 w-4 text-emerald-600" />
                {{ t.sections.quality }}
              </div>
              <p class="mt-1 text-xs text-slate-500">{{ qualityBadge }}</p>
            </div>
            <div class="space-y-2 p-5">
              <div
                v-for="item in qualityChecks"
                :key="item.key"
                class="rounded-md border border-slate-200 px-3 py-2 text-sm"
                :class="item.pass ? 'bg-emerald-50 border-emerald-200' : 'bg-rose-50 border-rose-200'"
              >
                <div class="inline-flex items-center gap-2">
                  <component :is="item.pass ? CheckCircle2 : XCircle" class="h-4 w-4" />
                  <span>{{ item.label }}</span>
                </div>
              <div class="text-xs text-slate-500">{{ item.pass ? t.labels.pass : t.labels.missing }}</div>
              </div>
              <button class="w-full rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white" @click="applyAiSuggestion">
                {{ t.actions.applySuggestion }}
              </button>
            </div>
          </section>

          <section class="rounded-lg border border-slate-200 bg-white shadow-sm" :title="t.labels.metadataHint">
            <div class="border-b border-slate-200 px-5 py-4">
              <h2 class="inline-flex items-center gap-2 text-sm font-bold text-slate-900">
                <FileText class="h-4 w-4 text-slate-700" />
                {{ t.sections.metadata }}
              </h2>
                <p class="mt-1 text-xs text-slate-500">{{ t.labels.metadataHint }}</p>
            </div>
            <div class="space-y-3 p-5">
              <input
                v-model="defaultScore"
                type="number"
                min="0"
                step="0.25"
                class="h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                :placeholder="t.labels.score"
              />
              <textarea
                v-model="explanation"
                class="min-h-24 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                :placeholder="t.labels.explanationPlaceholder"
              ></textarea>
              <input
                v-model="tags"
                class="h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                :placeholder="t.labels.tagPlaceholder"
              />
            </div>
          </section>

          <section class="rounded-lg border border-slate-200 bg-white shadow-sm" :title="t.labels.advancedSettings">
            <div class="border-b border-slate-200 px-5 py-4">
              <h2 class="inline-flex items-center gap-2 text-sm font-bold text-slate-900">
                <ShieldCheck class="h-4 w-4 text-slate-700" />
                {{ t.sections.advanced }}
              </h2>
              <p class="mt-1 text-xs text-slate-500">{{ t.labels.sourceSystems }}</p>
            </div>
            <div class="space-y-2 p-5">
              <label class="flex items-start justify-between gap-3 rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                <span>
                  <span class="font-medium">{{ t.labels.randomizeChoices }}</span>
                  <span class="block text-[11px] text-slate-500">{{ t.labels.randomizeChoicesHint }}</span>
                </span>
                <input v-model="randomizeChoices" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600" />
              </label>
              <label class="flex items-start justify-between gap-3 rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                <span>
                  <span class="font-medium">{{ t.labels.strictBlankMatch }}</span>
                  <span class="block text-[11px] text-slate-500">{{ t.labels.strictBlankMatchHint }}</span>
                </span>
                <input v-model="strictBlankMatch" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600" />
              </label>
              <label class="flex items-start justify-between gap-3 rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                <span>
                  <span class="font-medium">{{ t.labels.partialCredit }}</span>
                  <span class="block text-[11px] text-slate-500">{{ t.labels.partialCreditHint }}</span>
                </span>
                <input v-model="partialCredit" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600" />
              </label>
            </div>
          </section>

          <section class="rounded-lg border border-slate-200 bg-white shadow-sm" :title="t.labels.previewHint">
            <div class="border-b border-slate-200 px-5 py-4">
              <h2 class="inline-flex items-center gap-2 text-sm font-bold text-slate-900">
                <ListChecks class="h-4 w-4 text-slate-700" />
                {{ t.sections.preview }}
              </h2>
            </div>
            <div class="p-5">
              <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                <p class="text-sm font-bold text-slate-900">{{ previewTitle }}</p>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ title || t.labels.titlePlaceholder }}</p>
                <div class="mt-4 space-y-2">
                  <div
                    v-for="(option, index) in previewOptions"
                    :key="`${option.content}-${index}`"
                    class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                  >
                    {{ option.content || `${t.labels.optionLabel} ${index + 1}` }}
                  </div>
                  <div v-if="hasFillSection && fillBlanks.length" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm">
                    {{ t.labels.blankLabel }}: {{ fillBlanks.map((item) => `${item.blank_key}=${item.accepted_answer}`).join(' ; ') }}
                  </div>
                  <div v-if="hasMatchingSection && pairs.length" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm">
                    {{ t.labels.pairLabel }}: {{ pairs.length }}
                  </div>
                </div>
              </div>
            </div>
          </section>

          <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm" :title="t.labels.openAiLabel">
            <div class="text-sm font-semibold text-slate-900 inline-flex items-center gap-2">
              <MessageSquareText class="h-4 w-4" />
              {{ t.sections.aiCard }}
            </div>
            <p class="mt-2 text-xs text-slate-500">{{ t.labels.aiSourceHint }}</p>
            <div class="mt-3 grid gap-2">
              <a
                :href="getAiUrl('assistant')"
                class="rounded border border-cyan-200 bg-cyan-50 px-3 py-2 text-sm hover:bg-cyan-100"
              >
                1. {{ t.labels.aiActionWording }} {{ qualityRate }}%
              </a>
              <a
                :href="getAiUrl('generation')"
                class="rounded border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm hover:bg-indigo-100"
              >
                2. {{ t.labels.aiActionClone }}
              </a>
              <a
                :href="`/question-banks/import?bank_id=${selectedBankId || ''}`"
                class="rounded border border-slate-200 px-3 py-2 text-sm hover:bg-slate-100"
              >
                3. {{ t.labels.importRelated }}
              </a>
            </div>
            <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t.sections.quickTools }}</p>
            <div class="mt-2 grid gap-2">
              <a
                v-for="section in aiHubSectionCards"
                :key="section.id"
                :href="section.href"
                class="rounded border border-blue-200 bg-white px-3 py-2 text-sm hover:bg-blue-50"
                :title="section.tooltip"
              >
                <span class="inline-flex items-center gap-2 text-slate-800">
                  <component :is="section.icon" class="h-4 w-4 text-blue-700" />
                  {{ section.order }}. {{ section.title }}
                </span>
                <span class="ml-6 block text-xs text-slate-500">{{ section.hint }}</span>
              </a>
            </div>
            <div class="mt-3 rounded-md bg-slate-50 p-2 text-xs text-slate-600">
              {{ t.labels.contextLabel }}: {{ aiContext || 'new-question' }}
            </div>
            <p class="mt-3 text-xs text-slate-500">{{ t.sections.references }}</p>
            <ul class="mt-2 list-disc space-y-1 pl-4 text-xs text-slate-600">
              <li v-for="system in benchmarks" :key="system.key" :title="system.note">{{ system.label }} · {{ system.note }}</li>
            </ul>
          </section>

          <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm" :title="t.actions.save">
            <div class="grid grid-cols-2 gap-2">
              <button
                class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60"
                :title="t.actions.saveDraft"
                :disabled="saving"
                @click="saveDraft"
              >
                {{ t.actions.saveDraft }}
              </button>
              <button
                class="rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-60"
                :title="t.actions.submitReview"
                :disabled="saving"
                @click="submitReview"
              >
                {{ t.actions.submitReview }}
              </button>
            </div>
            <div class="mt-2 rounded-md border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
              {{ t.labels.status }}: {{ statusLabels[status] }} ·
              {{ t.labels.ready }}: {{ readyToSave ? t.labels.yes : t.labels.no }} ·
              {{ t.labels.review }}: {{ readyForReview ? t.labels.yes : t.labels.no }}
            </div>
          </section>
        </aside>
      </div>
    </section>
  </EraLmsLayout>
</template>
