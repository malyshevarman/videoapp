<script setup>
import { reactive, ref, onMounted, onBeforeUnmount, computed } from 'vue'

const props = defineProps({
    service: Object
})

const service = reactive(props.service)

const video = ref(null)
const isRecording = ref(false)
const isPaused = ref(false)
const recordingStartTime = ref(0)
const totalRecordingTime = ref(0)
const orientationLocked = ref(false) // Блокировка ориентации

let mediaRecorder
let stream
let videoChunks = []
let currentChunk = []

const uploadProgress = ref(0)
const uploading = ref(false)
const uploadStatus = ref('')
const defectsLocal = ref([])

// Новые состояния
const torchEnabled = ref(false)
const facingMode = ref('environment')
const microphoneEnabled = ref(true)

const canvas = ref(null)
const canvasCtx = ref(null)
let canvasStream = null

// Для отслеживания чанков и таймкодов
const chunkInfo = ref({
    totalChunks: 0,
    currentChunk: 0,
    chunkStartTime: 0,
    totalDuration: 0
})

const totalPauseTime = ref(0)
let pauseStartTime = 0
const stopReason = ref(null) // 'pause' | 'final' | null

// Таймер для отображения времени записи
let recordingTimer = null

const setVH = () => {
    const vh = window.innerHeight * 0.01
    document.documentElement.style.setProperty('--vh', `${vh}px`)
}

const parseDefects = (v) => {
    try {
        const data = typeof v === 'string' ? JSON.parse(v) : (v ?? [])
        return JSON.parse(JSON.stringify(data))
    } catch {
        return []
    }
}

onMounted(() => {
    setVH()
    window.addEventListener('resize', setVH)
    window.addEventListener('orientationchange', handleOrientationChange)

    // Проверяем ориентацию при загрузке
    setTimeout(() => {
        checkOrientation()
        window.scrollTo(0, 1)
    }, 100)
    defectsLocal.value = parseDefects(service.defects)
    startPreview()
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', setVH)
    window.removeEventListener('orientationchange', handleOrientationChange)
    stopAllTracks()
    clearTimer()
    unlockOrientation()
})

const stopAllTracks = () => {
    if (stream) {
        stream.getTracks().forEach(t => t.stop())
    }
    stream = null
}

const clearTimer = () => {
    if (recordingTimer) {
        clearInterval(recordingTimer)
        recordingTimer = null
    }
}

// Функции для работы с ориентацией
const checkOrientation = () => {
    const isLandscape = window.innerWidth > window.innerHeight
    if (!isLandscape) {
        orientationLocked.value = false
        showOrientationAlert()
    } else {
        orientationLocked.value = true
    }
}

const handleOrientationChange = () => {
    setTimeout(checkOrientation, 100)
}

const showOrientationAlert = () => {
    // Показываем сообщение только если видео не записывается
    if (!isRecording.value && !orientationLocked.value) {
        // Можно показать кастомное сообщение вместо alert
        const alertDiv = document.createElement('div')
        alertDiv.className = 'orientation-alert'
        alertDiv.innerHTML = `
            <div class="orientation-content">
                <div class="orientation-icon">📱</div>
                <div class="orientation-text">Пожалуйста, поверните устройство горизонтально</div>
            </div>
        `
        document.body.appendChild(alertDiv)

        // Удаляем через 3 секунды
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove()
            }
        }, 3000)
    }
}

const lockOrientation = () => {
    if (screen.orientation && screen.orientation.lock) {
        screen.orientation.lock('landscape').catch(() => {
            console.log('Блокировка ориентации не поддерживается')
        })
    }
}

const unlockOrientation = () => {
    if (screen.orientation && screen.orientation.unlock) {
        screen.orientation.unlock()
    }
}

const toggleTorch = async () => {
    if (!stream) return
    if (facingMode.value === 'user') {
        alert('Фонарик доступен только на задней камере')
        return
    }

    const videoTrack = stream.getVideoTracks()[0]
    const capabilities = videoTrack.getCapabilities?.()

    if (!capabilities?.torch) {
        alert('Фонарик не поддерживается на этом устройстве')
        return
    }

    try {
        torchEnabled.value = !torchEnabled.value
        await videoTrack.applyConstraints({
            advanced: [{ torch: torchEnabled.value }]
        })
    } catch (error) {
        console.error('Ошибка при переключении фонарика:', error)
        torchEnabled.value = false
    }
}


