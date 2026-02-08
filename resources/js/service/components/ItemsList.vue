<script setup lang="ts">
import type { PropType } from 'vue'

type Item = {
    id: number | string
    title: string
    image?: string
    customerApproved?: string
    answerStatus?: string
}

defineProps({
    items: {
        type: Array as PropType<Item[]>,
        required: true,
    },
    activeItemId: {
        type: [Number, String] as PropType<number | string | null>,
        default: null,
    },
    getStatusText: {
        type: Function as PropType<(status: string | undefined) => string>,
        required: true,
    },
    selectItem: {
        type: Function as PropType<(item: Item) => void>,
        required: true,
    },
})
</script>

<template>
    <div class="list" v-if="items.length > 0">
        <a class="item" href="#"
           v-for="(item, index) in items"
           :class="{ 'is-active': activeItemId === item.id }"
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
                    {{ getStatusText(item.customerApproved) }}
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
</template>
