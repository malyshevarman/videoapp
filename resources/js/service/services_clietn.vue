<script setup lang="ts">
import {computed, onMounted, reactive, ref, watch ,nextTick } from 'vue'
import dayjs from 'dayjs'
import 'dayjs/locale/ru'

dayjs.locale('ru')
import { Dialog, DialogPanel, DialogTitle, TransitionRoot } from '@headlessui/vue'
import { VueDatePicker } from '@vuepic/vue-datepicker'
import { ru } from 'date-fns/locale'

import { toast } from 'vue-sonner'

const props = defineProps({
    service: Object,
    items: Array,
})
const localItems = ref(Array.isArray(props.items) ? structuredClone(props.items) : [])

const activeItem = ref(null)

const videoData = ref(null)
const videoUrl = ref(null)
const isRejectOpen = ref(false)
const isDeferredOpen = ref(false)
const previewVideo = ref<HTMLVideoElement | null>(null)

onMounted(() => {
    loadVideo()
    if (localItems.value.length > 0) {
        activeItem.value = localItems.value[0]
    }
});

const activeIndex = computed(() => {
    if (!activeItem.value) return -1
    return localItems.value.findIndex(i => i.id === activeItem.value.id)
})
const isFirst = computed(() => activeIndex.value <= 0)

const isLast = computed(() =>
    activeIndex.value === localItems.value.length - 1
)
const approvedStats = computed(() => {
    return localItems.value.reduce(
        (acc, item) => {
            if (item.customerApproved !== 'approved') return acc

            acc.count++

            item.details.forEach(d => {
                acc.sumExVat += Number(d.positionAmountExVat || 0)
                acc.sumIncVat += Number(d.positionAmountIncVat || 0)
            })

            return acc
        },
        {
            count: 0,
            sumExVat: 0,
            sumIncVat: 0,
        }
    )
})

const loadVideo = async () => {
    const res = await fetch(`/api/video?service_order_id=${props.service.id}`)
    if (res.status === 204) return

    const data = await res.json()
    videoData.value = data.video
    videoUrl.value = data.url
}

const statusMeta = computed(() => {
    if (!activeItem.value) return null

    switch (activeItem.value.answerStatus) {
        case 'red':
            return {
                title: 'Обязательные работы',
            }

        case 'yellow':
            return {
                title: 'Необходимые работы',
            }

        case 'green':
            return {
                title: 'Информационные работы',
            }

        default:
            return {
                title: 'Дополнительные продажи',
            }
    }
})
function getStatusText(customerApproved) {
    if (customerApproved === 'approved') return 'Согласовано'
    if (customerApproved === 'rejected') return 'Отклонено'
    if (customerApproved === 'deferred') return 'Отложено'
    if (customerApproved === 'callback') return 'Звонок'
    return '—'
}
function selectItem(item) {
    activeItem.value = item
    nextTick(() => {
        seekTo(item.time) // time в секундах
    })
}
function seekTo(seconds: unknown) {
    const video = previewVideo.value
    if (!video) return

    const t = Math.max(0, Number(seconds ?? 0))

    // если метаданные ещё не загрузились — дождёмся
    if (video.readyState < 1) {
        const handler = () => {
            video.currentTime = t
            video.removeEventListener('loadedmetadata', handler)
        }
        video.addEventListener('loadedmetadata', handler, { once: true })
        return
    }

    // если уже всё ок
    video.currentTime = Math.min(t, Number.isFinite(video.duration) ? video.duration : t)
}
type DetailRow = {
    lineId: string
    positionType: string
    positionName: string
    positionQuantity?: number
    positionMeasure?: string
    positionAmountIncVat?: number
    positionAmountExVat?: number
}

const groups = computed(() => {
    const rows: DetailRow[] = (activeItem.value?.details ?? []) as DetailRow[]

    const labour = rows.filter(r => r.positionType === 'labour')
    const materials = rows.filter(r => r.positionType !== 'labour')

    const sum = (arr: DetailRow[]) =>
        arr.reduce((s, r) => s + Number(r.positionAmountIncVat ?? 0), 0)

    const res: Array<{ title: string; rows: DetailRow[]; total: number }> = []
    if (labour.length) res.push({ title: 'Тип работ', rows: labour, total: sum(labour) })
    if (materials.length) res.push({ title: 'Материалы', rows: materials, total: sum(materials) })
    return res
})

const total = computed(() => {
    const rows: DetailRow[] = (activeItem.value?.details ?? []) as DetailRow[]
    return rows.reduce((s, r) => s + Number(r.positionAmountIncVat ?? 0), 0)
})

const minDeferredDate = computed(() => dayjs().add(1, 'day').startOf('day').toDate())

// максимум +90 дней
const maxDeferredDate = computed(() => dayjs().add(90, 'day').toDate())

function applyDeferredDate(d: Date | null) {
    if (!d || !activeItem.value) return

    const iso = dayjs(d).format('YYYY-MM-DD')

    const id = activeItem.value.id
    const idx = localItems.value.findIndex((i: any) => i.id === id)

    if (idx !== -1) {
        localItems.value[idx].deferredTaskDate = iso
        activeItem.value = localItems.value[idx] // обновим ссылку
    } else {
        activeItem.value.deferredTaskDate = iso
    }
}

function money(v: unknown) {
    const n = Number(v ?? 0)
    return n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₽'
}