let drawLoopRunning = false

const drawCanvasFrame = () => {
    if (!canvasCtx.value || !video.value) return

    drawLoopRunning = true

    const draw = () => {
        if (!drawLoopRunning) return

        if (
            canvas.value.width !== video.value.videoWidth ||
            canvas.value.height !== video.value.videoHeight
        ) {
            canvas.value.width = video.value.videoWidth
            canvas.value.height = video.value.videoHeight
        }

        canvasCtx.value.clearRect(0, 0, canvas.value.width, canvas.value.height)

        if (facingMode.value === 'user') {
            canvasCtx.value.save()
            canvasCtx.value.scale(-1, 1)
            canvasCtx.value.drawImage(
                video.value,
                -canvas.value.width,
                0,
                canvas.value.width,
                canvas.value.height
            )
            canvasCtx.value.restore()
        } else {
            canvasCtx.value.drawImage(
                video.value,
                0,
                0,
                canvas.value.width,
                canvas.value.height
            )
        }

        requestAnimationFrame(draw)
    }

    draw()
}


const startRecordingHandler = async () => {
    if (!stream) return

    isRecording.value = true
    isPaused.value = false
    recordingStartTime.value = Date.now()
    totalRecordingTime.value = 0
    totalPauseTime.value = 0

    lockOrientation()
    orientationLocked.value = true

    videoChunks = []
    currentChunk = []

    chunkInfo.value = {
        totalChunks: 0,
        currentChunk: 0,
        chunkStartTime: 0,
        totalDuration: 0
    }

    recordingTimer = setInterval(() => {
        if (!isPaused.value) {
            totalRecordingTime.value =
                Date.now() - recordingStartTime.value - totalPauseTime.value
        }
    }, 500)

    await startNewChunk()
}


const toggleMicrophone = () => {
    if (!stream) return

    microphoneEnabled.value = !microphoneEnabled.value

    const audioTrack = stream.getAudioTracks()[0]
    if (audioTrack) {
        audioTrack.enabled = microphoneEnabled.value
    }
}

const startPreview = async () => {
    try {
        const constraints = {
            video: {
                facingMode: { exact: 'environment' },
                width: { ideal: 1920 },
                height: { ideal: 1080 },
                frameRate: { ideal: 30 }
            },
            audio: microphoneEnabled.value
        }

        try {
            stream = await navigator.mediaDevices.getUserMedia(constraints)
        } catch (e) {
            constraints.video.facingMode = 'environment'
            stream = await navigator.mediaDevices.getUserMedia(constraints)
        }

        video.value.srcObject = stream

        // создаём canvas для записи сразу
        canvas.value = document.createElement('canvas')
        canvasCtx.value = canvas.value.getContext('2d')

        // захватываем поток с canvas
        canvasStream = canvas.value.captureStream(30)
        drawCanvasFrame()

        const videoTrack = stream.getVideoTracks()[0]
        const capabilities = videoTrack.getCapabilities?.()
        if (capabilities?.torch) {
            torchEnabled.value = false
        }
    } catch (e) {
        showWarning('Не удалось получить доступ к камере')
        console.error(e)
    }
}

const getSupportedMimeType = () => {
    const types = [
        'video/mp4;codecs="avc1.42E01E,mp4a.40.2"',
        'video/mp4',
    ]
    for (const type of types) {
        if (window.MediaRecorder?.isTypeSupported(type)) return type
    }
    return null
}


const formatTime = (milliseconds) => {
    const totalSeconds = Math.floor(milliseconds / 1000)
    const minutes = Math.floor(totalSeconds / 60)
    const seconds = totalSeconds % 60
    return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
}

const addDefectTimecode = (defectIndex) => {
    if (!isRecording.value) {
        showWarning('Сначала начните запись')
        return
    }

    const currentTime = Math.floor(totalRecordingTime.value / 1000)
    defectsLocal.value[defectIndex].time = currentTime
    showTimecodeNotification(defectsLocal.value[defectIndex].title, currentTime)
}


