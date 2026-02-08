<script setup>
import { reactive, ref, onMounted } from 'vue'
import draggable from 'vuedraggable'

const props = defineProps({
    service: Object
})

const service = reactive(props.service)
const defects = ref([])


const isSaved = ref(false)

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
            defects.value = [...service.defects];
        } else {
            try {
                const parsed = JSON.parse(service.defects);
                defects.value = Array.isArray(parsed) ? parsed : [];
            } catch (e) {
                defects.value = [];
                console.error('Failed to parse defects JSON:', e);
            }
        }
    } else {
        defects.value = [];
    }

})

const generatePreview = async (item) => {
    if (item.preview) return

    const video = previewVideo.value
    const canvas = previewCanvas.value
    const ctx = canvas.getContext('2d')

    video.currentTime = item.time

    await new Promise(resolve => {
        video.onseeked = resolve
    })

    const targetWidth = 200
    const aspectRatio = video.videoHeight / video.videoWidth
    const targetHeight = Math.round(targetWidth * aspectRatio)

    canvas.width = targetWidth
    canvas.height = targetHeight

    ctx.drawImage(video, 0, 0, targetWidth, targetHeight)
    item.preview = canvas.toDataURL('image/jpeg')
}


const newDefect = reactive({
    time: null, // время будет добавляться при записи
    title: '',
    status: 'green' // статус по умолчанию
})



const loadVideo = async () => {
    const res = await fetch(`/video?service_order_id=${service.id}`)
    if (res.status === 204) return

    const data = await res.json()
    videoData.value = data.video
    videoUrl.value = data.url
}

// Добавление неисправности
const addDefect = async () => {
    if (!newDefect.title) return

    const tasksLength = service.tasks?.length || 0
    const defectsLength = defects.value.length || 0

    const item = {
        id: tasksLength + defectsLength + 1, // начинается с длины tasks
        time: 0,
        title: newDefect.title,
        status: newDefect.status || 'green',
        preview: null
    }

    defects.value.push(item)

    // сбрасываем форму
    newDefect.title = ''
    newDefect.status = 'green'
}


// Удаление неисправности
const removeDefect = (index) => {
    defects.value.splice(index, 1)
}

// Сохранение неисправностей
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
    setTimeout(() => isSaved.value = false, 2000)
}

// Иконки для статусов
const getStatusIcon = (status) => {
    switch (status) {
        case 'green': return '✓'
        case 'yellow': return '●'
        case 'red': return '✕'
        default: return '?'
    }
}

