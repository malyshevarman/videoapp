<script setup>
import { reactive, ref, onMounted } from 'vue'
import draggable from 'vuedraggable'
import { Toaster, toast } from 'vue-sonner'

const props = defineProps({
    service: Object
})

const service = reactive(props.service)
const defects = ref([])
const isSaved = ref(false)
const isLinkCopied = ref(false)

const videoData = ref(null)
const videoUrl = ref(null)

const previewVideo = ref(null)
const previewCanvas = ref(null)

function clampTime(element) {
    if (!previewVideo.value) return
    const maxTime = Math.floor(previewVideo.value.duration || 0)
    if (element.time > maxTime) element.time = maxTime
    if (element.time < 0) element.time = 0
}

function seekTo(seconds) {
    if (!previewVideo.value) return
    const maxTime = Math.floor(previewVideo.value.duration || 0)
    previewVideo.value.currentTime = Math.min(Math.max(seconds, 0), maxTime)
}

onMounted(() => {
    loadVideo()
    if (service.defects) {
        if (Array.isArray(service.defects)) {
            defects.value = [...service.defects]
        } else {
            try {
                const parsed = JSON.parse(service.defects)
                defects.value = Array.isArray(parsed) ? parsed : []
            } catch (e) {
                defects.value = []
                console.error('Failed to parse defects JSON:', e)
            }
        }
    } else {
        defects.value = []
    }
})

const newDefect = reactive({
    time: null,
    title: '',
    status: 'green'
})

const loadVideo = async () => {
    const res = await fetch(`/video?service_order_id=${service.id}`)
    if (res.status === 204) return

    const data = await res.json()
    videoData.value = data.video
    videoUrl.value = data.url
}

const addDefect = async () => {
    if (!newDefect.title) return

    const tasksLength = service.tasks?.length || 0
    const defectsLength = defects.value.length || 0

    const item = {
        id: tasksLength + defectsLength + 1,
        time: 0,
        title: newDefect.title,
        status: newDefect.status || 'green',
        preview: null
    }

    defects.value.push(item)
    newDefect.title = ''
    newDefect.status = 'green'
}

const removeDefect = (index) => {
    defects.value.splice(index, 1)
}

const saveDefects = async () => {
    await fetch('/video/defects', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document
                .querySelector('meta[name="csrf-token"]')
                .getAttribute('content'),
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            service_id: service.id,
            defects: defects.value.map(({ id, time, title, status }) => ({ id, time, title, status }))
        })
    })

    isSaved.value = true
    setTimeout(() => (isSaved.value = false), 2000)
}

const getStatusIcon = (status) => {
    switch (status) {
        case 'green': return 'OK'
        case 'yellow': return '!'
        case 'red': return 'X'
        default: return '?'
    }
}

const handleVideoRecord = async () => {
    window.location.href = `/admin/services/${service.id}/video`
}

const getStatusClass = (status) => {
    switch (status) {
        case 'green': return 'status-green'
        case 'yellow': return 'status-yellow'
        case 'red': return 'status-red'
        default: return ''
    }
}

const copyToClipboard = async (text) => {
    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text)
        return
    }

    const textarea = document.createElement('textarea')
    textarea.value = text
    textarea.setAttribute('readonly', '')
    textarea.style.position = 'absolute'
    textarea.style.left = '-9999px'
    document.body.appendChild(textarea)
    textarea.select()
    document.execCommand('copy')
    document.body.removeChild(textarea)
}

const copyServiceLink = async (event) => {
    event.preventDefault()

    try {
        if (defects.value.length > 0) {
            await saveDefects()
        }

        await fetch(`/services/${service.public_url}/sent`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute('content'),
                'Accept': 'application/json',
            },
        })

        const link = `${window.location.origin}/services/${service.public_url}/show`
        await copyToClipboard(link)

        isLinkCopied.value = true
        setTimeout(() => (isLinkCopied.value = false), 2000)
        toast.success('Ссылка скопирована', {
            description: 'Ссылка для клиента скопирована в буфер обмена',
        })
    } catch (e) {
        toast.error('Не удалось скопировать ссылку', {
            description: 'Проверьте доступ к буферу обмена и попробуйте снова',
        })
    }
}
</script>