const showTimecodeNotification = (title, time) => {
    const notification = document.createElement('div')
    notification.className = 'timecode-notification'
    notification.innerHTML = `
        <div class="timecode-content">
            <div class="timecode-icon">🎬</div>
            <div class="timecode-text">
                <div class="timecode-title">${title}</div>
                <div class="timecode-time">Время: ${formatTime(time * 1000)}</div>
            </div>
        </div>
    `
    document.body.appendChild(notification)

    // Анимация появления
    setTimeout(() => {
        notification.classList.add('show')
    }, 10)

    // Убираем через 3 секунды
    setTimeout(() => {
        notification.classList.remove('show')
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove()
            }
        }, 300)
    }, 3000)
}

const showWarning = (message) => {
    const notification = document.createElement('div');
    notification.className = 'timecode-notification warning';
    notification.innerHTML = `
        <div class="timecode-content">
            <div class="timecode-icon">⚠️</div>
            <div class="timecode-text">${message}</div>
        </div>
    `;
    document.body.appendChild(notification);

    // Анимация появления
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);

    // Автоудаление через 3 секунды
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) notification.remove();
        }, 300);
    }, 3000);
};


const startNewChunk = async () => {
    if (!stream || !isRecording.value) return

    const mimeType = getSupportedMimeType()
    if (!mimeType) {
        showWarning('Запись видео не поддерживается')
        stopRecording()
        return
    }

    mediaRecorder = new MediaRecorder(getCombinedStream(), {
        mimeType,
        videoBitsPerSecond: 5000000
    })

    mediaRecorder.ondataavailable = e => {
        if (e.data.size > 0) {
            currentChunk.push(e.data)
        }
    }

    mediaRecorder.onstop = () => {
        setTimeout(() => {
            // сохраняем текущий чанк ВСЕГДА, если есть данные
            if (currentChunk.length > 0) {
                videoChunks.push({
                    chunk: currentChunk.slice(),
                    cameraType: facingMode.value,
                    duration: Date.now() - chunkInfo.value.chunkStartTime
                })
                currentChunk = []
            }

            // если это финальная остановка — отправляем
            if (stopReason.value === 'final') {
                chunkInfo.value.totalDuration = Date.now() - recordingStartTime.value - totalPauseTime.value
                if (videoChunks.length === 0) {
                    uploadStatus.value = 'Видео не записалось'
                    return
                }
                sendVideoToServer()
            }
        }, 200)
    }

    chunkInfo.value.chunkStartTime = Date.now()
    mediaRecorder.start()
}

const togglePauseRecording = async () => {
    if (!isRecording.value || !mediaRecorder) return

    // pause
    if (!isPaused.value) {
        isPaused.value = true
        pauseStartTime = Date.now()
        clearTimer()

        // Всегда режем текущий чанк через stop, чтобы избежать "залипания" кадра
        // на некоторых устройствах при native pause/resume MediaRecorder.
        stopReason.value = 'pause'
        if (mediaRecorder.state !== 'inactive') mediaRecorder.stop()
        return
    }

    // resume
    isPaused.value = false
    if (pauseStartTime > 0) {
        totalPauseTime.value += Date.now() - pauseStartTime
        pauseStartTime = 0
    }

    stopReason.value = null
    // стартуем новый чанк после паузы
    await startNewChunk()

    clearTimer()
    recordingTimer = setInterval(() => {
        if (!isPaused.value) {
            totalRecordingTime.value = Date.now() - recordingStartTime.value - totalPauseTime.value
        }
    }, 500)
}


const stopRecording = async () => {
    if (!mediaRecorder || mediaRecorder.state === 'inactive') return

    const missingTimecodes = defectsLocal.value.filter(
        d => d.time == null || d.time === 0
    )
    if (missingTimecodes.length > 0) {
        showWarning('Не все дефекты имеют таймкод. Запись ставится на паузу. Добавьте таймкоды для всех дефектов.')
        if (!isPaused.value) await togglePauseRecording()
        return
    }

    console.log('stop прошел?')

    isRecording.value = false
    isPaused.value = false
    clearTimer()

    unlockOrientation()
    orientationLocked.value = false

    if (pauseStartTime > 0) {
        totalPauseTime.value += Date.now() - pauseStartTime
        pauseStartTime = 0
    }

    stopReason.value = 'final'
    if (mediaRecorder.state !== 'inactive') mediaRecorder.stop()
}


