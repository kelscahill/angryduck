<template>
  <Table :columns="columns" :data-source="leads" rowKey="ID" :row-selection="{ selectedRowKeys: selectedRowsKeys, onChange: onSelectChange }" :pagination="{
    ...leadsStore.pagination,
    position: ['bottomCenter']
  }" @change="handleTableChange">
    <template #headerCell="{ column }">
      <template v-if="column.key === 'name'">
        <span>
          {{column.title}}
        </span>
      </template>
    </template>
    <template #bodyCell="{ column, record }">
      <template v-if="column.key === 'name'">
        <a>
        {{ record.name }}
        </a>
      </template>
      <template v-else-if="column.key === 'role'">
        <span>
         {{ getRoleName(record.role) }}
        </span>
      </template>
      <template v-else-if="column.key === 'status'">
        <span>
          <Tag :color="getUserStatusColor(record.status, record.role)">
            {{ ucFirst( getUserStatus(record.status, record.role) ) }}
          </Tag>
        </span>
      </template>
      <template v-else-if="column.key === 'registered'">
        <span>
          <CalendarOutlined />
          {{ formatDate(record.registered) }}
        </span>
      </template>
      <template v-else-if="column.key === 'action'">

        <span class="lead-row-actions">
          <Popover :title="getI18n('approve_popup_title')" v-if="record.status === 'pending' || record.status === 'rejected'">
            <template #content>
              <p>{{ getI18n('approve_popup_text') }}</p>
            </template>

            <CheckSquareTwoTone
              class="action-icon"
              :style="{ fontSize: '18.75px' }"
              :two-tone-color="hoveredApproveId === record.ID ? '#23996E' : '#8c8c8c'"
              @mouseenter="hoveredApproveId = record.ID"
              @mouseleave="hoveredApproveId = null"
              @click="showApproveConfirm(record.ID)"
            />
          </Popover>

          <Popover :title="getI18n('reject_popup_title')" v-if="record.status === 'pending'">
            <template #content>
              <p>{{ getI18n('reject_popup_text')}}</p>
            </template>

            <CloseSquareTwoTone
              class="action-icon"
              :style="{ fontSize: '18.75px' }"
              :two-tone-color="hoveredRejectId === record.ID ? '#D91A1A' : '#8c8c8c'"
              @mouseenter="hoveredRejectId = record.ID"
              @mouseleave="hoveredRejectId = null"
              @click="showRejectConfirm(record.ID)"
            />
          </Popover>

          <EditLeadPanel :leadId="record.ID" />
        </span>
      </template>
    </template>
  </Table>
</template>
<script setup lang="ts">
import { onMounted,  computed, ref, nextTick } from 'vue';
import { useLeadsStore } from '../stores/leads';
import { Key } from '../utils/types';
import { useRoute, useRouter } from 'vue-router';
import { getUserStatusColor, getUserStatus } from '../utils/utils';
import EditLeadPanel from './EditLeadPanel.vue';

import { Table, Tag, Popover, Button, Modal } from 'ant-design-vue';
import { CheckSquareTwoTone, CloseSquareTwoTone, CalendarOutlined, QuestionCircleTwoTone, setTwoToneColor } from "@ant-design/icons-vue";
import { createVNode } from 'vue';
import { getRoleName, formatDate, ucFirst, getI18n } from "../utils/utils";

const leadsStore = useLeadsStore();
const route = useRoute();
const router = useRouter();

const fetchLeads = async () => {
  // Default values if route.query is not available
  const page = route?.query?.page ? Number(route.query.page) : 1;
  const pageSize = route?.query?.pageSize ? Number(route.query.pageSize) : leadsStore.pagination.pageSize;
  const status = route?.query?.status as string || 'all';
  const roles = route?.query?.roles as string || 'all';

  // Update store params
  leadsStore.params.status = status;
  leadsStore.params.roles = roles;

  await leadsStore.fetchLeads(page, pageSize);
};

/**
 * Fetch leads data.
 */
onMounted(() => {
  fetchLeads()
});

const leads = computed(() => leadsStore.leads);
const columns = computed(() => leadsStore.columns);

/**
 * Handle table change.
 *
 * @param pagination The pagination object.
 */
const handleTableChange = async (pagination: any) => {
  await leadsStore.fetchLeads(pagination.current, pagination.pageSize);

  // Only update URL if router is available.
  if (router) {
    try {
      await router.replace({
        query: {
          ...route.query,
          page: pagination.current.toString(),
          pageSize: pagination.pageSize.toString()
        }
      });
    } catch (error) {
      console.error('Navigation error:', error);
    }
  }
};

// Use computed instead of ref for selectedRowsKeys
const selectedRowsKeys = computed({
  get: () => leadsStore.selectedLeads,
  set: (value) => { leadsStore.selectedLeads = value; }
});

/**
 * Handle row selection change.
 *
 * @param selectedKeys The selected keys.
 */
const onSelectChange = (selectedKeys: Key[]) => {
  // Just set the store value directly
  leadsStore.selectedLeads = selectedKeys;
}

const hoveredApproveId = ref<number | null>(null);
const hoveredRejectId = ref<number | null>(null);

/**
 * Show approve confirmation modal.
 *
 * @param recordId The ID of the lead to approve.
 */
function showApproveConfirm(recordId:number) {
  leadsStore.selectedLeads = [recordId];

  Modal.confirm({
    title: getI18n('approve_title'),
    icon: createVNode(QuestionCircleTwoTone),
    content: getI18n('approve_text'),
    okText: getI18n('ok'),
    cancelText: getI18n('cancel'),
    async onOk() {
      try {
        await leadsStore.processSelectedLeads('approve');
        // Clear selections after operation
        leadsStore.selectedLeads = [];
      } catch {
        return console.log('Oops errors!');
      }
    },
    onCancel() {},
  });
}

/**
 * Show reject confirmation modal.
 *
 * @param recordId The ID of the lead to reject.
 */
function showRejectConfirm(recordId:number) {
  leadsStore.selectedLeads = [recordId];

  Modal.confirm({
    title: getI18n('reject_title'),
    icon: createVNode(QuestionCircleTwoTone, {
      props: {
        twoToneColor: '#dedede'
      }
    }),
    content: getI18n('reject_text'),
    okText: getI18n('ok'),
    cancelText: getI18n('cancel'),
    async onOk() {
      try {
        await leadsStore.processSelectedLeads('reject');
        // Clear selections after operation
        leadsStore.selectedLeads = [];
      } catch {
        return console.log('Oops errors!');
      }
    },
    onCancel() {},
  });
}
</script>
<style lang="less" scoped>
:deep(.ant-table) {
  .ant-table-thead > tr > th,
  .ant-table-tbody > tr > td {
    text-align: center;
  }
}

.lead-row-actions {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;

  .action-icon {
    width: 1.3em;
    height: 1.3em;
    margin-right: 4px;
  }
}
</style>

