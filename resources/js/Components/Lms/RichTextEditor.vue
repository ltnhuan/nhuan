<script setup>
import { computed, ref } from 'vue'
import { Ckeditor } from '@ckeditor/ckeditor5-vue'
import {
  Alignment,
  Autoformat,
  BlockQuote,
  Bold,
  ClassicEditor,
  Code,
  CodeBlock,
  Essentials,
  FontBackgroundColor,
  FontColor,
  FontFamily,
  FontSize,
  GeneralHtmlSupport,
  Heading,
  Highlight,
  HorizontalLine,
  HtmlEmbed,
  Image,
  ImageCaption,
  ImageInsert,
  ImageResize,
  ImageStyle,
  ImageToolbar,
  ImageUpload,
  Indent,
  Italic,
  Link,
  LinkImage,
  List,
  MediaEmbed,
  Paragraph,
  PasteFromOffice,
  RemoveFormat,
  SourceEditing,
  Strikethrough,
  Table,
  TableCaption,
  TableCellProperties,
  TableProperties,
  TableToolbar,
  TodoList,
  Underline,
} from 'ckeditor5'
import 'ckeditor5/ckeditor5.css'

const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: 'Soạn nội dung...' },
  minHeight: { type: String, default: '260px' },
  apiHeaders: { type: Object, default: () => ({}) },
  uploadUrl: { type: String, default: '/api/v1/editor/media-upload' },
})

const emit = defineEmits(['update:modelValue', 'media-uploaded'])
const editorInstance = ref(null)

class EraLmsUploadAdapter {
  constructor(loader) {
    this.loader = loader
    this.controller = new AbortController()
  }

  async upload() {
    const file = await this.loader.file
    const media = await uploadMedia(file, this.controller.signal)

    return {
      default: media.url,
    }
  }

  abort() {
    this.controller.abort()
  }
}

function uploadAdapterPlugin(editor) {
  editor.plugins.get('FileRepository').createUploadAdapter = (loader) => new EraLmsUploadAdapter(loader)
}

const editorConfig = computed(() => ({
  extraPlugins: [uploadAdapterPlugin],
  licenseKey: 'GPL',
  placeholder: props.placeholder,
  plugins: [
    Alignment,
    Autoformat,
    BlockQuote,
    Bold,
    Code,
    CodeBlock,
    Essentials,
    FontBackgroundColor,
    FontColor,
    FontFamily,
    FontSize,
    GeneralHtmlSupport,
    Heading,
    Highlight,
    HorizontalLine,
    HtmlEmbed,
    Image,
    ImageCaption,
    ImageInsert,
    ImageResize,
    ImageStyle,
    ImageToolbar,
    ImageUpload,
    Indent,
    Italic,
    Link,
    LinkImage,
    List,
    MediaEmbed,
    Paragraph,
    PasteFromOffice,
    RemoveFormat,
    SourceEditing,
    Strikethrough,
    Table,
    TableCaption,
    TableCellProperties,
    TableProperties,
    TableToolbar,
    TodoList,
    Underline,
  ],
  toolbar: {
    items: [
      'undo',
      'redo',
      '|',
      'heading',
      '|',
      'bold',
      'italic',
      'underline',
      'strikethrough',
      'code',
      'removeFormat',
      '|',
      'fontSize',
      'fontColor',
      'fontBackgroundColor',
      'highlight',
      '|',
      'alignment',
      'bulletedList',
      'numberedList',
      'todoList',
      'outdent',
      'indent',
      '|',
      'link',
      'insertImage',
      'mediaEmbed',
      'insertTable',
      'blockQuote',
      'codeBlock',
      'htmlEmbed',
      'horizontalLine',
      '|',
      'sourceEditing',
    ],
    shouldNotGroupWhenFull: false,
  },
  image: {
    toolbar: [
      'imageTextAlternative',
      'toggleImageCaption',
      '|',
      'imageStyle:inline',
      'imageStyle:block',
      'imageStyle:side',
      '|',
      'resizeImage',
      'linkImage',
    ],
  },
  table: {
    contentToolbar: [
      'tableColumn',
      'tableRow',
      'mergeTableCells',
      'tableProperties',
      'tableCellProperties',
      'toggleTableCaption',
    ],
  },
  mediaEmbed: {
    previewsInData: true,
  },
  htmlSupport: {
    allow: [
      {
        name: /.*/,
        attributes: true,
        classes: true,
        styles: true,
      },
    ],
  },
  link: {
    addTargetToExternalLinks: true,
    defaultProtocol: 'https://',
  },
}))

