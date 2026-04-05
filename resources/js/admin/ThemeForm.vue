<script setup>
import { ref } from 'vue'
import InputText from 'primevue/inputtext'

const props = defineProps({
  initial: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  submitLabel: { type: String, default: 'Сохранить' },
  isEdit: { type: Boolean, default: false },
  currentLogoUrl: { type: String, default: '' },
})

const form = ref({
  name: props.initial.name ?? '',
})

const fieldError = (name) => {
  const value = props.errors?.[name]
  return Array.isArray(value) ? value[0] : null
}
</script>

<template>
  <div class="form-shell">
    <section class="panel">
      <div class="panel-head">
        <h3 class="panel-title">Карточка темы</h3>
        <p class="panel-subtitle">Название темы и логотип, который будет показан клиенту</p>
      </div>

      <div class="grid">
        <div class="field">
          <label for="tf_name" class="label">Название</label>
          <InputText id="tf_name" v-model="form.name" name="name" class="full" :invalid="!!fieldError('name')" />
          <small v-if="fieldError('name')" class="error">{{ fieldError('name') }}</small>
        </div>

        <div class="field">
          <label for="logo" class="label">Логотип</label>
          <div class="upload-box">
            <input
              id="logo"
              name="logo"
              type="file"
              accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
              class="native-file"
            >
            <div class="hint">
              {{ isEdit ? 'Можно загрузить новый логотип поверх текущего.' : 'Логотип обязателен при создании темы.' }}
            </div>
          </div>
          <small v-if="fieldError('logo')" class="error">{{ fieldError('logo') }}</small>
        </div>

        <div v-if="isEdit && currentLogoUrl" class="field">
          <label class="label">Текущий логотип</label>
          <div class="logo-box">
            <img :src="currentLogoUrl" alt="Логотип темы" class="logo-preview">
          </div>
        </div>
      </div>
    </section>

    <div class="actions">
      <a href="/admin/themes" class="btn btn-secondary">Назад</a>
      <button type="submit" class="btn btn-primary">{{ submitLabel }}</button>
    </div>
  </div>
</template>

<style scoped>
.form-shell {
  padding: 16px;
  background: linear-gradient(180deg, #fffaf2 0%, #ffffff 55%);
}

.panel {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  padding: 16px;
  box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
}

.panel-head {
  margin-bottom: 14px;
}

.panel-title {
  margin: 0;
  font-size: 16px;
  line-height: 1.3;
  font-weight: 700;
  color: #0f172a;
}

.panel-subtitle {
  margin: 4px 0 0;
  color: #64748b;
  font-size: 13px;
}

.grid {
  display: grid;
  gap: 14px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.label {
  font-size: 13px;
  font-weight: 600;
  color: #334155;
}

.hint {
  color: #64748b;
  font-size: 12px;
}

.error {
  color: #dc2626;
  font-size: 12px;
}

.upload-box,
.logo-box {
  border: 1px dashed #cbd5e1;
  border-radius: 12px;
  background: #f8fafc;
  padding: 12px;
}

.native-file {
  width: 100%;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 10px;
  background: #ffffff;
  color: #334155;
}

.native-file::file-selector-button {
  margin-right: 10px;
  border: none;
  border-radius: 8px;
  padding: 7px 12px;
  background: #ea580c;
  color: #fff;
  font-weight: 600;
  cursor: pointer;
}

.native-file::file-selector-button:hover {
  background: #c2410c;
}

.logo-preview {
  width: 140px;
  height: 140px;
  object-fit: contain;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  padding: 8px;
}

.actions {
  margin-top: 12px;
  padding: 14px 16px;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  background: #ffffff;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 40px;
  padding: 0 14px;
  border-radius: 10px;
  border: 1px solid transparent;
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
}

.btn-primary {
  background: #ea580c;
  color: #fff;
  border-color: #ea580c;
}

.btn-primary:hover {
  background: #c2410c;
  border-color: #c2410c;
}

.btn-secondary {
  background: #fff;
  color: #334155;
  border-color: #cbd5e1;
}

.btn-secondary:hover {
  background: #f8fafc;
}

:deep(.full),
:deep(.full .p-inputtext) {
  width: 100%;
}

:deep(.p-inputtext) {
  background: #fff !important;
  color: #0f172a !important;
  border: 1px solid #cbd5e1 !important;
  border-radius: 10px !important;
  box-shadow: none !important;
}

:deep(.p-inputtext:enabled:hover) {
  border-color: #94a3b8 !important;
}

:deep(.p-inputtext:enabled:focus) {
  border-color: #fb923c !important;
  box-shadow: 0 0 0 3px rgba(251, 146, 60, 0.15) !important;
}

:deep(.p-invalid),
:deep(.p-inputtext.p-invalid) {
  border-color: #ef4444 !important;
}

@media (max-width: 768px) {
  .actions {
    flex-direction: column-reverse;
    align-items: stretch;
  }
}
</style>
