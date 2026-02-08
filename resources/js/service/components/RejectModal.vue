<script setup lang="ts">
import { Dialog, DialogPanel, DialogTitle, TransitionRoot } from '@headlessui/vue'

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true,
    },
    activeItemNumber: {
        type: Number,
        required: true,
    },
})

const emit = defineEmits(['close', 'confirm'])
</script>

<template>
    <TransitionRoot as="template" :show="props.isOpen">
        <Dialog as="div" :open="props.isOpen" class="modal-root" @close="() => {}">
            <div class="modal-overlay"></div>

            <div class="modal-wrap">
                <DialogPanel class="modal-panel icon">
                    <DialogTitle class="modal-title">
                        Отклонить предложение {{ activeItemNumber }} по ремонту автомобиля?
                    </DialogTitle>

                    <p class="modal-text">
                        Если не делать вовремя ремонт критических элементов автомобиля, это может быть небезопасно
                    </p>

                    <div class="modal-actions">
                        <button class="btn btn--ghost" type="button" @click="emit('close')">Нет</button>
                        <button class="btn btn--primary" type="button" @click="emit('confirm')">Да, отклонить</button>
                    </div>
                </DialogPanel>
            </div>
        </Dialog>
    </TransitionRoot>
</template>