const activeItemTimeHours = computed(() => {
    if (!activeItem.value) return 0

    return (activeItem.value.details ?? [])
        .filter((r: any) => String(r.positionMeasure).toUpperCase() === 'ЧАС')
        .reduce((sum: number, r: any) => {
            return sum + Number(r.positionQuantity ?? 0)
        }, 0)
})
function formatTime(hours: number) {
    return `${hours} ч.`
}
type CustomerApproved = 'approved' | 'rejected' | 'deferred' | 'callback'

function setCustomerApproved(status: CustomerApproved) {
    if (!activeItem.value) return

    const id = (activeItem.value as any).id

    const idx = localItems.value.findIndex((i: any) => i.id === id)
    const target = idx !== -1 ? localItems.value[idx] : (activeItem.value as any)

    target.customerApproved = status

    // ✅ дата нужна только для deferred
    if (status !== 'deferred') {
        target.deferredTaskDate = ""
    }

    // чтобы activeItem точно ссылался на объект из массива
    if (idx !== -1) activeItem.value = localItems.value[idx]
}




function openRejectConfirm() {
    if (!activeItem.value) return
    isRejectOpen.value = true
}

function closeRejectConfirm() {
    isRejectOpen.value = false
}

function confirmReject() {
    setCustomerApproved('rejected')
    closeRejectConfirm()

    const index =
        localItems.value.findIndex(i => i.id === (activeItem.value as any).id) + 1

    toast.info(`Предложение ${index} отклонено`, {
        description: activeItem.value.title,
    })
}

type AnyItem = { id: any; deferredTaskDate?: string | null }

function syncActiveToLocal() {
    if (!activeItem.value) return
    const id = (activeItem.value as any).id
    const idx = localItems.value.findIndex((i: any) => i.id === id)
    if (idx !== -1) {
        localItems.value[idx].deferredTaskDate = (activeItem.value as any).deferredTaskDate ?? null
        activeItem.value = localItems.value[idx] // держим ссылку на объект из массива
    }
}

const deferredTaskDateModel = computed<string | null>({
    get() {
        return (activeItem.value as any)?.deferredTaskDate ?? null
    },
    set(v) {
        if (!activeItem.value) return
            ;(activeItem.value as any).deferredTaskDate = v
        syncActiveToLocal()
    },
})

function openDeferredModal() {
    if (!activeItem.value) return
        // если хочешь каждый раз чистить дату:
        ;(activeItem.value as any).deferredTaskDate = null
    syncActiveToLocal()
    isDeferredOpen.value = true
}

function closeDeferredModal(clear = true) {
    if (clear && activeItem.value) {
        ;(activeItem.value as any).deferredTaskDate = ''
        syncActiveToLocal()
    }

    isDeferredOpen.value = false
}
function confirmDeferred() {
    if (!activeItem.value || !(activeItem.value as any).deferredTaskDate) return
    setCustomerApproved('deferred')
    closeDeferredModal(false) // ✅ НЕ чистим дату

    const index =
        localItems.value.findIndex(i => i.id === (activeItem.value as any).id) + 1

    toast.info(`Предложение ${index} отложено`, {
        description: activeItem.value.title,
    })
}

function requestCallback() {
    setCustomerApproved('callback')
    toast.info(`Заявка принята`, {
        description: 'Мастер-консультант свяжется с Вами в ближайшее время',
        class: 'toast-callback',
    })
}
function approveActiveItem() {
    if (!activeItem.value) return

    if (activeItem.value.customerApproved === 'approved') return

    setCustomerApproved('approved')

    const index =
        localItems.value.findIndex(i => i.id === (activeItem.value as any).id) + 1

    toast.info(`Предложение ${index} согласовано`, {
        description: activeItem.value.title,
        class: 'toast-callback',
    })

}
function submitAll() {
    if (!allHaveStatus.value) return

    console.log('Готово:', localItems.value)

    toast.success('Решение принято', {
        description: 'Все предложения обработаны',
    })
}
function goNext() {
    if (isLast.value) return
    activeItem.value = localItems.value[activeIndex.value + 1]
}

function goPrev() {
    if (isFirst.value) return
    activeItem.value = localItems.value[activeIndex.value - 1]
}
const allHaveStatus = computed(() =>
    localItems.value.every(i =>
        ['approved', 'rejected', 'deferred', 'callback'].includes(i.customerApproved)
    )
)


const stickyOpen = ref(false)

// список согласованных работ для вывода в "Детали заказа"
const approvedItemsList = computed(() => {
    return localItems.value
        .filter(i => i.customerApproved === 'approved')
        .map(i => {
            const sum = (i.details ?? []).reduce(
                (s: number, r: any) => s + Number(r.positionAmountIncVat ?? 0),
                0
            )
            return { id: i.id, title: i.title, sum }
        })
})

