<script setup lang="ts">
import { computed, onMounted, ref, nextTick } from 'vue'
import dayjs from 'dayjs'
import 'dayjs/locale/ru'

dayjs.locale('ru')
import { ru } from 'date-fns/locale'

import { toast } from 'vue-sonner'
import ServiceHeader from './components/ServiceHeader.vue'
import WorkDetails from './components/WorkDetails.vue'
import ItemsList from './components/ItemsList.vue'
import StickyFooter from './components/StickyFooter.vue'
import RejectModal from './components/RejectModal.vue'
import DeferredModal from './components/DeferredModal.vue'

type Service = {
    id?: number | string
    visitStartTime?: string | Date
    client?: { customerFirstName?: string }
    surveyObject?: { carBrand?: string; carModelCode?: string; carLicensePlate?: string }
    responsibleEmployee?: { specialistFirstName?: string; specialistLastName?: string }
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

type CustomerApproved = 'approved' | 'rejected' | 'deferred' | 'callback'

type Item = {
    id: number | string
    title: string
    details?: DetailRow[]
    image?: string
    time?: number
    customerApproved?: CustomerApproved
    answerStatus?: string
    deferredTaskDate?: string | null
}

const props = defineProps({
    service: {
        type: Object as () => Service,
        required: true,
    },
    items: {
        type: Array as () => Item[],
        default: () => [],
    },
})
const localItems = ref<Item[]>(Array.isArray(props.items) ? structuredClone(props.items) : [])

const activeItem = ref<Item | null>(null)

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

const activeIndex = computed(() =>
    activeItem.value
        ? localItems.value.findIndex(i => i.id === activeItem.value?.id)
        : -1
)
const isFirst = computed(() => activeIndex.value <= 0)

const isLast = computed(() =>
    activeIndex.value === localItems.value.length - 1
)
const activeItemNumber = computed(() => {
    if (!activeItem.value) return 0
    return localItems.value.findIndex(i => i.id === activeItem.value.id) + 1
})
const visitDate = computed(() =>
    dayjs(props.service.visitStartTime).format('D MMMM YYYY')
)
const approvedStats = computed(() => {
    return localItems.value.reduce(
        (acc, item) => {
            if (item.customerApproved !== 'approved') return acc

            acc.count += 1

            item.details?.forEach(d => {
                acc.sumExVat += Number(d.positionAmountExVat || 0)
                acc.sumIncVat += Number(d.positionAmountIncVat || 0)
            })
console.log('item-',item)
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
    const res = await fetch(`/video?service_order_id=${props.service.id}`)
    if (res.status === 204) return

    const data = await res.json()
    videoData.value = data.video
    videoUrl.value = data.url
}

const STATUS_META = {
    red: 'Обязательные работы',
    yellow: 'Необходимые работы',
    green: 'Информационные работы',
    default: 'Дополнительные продажи',
} as const

const statusMeta = computed(() => {
    if (!activeItem.value) return null

    return {
        title:
            STATUS_META[activeItem.value.answerStatus as keyof typeof STATUS_META] ??
            STATUS_META.default,
    }
})

const STATUS_TEXT = {
    approved: 'Согласовано',
    rejected: 'Отклонено',
    deferred: 'Отложено',
    callback: 'Звонок',
} as const

function getStatusText(customerApproved?: CustomerApproved) {
    return STATUS_TEXT[customerApproved ?? ''] ?? '—'
}
function selectItem(item: Item) {
    activeItem.value = item
    nextTick(() => {
        seekTo(item.time) // time в секундах
    })
}
function seekTo(seconds: number | undefined) {
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

function money(v: unknown) {
    const n = Number(v ?? 0)
    return n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₽'
}


const activeItemTimeHours = computed(() => {
    if (!activeItem.value) return 0

    return (activeItem.value.details ?? [])
        .filter(r => String(r.positionMeasure).toUpperCase() === 'ЧАС')
        .reduce((sum: number, r) => {
            return sum + Number(r.positionQuantity ?? 0)
        }, 0)
})
function formatTime(hours: number) {
    return `${hours} ч.`
}
function setCustomerApproved(status: CustomerApproved) {
    if (!activeItem.value) return

    const id = activeItem.value.id

    const idx = localItems.value.findIndex(i => i.id === id)
    const target = idx !== -1 ? localItems.value[idx] : activeItem.value

    target.customerApproved = status

    // ✅ дата нужна только для deferred
    if (status !== 'deferred') {
        target.deferredTaskDate = ''
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

    const index = localItems.value.findIndex(i => i.id === activeItem.value?.id) + 1

    toast.info(`Предложение ${index} отклонено`, {
        description: activeItem.value.title,
    })
}

function syncActiveToLocal() {
    if (!activeItem.value) return
    const id = activeItem.value.id
    const idx = localItems.value.findIndex(i => i.id === id)
    if (idx !== -1) {
        localItems.value[idx].deferredTaskDate = activeItem.value.deferredTaskDate ?? null
        activeItem.value = localItems.value[idx] // держим ссылку на объект из массива
    }
}

const deferredTaskDateModel = computed<string | null>({
    get() {
        return activeItem.value?.deferredTaskDate ?? null
    },
    set(v) {
        if (!activeItem.value) return
            ;(activeItem.value as Item).deferredTaskDate = v
        syncActiveToLocal()
    },
})

function updateDeferredTaskDate(value: string | null) {
    deferredTaskDateModel.value = value
}

function openDeferredModal() {
    if (!activeItem.value) return
        // если хочешь каждый раз чистить дату:
        ;(activeItem.value as Item).deferredTaskDate = null
    syncActiveToLocal()
    isDeferredOpen.value = true
}

function closeDeferredModal(clear = true) {
    if (clear && activeItem.value) {
        ;(activeItem.value as Item).deferredTaskDate = ''
        syncActiveToLocal()
    }

    isDeferredOpen.value = false
}
function confirmDeferred() {
    if (!activeItem.value || !activeItem.value.deferredTaskDate) return
    setCustomerApproved('deferred')
    closeDeferredModal(false) // ✅ НЕ чистим дату

    const index = localItems.value.findIndex(i => i.id === activeItem.value?.id) + 1

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

    const index = localItems.value.findIndex(i => i.id === activeItem.value?.id) + 1

    toast.info(`Предложение ${index} согласовано`, {
        description: activeItem.value.title,
        class: 'toast-callback',
    })

}
function submitAll() {
    if (!allHaveStatus.value) return

    fetch(`/services/${props.service.public_url}/update`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document
                .querySelector('meta[name="csrf-token"]')
                .getAttribute('content'),
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            items: localItems.value,
        }),
    })
        .then(res => res.json())
        .then(data => {
            toast.success('Решение принято', {
                description: 'Все предложения обработаны',
            })
        })
        .catch(err => {
            console.error(err)
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
        ['approved', 'rejected', 'deferred'].includes(i.customerApproved)
    )
)


const stickyOpen = ref(false)

// список согласованных работ для вывода в "Детали заказа"
const approvedItemsList = computed(() => {
    return localItems.value
        .filter(i => i.customerApproved === 'approved')
        .map(i => {
            const sum = (i.details ?? []).reduce(
                (s: number, r) => s + Number(r.positionAmountIncVat ?? 0),
                0
            )
            return { id: i.id, title: i.title, sum }
        })
})

const approvedRepairTimeHours = computed(() => {
    return localItems.value
        .filter(i => i.customerApproved === 'approved')
        .reduce((sum, item) => {
            const rows = item.details ?? []
            const hours = rows
                .filter(r => String(r.positionMeasure ?? '').toUpperCase() === 'ЧАС')
                .reduce((s: number, r) => s + Number(r.positionQuantity ?? 0), 0)

            return sum + hours
        }, 0)
})
</script>
<template>
    <div class="page">
        <!-- TOP CARD -->
        <ServiceHeader :service="service" :visit-date="visitDate" />

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
                            <WorkDetails
                                :active-item="activeItem"
                                :active-item-number="activeItemNumber"
                                :groups="groups"
                                :total="total"
                                :active-item-time-hours="activeItemTimeHours"
                                :get-status-text="getStatusText"
                                :format-time="formatTime"
                                :money="money"
                                :request-callback="requestCallback"
                                :open-deferred-modal="openDeferredModal"
                                :open-reject-confirm="openRejectConfirm"
                                :approve-active-item="approveActiveItem"
                            />


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

                        <ItemsList
                            :items="localItems"
                            :active-item-id="activeItem?.id ?? null"
                            :get-status-text="getStatusText"
                            :select-item="selectItem"
                        />
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
        <StickyFooter
            :sticky-open="stickyOpen"
            :approved-stats="approvedStats"
            :approved-items-list="approvedItemsList"
            :approved-repair-time-hours="approvedRepairTimeHours"
            :items-length="items.length"
            :is-first="isFirst"
            :is-last="isLast"
            :all-have-status="allHaveStatus"
            :money="money"
            :go-prev="goPrev"
            :go-next="goNext"
            :submit-all="submitAll"
            @toggle="stickyOpen = !stickyOpen"
        />
    </div>



    <RejectModal
        :is-open="isRejectOpen"
        :active-item-number="activeItemNumber"
        @close="closeRejectConfirm"
        @confirm="confirmReject"
    />


    <DeferredModal
        :is-open="isDeferredOpen"
        :model-value="deferredTaskDateModel"
        :locale="ru"
        :min-date="minDeferredDate"
        :max-date="maxDeferredDate"
        :can-confirm="Boolean(activeItem?.deferredTaskDate)"
        @update:model-value="updateDeferredTaskDate"
        @close="closeDeferredModal(true)"
        @confirm="confirmDeferred"
    />


    <Toaster position="top-right" />

</template>
