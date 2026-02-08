<script setup lang="ts">
import type { PropType } from 'vue'

type DetailRow = {
    lineId: string
    positionName: string
    positionQuantity?: number
    positionMeasure?: string
    positionAmountIncVat?: number
}

type Group = { title: string; rows: DetailRow[]; total: number }

type Item = {
    id: number | string
    title: string
    customerApproved?: string
}

const props = defineProps({
    activeItem: {
        type: Object as PropType<Item | null>,
        default: null,
    },
    activeItemNumber: {
        type: Number,
        required: true,
    },
    groups: {
        type: Array as PropType<Group[]>,
        required: true,
    },
    total: {
        type: Number,
        required: true,
    },
    activeItemTimeHours: {
        type: Number,
        required: true,
    },
    getStatusText: {
        type: Function as PropType<(status: string | undefined) => string>,
        required: true,
    },
    formatTime: {
        type: Function as PropType<(hours: number) => string>,
        required: true,
    },
    money: {
        type: Function as PropType<(value: unknown) => string>,
        required: true,
    },
    requestCallback: {
        type: Function as PropType<() => void>,
        required: true,
    },
    openDeferredModal: {
        type: Function as PropType<() => void>,
        required: true,
    },
    openRejectConfirm: {
        type: Function as PropType<() => void>,
        required: true,
    },
    approveActiveItem: {
        type: Function as PropType<() => void>,
        required: true,
    },
})

const statusText = (item: Item | null) => props.getStatusText(item?.customerApproved)

const statusClass = (text: string) => ({
    'work__status--green': text === 'Согласовано',
    'work__status--red': text === 'Отклонено',
    'work__status--yellow': text === 'Отложено',
})
</script>

<template>
    <div class="work" v-if="activeItem">
        <div class="work__head">
            <div class="work__badge">
                {{ activeItemNumber }}</div>
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
                <div class="work__status" :class="statusClass(statusText(activeItem))">
                    <span class="dot"></span> {{ statusText(activeItem) }}
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
</template>