const approvedRepairTimeHours = computed(() => {
    return localItems.value
        .filter(i => i.customerApproved === 'approved')
        .reduce((sum, item: any) => {
            const rows = item.details ?? []
            const hours = rows
                .filter((r: any) => String(r.positionMeasure ?? '').toUpperCase() === 'ЧАС')
                .reduce((s: number, r: any) => s + Number(r.positionQuantity ?? 0), 0)

            return sum + hours
        }, 0)
})
</script>
<template>
    <div class="page">
        <!-- TOP CARD -->
        <header class="top">
            <div class="container">
                <div class="top__card">
                    <div class="top__head">
                        <h1 class="top__title">{{ service.client.customerFirstName }}, это видео-отчет о состоянии
                            автомобиля</h1>

                        <div class="top__brand">
                            <div class="top__logo" aria-label="БорисХоф">

                            </div>
                        </div>
                    </div>

                    <a class="top__share" href="#">
            <span class="top__share-ic" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path d="M12 3v12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <path d="M8 7l4-4 4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                      stroke-linejoin="round"/>
                <path d="M4 13v6a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-6" stroke="currentColor" stroke-width="2"
                      stroke-linecap="round"/>
              </svg>
            </span>
                        Поделиться отчетом
                    </a>

                    <div class="top__divider"></div>

                    <div class="top__grid">
                        <div class="kv">
                            <div class="kv__row">
                                <div class="kv__k">Дата визита:</div>
                                <div class="kv__v"> {{ dayjs(service.visitStartTime).format('D MMMM YYYY') }}</div>
                            </div>
                            <div class="kv__row">
                                <div class="kv__k">Причина визита:</div>
                                <div class="kv__v">Тех. обслуживание</div>
                            </div>
                            <div class="kv__row">
                                <div class="kv__k">Автомобиль:</div>
                                <div class="kv__v">{{ service.surveyObject.carBrand }},
                                    {{ service.surveyObject.carModelCode }}
                                </div>
                            </div>
                            <div class="kv__row">
                                <div class="kv__k">Регистрационный номер:</div>
                                <div class="kv__v">{{ service.surveyObject.carLicensePlate }}</div>
                            </div>
                        </div>

                        <div class="kv">
                            <div class="kv__row">
                                <div class="kv__k">Дилерский центр:</div>
                                <div class="kv__v">BMW БорисХоф Север</div>
                            </div>
                            <div class="kv__row">
                                <div class="kv__k">Мастер консультант:</div>
                                <div class="kv__v">{{ service.responsibleEmployee.specialistFirstName }},
                                    {{ service.responsibleEmployee.specialistLastName }}
                                </div>
                            </div>
                            <div class="kv__row">
                                <div class="kv__k">Механик:</div>
                                <div class="kv__v">Старовойтов А. М.</div>
                            </div>
                        </div>

                        <div class="top__links">
                            <div class="top__links-title">Первичный осмотр:</div>

                            <a class="top__link" href="#">
                <span class="top__link-ic" aria-hidden="true">
                 <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
<path
    d="M9.95647 11.9967C9.92291 11.9634 9.87759 11.9448 9.83036 11.9448C9.78312 11.9448 9.7378 11.9634 9.70424 11.9967L7.11049 14.5904C5.9096 15.7913 3.88281 15.9185 2.55692 14.5904C1.22879 13.2623 1.35603 11.2377 2.55692 10.0368L5.15067 7.44308C5.21987 7.37389 5.21987 7.26005 5.15067 7.19085L4.26228 6.30246C4.22871 6.26922 4.18339 6.25058 4.13616 6.25058C4.08893 6.25058 4.04361 6.26922 4.01004 6.30246L1.41629 8.89621C-0.472098 10.7846 -0.472098 13.8404 1.41629 15.7266C3.30469 17.6127 6.36049 17.615 8.24665 15.7266L10.8404 13.1328C10.9096 13.0636 10.9096 12.9498 10.8404 12.8806L9.95647 11.9967ZM15.7288 1.41629C13.8404 -0.472098 10.7846 -0.472098 8.89844 1.41629L6.30245 4.01005C6.26922 4.04361 6.25058 4.08893 6.25058 4.13616C6.25058 4.18339 6.26922 4.22872 6.30245 4.26228L7.18862 5.14844C7.25781 5.21763 7.37165 5.21763 7.44085 5.14844L10.0346 2.55469C11.2355 1.35379 13.2623 1.22656 14.5882 2.55469C15.9163 3.88281 15.7891 5.90737 14.5882 7.10826L11.9944 9.70201C11.9612 9.73557 11.9425 9.7809 11.9425 9.82813C11.9425 9.87536 11.9612 9.92068 11.9944 9.95424L12.8828 10.8426C12.952 10.9118 13.0658 10.9118 13.135 10.8426L15.7288 8.24889C17.615 6.36049 17.615 3.30469 15.7288 1.41629ZM10.7623 5.45424C10.7287 5.42101 10.6834 5.40237 10.6362 5.40237C10.5889 5.40237 10.5436 5.42101 10.51 5.45424L5.45424 10.5078C5.42101 10.5414 5.40237 10.5867 5.40237 10.6339C5.40237 10.6812 5.42101 10.7265 5.45424 10.76L6.33817 11.644C6.40737 11.7132 6.52121 11.7132 6.5904 11.644L11.644 6.5904C11.7132 6.52121 11.7132 6.40737 11.644 6.33817L10.7623 5.45424Z"
    fill="#002239" fill-opacity="0.5"/>
</svg>
                </span>
                                Визуальный осмотр от 31.09.2024
                            </a>

                            <a class="top__link" href="#">
                <span class="top__link-ic" aria-hidden="true">
       <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
