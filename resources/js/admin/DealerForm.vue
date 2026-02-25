<script setup>
import { ref } from 'vue'
import InputText from 'primevue/inputtext'
import Checkbox from 'primevue/checkbox'

const props = defineProps({
  initial: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  submitLabel: { type: String, default: 'Сохранить' },
  isEdit: { type: Boolean, default: false },
  currentLogoUrl: { type: String, default: '' },
})

const form = ref({
  external_id: props.initial.external_id ?? '',
  name: props.initial.name ?? '',
  remove_logo: !!props.initial.remove_logo,
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
        <h3 class="panel-title">Карточка диллера</h3>
        <p class="panel-subtitle">Название, внешний ID и логотип</p>
      </div>

      <div class="grid two-cols">
        <div class="field">
          <label for="df_external_id" class="label">Внешний ID</label>
          <InputText id="df_external_id" v-model="form.external_id" name="external_id" class="full" :invalid="!!fieldError('external_id')" />
          <small v-if="fieldError('external_id')" class="error">{{ fieldError('external_id') }}</small>
        </div>

        <div class="field">
          <label for="df_name" class="label">Название</label>
          <InputText id="df_name" v-model="form.name" name="name" class="full" :invalid="!!fieldError('name')" />
          <small v-if="fieldError('name')" class="error">{{ fieldError('name') }}</small>
        </div>

        <div class="field span-all">
          <label for="logo" class="label">Логотип</label>
          <div class="upload-box">
            <input
              id="logo"
              name="logo"
              type="file"
              accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
              class="native-file"
            >
            <div class="hint">JPG/PNG/WEBP. Изображение автоматически уменьшится до 500x500, если оно больше.</div>
          </div>
          <small v-if="fieldError('logo')" class="error">{{ fieldError('logo') }}</small>
        </div>

        <div v-if="isEdit && currentLogoUrl" class="field span-all">
          <label class="label">Текущий логотип</label>
          <div class="logo-box">
            <img :src="currentLogoUrl" alt="Логотип диллера" class="logo-preview">
            <div class="logo-meta">
              <div class="hint">Можно загрузить новый логотип поверх текущего или удалить его.</div>
              <div class="checkbox-row">
                <Checkbox v-model="form.remove_logo" input-id="remove_logo" binary />
                <label for="remove_logo">Удалить текущий логотип</label>
              </div>
            </div>
            <input type="hidden" name="remove_logo" :value="form.remove_logo ? 1 : 0">
          </div>
        </div>
      </div>
    </section>

    <div class="actions">
      <a href="/admin/dealers" class="btn btn-secondary">Назад</a>
      <button type="submit" class="btn btn-success">{{ submitLabel }}</button>
    </div>
  </div>
</template>

<style scoped>
.form-shell {
  padding: 16px;
  background: linear-gradient(180deg, #f7fffb 0%, #ffffff 55%);
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

.two-cols {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.span-all {
  grid-column: 1 / -1;
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

.upload-box {
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
  background: #059669;
  color: #fff;
  font-weight: 600;
  cursor: pointer;
}

.native-file::file-selector-button:hover {
  background: #047857;
}

.logo-box {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #ffffff;
  padding: 12px;
}

.logo-preview {
  width: 112px;
  height: 112px;
  object-fit: contain;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #f8fafc;
  padding: 8px;
}

.logo-meta {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.checkbox-row {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #334155;
  font-size: 14px;
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

.btn-secondary {
  background: #fff;
  color: #334155;
  border-color: #cbd5e1;
}

.btn-secondary:hover {
  background: #f8fafc;
}

.btn-success {
  background: #059669;
  color: #fff;
  border-color: #059669;
}

.btn-success:hover {
  background: #047857;
  border-color: #047857;
}

:deep(.full),
:deep(.full .p-inputtext) {
  width: 100%;
}

:deep(.p-inputtext),
:deep(.p-checkbox-box) {
  background: #fff !important;
  color: #0f172a !important;
  border-color: #cbd5e1 !important;
  box-shadow: none !important;
}

@media (max-width: 768px) {
  .two-cols {
    grid-template-columns: 1fr;
  }

  .actions {
    flex-direction: column-reverse;
    align-items: stretch;
  }
}
</style>
