<template>
     <FormItem :label="getI18n('state')">
        <Select
            v-if="states.length > 0"
            :value="state"
            :disabled="readonly"
            :loading="loading"
            :placeholder="getI18n('state')"
            :options="stateOptions"
            @update:value="$emit('update:state', $event as string)"
            name="wwlc_state"
            class="wwlc-ant-select"
            size="large"
            show-search
            option-filter-prop="name"
        >
            <SelectOption v-for="state in states" :key="state.value" :value="state.value">
                {{ state.label }}
            </SelectOption>
        </Select>
        <Input v-else :disabled="readonly" :value="state" @update:value="$emit('update:state', $event)" size="large" :placeholder="getI18n('state')" />
     </FormItem>
</template>

<script setup lang="ts">
import { getI18n } from "../utils/utils";
import { watch, computed } from 'vue';
import { PropType } from 'vue';
import { Select, FormItem, Input, SelectOption } from 'ant-design-vue';

const props = defineProps({
    states: {
        type: Array as PropType<Array<{value: string, label: string}>>,
        required: true
    },
    state: {
        type: String,
        required: true
    },
    loading: {
        type: Boolean,
        required: false,
        default: false
    },
    readonly: {
        type: Boolean,
        required: false,
        default: false
    }
});

const emit = defineEmits<{
    'update:state': [value: string];
}>();

const stateOptions = computed(() => {
    return props.states.map(state => ({
        value: state.value,
        label: state.label
    }));
});

// Reset state when states array changes
watch(() => props.states, () => {
    emit('update:state', '');
}, { deep: true });
</script>