const sendVideoToServer = async () => {
    const missingTimecodes = defectsLocal.value.filter(d => d.time == null || d.time === 0)
    if (missingTimecodes.length > 0) {
        showWarning('Все дефекты должны иметь таймкод перед отправкой')
        return
    }

    if (uploading.value) return

    uploading.value = true
    uploadProgress.value = 0
    uploadStatus.value = 'Подготовка и отправка видео...'

    try {
        if (videoChunks.length === 0) {
            throw new Error('Нет видео данных для отправки')
        }

        const formData = new FormData()
        formData.append('service_order_id', service.id)
        formData.append('total_duration', chunkInfo.value.totalDuration)
        formData.append('original_name', `recording_${Date.now()}.mp4`)
        formData.append('total_chunks', videoChunks.length.toString())

        if (defectsLocal.value.length > 0) {
            formData.append('defects', JSON.stringify(defectsLocal.value))
        }

        let validChunks = 0
        videoChunks.forEach((chunkData, index) => {
            const totalSize = chunkData.chunk.reduce((sum, chunk) => sum + (chunk.size || 0), 0)
            if (totalSize === 0) return

            const blob = new Blob(chunkData.chunk, { type: 'video/mp4' })
            if (blob.size === 0) return

            const file = new File([blob], `chunk_${index}.mp4`, { type: 'video/mp4' })
            if (file.size === 0) return

            formData.append(`chunk_${index}`, file)
            formData.append(`camera_type_${index}`, chunkData.cameraType)
            formData.append(`duration_${index}`, chunkData.duration.toString())
            validChunks++
        })

        if (validChunks === 0) {
            throw new Error('Все чанки оказались пустыми')
        }

        uploadStatus.value = 'Отправка видео на сервер...'
        const token = document
            .querySelector('meta[name="csrf-token"]')
            .getAttribute('content')

        const result = await new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest()
            xhr.open('POST', '/video/upload-chunks', true)
            xhr.responseType = 'json'
            xhr.setRequestHeader('X-CSRF-TOKEN', token)
            xhr.setRequestHeader('Accept', 'application/json')

            xhr.upload.onprogress = (e) => {
                if (!e.lengthComputable) return
                uploadProgress.value = Math.round((e.loaded / e.total) * 100)
            }

            xhr.onload = () => {
                const data = xhr.response
                if (xhr.status >= 200 && xhr.status < 300) resolve(data)
                else reject(new Error(data?.error || `Ошибка загрузки (${xhr.status})`))
            }

            xhr.onerror = () => reject(new Error('Ошибка сети при загрузке'))
            xhr.send(formData)
        })

        uploadProgress.value = 100
        uploadStatus.value = 'Видео успешно загружено'
        console.log('Видео успешно отправлено:', result)

    } catch (e) {
        console.error('Ошибка при отправке видео:', e)
        uploadStatus.value = 'Ошибка: ' + e.message
    } finally {
        uploading.value = false
    }
}

const getCombinedStream = () => {
    const combined = canvasStream.clone()

    stream.getAudioTracks().forEach(track => {
        combined.addTrack(track)
    })

    return combined
}


// Получаем цвет для статуса дефекта
const getStatusColor = (status) => {
    switch(status) {
        case 'red': return '#ff4444'
        case 'yellow': return '#ffd700'
        case 'green': return '#4CAF50'
        default: return '#666'
    }
}

</script>

