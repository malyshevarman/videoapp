<script setup lang="ts">
import type { PropType } from 'vue'
import { Dialog, DialogPanel, DialogTitle, TransitionRoot } from '@headlessui/vue'
import { VueDatePicker } from '@vuepic/vue-datepicker'
import type { Locale } from 'date-fns'

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true,
    },
    modelValue: {
        type: String as PropType<string | null>,
        default: null,
    },
    locale: {
        type: Object as PropType<Locale>,
        required: true,
    },
    minDate: {
        type: Date,
        required: true,
    },
    maxDate: {
        type: Date,
        required: true,
    },
    canConfirm: {
        type: Boolean,
        required: true,
    },
})

const emit = defineEmits(['update:modelValue', 'close', 'confirm'])
</script>

<template>
    <TransitionRoot as="template" :show="props.isOpen">
        <Dialog as="div" :open="props.isOpen" class="modal-root" @close="() => {}">
            <div class="modal-overlay"></div>

            <div class="modal-wrap">
                <DialogPanel class="modal-panel">
                    <DialogTitle class="modal-title">
                        Выберите удобную дату и мы Вам напомним о предложении по ремонту автомобиля
                    </DialogTitle>

                    <div class="modal-body">
                        <VueDatePicker
                            :model-value="modelValue"
                            @update:model-value="value => emit('update:modelValue', value)"
                            :enable-time-picker="false"
                            :time-picker="false"
                            :auto-apply="true"
                            :locale="locale"
                            :min-date="minDate"
                            :max-date="maxDate"
                            :time-config="{ enableTimePicker: false }"
                            :model-type="'yyyy-MM-dd'"
                            inline
                        />
                    </div>

                    <div class="modal-actions">
                        <button class="btn btn--ghost" type="button" @click="emit('close')">
                            Отменить
                        </button>

                        <button
                            class="btn btn--primary"
                            type="button"
                            :disabled="!canConfirm"
                            @click="emit('confirm')"
                        >
                            Подтвердить
                        </button>
                    </div>
                </DialogPanel>
            </div>
        </Dialog>
    </TransitionRoot>
</template>
