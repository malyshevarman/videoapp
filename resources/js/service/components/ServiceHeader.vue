<script setup lang="ts">
import type { PropType } from 'vue'

type Service = {
    client?: { customerFirstName?: string }
    visitStartTime?: string | Date
    surveyObject?: { carBrand?: string; carModelCode?: string; carLicensePlate?: string }
    responsibleEmployee?: { specialistFirstName?: string; specialistLastName?: string }
    requester?: { specialistFirstName?: string; specialistLastName?: string }
    dealer?: { name?: string }
}

defineProps({
    service: {
        type: Object as PropType<Service>,
        required: true,
    },
    visitDate: {
        type: String,
        required: true,
    },
    logoUrl: {
        type: String,
        default: '',
    },
    logoAlt: {
        type: String,
        default: 'Логотип дилера',
    },
})
</script>

<template>
    <header class="top">
        <div class="container">
            <div class="top__card">
                <div class="top__head">
                    <h1 class="top__title">{{ service.client?.customerFirstName }}, это видео-отчет о состоянии автомобиля</h1>

                    <div class="top__brand">
                        <div class="top__logo" :class="{ 'top__logo--image': logoUrl }" :aria-label="logoAlt">
                            <img v-if="logoUrl" :src="logoUrl" :alt="logoAlt" class="top__logo-img">
                            <span v-else>{{ logoAlt }}</span>
                        </div>
                    </div>
                </div>

                <a class="top__share" href="#">
                    <span class="top__share-ic" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M12 3v12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <path d="M8 7l4-4 4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M4 13v6a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </span>
                    Поделиться отчетом
                </a>

                <div class="top__divider"></div>

                <div class="top__grid">
                    <div class="kv">
                        <div class="kv__row">
                            <div class="kv__k">Дата визита:</div>
                            <div class="kv__v">{{ visitDate }}</div>
                        </div>
                        <div class="kv__row">
                            <div class="kv__k">Причина визита:</div>
                            <div class="kv__v">Тех. обслуживание</div>
                        </div>
                        <div class="kv__row">
                            <div class="kv__k">Автомобиль:</div>
                            <div class="kv__v">{{ service.surveyObject?.carBrand }}, {{ service.surveyObject?.carModelCode }}</div>
                        </div>
                        <div class="kv__row">
                            <div class="kv__k">Регистрационный номер:</div>
                            <div class="kv__v">{{ service.surveyObject?.carLicensePlate }}</div>
                        </div>
                    </div>

                    <div class="kv">
                        <div class="kv__row">
                            <div class="kv__k">Дилерский центр:</div>
                            <div class="kv__v">{{ service.dealer?.name }}</div>
                        </div>
                        <div class="kv__row">
                            <div class="kv__k">Мастер-консультант:</div>
                            <div class="kv__v">{{ service.responsibleEmployee?.specialistFirstName }}, {{ service.responsibleEmployee?.specialistLastName }}</div>
                        </div>
                        <div class="kv__row">
                            <div class="kv__k">Механик:</div>
                            <div class="kv__v">{{ service.requester?.specialistLastName }} {{ service.requester?.specialistFirstName }}</div>
                        </div>
                    </div>

                    <div class="top__links"></div>
                </div>

                <button class="top__collapse" type="button" aria-label="Свернуть">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                        <path d="M7 14l5-5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
        </div>
    </header>
</template>
