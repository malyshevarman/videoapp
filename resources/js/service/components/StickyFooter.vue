<script setup lang="ts">
import type { PropType } from 'vue'
import { computed, ref } from 'vue'

type ApprovedStats = {
    count: number
    sumIncVat: number
}

type ApprovedItem = {
    id: number | string
    title: string
    sum: number
}

const props = defineProps({
    stickyOpen: {
        type: Boolean,
        required: true,
    },
    approvedStats: {
        type: Object as PropType<ApprovedStats>,
        required: true,
    },
    approvedItemsList: {
        type: Array as PropType<ApprovedItem[]>,
        required: true,
    },
    deferredStats: {
        type: Object as PropType<ApprovedStats>,
        required: true,
    },
    deferredItemsList: {
        type: Array as PropType<ApprovedItem[]>,
        required: true,
    },
    approvedRepairTimeHours: {
        type: Number,
        required: true,
    },
    itemsLength: {
        type: Number,
        required: true,
    },
    isFirst: {
        type: Boolean,
        required: true,
    },
    isLast: {
        type: Boolean,
        required: true,
    },
    allHaveStatus: {
        type: Boolean,
        required: true,
    },
    money: {
        type: Function as PropType<(value: unknown) => string>,
        required: true,
    },
    goPrev: {
        type: Function as PropType<() => void>,
        required: true,
    },
    goNext: {
        type: Function as PropType<() => void>,
        required: true,
    },
    submitAll: {
        type: Function as PropType<() => void>,
        required: true,
    },
})

const emit = defineEmits(['toggle'])
const showDeferred = ref(false)
const activeStats = computed(() => (showDeferred.value ? props.deferredStats : props.approvedStats))
const activeItemsList = computed(() =>
    showDeferred.value ? props.deferredItemsList : props.approvedItemsList
)
const detailsTitle = computed(() =>
    showDeferred.value ? 'Работы' : 'Детали заказа'
)
const summaryLabel = computed(() =>
    showDeferred.value ? 'Отложенные работы:' : 'Согласовано работ:'
)

function toggleDeferredView() {
    showDeferred.value = !showDeferred.value
}
</script>

<template>
    <div class="sticky">
        <div class="container">
            <div class="" v-if="activeStats.count">
                <div class="sticky__head"
                     :class="{ 'is-open': stickyOpen }"
                     @click="emit('toggle')">
                    <div class="sticky__head-title">
                        {{ detailsTitle }} <span>({{ activeItemsList.length }})</span>
                    </div>
                </div>

                <Transition name="sticky-acc">
                    <div v-show="stickyOpen" class="sticky__body">
                        <div class="sticky__list" v-if="activeItemsList.length">
                            <div class="sticky__list-row" v-for="w in activeItemsList" :key="w.id">
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
                        <div class="sticky__label">{{ summaryLabel }}</div>
                        <div class="sticky__value"><span
                            class="sticky__value-accent">{{ activeStats.count }}</span> из {{ itemsLength }}
                        </div>
                    </div>
                    <div class="mrg"></div>

                    <div class="sticky__mid">
                        <div class="sticky__label">Итого:</div>
                        <div class="sticky__sum">{{ activeStats.sumIncVat.toFixed(2) }} ₽</div>
                    </div>
                </div>
                <div class="col_right">

                    <div class="text__job" v-if="approvedStats.count && !showDeferred">
                        Ориентировочное время ремонта {{ approvedRepairTimeHours }} ч.<br/>
                        после согласования ремонтных работ
                    </div>
                    <div class="text__job" v-if="approvedStats.count && showDeferred">
                        Данные работы будут предложены<br/>
                        при следующем визите
                    </div>

                    <div class="mrg"></div>
                    <div class="sticky__right">

                        <button
                            class="btn btn--ghost"
                            type="button"
                            v-if="!isFirst && !showDeferred && !isLast"
                            @click="goPrev"
                        >
                            Назад
                        </button>

                        <button
                            v-if="!isLast && !showDeferred"
                            class="btn btn--next"
                            type="button"
                            @click="goNext"
                        >
                            Далее
                        </button>

                       <div class="groupBtn">
                           <button
                               v-if="isLast && deferredStats.count"
                               class="btn btn--ghost"
                               type="button"
                               :class="showDeferred ? '':'defer'"
                               @click="toggleDeferredView"
                           >
                               {{ showDeferred ? 'Назад' : 'Отложенные работы' }}
                           </button>
                           <button
                               v-if="isLast && !showDeferred"
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
</template>