<template>
    <div class="card card-primary service-edit-card">
        <Toaster position="top-right" />
        <div class="card-header">
            <div class="d-flex flex-wrap align-items-center justify-content-between w-100" style="gap: 10px;">
                <h3 class="card-title mb-0">Редактирование заявки #{{ service.id }}</h3>
                <span class="badge badge-light border px-3 py-2">External ID: {{ service.order_id || '-' }}</span>
            </div>
        </div>

        <div class="card-body service-edit-body">
            <div class="section-title-wrap mb-3">
                <h5 class="section-title mb-1">Неисправности</h5>
                <p class="text-muted mb-0 small">Добавляйте и сортируйте пункты в нужном порядке.</p>
            </div>

            <div class="row">
                <div class="col-12 col-lg-6 order-2 order-lg-1">
                    <div class="card card-outline card-success">
                        <div class="card-header">
                            <h4 class="card-title">Добавленные неисправности</h4>
                            <span class="badge badge-primary ml-2">{{ defects.length }}</span>
                        </div>

                        <div class="card-body p-0">
                            <div v-if="defects.length === 0" class="text-center p-4 text-muted">
                                Неисправности не добавлены
                            </div>

                            <draggable
                                v-model="defects"
                                item-key="id"
                                class="list-group"
                                ghost-class="ghost-item"
                                handle=".handle"
                            >
                                <template #item="{ element, index }">
                                    <div class="list-group-item defect-item">
                                        <div class="defect-meta">
                                            <div class="defect-main">
                                                <span class="handle" title="Перетащить">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </span>

                                                <span class="status-icon" :class="getStatusClass(element.status)">
                                                    {{ getStatusIcon(element.status) }}
                                                </span>

                                                <span class="defect-title">{{ element.title }}</span>
                                            </div>

                                            <div class="defect-time" v-if="videoUrl">
                                                <label class="mb-1 text-muted small">Время (сек)</label>
                                                <div class="timeel" @click="seekTo(element.time)">
                                                    <input
                                                        class="form-control form-control-sm"
                                                        v-model="element.time"
                                                        @input="clampTime(element)"
                                                        type="number"
                                                    >
                                                </div>
                                            </div>
                                        </div>

                                        <div class="actions defect-actions">
                                            <button
                                                class="btn btn-sm btn-outline-danger"
                                                @click="removeDefect(index)"
                                                title="Удалить"
                                            >
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </draggable>
                        </div>

                        <div class="card-footer" v-if="defects.length > 0">
                            <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap: 8px;">
                                <div class="actions-inline">
                                    <button
                                        class="btn btn-sm"
                                        :class="isSaved ? 'btn-success' : 'btn-primary'"
                                        :disabled="isSaved"
                                        @click="saveDefects"
                                    >
                                        <i class="fas fa-save mr-1"></i>
                                        <span v-if="!isSaved">Сохранить</span>
                                        <span v-else>Сохранено</span>
                                    </button>

                                    <a href="#" class="btn btn-sm btn-info" @click.prevent="handleVideoRecord">
                                        <i class="fas fa-video mr-1"></i>Видео
                                    </a>

                                    <a href="#" class="btn btn-sm btn-info" @click.prevent="copyServiceLink" title="Скопировать ссылку клиента">
                                        <i class="fas fa-copy mr-1"></i>{{ isLinkCopied ? 'Скопировано' : 'Копировать ссылку' }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-6 order-1 order-lg-2">
                    <div class="card card-outline card-info">
                        <div class="card-header">
                            <h4 class="card-title">Добавление неисправности</h4>
                        </div>

                        <div class="card-body">
                            <div class="form-group">
                                <label>Название неисправности</label>
                                <textarea
                                    class="form-control"
                                    rows="4"
                                    placeholder="Введите название неисправности"
                                    v-model="newDefect.title"
                                    @keyup.enter="addDefect"
                                ></textarea>
                            </div>

                            <div class="form-group">
                                <label>Статус</label>
                                <div class="d-flex align-items-center status-row">
                                    <div class="btn-group btn-group-toggle status-group" data-toggle="buttons">
                                        <label class="btn btn-outline-success" :class="{ active: newDefect.status === 'green' }">
                                            <input type="radio" v-model="newDefect.status" value="green"> <i class="fas fa-check mr-1"></i>Норма
                                        </label>
                                        <label class="btn btn-outline-warning" :class="{ active: newDefect.status === 'yellow' }">
                                            <input type="radio" v-model="newDefect.status" value="yellow"> <i class="fas fa-exclamation mr-1"></i>Внимание
                                        </label>
                                        <label class="btn btn-outline-danger" :class="{ active: newDefect.status === 'red' }">
                                            <input type="radio" v-model="newDefect.status" value="red"> <i class="fas fa-times mr-1"></i>Проблема
                                        </label>
                                    </div>

                                    <div class="status-example status-legend">
                                        <span class="status-icon status-green">OK</span> - Норма
                                        <span class="status-icon status-yellow ml-2">!</span> - Внимание
                                        <span class="status-icon status-red ml-2">X</span> - Проблема
                                    </div>
                                </div>
                            </div>

                            <button class="btn btn-primary btn-block" @click="addDefect" :disabled="!newDefect.title">
                                <i class="fas fa-plus mr-1"></i>Добавить неисправность
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <template v-if="videoUrl">
                <video
                    ref="previewVideo"
                    :src="videoUrl"
                    controls
                    preload="auto"
                    class="w-100 mt-3 border rounded service-video-preview"
                    style="max-height:300px"
                ></video>

                <canvas ref="previewCanvas" style="display:none"></canvas>
                <hr>
            </template>

            <div class="mobile-actions" v-if="defects.length > 0">
                <button class="btn" :class="isSaved ? 'btn-success' : 'btn-primary'" :disabled="isSaved" @click="saveDefects">
                    <i class="fas fa-save mr-1"></i>
                    <span v-if="!isSaved">Сохранить</span>
                    <span v-else>Сохранено</span>
                </button>
                <a href="#" class="btn btn-info" @click.prevent="handleVideoRecord">
                    <i class="fas fa-video mr-1"></i>Видео
                </a>
                <a href="#" class="btn btn-info" @click.prevent="copyServiceLink">
                    <i class="fas fa-copy mr-1"></i>{{ isLinkCopied ? 'Скопировано' : 'Копировать' }}
                </a>
            </div>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.handle {
    cursor: grab;
    color: #888;
    user-select: none;
    font-size: 20px;

    .fas {
        margin: 0 0.2em 0 0;
    }
}

.handle:active {
    cursor: grabbing;
}

.ghost-item {
    opacity: 0.5;
    background-color: #e9ecef;
}

.list-group-item {
    transition: all 0.3s;
}

.list-group-item:hover {
    background-color: #f8f9fa;
}

.defect-title {
    flex-grow: 1;
    word-break: break-word;
    padding-right: 10px;
}

.status-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    font-weight: bold;
    flex-shrink: 0;
    font-size: 11px;
}

