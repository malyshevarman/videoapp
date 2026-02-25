import './bootstrap';
import {createApp} from 'vue';
import PrimeVue from 'primevue/config';
import Aura from '@primeuix/themes/aura';
import 'primeicons/primeicons.css';
import ServiceEdit from './service/services.vue';
import ServiceVideo from './service/video.vue';
import ServicesClient from './service/services_clietn.vue';
import UserForm from './admin/UserForm.vue';
import DealerForm from './admin/DealerForm.vue';
import 'primeicons/primeicons.css';
import { Toaster } from 'vue-sonner'

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

const el = document.getElementById('service-edit');
if (el) {
    const service = JSON.parse(el.dataset.service);
    createApp(ServiceEdit, {service}).mount(el);
}

const eltwo = document.getElementById('service-video');
if (eltwo) {
    const service = JSON.parse(eltwo.dataset.service);
    createApp(ServiceVideo, {service}).mount(eltwo);
}
const elttree = document.getElementById('service-client');
if (elttree) {
    const service = JSON.parse(elttree.dataset.service);
    const items = JSON.parse(elttree.dataset.items)

    const app = createApp(ServicesClient, { service, items })
    app.component('Toaster', Toaster)
    app.mount(elttree)
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
        errors: parseDatasetPayload(dealerFormEl.dataset.errors, {}),
        submitLabel: dealerFormEl.dataset.submitLabel || 'Сохранить',
        isEdit: dealerFormEl.dataset.isEdit === '1',
        currentLogoUrl: dealerFormEl.dataset.currentLogoUrl || '',
    });
}