function onReady(editor) {
  editorInstance.value = editor
  editor.editing.view.change((writer) => {
    writer.setStyle('min-height', props.minHeight, editor.editing.view.document.getRoot())
  })
}

function openMediaPicker(accept) {
  const input = document.createElement('input')
  input.type = 'file'
  input.accept = accept
  input.style.display = 'none'
  input.addEventListener('change', async () => {
    const file = input.files?.[0]
    input.remove()
    if (!file) return

    const media = await uploadMedia(file)
    insertMedia(media)
    emit('media-uploaded', media)
  }, { once: true })

  document.body.append(input)
  input.click()
}

async function uploadMedia(file, signal = undefined) {
  const form = new FormData()
  form.append('file', file)
  form.append('title', file.name.replace(/\.[^.]+$/, ''))

  const headers = { ...props.apiHeaders }
  delete headers['Content-Type']

  const response = await fetch(props.uploadUrl, {
    method: 'POST',
    headers,
    body: form,
    signal,
  })
  const payload = await response.json().catch(() => ({}))

  if (!response.ok || payload.success === false) {
    throw new Error(payload.message || 'Không thể upload media vào editor.')
  }

  return payload.data || payload
}

function insertMedia(media) {
  const editor = editorInstance.value
  if (!editor || !media?.url) return

  const title = escapeHtml(media.title || 'Media')
  const url = escapeAttribute(media.url)
  const mime = escapeAttribute(media.mime_type || '')
  const html = media.mime_type?.startsWith('audio/')
    ? `<figure class="media"><audio controls preload="metadata" src="${url}"></audio><figcaption>${title}</figcaption></figure>`
    : media.mime_type?.startsWith('video/')
      ? `<figure class="media"><video controls preload="metadata" src="${url}" style="max-width:100%"></video><figcaption>${title}</figcaption></figure>`
      : media.mime_type?.startsWith('image/')
        ? `<figure class="image"><img src="${url}" alt="${title}"><figcaption>${title}</figcaption></figure>`
        : `<p><a href="${url}" data-mime-type="${mime}" target="_blank" rel="noopener noreferrer">${title}</a></p>`

  editor.execute('htmlEmbed', html)
  editor.editing.view.focus()
}

function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
}

function escapeAttribute(value) {
  return escapeHtml(value).replaceAll("'", '&#039;')
}
</script>

<template>
  <div class="eralms-rich-text-editor min-w-0 max-w-full overflow-hidden rounded-md border border-slate-300 bg-white shadow-sm">
    <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 bg-slate-50 px-3 py-2">
      <button type="button" class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100" @click="openMediaPicker('image/*')">Ảnh</button>
      <button type="button" class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100" @click="openMediaPicker('audio/*')">Âm thanh</button>
      <button type="button" class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100" @click="openMediaPicker('video/*')">Video</button>
      <span class="text-xs text-slate-500">Upload media vào Repository và chèn trực tiếp vào nội dung.</span>
    </div>
    <ckeditor
      :editor="ClassicEditor"
      :config="editorConfig"
      :model-value="modelValue"
      @ready="onReady"
      @update:model-value="emit('update:modelValue', $event)"
    />
  </div>
</template>

<style scoped>
.eralms-rich-text-editor :deep(.ck),
.eralms-rich-text-editor :deep(.ck-editor),
.eralms-rich-text-editor :deep(.ck-editor__main),
.eralms-rich-text-editor :deep(.ck-editor__editable),
.eralms-rich-text-editor :deep(.ck-toolbar),
.eralms-rich-text-editor :deep(.ck-toolbar__items) {
  max-width: 100%;
  min-width: 0;
}

.eralms-rich-text-editor :deep(.ck-toolbar__items) {
  flex-wrap: wrap;
}

.eralms-rich-text-editor :deep(.ck-editor__editable_inline) {
  overflow-wrap: anywhere;
}
</style>