<path
    d="M9.95647 11.9967C9.92291 11.9634 9.87759 11.9448 9.83036 11.9448C9.78312 11.9448 9.7378 11.9634 9.70424 11.9967L7.11049 14.5904C5.9096 15.7913 3.88281 15.9185 2.55692 14.5904C1.22879 13.2623 1.35603 11.2377 2.55692 10.0368L5.15067 7.44308C5.21987 7.37389 5.21987 7.26005 5.15067 7.19085L4.26228 6.30246C4.22871 6.26922 4.18339 6.25058 4.13616 6.25058C4.08893 6.25058 4.04361 6.26922 4.01004 6.30246L1.41629 8.89621C-0.472098 10.7846 -0.472098 13.8404 1.41629 15.7266C3.30469 17.6127 6.36049 17.615 8.24665 15.7266L10.8404 13.1328C10.9096 13.0636 10.9096 12.9498 10.8404 12.8806L9.95647 11.9967ZM15.7288 1.41629C13.8404 -0.472098 10.7846 -0.472098 8.89844 1.41629L6.30245 4.01005C6.26922 4.04361 6.25058 4.08893 6.25058 4.13616C6.25058 4.18339 6.26922 4.22872 6.30245 4.26228L7.18862 5.14844C7.25781 5.21763 7.37165 5.21763 7.44085 5.14844L10.0346 2.55469C11.2355 1.35379 13.2623 1.22656 14.5882 2.55469C15.9163 3.88281 15.7891 5.90737 14.5882 7.10826L11.9944 9.70201C11.9612 9.73557 11.9425 9.7809 11.9425 9.82813C11.9425 9.87536 11.9612 9.92068 11.9944 9.95424L12.8828 10.8426C12.952 10.9118 13.0658 10.9118 13.135 10.8426L15.7288 8.24889C17.615 6.36049 17.615 3.30469 15.7288 1.41629ZM10.7623 5.45424C10.7287 5.42101 10.6834 5.40237 10.6362 5.40237C10.5889 5.40237 10.5436 5.42101 10.51 5.45424L5.45424 10.5078C5.42101 10.5414 5.40237 10.5867 5.40237 10.6339C5.40237 10.6812 5.42101 10.7265 5.45424 10.76L6.33817 11.644C6.40737 11.7132 6.52121 11.7132 6.5904 11.644L11.644 6.5904C11.7132 6.52121 11.7132 6.40737 11.644 6.33817L10.7623 5.45424Z"
    fill="#002239" fill-opacity="0.5"/>
</svg>
                </span>
                                Результат проверки автомобиля
                            </a>
                        </div>
                    </div>

                    <button class="top__collapse" type="button" aria-label="Свернуть">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                            <path d="M7 14l5-5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                  stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
            </div>
        </header>

        <!-- MAIN -->
        <main class="main">
            <div class="container">
                <section class="panel">
                    <div class="panel__left">
                        <!-- VIDEO -->
                        <div class="video">
                            <div class="video__media">

                                <video
                                    ref="previewVideo"
                                    :src="videoUrl"
                                    controls
                                    controlsList="nodownload"
                                    preload="auto"
                                    class="w-100 mt-3 border rounded"

                                ></video>
                                <div class="video__bar"
                                     v-if="statusMeta"
                                     :class="{
    red: activeItem?.answerStatus === 'red',
    yellow: activeItem?.answerStatus === 'yellow',
    green: activeItem?.answerStatus === 'green',
  }">
  <span class="video__bar-ic" aria-hidden="true">
    <!-- svg оставляешь как есть -->
    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
<g clip-path="url(#clip0_2342_7180)">
<path
    d="M7.02684 10.3268C7.42417 10.7235 7.42417 11.3675 7.02684 11.7648L3.35484 15.4368C2.56284 16.2288 1.26284 16.2035 0.502836 15.3602C-0.209164 14.5695 -0.0978307 13.3315 0.654169 12.5795L4.2475 8.98618C4.64417 8.58951 5.28817 8.58885 5.68484 8.98618L7.02617 10.3275L7.02684 10.3268ZM10.6348 7.33551C11.4768 7.33685 12.3308 7.66951 12.9595 8.29885C13.3335 8.66685 13.8348 8.59618 14.0002 8.48218C15.2128 7.64285 16.0122 6.25351 16.0122 4.66685C16.0122 4.39151 15.9875 4.12218 15.9415 3.86018C15.8508 3.34818 15.2035 3.16618 14.8355 3.53351L13.7375 4.63151C12.9735 5.39551 11.9688 5.51285 11.2942 4.96085C10.5182 4.32618 10.4755 3.17951 11.1668 2.48818L12.4768 1.17818C12.8442 0.810179 12.6642 0.160845 12.1522 0.0701784C11.8902 0.0235117 11.6208 -0.000488281 11.3455 -0.000488281C9.3835 -0.000488281 7.70884 1.21151 7.0195 2.92685C6.8815 3.26951 6.96217 3.66351 7.2235 3.92418L10.6348 7.33485V7.33551ZM12.0168 9.24151C11.5095 8.73418 10.7962 8.57018 10.1475 8.73418L5.33284 3.92018V2.66618C5.33284 2.16485 5.06484 1.70085 4.63017 1.45018L2.3595 0.140845C1.9475 -0.097155 1.42684 -0.0284883 1.09017 0.308178L0.308836 1.08951C-0.0284973 1.42618 -0.097164 1.94751 0.140836 2.35951L1.45084 4.63085C1.7015 5.06551 2.16484 5.33285 2.66617 5.33285H3.91817L8.73417 10.1482C8.57084 10.7962 8.73417 11.5095 9.2415 12.0168L12.6128 15.3522C13.3775 16.1168 14.6982 16.1895 15.4622 15.4242C16.2275 14.6575 16.1308 13.5128 15.4615 12.6495L12.0168 9.24151Z"
    fill="white"/>
