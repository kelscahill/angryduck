<script setup>
import { Spin } from 'ant-design-vue';
import LeadsTable from "./components/LeadsTable.vue";
import QuickFilters from "./components/QuickFilters.vue";
import BulkActions from "./components/BulkActions.vue";
import { getI18n } from "./utils/utils";
import {computed} from 'vue';

import { useLeadsStore } from "./stores/leads";

const leadsStore = useLeadsStore();

const isLoading = computed(() => leadsStore.isLoading)
const statistics = computed(() => leadsStore.statistics)

const wholesaleSuiteLogo = computed(() => window.wwlc_lap.wholesale_suite_logo)
const wholesaleSuiteLogoAlt = computed(() => window.wwlc_lap.logo_alt)

</script>

<template>
  <div>
    <div id="wholesale-suite-logo-container">
      <img
				id="wholesale-suite-logo"
				:src="`${wholesaleSuiteLogo}`"
				:alt="`${wholesaleSuiteLogoAlt}`"
			/>
    </div>
    <h1 id="wwlc-lap-title">
			{{ getI18n('app_name') }}
		</h1>

    <Spin :spinning="isLoading" size="large" title="Loading">
      <div class="toolbar">
        <QuickFilters :statistics="statistics" />
      </div>

      <div id="bulk-actions">
        <BulkActions />
      </div>

      <LeadsTable />
    </Spin>
  </div>
</template>
<style lang="less">
.wholesale_page_wwlc-leads-admin-page {
  background: #ffffff;

  #wholesale-suite-logo {
    width: 300px;
    margin-bottom: 24px;
  }

  #leads-admin-page {
    margin-top: 24px;

    #wwlc-lap-title {
      font-weight: 700;
      font-size: 24px;
      line-height: 28.8px;
    }

    .toolbar {
      display: flex;
      flex-direction: row;
      justify-content: space-between;
      align-items: center;
      margin: 18px 0;
    }

    input {
      width: 100%;
      padding: 10px;
    }
  }
}
</style>