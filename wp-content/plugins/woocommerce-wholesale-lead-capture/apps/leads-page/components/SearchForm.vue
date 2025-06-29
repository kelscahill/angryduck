<template>
    <InputSearch
      v-model:value="searchValue"
      :placeholder="getI18n('search_placeholder')"
      size="large"
      @search="onSearch"
      @change="onChange"
    >
      <template #enterButton>
        <Button>{{getI18n('search')}}</Button>
      </template>
    </InputSearch>
</template>
<script lang="ts" setup>
import { ref } from 'vue';
import { debounce } from 'lodash-es';
const searchValue = ref<string>('');
import { getI18n } from "../utils/utils";
import {useLeadsStore} from '../stores/leads';
import { InputSearch, Button } from 'ant-design-vue';

const leadsStore = useLeadsStore()

// Search for leads on click of search button.
const onSearch = () => {
  leadsStore.searchLeads(searchValue.value).then(() => (searchValue.value = ''))
}

// Create debounced search function.
const debouncedSearch = debounce((value: string) => {
  leadsStore.searchLeads(value)
}, 1000)

// Search for leads on change of input.
const onChange = (e: Event) => {
  searchValue.value = (e.target as HTMLInputElement).value
  debouncedSearch(searchValue.value)
}
</script>
<style lang="less">
.wholesale_page_wwlc-leads-admin-page #leads-admin-page {
  .ant-input-search {
    border: 1px solid #D9D9D9;
    border-radius: 8px;
    transition: border-color 0.3s ease;

    /* Change border color when input is focused */
    &:focus-within {
      border-color: #2ea2cc;
    }

    /* Increase specificity by using more selectors */
    .ant-input-affix-wrapper .ant-input,
    input.ant-input {
      width: auto;
      padding: 6.5px 11px;
      border: 0;
      box-shadow: none;
      border-radius: 8px;
      margin: 2px;
    }

    /* Using :deep to target Vue component internals */
    :deep(.ant-input) {
      border: 0;
      box-shadow: none;

      &:hover,
      &:focus,
      &:focus-within,
      &:active {
        border: 0;
        box-shadow: none;
        outline: none;
      }
    }

    .ant-input-wrapper .ant-input-affix-wrapper .ant-input,
    .ant-input-wrapper input.ant-input {
      border: 0;
    }

    .ant-input-group-addon button {
      border: none;
    }
  }
}
</style>