<template>
    <div class="video-fullscreen" :class="{ 'landscape-mode': orientationLocked }">
        <!-- Кнопка назад -->
        <a :href="'/admin/services/'+service.id+'/edit'" class="back-button" v-if="!isRecording && !uploading">
            ← Назад
        </a>

        <!-- Основной контейнер с видео и дефектами -->
        <div class="main-container">
            <!-- Видео контейнер -->
            <div class="video-container">
                <video
                    ref="video"
                    autoplay
                    playsinline
                    muted
                    class="video-element"
                ></video>

                <!-- Левая панель с кнопками -->
                <div class="control-panel left-panel">
                    <!-- Фонарик -->
                    <button
                        class="control-button"
                        @click="toggleTorch"
                        :class="{
                            active: torchEnabled,
                            disabled: facingMode === 'user'
                        }"
                        title="Фонарик"
                        :disabled="facingMode === 'user'"
                    >
                        <svg
                            width="24"
                            height="24"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <path d="M9 21h6v-1H9v1zm3-19C7.93 2 5 4.93 5 8c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-3.26c1.81-1.27 3-3.36 3-5.74 0-3.07-2.93-6-7-6z"/>
                        </svg>
                    </button>

                    <!-- Микрофон -->
                    <button
                        class="control-button"
                        @click="toggleMicrophone"
                        :class="{ muted: !microphoneEnabled }"
                        title="Микрофон"
                        :disabled="uploading"
                    >
                        <svg v-if="microphoneEnabled" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 14C13.66 14 15 12.66 15 11V5C15 3.34 13.66 2 12 2S9 3.34 9 5V11C9 12.66 10.34 14 12 14ZM11 5C11 4.45 11.45 4 12 4S13 4.45 13 5V11C13 11.55 12.55 12 12 12S11 11.55 11 11V5ZM17 11C17 14.31 14.31 17 11 17S5 14.31 5 11H3C3 15.42 6.58 19 11 19V21H13V19C17.42 19 21 15.42 21 11H17Z"/>
                        </svg>
                        <svg v-else width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 11H17.3C17.3 11.74 17.14 12.43 16.87 13.05L18.1 14.28C18.66 13.3 19 12.18 19 11ZM14.98 11.17C14.98 11.11 15 11.06 15 11V5C15 3.34 13.66 2 12 2S9 3.34 9 5V5.18L14.98 11.17ZM4.27 3L3 4.27L9.01 10.28V11C9.01 12.66 10.34 14 12 14C12.22 14 12.44 13.97 12.65 13.92L14.31 15.58C13.6 15.91 12.81 16.1 12 16.1C9.24 16.1 6.7 14 6.7 11H5C5 14.41 7.72 17.23 11 17.72V21H13V17.72C13.91 17.59 14.77 17.27 15.54 16.82L19.73 21L21 19.73L4.27 3Z"/>
                        </svg>
                    </button>
                </div>

                <!-- Правая панель с кнопками записи -->
                <div class="control-panel right-panel">
                    <!-- Кнопка паузы/возобновления -->
                    <button
                        v-if="isRecording"
                        class="control-button pause-button"
                        @click="togglePauseRecording"
                        :class="{ paused: isPaused }"
                        title="Пауза/Возобновить"
                        :disabled="uploading"
                    >
                        <svg v-if="!isPaused" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
                        </svg>
                        <svg v-else width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                    </button>

                    <!-- Кнопка записи/остановки -->
                    <button
                        class="record-button"
                        :class="{ recording: isRecording }"
                        @click="isRecording ? stopRecording() : startRecordingHandler()"
                        :title="isRecording ? 'Остановить запись' : 'Начать запись'"
                        :disabled="uploading"
                    >
                        <svg v-if="!isRecording" width="24" height="24" viewBox="0 0 24 24" fill="white">
                            <circle cx="12" cy="12" r="10"/>
                        </svg>
                        <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="white">
                            <rect x="6" y="6" width="12" height="12" rx="1"/>
                        </svg>
                    </button>
                </div>

                <!-- Информация о записи -->
                <div v-if="isRecording" class="recording-info">
                    <div class="recording-indicator">
                        <div class="recording-dot" :class="{ paused: isPaused }"></div>
                        <span v-if="!isPaused">Запись...</span>
                        <span v-else>Пауза</span>
                    </div>
                    <div class="recording-time">
                        {{ formatTime(totalRecordingTime) }}
                    </div>
                    <div class="chunk-info" v-if="chunkInfo.totalChunks > 1">
                        Часть {{ chunkInfo.currentChunk }} из {{ chunkInfo.totalChunks }}
                    </div>
                </div>
            </div>

            <!-- Панель дефектов (справа) -->
            <div class="defects-panel" >


                <div class="defects-list">
                    <div
                        v-for="(defect, index) in defectsLocal"
                        :key="index"
                        class="defect-item"
                        :style="{ borderLeftColor: getStatusColor(defect.status) }"
                    >
                        <div class="defect-row">
                            <div class="defect-title">{{ defect.title }}</div>

                            <button
                                class="timecode-button"
                                @click="addDefectTimecode(index)"
                                :disabled="!isRecording"
                                :class="{ 'has-timecode': defect.time != null }"
                                title="Добавить таймкод"
                            >
                                <i class="fas fa-camera"></i>
                            </button>
                        </div>

                        <div class="timecode-info" v-if="defect.time != null && defect.time !== 0 ">
                            Таймкод: {{ defect.time }}
                        </div>
                    </div>
                </div>



            </div>
        </div>

        <!-- Прогресс загрузки -->
        <div v-if="uploading" class="upload-progress">
            <div class="progress-bar" :style="{ width: uploadProgress + '%' }"></div>
            <small>{{ uploadStatus }}</small>
        </div>
    </div>
