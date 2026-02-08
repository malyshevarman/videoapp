<script setup lang="ts">
import type { PropType } from 'vue'

type Service = {
    client?: { customerFirstName?: string }
    visitStartTime?: string | Date
    surveyObject?: { carBrand?: string; carModelCode?: string; carLicensePlate?: string }
    responsibleEmployee?: { specialistFirstName?: string; specialistLastName?: string }
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
})
</script>

<template>
    <header class="top">
        <div class="container">
            <div class="top__card">
                <div class="top__head">
                    <h1 class="top__title">{{ service.client?.customerFirstName }}, это видео-отчет о состоянии
                        автомобиля</h1>

                    <div class="top__brand">
                        <div class="top__logo" aria-label="БорисХоф"></div>
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
                            <div class="kv__v"> {{ visitDate }}</div>
                        </div>
                        <div class="kv__row">
                            <div class="kv__k">Причина визита:</div>
                            <div class="kv__v">Тех. обслуживание</div>
                        </div>
                        <div class="kv__row">
                            <div class="kv__k">Автомобиль:</div>
                            <div class="kv__v">{{ service.surveyObject?.carBrand }},
                                {{ service.surveyObject?.carModelCode }}
                            </div>
                        </div>
                        <div class="kv__row">
                            <div class="kv__k">Регистрационный номер:</div>
                            <div class="kv__v">{{ service.surveyObject?.carLicensePlate }}</div>
                        </div>
                    </div>

                    <div class="kv">
                        <div class="kv__row">
                            <div class="kv__k">Дилерский центр:</div>
                            <div class="kv__v">BMW БорисХоф Север</div>
                        </div>
                        <div class="kv__row">
                            <div class="kv__k">Мастер консультант:</div>
                            <div class="kv__v">{{ service.responsibleEmployee?.specialistFirstName }},
                                {{ service.responsibleEmployee?.specialistLastName }}
                            </div>
                        </div>
                        <div class="kv__row">
                            <div class="kv__k">Механик:</div>
                            <div class="kv__v"> {{ service?.mechanic?.name }}</div>
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
</template>