</g>
<defs>
<clipPath id="clip0_2342_7180">
<rect width="16" height="16" fill="white"/>
</clipPath>
</defs>
</svg>
  </span>

                                    {{ statusMeta.title }}

                                </div>


                            </div>

                            <!-- DETAILS -->
                            <div class="work" v-if="activeItem">
                                <div class="work__head">
                                    <div class="work__badge">
                                        {{ localItems.findIndex(i => i.id === activeItem.id) + 1 }}</div>
                                    <div class="work__title">
                                        {{ activeItem.title }}
                                        <span class="work__hint" aria-hidden="true">
                                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"
                                             xmlns="http://www.w3.org/2000/svg">
<g clip-path="url(#clip0_2342_133)">
<path
    d="M7 0C3.13437 0 0 3.13437 0 7C0 10.8656 3.13437 14 7 14C10.8656 14 14 10.8656 14 7C14 3.13437 10.8656 0 7 0ZM7 12.8125C3.79063 12.8125 1.1875 10.2094 1.1875 7C1.1875 3.79063 3.79063 1.1875 7 1.1875C10.2094 1.1875 12.8125 3.79063 12.8125 7C12.8125 10.2094 10.2094 12.8125 7 12.8125Z"
    fill="#949494"/>
<path
    d="M8.74351 3.94795C8.27476 3.53701 7.65601 3.31201 6.99976 3.31201C6.34351 3.31201 5.72476 3.53857 5.25601 3.94795C4.76851 4.37451 4.49976 4.94795 4.49976 5.56201V5.68076C4.49976 5.74951 4.55601 5.80576 4.62476 5.80576H5.37476C5.44351 5.80576 5.49976 5.74951 5.49976 5.68076V5.56201C5.49976 4.87295 6.17319 4.31201 6.99976 4.31201C7.82632 4.31201 8.49976 4.87295 8.49976 5.56201C8.49976 6.04795 8.15601 6.49326 7.62319 6.69795C7.29194 6.82451 7.01069 7.04639 6.80913 7.33701C6.60444 7.63389 6.49819 7.99014 6.49819 8.35107V8.68701C6.49819 8.75576 6.55444 8.81201 6.62319 8.81201H7.37319C7.44194 8.81201 7.49819 8.75576 7.49819 8.68701V8.33232C7.499 8.18064 7.5455 8.03272 7.63162 7.90785C7.71775 7.78298 7.8395 7.68697 7.98101 7.63232C8.90288 7.27764 9.49819 6.46514 9.49819 5.56201C9.49976 4.94795 9.23101 4.37451 8.74351 3.94795ZM6.37476 10.437C6.37476 10.6028 6.4406 10.7617 6.55781 10.879C6.67502 10.9962 6.834 11.062 6.99976 11.062C7.16552 11.062 7.32449 10.9962 7.4417 10.879C7.55891 10.7617 7.62476 10.6028 7.62476 10.437C7.62476 10.2713 7.55891 10.1123 7.4417 9.99507C7.32449 9.87786 7.16552 9.81201 6.99976 9.81201C6.834 9.81201 6.67502 9.87786 6.55781 9.99507C6.4406 10.1123 6.37476 10.2713 6.37476 10.437Z"
    fill="#949494"/>
