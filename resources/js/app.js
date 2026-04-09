import './bootstrap';
import { createApp } from 'vue';
import PrimeVue from 'primevue/config';
import Aura from '@primeuix/themes/aura';
import 'primeicons/primeicons.css';
import 'quill/dist/quill.core.css';
import 'quill/dist/quill.snow.css';
import { Toaster } from 'vue-sonner';
import ServiceEdit from './service/services.vue';
import ServiceVideo from './service/video.vue';
import ServicesClient from './service/services_clietn.vue';
import UserForm from './admin/UserForm.vue';
import DealerForm from './admin/DealerForm.vue';
import ThemeForm from './admin/ThemeForm.vue';

function parseDatasetPayload(raw, fallback) {
    if (!raw) {
        return fallback;
    }

    const value = String(raw).trim();

    try {
        return JSON.parse(value);
    } catch (e) {
        const normalized = value.replace(/;+\s*$/, '');

        if (normalized.startsWith('JSON.parse(')) {
            // Payload can come from Laravel Js::from(...), which renders JSON.parse('...')
            // eslint-disable-next-line no-new-func
            return Function(`"use strict"; return (${normalized});`)();
        }

        return fallback;
    }
}

function mountPrimeApp(el, component, props = {}) {
    const app = createApp(component, props);
    app.use(PrimeVue, {
        theme: {
            preset: Aura,
            options: {
                darkModeSelector: false,
            },
        },
    });
    app.mount(el);
    return app;
}

const serviceEditEl = document.getElementById('service-edit');
if (serviceEditEl) {
    const service = JSON.parse(serviceEditEl.dataset.service);
    createApp(ServiceEdit, { service }).mount(serviceEditEl);
}

const serviceVideoEl = document.getElementById('service-video');
if (serviceVideoEl) {
    const service = JSON.parse(serviceVideoEl.dataset.service);
    createApp(ServiceVideo, { service }).mount(serviceVideoEl);
}

const serviceClientEl = document.getElementById('service-client');
if (serviceClientEl) {
    const service = JSON.parse(serviceClientEl.dataset.service);
    const items = JSON.parse(serviceClientEl.dataset.items);

    const app = createApp(ServicesClient, { service, items });
    app.component('Toaster', Toaster);
    app.mount(serviceClientEl);
}

const userFormEl = document.getElementById('admin-user-form');
if (userFormEl) {
    mountPrimeApp(userFormEl, UserForm, {
        initial: parseDatasetPayload(userFormEl.dataset.initial, {}),
        dealers: parseDatasetPayload(userFormEl.dataset.dealers, []),
        errors: parseDatasetPayload(userFormEl.dataset.errors, {}),
        submitLabel: userFormEl.dataset.submitLabel || 'Сохранить',
        isEdit: userFormEl.dataset.isEdit === '1',
    });
}

const dealerFormEl = document.getElementById('admin-dealer-form');
if (dealerFormEl) {
    mountPrimeApp(dealerFormEl, DealerForm, {
        initial: parseDatasetPayload(dealerFormEl.dataset.initial, {}),
        themes: parseDatasetPayload(dealerFormEl.dataset.themes, []),
        errors: parseDatasetPayload(dealerFormEl.dataset.errors, {}),
        submitLabel: dealerFormEl.dataset.submitLabel || 'Сохранить',
        isEdit: dealerFormEl.dataset.isEdit === '1',
        currentLogoUrl: dealerFormEl.dataset.currentLogoUrl || '',
    });
}

const themeFormEl = document.getElementById('admin-theme-form');
if (themeFormEl) {
    mountPrimeApp(themeFormEl, ThemeForm, {
        initial: parseDatasetPayload(themeFormEl.dataset.initial, {}),
        errors: parseDatasetPayload(themeFormEl.dataset.errors, {}),
        submitLabel: themeFormEl.dataset.submitLabel || 'Сохранить',
        isEdit: themeFormEl.dataset.isEdit === '1',
        currentLogoUrl: themeFormEl.dataset.currentLogoUrl || '',
    });
}