.status-green {
    background-color: #d4edda;
    color: #155724;
}

.status-yellow {
    background-color: #fff3cd;
    color: #856404;
}

.status-red {
    background-color: #f8d7da;
    color: #721c24;
}

.btn-group-toggle .btn {
    padding: 0.35rem 0.75rem;
    font-size: 0.9rem;
}

.btn-group-toggle .btn.active {
    color: white;
}

.btn-group-toggle .btn-outline-success.active {
    background-color: #28a745;
    border-color: #28a745;
}

.btn-group-toggle .btn-outline-warning.active {
    background-color: #ffc107;
    border-color: #ffc107;
    color: #212529;
}

.btn-group-toggle .btn-outline-danger.active {
    background-color: #dc3545;
    border-color: #dc3545;
}

.status-example {
    font-size: 0.85rem;
}

.actions {
    opacity: 0.75;
    transition: opacity 0.2s;
    flex-shrink: 0;
}

.list-group-item:hover .actions {
    opacity: 1;
}

.form-group {
    margin-bottom: 1.2rem;
}

.timeel {
    display: flex;
    align-items: center;
    cursor: pointer;

    .form-control {
        width: 110px;
    }
}

.defect-item {
    gap: 0.5rem;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
}

.defect-meta {
    flex: 1 1 auto;
    min-width: 0;
}

.defect-main {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
}

.defect-time {
    margin-top: 0.45rem;
}

.defect-actions {
    flex-shrink: 0;
}

.status-row {
    flex-wrap: wrap;
    gap: 0.75rem;
}

.status-group {
    flex-wrap: wrap;
    gap: 0.35rem;
}

.status-legend {
    line-height: 1.4;
}

.service-edit-card {
    overflow: visible;
}

.service-edit-body {
    padding-bottom: 84px;
}

.actions-inline {
    display: flex;
    gap: 0.45rem;
}

.section-title {
    font-weight: 700;
}

.service-video-preview {
    background: #111;
}

.mobile-actions {
    display: none;
}

@media (max-width: 991.98px) {
    .status-row {
        align-items: flex-start !important;
    }

    .status-legend {
        width: 100%;
        margin-top: 0.25rem;
    }
}

@media (max-width: 767.98px) {
    .service-edit-body {
        padding-bottom: 98px;
    }

    .defect-item {
        flex-direction: column;
        gap: 0.65rem;
    }

    .defect-actions {
        width: 100%;
    }

    .defect-actions .btn {
        width: 100%;
    }

    .timeel .form-control {
        width: 100%;
        margin-right: 0;
    }

    .actions-inline {
        display: none;
    }

    .mobile-actions {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 0.5rem;
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 1035;
        padding: 0.55rem 0.75rem calc(0.55rem + env(safe-area-inset-bottom));
        background: rgba(255, 255, 255, 0.96);
        border-top: 1px solid #dfe3e8;
        backdrop-filter: blur(4px);
    }

    .mobile-actions .btn {
        font-size: 0.8rem;
        padding: 0.4rem 0.35rem;
        white-space: nowrap;
    }
}
</style>
