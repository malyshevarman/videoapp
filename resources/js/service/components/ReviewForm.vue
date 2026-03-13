<script setup lang="ts">
import { computed, reactive } from 'vue'
import logoImage from '../../../images/logo.svg'

type ReviewPayload = {
    info_usefulness: number | null
    usability: number | null
    video_content: number | null
    video_image: number | null
    video_sound: number | null
    video_duration: number | null
    comment: string
}

const props = defineProps<{
    submitting?: boolean
}>()

const emit = defineEmits<{
    (e: 'back'): void
    (e: 'submit', payload: ReviewPayload): void
}>()

const form = reactive<ReviewPayload>({
    info_usefulness: null,
    usability: null,
    video_content: null,
    video_image: null,
    video_sound: null,
    video_duration: null,
    comment: '',
})

const serviceRatings = [
    { key: 'info_usefulness', label: 'Полезность информации' },
    { key: 'usability', label: 'Удобство использования' },
] as const

const videoRatings = [
    { key: 'video_content', label: 'Содержание/контент' },
    { key: 'video_image', label: 'Изображение' },
    { key: 'video_sound', label: 'Звук' },
    { key: 'video_duration', label: 'Длительность' },
] as const

const canSubmit = computed(() =>
    serviceRatings.every(({ key }) => form[key] !== null) &&
    videoRatings.every(({ key }) => form[key] !== null) &&
    !props.submitting
)

function setRating(key: keyof ReviewPayload, value: number) {
    if (key === 'comment') return
    form[key] = value
}

function currentRating(key: Exclude<keyof ReviewPayload, 'comment'>) {
    return Number(form[key] ?? 0)
}

function submitReview() {
    if (!canSubmit.value) return

    emit('submit', {
        ...form,
        comment: form.comment.trim(),
    })
}
</script>

<template>
    <div class="container">
        <main class="review-page">
            <div class="success-page__banner">
                <div class="success-page__banner-inner">
                    <span class="mrg"></span>
                    <div class="top__brand">
                        <div class="top__logo" aria-label="БорисХоф"></div>
                    </div>
                </div>
            </div>

            <div class="review-page__content">
                <div class="review-card">
                    <h2 class="review-card__title">Оцените Сервис по согласованию работ</h2>

                    <div
                        v-for="item in serviceRatings"
                        :key="item.key"
                        class="review-stars"
                    >
                        <div class="review-stars__label">{{ item.label }}</div>
                        <div class="review-stars__row">
                            <button
                                v-for="value in 5"
                                :key="value"
                                type="button"
                                class="review-stars__star"
                                :class="{ 'is-active': value <= currentRating(item.key) }"
                                @click="setRating(item.key, value)"
                            >
                                ★
                            </button>
                        </div>
                        <div class="review-stars__legend">
                            <span>Очень плохо</span>
                            <span>Отлично</span>
                        </div>
                    </div>
                </div>

                <div class="review-card">
                    <h2 class="review-card__title">Оцените видео-отчет, по параметрам:</h2>
                    <div class="review-card__sub">где 1 - очень плохо, 5 - отлично</div>

                    <div class="review-grid">
                        <div
                            v-for="item in videoRatings"
                            :key="item.key"
                            class="review-grid__row"
                        >
                            <div class="review-grid__label">{{ item.label }}</div>
                            <div class="review-grid__values">
                                <button
                                    v-for="value in 5"
                                    :key="value"
                                    type="button"
                                    class="review-grid__value"
                                    :class="{ 'is-active': form[item.key] === value }"
                                    @click="setRating(item.key, value)"
                                >
                                    {{ value }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="review-card review-card--comment">
                    <div class="review-card__small-title">Есть что-то, что мы не учли - расскажите:</div>
                    <textarea
                        v-model="form.comment"
                        class="review-card__textarea"
                        placeholder="Что понравилось, а что нет"
                        rows="4"
                    />
                </div>

                <div class="review-page__actions">
                    <button
                        type="button"
                        class="review-page__submit"
                        :disabled="!canSubmit"
                        @click="submitReview"
                    >
                        {{ submitting ? 'Отправляем...' : 'Отправить отзыв' }}
                    </button>
                </div>
            </div>

            <section class="note">
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
        </main>
    </div>
</template>