const handleVideoRecord = async () => {

    // После сохранения открываем страницу записи
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

const openUrl = async (event) => {
    event.preventDefault() // отменяем стандартное поведение ссылки

    if (defects.value.length > 0) {
        await saveDefects()
    }

    window.open(`/services/${service.public_url}/show`, '_blank')
}
</script>

<template>
    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title">
                Редактирование заявки #{{ service.id }}
            </h3>
        </div>

        <div class="card-body">
            <div class="form-group">
                <label>External ID</label>
                <input type="text" class="form-control" readonly v-model="service.order_id">
            </div>


            <h5>Неисправности</h5>

            <div class="row">
                <!-- Левая колонка - список добавленных неисправностей -->
                <div class="col-md-6">
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
                                    <div class="list-group-item d-flex align-items-center justify-content-between">

                                        <div class="d-flex align-items-center">

                                            <!-- 🔥 Хэндл для перетаскивания -->
                                            <span class="handle mr-2">
                    <i class="fas fa-ellipsis-v"></i>
                    <i class="fas fa-ellipsis-v"></i>
                </span>

                                            <!-- Иконка статуса -->
                                            <span class="status-icon mr-2" :class="getStatusClass(element.status)">
                    {{ getStatusIcon(element.status) }}
                </span>

                                            <!-- Время -->
                                            <span
                                                class="mr-2 text-nowrap timeel"
                                                v-if="videoUrl"
                                                @click="seekTo(element.time)"
                                            >
                    <input
                        class="form-control"
                        v-model="element.time"
                        @input="clampTime(element)"
                        type="number"
                    >
                    сек —
                </span>

                                            <!-- Название -->
                                            <span class="defect-title">
                    {{ element.title }}
                </span>
                                        </div>

                                        <div class="actions">
                                            <button
                                                class="btn btn-sm btn-outline-danger"
                                                @click="removeDefect(index)"
                                                title="Удалить"
                                            >
                                                ✕
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </draggable>


                        </div>
                        <div class="card-footer" v-if="defects.length > 0">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <button
                                        class="btn"
                                        :class="isSaved ? 'btn-success' : 'btn-primary'"
                                        :disabled="isSaved"
                                        @click="saveDefects"
                                    >
                                        <span v-if="!isSaved">Сохранить неисправности</span>
                                        <span v-else>Сохранено ✓</span>
                                    </button>

                                    <a
                                        href="#"
                                        class="btn btn-info ml-2"
                                        @click.prevent="handleVideoRecord"
                                    ><i class="fas fa-video mr-1"></i></a>


                                    <a href="#"
                                       @click.prevent="openUrl"
                                       class="btn btn-info ml-2 mr-1"
                                       title="Поделиться">
                                        <i class="fas fa-share-alt"></i>
                                    </a>


                                </div>


                            </div>
                        </div>
                    </div>
                </div>

                <!-- Правая колонка - форма добавления -->
                <div class="col-md-6">
                    <div class="card card-outline card-info">
                        <div class="card-header">
                            <h4 class="card-title">Добавление неисправности</h4>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Название неисправности</label>
                                <textarea
                                    type="text"
                                    class="form-control"
                                    placeholder="Введите название неисправности"
                                    v-model="newDefect.title"
                                    @keyup.enter="addDefect"
                                ></textarea>
                            </div>

                            <div class="form-group">
                                <label>Статус</label>
                                <div class="d-flex align-items-center">
                                    <div class="btn-group btn-group-toggle mr-3" data-toggle="buttons">
                                        <label class="btn btn-outline-success" :class="{ active: newDefect.status === 'green' }">
                                            <input type="radio" v-model="newDefect.status" value="green"> ✓
                                        </label>
                                        <label class="btn btn-outline-warning" :class="{ active: newDefect.status === 'yellow' }">
                                            <input type="radio" v-model="newDefect.status" value="yellow"> ●
                                        </label>
                                        <label class="btn btn-outline-danger" :class="{ active: newDefect.status === 'red' }">
                                            <input type="radio" v-model="newDefect.status" value="red"> ✕
                                        </label>
                                    </div>
                                    <div class="status-example">
                                        <span class="status-icon status-green">✓</span> - Норма
                                        <span class="status-icon status-yellow ml-2">●</span> - Внимание
                                        <span class="status-icon status-red ml-2">✕</span> - Проблема
                                    </div>
                                </div>
                            </div>



                            <button
                                class="btn btn-primary btn-block"
                                @click="addDefect"
                                :disabled="!newDefect.title"
                            >
                                Добавить неисправность
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <template v-if="videoUrl">
                <!-- основное видео -->
                <video
                    ref="previewVideo"
                    :src="videoUrl"
                    controls
                    preload="auto"
                    class="w-100 mt-3 border rounded"
                    style="max-height:300px"
                ></video>

                <!-- служебный canvas -->
                <canvas ref="previewCanvas" style="display:none"></canvas>

                <hr>
            </template>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.handle {
    cursor: grab;
    color: #888;
    user-select: none;
    font-size: 20px;
    .fas{
        margin: 0 0.2em 0 0;
    }
}

.handle:active {
    cursor: grabbing;
}

.ghost-item {
    opacity: 0.5;
}

.list-group-item {
    transition: all 0.3s;
}

.list-group-item:hover {
    background-color: #f8f9fa;
}

.ghost-item {
    opacity: 0.5;
    background-color: #e9ecef;
}

.time-badge {
    background-color: #e9ecef;
    padding: 2px 10px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.8rem;
    min-width: 100px;
    text-align: center;
    white-space: nowrap;
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
    padding: 0.25rem 0.75rem;
    font-size: 1.2rem;
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
    opacity: 0.7;
    transition: opacity 0.2s;
    flex-shrink: 0;
}

.list-group-item:hover .actions {
    opacity: 1;
}

.alert-info {
    font-size: 0.9rem;
    padding: 8px 12px;
}

.form-group {
    margin-bottom: 1.2rem;
}
.timeel{
    display:flex; align-items: center; cursor: pointer; color: blue;
    .form-control{
        width: 80px;
        margin: 0 10px 0 0;
    }
}
</style>
