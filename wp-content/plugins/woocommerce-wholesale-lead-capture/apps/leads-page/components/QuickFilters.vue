<template>
    <div id="wwlc-lap-status-filters">
        <label>
            {{ getI18n( 'quick_filters')}}
            <a href="#" class="wwlc-lap-status-filter" @click="handleFilterClick('all', $event)" :class="{ 'selected': selectedFilter === 'all' }">
                <span>{{ getI18n( 'all') }} <span class="wwlc-lap-status-count">({{ statistics.all }})</span></span>
            </a> |
            <a href="#" class="wwlc-lap-status-filter" @click="handleFilterClick('approved', $event)" :class="{ 'selected': selectedFilter === 'approved' }">
                <span>{{ getI18n( 'approved') }} <span class="wwlc-lap-status-count">({{ statistics.approved }})</span></span>
            </a> |
            <a href="#" class="wwlc-lap-status-filter" @click="handleFilterClick('pending', $event)" :class="{ 'selected': selectedFilter === 'pending' }">
                <span>{{ getI18n( 'pending') }} <span class="wwlc-lap-status-count">({{ statistics.pending }})</span></span>
            </a> |
            <a href="#" class="wwlc-lap-status-filter" @click="handleFilterClick('rejected', $event)" :class="{ 'selected': selectedFilter === 'rejected' }">
                <span>{{ getI18n( 'rejected') }} <span class="wwlc-lap-status-count">({{ statistics.rejected }})</span></span>
            </a>
        </label>
    </div>
</template>

<script lang="ts" setup>
import { getI18n } from "../utils/utils";
import { useLeadsStore } from "../stores/leads";
import { useRouter, useRoute } from 'vue-router';
import { nextTick , ref} from 'vue';
const leadsStore = useLeadsStore();
const router = useRouter();
const route = useRoute();
const selectedFilter = ref('all');

const props = defineProps<{
    statistics: any;
}>();

/**
 * Handle filter click.
 *
 * @param filter The filter to apply.
 * @param e The event object.
 */
const handleFilterClick = async (filter: string, e: Event) => {
    e.preventDefault();
    selectedFilter.value = filter;
    leadsStore.setFilter(filter);

    try {
        await nextTick();
        if (router && route) {
            await router.replace({
                query: {
                    ...route.query,
                    roles: filter,
                    page: '1',
                    pageSize: route.query.pageSize?.toString() || leadsStore.pagination.pageSize
                }
            });
        }
    } catch (error) {}
};
</script>

<style lang="less" scoped>
#wwlc-lap-status-filters {
  font-size: 16px;
  line-height: 21px;
  align-content: center;
  justify-content: center;

  label {
    margin-right: 10px;
    font-weight: 600;
  }

  span {
    font-weight: 500;

    a {
      text-decoration: none;

      &:hover,
      &:active {
        text-decoration: underline;
      }
    }
  }

  .wwlc-lap-status-filter {
    font-weight: 400;
    color: #333333;

    &:active,
    &:hover,
    &.selected {
      color: #0F62FE;
    }

    span.wwlc-lap-status-count {
      color: #0F62FE;
    }
  }
}
</style>
