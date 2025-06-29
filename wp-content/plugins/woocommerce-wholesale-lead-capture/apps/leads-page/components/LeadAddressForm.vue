<template>
  <div>
    <FormItem :label="getI18n('address_1')" name="address_1">
        <Input
          :value="address_one"
          @update:value="$emit('update:address_one', $event)"
          size="large"
          :readonly="readonly"
          :placeholder="getI18n('address_1')"
        />
    </FormItem>

    <FormItem :label="getI18n('address_2')" name="address_2">
        <Input
          :value="address_two"
          @update:value="$emit('update:address_two', $event)"
          size="large"
          :readonly="readonly"
          :placeholder="getI18n('address_2')"
        />
    </FormItem>

    <Row :gutter="16">
        <Col :span="12">
            <FormItem :label="getI18n('country')" name="country" size="large">
                <Select
                    :value="country"
                    @update:value="$emit('update:country', $event)"
                    :disabled="readonly"
                    class="wwlc-ant-select"
                    size="large"
                    show-search
                    @change="handleCountryChange"
                >
                    <SelectOption v-for="(country, countryCode) in getCountries()" :key="countryCode" :value="countryCode">
                      {{ country['name'] }}
                    </SelectOption>
                </Select>
            </FormItem>
        </Col>
        <Col :span="12">
            <AddressStatesField
                :key="country"
                :state="state"
                :states="states"
                :readonly="readonly"
                @update:state="$emit('update:state', $event)"
            />
        </Col>
    </Row>

    <Row :gutter="16">
        <Col :span="12">
            <FormItem :label="getI18n('city')" name="city">
                <Input
                  :value="city"
                  @update:value="$emit('update:city', $event)"
                  :disabled="readonly"
                  :placeholder="getI18n('city')"
                  size="large"
                />
            </FormItem>
        </Col>
        <Col :span="12">
            <FormItem :label="getI18n('zip_code')" name="zip_code">
                <Input
                  :value="postcode"
                  @update:value="$emit('update:postcode', $event)"
                  :disabled="readonly"
                  :placeholder="getI18n('zip_code')"
                  size="large"
                />
            </FormItem>
        </Col>
    </Row>

    <FormItem :label="getI18n('phone_number')" name="phone_number">
        <Input
          :value="phone"
          @update:value="$emit('update:phone', $event)"
          :disabled="readonly"
          :placeholder="getI18n('phone_placeholder')"
          size="large"
        />
    </FormItem>

    <FormItem :label="getI18n('company_name')" name="company_name">
        <Input
          :value="company"
          @update:value="$emit('update:company', $event)"
          :disabled="readonly"
          :placeholder="getI18n('company_name')"
          size="large"
        />
    </FormItem>
  </div>
</template>

<script lang="ts" setup>
import { ref , watch } from 'vue';
import { getI18n } from "../utils/utils";
import AddressStatesField from './AddressStatesField.vue';
import { defineProps, defineEmits } from 'vue';
import { Input, FormItem, Row, Col, Select, SelectOption } from 'ant-design-vue';
import type { SelectValue } from 'ant-design-vue/es/select';
const states = ref<{value: string, label: string}[]>([]);

const props = defineProps({
  address_one: {
    type: String,
    default: '',
  },
  address_two: {
    type: String,
    default: '',
  },
  country: {
    type: String,
    default: '',
  },
  state: {
    type: String,
    default: '',
  },
  city: {
    type: String,
    default: '',
  },
  phone: {
    type: String,
    default: '',
  },
  company: {
    type: String,
    default: '',
  },
  postcode: {
    type: String,
    default: '',
  },
  readonly: {
    type: Boolean,
    default: false,
  },
});

const getCountries = () => {
    return (window as any).wwlc_lap.countries;
}

const getStates = (cc: string) => {
    const countries = getCountries();
    const statesList = countries[cc]['states'] ?? {};
    // Convert object to array of {value, label} pairs
    return Object.entries(statesList).map(([code, name]) => ({
      value: code,
      label: name as string
    }));
}

const handleCountryChange = async (value: SelectValue) => {
  // Reset state when country changes
  states.value = await getStates(value as string);
  emit('update:country', value);
};

const handleCountryBlur = () => {
  console.log(`handleCountryBlur ${props.country}`);
  if ( props.country !== '') {
    states.value = getStates(props.country);
  }
};

const handleCountryFocus = () => {
 // states.value = [];
};

// Watch for country changes to update states.
watch(() => props.country, async (newCountry) => {
  if (newCountry) {
    states.value = await getStates(newCountry);
  }
});

const emit = defineEmits([
  'update:address_one',
  'update:address_two',
  'update:country',
  'update:state',
  'update:city',
  'update:postcode',
  'update:phone',
  'update:company'
]);

</script>
<style lang="less" scoped>
.ant-input{
  padding: 8px;
}
.wwlc-ant-select.ant-select-single .ant-select-selector{
  width: 100%;
  height: auto;
  padding: 10px 11px;
}
</style>