</g>
<defs>
<clipPath id="clip0_2342_133">
<rect width="14" height="14" fill="white"/>
</clipPath>
</defs>
</svg>

                                    </span>
                                    </div>

                                    <div class="work__meta">
                                        <div class="work__status"
                                             :class="{
  'work__status--green': getStatusText(activeItem.customerApproved) === 'Согласовано',
  'work__status--red': getStatusText(activeItem.customerApproved) === 'Отклонено',
    'work__status--yellow': getStatusText(activeItem.customerApproved) === 'Отложено',
}">
                                            <span class="dot"></span>   {{getStatusText(activeItem.customerApproved) }}
                                        </div>
                                        <div class="work__time" v-if="activeItemTimeHours > 0">
                                            ~ {{ formatTime(activeItemTimeHours) }}
                                        </div>
                                    </div>
                                </div>

                                <div class="work__body">
                                    <div class="work__cols">
                                        <div class="work__list">
                                            <div class="work__group" v-for="(g, gi) in groups" :key="gi">
                                                <div class="work__group-title">
                                                    {{ g.title }}
                                                    <span class="chev">^</span>
                                                    <span class="mrg"></span>
                                                    <div class="work__price">{{ money(g.total) }}</div>
                                                </div>

                                                <div class="work__row" v-for="row in g.rows" :key="row.lineId">
                                                    <div class="work__name">
                                                        {{ row.positionName }}
                                                        <small v-if="row.positionQuantity">
                                                            ({{ row.positionQuantity }} {{ row.positionMeasure }})
                                                        </small>
                                                    </div>
                                                    <div class="work__price">{{ money(row.positionAmountIncVat) }}</div>
                                                </div>
                                            </div>

                                            <div class="work__total">
                                                <div class="work__total-k">Всего:</div>
                                                <div class="work__total-v">{{ money(total) }}</div>
                                            </div>
                                        </div>
                                        <div class="work__actions">
                                            <button class="btn btn--ghost" type="button"
                                                    :disabled="activeItem?.customerApproved === 'callback'"
                                                    @click="requestCallback"
                                            >Обратный звонок</button>

                                            <button class="btn btn--ghost" type="button"
                                                    :disabled="activeItem?.customerApproved === 'deferred'"
                                                    @click="openDeferredModal"
                                            >
                                                Напомнить позже
                                            </button>

                                            <button class="btn btn--ghost" type="button"
                                                    :disabled="activeItem?.customerApproved === 'rejected'"
                                                    @click="openRejectConfirm">
                                                Отклонить
                                            </button>

                                            <button class="btn btn--primary" type="button"
                                                    :disabled="activeItem?.customerApproved === 'approved'"
                                                    @click="approveActiveItem">
                                                Согласовать
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>


                        </div>
                    </div>

                    <!-- RIGHT SIDE -->
                    <aside class="panel__right">
                        <div class="callout">
                            <div class="callout__title">
                                Пожалуйста, посмотрите предложения по ремонтным работам и примите решение
                            </div>
                            <div class="callout__actions">
                                <a class="callout__link" href="#">
                                    <svg width="17" height="15" viewBox="0 0 17 15" fill="none"
                                         xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M16.6189 1.28485C16.8423 0.228264 16.2466 -0.186825 15.5765 0.0773227L0.75919 5.85083C-0.246002 6.26592 -0.208773 6.83195 0.610272 7.0961L4.37044 8.26589L13.1566 2.68106C13.5661 2.37918 13.9756 2.56786 13.6405 2.832L6.52974 9.32248L6.26913 13.2847C6.67865 13.2847 6.82757 13.1338 7.05095 12.9073L8.87518 11.096L12.7098 13.9639C13.4172 14.379 13.9384 14.1526 14.1245 13.3224L16.6189 1.28485Z"
                                            fill="#002239"/>
                                    </svg>

                                    Задать вопрос
                                </a>
                                <a class="callout__link" href="tel:+74951536873">
                                    <svg width="17" height="17" viewBox="0 0 17 17" fill="none"
                                         xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M15.3437 2.54297L13.2637 0.464844C13.1169 0.317468 12.9425 0.200535 12.7505 0.120763C12.5584 0.0409902 12.3525 -4.9843e-05 12.1445 4.54287e-08C11.7207 4.54287e-08 11.3223 0.166016 11.0234 0.464844L8.78516 2.70312C8.63778 2.84986 8.52085 3.02426 8.44108 3.21632C8.3613 3.40837 8.32026 3.6143 8.32031 3.82227C8.32031 4.24609 8.48633 4.64453 8.78516 4.94336L10.4219 6.58008C10.0388 7.42453 9.5061 8.19275 8.84961 8.84766C8.19477 9.50574 7.42662 10.0403 6.58203 10.4258L4.94531 8.78906C4.79858 8.64169 4.62418 8.52475 4.43212 8.44498C4.24006 8.36521 4.03414 8.32417 3.82617 8.32422C3.40234 8.32422 3.00391 8.49023 2.70508 8.78906L0.464845 11.0254C0.317288 11.1724 0.200255 11.3471 0.120478 11.5395C0.0407023 11.7319 -0.000242029 11.9382 1.07624e-06 12.1465C1.07624e-06 12.5703 0.166017 12.9688 0.464845 13.2676L2.54102 15.3438C3.01758 15.8223 3.67578 16.0938 4.35156 16.0938C4.49414 16.0938 4.63086 16.082 4.76563 16.0586C7.39844 15.625 10.0098 14.2246 12.1172 12.1191C14.2227 10.0156 15.6211 7.40625 16.0605 4.76562C16.1934 3.95898 15.9258 3.12891 15.3437 2.54297Z"
                                            fill="#002239"/>
                                    </svg>

                                    +7 495 153-68-73
                                </a>
                            </div>
                        </div>

                        <div class="list" v-if="localItems.length > 0">
                            <!-- ITEM 1 -->
                            <a class="item" href="#"
                               v-for="(item, index) in localItems"
                               :class="{ 'is-active': activeItem?.id === item.id }"
                               @click.prevent="selectItem(item)"
                               :key="item.id">
                                <div class="item__thumb">
                                    <img :src="item.image" alt=""/>
                                    <div class="item__num">{{ index + 1 }}</div>
                                </div>
                                <div class="item__info">
                                    <div class="item__title">{{ item.title }}</div>
                                    <div class="item__sub"
                                         :class="{
  'work__status--green': getStatusText(item.customerApproved) === 'Согласовано',
  'work__status--red': getStatusText(item.customerApproved) === 'Отклонено',
  'work__status--yellow': getStatusText(item.customerApproved) === 'Отложено',
}">
                                        <span class="dot"></span>
                                        {{getStatusText(item.customerApproved) }}
                                    </div>
                                </div>
                                <div class="item__tag"
                                     :class="{
    'is-red': item.answerStatus === 'red',
    'is-yellow': item.answerStatus === 'yellow',
    'is-green': item.answerStatus === 'green',
    'is-dark': item.answerStatus === 'dark',
  }"
                                     aria-hidden="true">
                                    <svg width="32" height="32" viewBox="0 0 32 32" fill="none"
                                         xmlns="http://www.w3.org/2000/svg">
                                        <rect width="32" height="32" rx="16" fill-opacity="0.75"/>
                                        <g clip-path="url(#clip0_2342_749)">
                                            <path
                                                d="M15.0268 18.3268C15.4242 18.7235 15.4242 19.3675 15.0268 19.7648L11.3548 23.4368C10.5628 24.2288 9.26284 24.2035 8.50284 23.3602C7.79084 22.5695 7.90217 21.3315 8.65417 20.5795L12.2475 16.9862C12.6442 16.5895 13.2882 16.5888 13.6848 16.9862L15.0262 18.3275L15.0268 18.3268ZM18.6348 15.3355C19.4768 15.3368 20.3308 15.6695 20.9595 16.2988C21.3335 16.6668 21.8348 16.5962 22.0002 16.4822C23.2128 15.6428 24.0122 14.2535 24.0122 12.6668C24.0122 12.3915 23.9875 12.1222 23.9415 11.8602C23.8508 11.3482 23.2035 11.1662 22.8355 11.5335L21.7375 12.6315C20.9735 13.3955 19.9688 13.5128 19.2942 12.9608C18.5182 12.3262 18.4755 11.1795 19.1668 10.4882L20.4768 9.17818C20.8442 8.81018 20.6642 8.16085 20.1522 8.07018C19.8902 8.02351 19.6208 7.99951 19.3455 7.99951C17.3835 7.99951 15.7088 9.21151 15.0195 10.9268C14.8815 11.2695 14.9622 11.6635 15.2235 11.9242L18.6348 15.3348V15.3355ZM20.0168 17.2415C19.5095 16.7342 18.7962 16.5702 18.1475 16.7342L13.3328 11.9202V10.6662C13.3328 10.1648 13.0648 9.70085 12.6302 9.45018L10.3595 8.14085C9.9475 7.90285 9.42684 7.97151 9.09017 8.30818L8.30884 9.08951C7.9715 9.42618 7.90284 9.94751 8.14084 10.3595L9.45084 12.6308C9.7015 13.0655 10.1648 13.3328 10.6662 13.3328H11.9182L16.7342 18.1482C16.5708 18.7962 16.7342 19.5095 17.2415 20.0168L20.6128 23.3522C21.3775 24.1168 22.6982 24.1895 23.4622 23.4242C24.2275 22.6575 24.1308 21.5128 23.4615 20.6495L20.0168 17.2415Z"
                                                fill="white"/>
                                        </g>
                                        <defs>
                                            <clipPath id="clip0_2342_749">
                                                <rect width="16" height="16" fill="white" transform="translate(8 8)"/>
                                            </clipPath>
                                        </defs>
                                    </svg>
                                </div>
                            </a>
                        </div>
                    </aside>
                </section>

                <!-- FOOTER TEXTS -->
                <section class="note">
                    <p class="bold">
                        Уважаемый клиент! Сообщаем Вам, что в Группе Компаний БорисХоф оплата товаров и услуг
                        осуществляется
                        исключительно на расчётные счета организации. Пожалуйста, не платите деньги на личные счета
                        сторонних лиц! Остерегайтесь мошенников!

                    </p>
                    <p class="note__muted">
                        Информируем, что отмечаются случаи мошеннических действий,
                        когда после передачи автомобиля в автосервис неизвестное лицо от имени сотрудника сервиса звонит
                        клиенту, сообщает о выявленной неисправности и под предлогом срочного заказа, необходимых для
                        ремонта, запчастей и оперативности их доставки просит перевести денежные средства на его личный
                        счет. После перевода денег лицо перестает отвечать на звонки, а автосервис также не может
                        ответить
                        за переведенные деньги
                    </p>

                    <div class="note__divider"></div>

                    <div class="note__bottom">
                        <div class="note__left">
                            <div>ООО «БОРИСХОФ 1»</div>
                            <div class="note__muted">с/п Булатниковское, район 29 км МКАД, уч. 1</div>
                            <div class="note__muted">+7 495 745-11-11</div>
                        </div>

                        <div class="note__right">
                            <a href="#" class="note__tg">
                                <span class="note__tg-ic" aria-hidden="true">
                                    <svg width="17" height="15" viewBox="0 0 17 15" fill="none"
                                         xmlns="http://www.w3.org/2000/svg">
