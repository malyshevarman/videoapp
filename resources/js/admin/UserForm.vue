<script setup>
import { computed, ref } from 'vue'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Select from 'primevue/select'
import MultiSelect from 'primevue/multiselect'

const props = defineProps({
  initial: { type: Object, required: true },
  dealers: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
  submitLabel: { type: String, default: 'Сохранить' },
  isEdit: { type: Boolean, default: false },
})

const form = ref({
  name: props.initial.name ?? '',
  email: props.initial.email ?? '',
  role: props.initial.role ?? 'manager',
  dealer_ids: Array.isArray(props.initial.dealer_ids) ? props.initial.dealer_ids.map(Number) : [],
  password: '',
  password_confirmation: '',
})

const roleOptions = [
  { label: 'Админ', value: 'admin' },
  { label: 'Менеджер', value: 'manager' },
]

const fieldError = (name) => {
  const value = props.errors?.[name]
  return Array.isArray(value) ? value[0] : null
}

const dealerItemError = computed(() => {
  const value = props.errors?.['dealer_ids.*']
  return Array.isArray(value) ? value[0] : null
})
</script>

<template>
  <div class="form-shell">
    <section class="panel">
      <div class="panel-head">
        <h3 class="panel-title">Основные данные</h3>
        <p class="panel-subtitle">Имя, email и роль пользователя</p>
      </div>

      <div class="grid two-cols">
        <div class="field">
          <label for="uf_name" class="label">Имя</label>
          <InputText id="uf_name" v-model="form.name" name="name" class="full" :invalid="!!fieldError('name')" />
          <small v-if="fieldError('name')" class="error">{{ fieldError('name') }}</small>
        </div>

        <div class="field">
          <label for="uf_email" class="label">Email</label>
          <InputText id="uf_email" v-model="form.email" name="email" type="email" class="full" :invalid="!!fieldError('email')" />
          <small v-if="fieldError('email')" class="error">{{ fieldError('email') }}</small>
        </div>

        <div class="field span-all">
          <label for="uf_role" class="label">Роль</label>
          <Select
            id="uf_role"
            v-model="form.role"
            :options="roleOptions"
            option-label="label"
            option-value="value"
            class="full"
            :invalid="!!fieldError('role')"
          />
          <input type="hidden" name="role" :value="form.role">
          <small v-if="fieldError('role')" class="error">{{ fieldError('role') }}</small>
        </div>
      </div>
    </section>

    <section class="panel">
      <div class="panel-head">
        <h3 class="panel-title">Дилеры</h3>
        <p class="panel-subtitle">Привязка пользователя к нескольким дилерам</p>
      </div>

      <div class="field">
        <label for="uf_dealers" class="label">Выбор дилеров</label>
        <MultiSelect
          id="uf_dealers"
          v-model="form.dealer_ids"
          :options="dealers"
          option-label="name"
          option-value="id"
          filter
          display="chip"
          :max-selected-labels="4"
          placeholder="Выберите дилеров"
          class="full"
          :invalid="!!fieldError('dealer_ids') || !!dealerItemError"
        />
        <input v-for="dealerId in form.dealer_ids" :key="dealerId" type="hidden" name="dealer_ids[]" :value="dealerId">
        <small class="hint">Можно искать по названию в выпадающем списке.</small>
        <small v-if="fieldError('dealer_ids')" class="error">{{ fieldError('dealer_ids') }}</small>
        <small v-if="dealerItemError" class="error">{{ dealerItemError }}</small>
      </div>
    </section>

    <section class="panel">
      <div class="panel-head">
        <h3 class="panel-title">Пароль</h3>
        <p class="panel-subtitle">{{ isEdit ? 'Оставьте пустым, если пароль менять не нужно' : 'Создайте пароль для входа' }}</p>
      </div>

      <div class="grid two-cols">
        <div class="field">
          <label for="uf_password" class="label">{{ isEdit ? 'Новый пароль' : 'Пароль' }}</label>
          <Password
            id="uf_password"
            v-model="form.password"
            name="password"
            class="full"
            input-class="full"
            :feedback="!isEdit"
            toggle-mask
            :invalid="!!fieldError('password')"
          />
          <small v-if="fieldError('password')" class="error">{{ fieldError('password') }}</small>
        </div>

        <div class="field">
          <label for="uf_password_confirmation" class="label">Подтверждение пароля</label>
          <Password
            id="uf_password_confirmation"
            v-model="form.password_confirmation"
            name="password_confirmation"
            class="full"
            input-class="full"
            :feedback="false"
            toggle-mask
          />
        </div>
      </div>
    </section>

    <div class="actions">
      <a href="/admin/users" class="btn btn-secondary">Назад</a>
      <button type="submit" class="btn btn-primary">{{ submitLabel }}</button>
    </div>
  </div>
</template>

<style scoped>
.form-shell {
  padding: 16px;
  background: linear-gradient(180deg, #f8fbff 0%, #ffffff 55%);
}

.panel {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  padding: 16px;
  box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
}

.panel + .panel {
  margin-top: 12px;
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
  background: #0284c7;
  color: #fff;
  border-color: #0284c7;
}

.btn-primary:hover {
  background: #0369a1;
  border-color: #0369a1;
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

:deep(.p-inputtext),
:deep(.p-select),
:deep(.p-multiselect),
:deep(.p-password .p-inputtext),
:deep(.p-password-input) {
  background: #fff !important;
  color: #0f172a !important;
  border: 1px solid #cbd5e1 !important;
  border-radius: 10px !important;
  box-shadow: none !important;
}

:deep(.p-select-label),
:deep(.p-multiselect-label) {
  color: #0f172a !important;
}

:deep(.p-select-label.p-placeholder),
:deep(.p-multiselect-label.p-placeholder),
:deep(.p-inputtext::placeholder) {
  color: #64748b !important;
}

:deep(.p-inputtext:enabled:hover),
:deep(.p-select:hover),
:deep(.p-multiselect:hover) {
  border-color: #94a3b8 !important;
}

:deep(.p-inputtext:enabled:focus),
:deep(.p-password-input:enabled:focus),
:deep(.p-select.p-focus),
:deep(.p-multiselect.p-focus) {
  border-color: #38bdf8 !important;
  box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15) !important;
}

:deep(.p-multiselect-token) {
  background: #eff6ff !important;
  color: #1d4ed8 !important;
  border: 1px solid #bfdbfe !important;
  border-radius: 999px !important;
}

:deep(.p-invalid),
:deep(.p-inputtext.p-invalid),
:deep(.p-select.p-invalid),
:deep(.p-multiselect.p-invalid) {
  border-color: #ef4444 !important;
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
