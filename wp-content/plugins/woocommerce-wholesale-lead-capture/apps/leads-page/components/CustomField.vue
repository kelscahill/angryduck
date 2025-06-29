<template>
  <div>
    <Select v-if="field.type === 'select' && field.active"
      :value="field.value"
      @update:value="$emit('update:value', $event)"
      v-bind="field.attributes"
      size="large">
      <SelectOption v-for="option in field.options" :value="option.value" :label="option.label" :key="option.value" v-bind="field.attributes">
        {{ option.text }}
      </SelectOption>
    </Select>
    <CheckboxGroup v-else-if="field.type === 'checkbox-group' && field.active"
      :value="field.value"
      @update:value="$emit('update:value', $event)"
      v-bind="field.attributes">
      <Checkbox v-for="option in field.options" :value="option.value" :key="option.key">{{ option.text }}</Checkbox>
    </CheckboxGroup>
    <Checkbox v-else-if="field.type === 'checkbox' && field.active"
      :checked="convertedCheckboxValue"
      @update:checked="$emit('update:value', $event)"
      v-bind="field.attributes" />
    <Checkbox v-else-if="field.type === 'terms_conditions' && field.active"
      :checked="convertedCheckboxValue"
      @update:checked="$emit('update:value', $event)"
      v-bind="field.attributes">
      <span v-html="field.terms_text || field.placeholder"></span>
    </Checkbox>
    <Switch v-else-if="field.type === 'switch' && field.active"
      :checked="field.value"
      @update:checked="$emit('update:value', $event)"
      v-bind="field.attributes" />
    <Textarea v-else-if="field.type === 'textarea' && field.active"
      :value="field.value"
      @update:value="$emit('update:value', $event)"
      v-bind="field.attributes"
      :auto-size="{ minRows: 2, maxRows: 5 }"></Textarea>
    <RadioGroup v-else-if="field.type === 'radio' && field.active"
      :value="field.value"
      @update:value="$emit('update:value', $event)"
      v-bind="field.attributes">
      <Radio :name="field.name" v-for="option in field.options" :value="option.value" :key="option.key">{{ option.text }}</Radio>
    </RadioGroup>
    <div v-else-if="field.type === 'content' && field.active" v-html="field.content || field.placeholder" class="wwlc-content-field"></div>
    <Input v-else-if="field.type === 'file' && field.active" type="file" v-bind="field.attributes" @change="handleFileUpload" />
    <Input v-else-if="field.type === 'hidden'" type="hidden" :value="field.value" @update:value="$emit('update:value', $event)" />
    <Input v-else
      :value="field.value"
      @update:value="$emit('update:value', $event)"
      :type="field.type"
      :aria-placeholder="field.placeholder"
      v-bind="field.attributes" />
  </div>
</template>

<script lang="ts">
import { Select, SelectOption, Checkbox, CheckboxGroup, RadioGroup, Radio, Switch, Textarea, Input } from 'ant-design-vue';
export default {
  name: 'CustomField',
  components: {
    Select,
    SelectOption,
    Checkbox,
    CheckboxGroup,
    RadioGroup,
    Radio,
    Switch,
    Textarea,
    Input
  },
  props: {
    field: {
      type: Object,
      required: true
    },
    value: {
      type: [String, Number, Boolean, Array, Object],
      default: ''
    }
  },
  emits: ['update:value', 'file-selected'],
  watch: {
    value: {
      handler(newVal) {
        if (newVal !== this.field.value) {
          this.field.value = newVal;
        }
      },
      immediate: true
    }
  },
  methods: {
    checkBoxOptions() {
      return this.field.options.map((option: any) => ({
        label: option.text,
        value: option.value
      }));
    },
    handleFileUpload(e: any) {
      const file = e.target.files[0];
      if (file) {
        this.field.value = file;
        this.$emit('file-selected', file, this.field.name);
        this.$emit('update:value', file);
      }
    }
  },
  computed: {
    inputValue() {
      // Convert boolean values to strings for inputs that need it.
      return typeof this.field.value === 'boolean' ? String(this.field.value) : this.field.value;
    },
    convertedCheckboxValue() {
      // Convert string values to booleans for checkbox
      if (this.field.value === "1" || this.field.value === "true") return true;
      if (this.field.value === "0" || this.field.value === "false") return false;
      return Boolean(this.field.value);
    }
  }
}
</script>
<style lang="less" scoped>
.ant-input,
.ant-textarea {
  padding: 8px;
}

.wwlc-ant-select {
  .ant-select-selector {
    width: 100%;
    height: auto;
    padding: 10px 11px;
  }
}

.wwlc-address-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.wwlc-content-field {
  margin: 10px 0;
}
</style>