</template>

<style scoped>
html, body {
    margin: 0;
    padding: 0;
    width: 100%;
    height: 100%;
}

body {
    padding-left: env(safe-area-inset-left);
    padding-right: env(safe-area-inset-right);
    min-height: 200vh;
}

.defect-item {
    display: flex;
    flex-direction: column;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    padding: 5px 8px;
    margin-bottom: 8px;
    border-left: 4px solid #666;
}

.defect-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.defect-title {
    color: white;
    font-size: 13px;
    font-weight: 500;
    flex: 1;
    margin-right: 5px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.timecode-button {
    width: 28px;
    height: 28px;
    padding: 0;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    transition: all 0.2s;
}

.timecode-button svg {
    width: 16px;
    height: 16px;
}

.timecode-button.has-timecode {
    background: #ff4444;
    border-color: #ff4444;
}

/* Основные стили */
.video-fullscreen {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    height: 100dvh;
    background: black;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    z-index: 9999;
}

/* Горизонтальный режим */
.video-fullscreen.landscape-mode {
    flex-direction: row;
}

/* Основной контейнер */
.main-container {
    display: flex;
    flex: 1;
    width: 100%;
    height: 100%;
}

/* Контейнер видео */
.video-container {
    flex: 1;
    position: relative;
    overflow: hidden;
    display: flex;
    justify-content: center;
    align-items: center;
}

.video-element {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Панель дефектов */
.defects-panel {
    width: 150px;
    background: rgba(0, 0, 0, 0.3);
    border-left: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    backdrop-filter: blur(10px);
    position: fixed;
    right: 0;
    bottom: 0;
    top:0;

}

.defects-header {
    padding: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.defects-header h3 {
    color: white;
    margin: 0 0 10px 0;
    font-size: 18px;
}

.recording-status {
    color: #ff4444;
    font-size: 14px;
    font-weight: bold;
}

.defects-list {
    flex: 1;
    overflow-y: auto;
    padding: 15px;
}

.defect-item {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    padding: 5px;
    margin-bottom: 10px;
    border-left: 4px solid #666;
}

.defect-info {
    margin-bottom: 10px;
}

.defect-title {
    color: white;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 5px;
}

.defect-status {
    font-size: 12px;
    opacity: 0.8;
}

.defect-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.timecode-info {
    color: #4CAF50;
    font-size: 12px;
    text-align: center;
}

.timecode-button {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 6px;
    color: white;
    padding: 8px 12px;
    font-size: 13px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all 0.2s;
}

.timecode-button:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.2);
}

.timecode-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.timecode-button.has-timecode {
    background: rgba(76, 175, 80, 0.2);
    border-color: #4CAF50;
}

.defects-summary {
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding: 15px;
}

.defects-summary h4 {
    color: white;
    font-size: 14px;
    margin: 0 0 10px 0;
    opacity: 0.8;
}

.timecodes-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.timecode-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 6px;
}

.timecode-marker {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.timecode-details {
    flex: 1;
}

.timecode-title {
    color: white;
    font-size: 12px;
    margin-bottom: 2px;
}

.timecode-time {
    color: rgba(255, 255, 255, 0.6);
    font-size: 11px;
}

/* Остальные стили остаются как были */
.back-button {
    position: absolute;
    top: 30px;
    left: 15px;
    z-index: 10000;
    color: white;
    font-size: 18px;
    background: rgba(0,0,0,0.4);
    padding: 6px 12px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 500;
    transition: background 0.2s;
}

.back-button:hover {
    background: rgba(0,0,0,0.6);
}

.control-panel {
    position: absolute;
    display: flex;
    flex-direction: column;
    gap: 20px;
    z-index: 10001;
}

.left-panel {
    left: 20px;
    bottom: 35px;
}

.right-panel {
    right: 170px;
    bottom: 35px;
}

.control-button {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.5);
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    backdrop-filter: blur(10px);
    position: relative;
}