<path
    d="M16.6189 1.28485C16.8423 0.228264 16.2466 -0.186825 15.5765 0.0773227L0.75919 5.85083C-0.246002 6.26592 -0.208773 6.83195 0.610272 7.0961L4.37044 8.26589L13.1566 2.68106C13.5661 2.37918 13.9756 2.56786 13.6405 2.832L6.52974 9.32248L6.26913 13.2847C6.67865 13.2847 6.82757 13.1338 7.05095 12.9073L8.87518 11.096L12.7098 13.9639C13.4172 14.379 13.9384 14.1526 14.1245 13.3224L16.6189 1.28485Z"
    fill="#002239"/>
</svg>

                                </span> Официальный Telegram-канал
                            </a>
                            <a href="#" class="note__tg">
                                <span class="note__tg-ic" aria-hidden="true">
                                    <svg width="17" height="15" viewBox="0 0 17 15" fill="none"
                                         xmlns="http://www.w3.org/2000/svg">
<path
    d="M16.6189 1.28485C16.8423 0.228264 16.2466 -0.186825 15.5765 0.0773227L0.75919 5.85083C-0.246002 6.26592 -0.208773 6.83195 0.610272 7.0961L4.37044 8.26589L13.1566 2.68106C13.5661 2.37918 13.9756 2.56786 13.6405 2.832L6.52974 9.32248L6.26913 13.2847C6.67865 13.2847 6.82757 13.1338 7.05095 12.9073L8.87518 11.096L12.7098 13.9639C13.4172 14.379 13.9384 14.1526 14.1245 13.3224L16.6189 1.28485Z"
    fill="#002239"/>
