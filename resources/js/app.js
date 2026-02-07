import './bootstrap';
import {createApp} from 'vue';
import ServiceEdit from './service/services.vue';
import ServiceVideo from './service/video.vue';
import ServicesClient from './service/services_clietn.vue';

import { Toaster } from 'vue-sonner'

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