.control-button:hover:not(:disabled) {
    background: rgba(0, 0, 0, 0.7);
    border-color: rgba(255, 255, 255, 0.5);
    transform: scale(1.05);
}

.control-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.control-button.active {
    background: rgba(255, 215, 0, 0.3);
    border-color: gold;
    color: gold;
}

.control-button.muted {
    background: rgba(255, 0, 0, 0.3);
    border-color: #ff4444;
    color: #ff4444;
}

.pause-button.paused {
    background: rgba(0, 128, 255, 0.3);
    border-color: #0080ff;
    color: #0080ff;
}

.camera-indicator {
    position: absolute;
    bottom: -5px;
    font-size: 10px;
    background: rgba(0,0,0,0.7);
    padding: 1px 4px;
    border-radius: 4px;
}

.record-button {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    border: 4px solid white;
    background-color: #ff4444;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.record-button:hover:not(:disabled) {
    transform: scale(1.05);
}

.record-button:active:not(:disabled) {
    transform: scale(0.95);
}

.record-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.record-button.recording {
    animation: pulse 1.2s infinite;
    background-color: #ff0000;
    border-color: #ff0000;
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(255, 0, 0, 0.7);
    }
    70% {
        box-shadow: 0 0 0 15px rgba(255, 0, 0, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(255, 0, 0, 0);
    }
}

.recording-info {
    position: absolute;
    top: 30px;
    right: 170px;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 5px;
    z-index: 10001;
}

.recording-indicator {
    background: rgba(0, 0, 0, 0.6);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.recording-dot {
    width: 10px;
    height: 10px;
    background-color: #ff0000;
    border-radius: 50%;
    animation: blink 1s infinite;
}

.recording-dot.paused {
    background-color: #0080ff;
    animation: none;
}

.recording-time {
    background: rgba(0, 0, 0, 0.6);
    color: white;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: bold;
}

.chunk-info {
    background: rgba(0, 0, 0, 0.6);
    color: white;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
}

@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}

.upload-progress {
    position: absolute;
    bottom: 120px;
    left: 50%;
    transform: translateX(-50%);
    width: 80%;
    max-width: 300px;
    background: rgba(0, 0, 0, 0.7);
    border-radius: 10px;
    padding: 10px;
    z-index: 10002;
}

.progress-bar {
    height: 6px;
    background: linear-gradient(90deg, #4CAF50, #8BC34A);
    border-radius: 3px;
    transition: width 0.3s;
    margin-bottom: 5px;
}

.upload-progress small {
    color: white;
    font-size: 12px;
    display: block;
    text-align: center;
}

/* Адаптивность */
@media (max-height: 600px) {
    .control-button {
        width: 40px;
        height: 40px;
    }

    .record-button {
        width: 60px;
        height: 60px;
    }

    .left-panel,
    .right-panel {
        bottom: 20px;
    }


}

/* Вертикальный режим - скрываем панель дефектов */
@media (orientation: portrait) {
    .defects-panel {
        display: none;
    }
}
</style>

<style>
/* Стили для уведомлений */
.orientation-alert {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10003;
}

.orientation-content {
    text-align: center;
    padding: 30px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.orientation-icon {
    font-size: 60px;
    margin-bottom: 20px;
    animation: rotate 2s infinite;
}

@keyframes rotate {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(90deg); }
}

.orientation-text {
    color: white;
    font-size: 18px;
    max-width: 300px;
}

.timecode-notification {
    position: fixed;
    top: 20px;
    left: 20px;
    background: rgba(0, 0, 0, 0.8);
    border-radius: 10px;
    padding: 15px;
    max-width: 300px;
    transform: translateX(-100%);
    transition: transform 0.3s ease;
    z-index: 10004;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.timecode-notification.show {
    transform: translateX(0);
}

.timecode-content {
    display: flex;
    align-items: center;
    gap: 15px;
}

.timecode-icon {
    font-size: 24px;
}

.timecode-text {
    color: white;
}

.timecode-title {
    font-weight: bold;
    margin-bottom: 5px;
}

.timecode-time {
    font-size: 12px;
    opacity: 0.8;
}
</style>