</svg>

                                </span> Telegram бот
                            </a>
                        </div>
                    </div>

                    <div class="note__fine">
                        Все цены указаны с учетом всех налогов, индивидуальных скидок и акций. Авторизация ремонта
                        означает согласие клиента на выполнение дополнительных работ на автомобиле в рамках указанной
                        стоимости.
                    </div>
                </section>
            </div>
        </main>

        <!-- STICKY BOTTOM -->
        <div class="sticky">
            <div class="container">

                <div class="" v-if="approvedStats.count">
                    <div class="sticky__head"
                         :class="{ 'is-open': stickyOpen }"
                         @click="stickyOpen = !stickyOpen">
                        <div class="sticky__head-title">
                            Детали заказа <span>({{ approvedItemsList.length }})</span>
                        </div>
                    </div>

                    <!-- Раскрывающаяся часть -->
                    <Transition name="sticky-acc">
                        <div v-show="stickyOpen" class="sticky__body">

                            <!-- список работ -->
                            <div class="sticky__list" v-if="approvedItemsList.length">
                                <div class="sticky__list-row" v-for="w in approvedItemsList" :key="w.id">
                                    <div class="sticky__list-title">{{ w.title }}</div>
                                    <div class="sticky__list-sum">{{ money(w.sum) }}</div>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </div>


                <div class="sticky__bar">
                    <div class="col_left">
                        <div class="sticky__left">
                            <div class="sticky__label">Согласовано работ:</div>
                            <div class="sticky__value"><span
                                class="sticky__value-accent">{{ approvedStats.count }}</span> из {{ items.length }}
                            </div>
                        </div>
                        <div class="mrg"></div>

                        <div class="sticky__mid">
                            <div class="sticky__label">Итого:</div>
                            <div class="sticky__sum">{{ approvedStats.sumIncVat }} ₽</div>
                        </div>
                    </div>
                    <div class="col_right">

                        <div class="text__job" v-if="approvedStats.count">
                            Ориентировочное время ремонта {{approvedRepairTimeHours}} ч.<br/>
                            после согласования ремонтных работ
                        </div>

                        <div class="mrg"></div>
                        <div class="sticky__right">
                            <button
                                class="btn btn--ghost"
                                type="button"
                                v-if="!isFirst"
                                @click="goPrev"
                            >
                                Назад
                            </button>

                            <!-- Далее -->
                            <button
                                v-if="!isLast"
                                class="btn btn--next"
                                type="button"
                                @click="goNext"
                            >
                                Далее
                            </button>

                            <!-- Подтвердить -->
                            <button
                                v-else
                                class="btn btn--primary"
                                type="button"
                                :disabled="!allHaveStatus"
                                @click="submitAll"
                            >
                                Подтвердить
                            </button>
                        </div>
                    </div>



                </div>
            </div>
        </div>
    </div>



    <TransitionRoot as="template" :show="isRejectOpen">
        <Dialog as="div" :open="isRejectOpen" class="modal-root" @close="() => {}">
            <div class="modal-overlay"></div>

            <div class="modal-wrap">
                <DialogPanel class="modal-panel icon">
                    <DialogTitle class="modal-title">
                        Отклонить предложение {{ localItems.findIndex(i => i.id === activeItem?.id) + 1 }} по ремонту автомобиля?
                    </DialogTitle>

                    <p class="modal-text">
                        Если не делать вовремя ремонт критических элементов автомобиля, это может быть небезопасно
                    </p>

                    <div class="modal-actions">
                        <button class="btn btn--ghost" type="button" @click="closeRejectConfirm">Нет</button>
                        <button class="btn btn--primary" type="button" @click="confirmReject">Да, отклонить</button>
                    </div>
                </DialogPanel>
            </div>
        </Dialog>
    </TransitionRoot>


    <TransitionRoot as="template" :show="isDeferredOpen">
        <Dialog as="div" :open="isDeferredOpen" class="modal-root" @close="() => {}">
            <div class="modal-overlay"></div>

            <div class="modal-wrap">
                <DialogPanel class="modal-panel">
                    <DialogTitle class="modal-title">
                        Выберите удобную дату и мы Вам напомним о предложении по ремонту автомобиля
                    </DialogTitle>

                    <div class="modal-body">
                        <VueDatePicker
                            v-model="deferredTaskDateModel"
                            :enable-time-picker="false"
                            :time-picker="false"
                            :auto-apply="true"
                            :locale="ru"
                            :min-date="minDeferredDate"
                            :max-date="maxDeferredDate"
                            :time-config="{ enableTimePicker: false }"
                            :model-type="'yyyy-MM-dd'"
                            inline
                        />
                    </div>

                    <div class="modal-actions">
                        <button class="btn btn--ghost" type="button" @click="closeDeferredModal(true)">
                            Отменить
                        </button>

                        <button
                            class="btn btn--primary"
                            type="button"
                            :disabled="!activeItem?.deferredTaskDate"
                            @click="confirmDeferred"
                        >
                            Подтвердить
                        </button>
                    </div>
                </DialogPanel>
            </div>
        </Dialog>
    </TransitionRoot>


    <Toaster position="top-right" />

</template>
