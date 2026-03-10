<script setup lang="ts">
import logoImage from '../../../images/logo.svg'

defineProps<{
    homeUrl?: string
    reviewUrl?: string
}>()
</script>

<template>
    <main class="success-page">
        <div class="success-page__banner">
            <div class="success-page__banner-inner">
                <div class="success-page__status">
                    <span class="success-page__check" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="12" fill="#56C11B" />
                            <path d="M7.5 12.2L10.5 15.2L16.5 8.8" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <span>Данные успешно переданы</span>
                </div>

                <img :src="logoImage" alt="БорисХоф" class="success-page__logo">
            </div>
        </div>

        <div class="success-page__content">
            <div class="success-page__body">
                <h1 class="success-page__title">Мы хотим сделать сервис лучше</h1>
                <p class="success-page__subtitle">
                    Нажмите на кнопку «Оставить отзыв» и пройдите короткий опрос
                </p>

                <div class="success-page__actions">

                    <a :href="reviewUrl || '#'" class="success-page__button success-page__button--primary">
                        Оставить отзыв
                    </a>
                </div>
            </div>
        </div>

        <footer class="success-page__footer">
            <div class="success-page__footer-top">
                <div class="success-page__company">
                    <div>ООО «БОРИСХОФ 1»</div>
                    <div class="success-page__muted">с/п Булатниковское, район 29 км МКАД, уч. 1</div>
                    <div class="success-page__muted">+7 495 745-11-11</div>
                </div>

                <div class="success-page__links">
                    <a href="#" class="success-page__link">
                        <span class="success-page__link-icon" aria-hidden="true">
                            <svg viewBox="0 0 17 15" fill="none">
                                <path d="M16.6189 1.28485C16.8423 0.228264 16.2466 -0.186825 15.5765 0.0773227L0.75919 5.85083C-0.246002 6.26592 -0.208773 6.83195 0.610272 7.0961L4.37044 8.26589L13.1566 2.68106C13.5661 2.37918 13.9756 2.56786 13.6405 2.832L6.52974 9.32248L6.26913 13.2847C6.67865 13.2847 6.82757 13.1338 7.05095 12.9073L8.87518 11.096L12.7098 13.9639C13.4172 14.379 13.9384 14.1526 14.1245 13.3224L16.6189 1.28485Z" fill="currentColor"/>
                            </svg>
                        </span>
                        Официальный Telegram-канал
                    </a>
                    <a href="#" class="success-page__link">
                        <span class="success-page__link-icon" aria-hidden="true">
                            <svg viewBox="0 0 17 15" fill="none">
                                <path d="M16.6189 1.28485C16.8423 0.228264 16.2466 -0.186825 15.5765 0.0773227L0.75919 5.85083C-0.246002 6.26592 -0.208773 6.83195 0.610272 7.0961L4.37044 8.26589L13.1566 2.68106C13.5661 2.37918 13.9756 2.56786 13.6405 2.832L6.52974 9.32248L6.26913 13.2847C6.67865 13.2847 6.82757 13.1338 7.05095 12.9073L8.87518 11.096L12.7098 13.9639C13.4172 14.379 13.9384 14.1526 14.1245 13.3224L16.6189 1.28485Z" fill="currentColor"/>
                            </svg>
                        </span>
                        Telegram бот
                    </a>
                </div>
            </div>

            <p class="success-page__fineprint">
                Все цены указаны с учетом всех налогов, индивидуальных скидок и акций. Авторизация ремонта означает согласие клиента на выполнение дополнительных работ на автомобиле в рамках указанной стоимости.
            </p>
        </footer>
    </main>
</template>
