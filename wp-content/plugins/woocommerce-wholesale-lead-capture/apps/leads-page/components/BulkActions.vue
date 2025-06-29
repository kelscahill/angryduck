<template>
  <div class="bulk-actions-filters">
    <div id="bulk-actions-and-filters">
        <div id="bulk-actions">
            <Dropdown :trigger="['click']" id="bulk-actions-dropdown">
                <template #overlay>
                    <Menu @click="handleBulkActionChanged">
                        <MenuItem key="approve">
                            {{ getI18n('approve') }}
                        </MenuItem>
                        <MenuItem key="reject">
                            {{getI18n('reject') }}
                        </MenuItem>
                        <MenuItem key="deactivate">
                            {{ getI18n('deactivate') }}
                        </MenuItem>
                    </Menu>
                </template>
                <Button size="large" id="bulk-actions-trigger">
                    {{ getBulkActionsText() }}
                    <DownOutlined />
                </Button>
            </Dropdown>
            <Button
                type="primary" id="apply-button"
                :disabled="bulkAction == ''"
                size="large"
                @click="applyBulkActions"
                :loading="processing">
                    {{ processing ? getI18n('processing') : getI18n('apply_action') }}
            </Button>
        </div>

        <div id="filters">
            <Dropdown :trigger="['click']">
                <template #overlay>
                    <Menu @click="handleStatusFilterChanged">
                        <MenuItem key="approved">{{ getI18n('approved') }}</MenuItem>
                        <MenuItem key="pending">{{ getI18n('pending') }}</MenuItem>
                        <MenuItem key="rejected">{{ getI18n('rejected')}}</MenuItem>
                        <MenuItem key="all">{{ getI18n('all_statuses')}}</MenuItem>
                    </Menu>
                </template>
                <Button id="status-filter-trigger" size="large">
                    {{ getStatusButtonText() }}
                    <DownOutlined />
                </Button>
            </Dropdown>

            <Dropdown :trigger="['click']">
                <template #overlay>
                    <Menu @click="handleRoleFilterChanged">
                        <MenuItem v-for="(roleName, roleKey ) in userRoles()" :key="roleKey">
                            {{ roleName }}
                        </MenuItem>
                        <MenuItem key="all">{{ getI18n('all_roles')}}</MenuItem>
                    </Menu>
                </template>
                <Button id="role-filter-trigger" size="large" class="filter-button">
                    <span>{{ getRoleFilterText() }}</span>
                    <DownOutlined />
                </Button>
            </Dropdown>

            <Button id="apply-filters-button"  type="primary" size="large" @click="applyFilters" :disabled="role == '' && status == ''">
                {{ getI18n('filter') }}
            </Button>

            <Popover trigger="hover">
                <template #content><p>Clear all filters</p></template>
                <Button type="text" size="large" :disabled="buttonDisabled" @click="clearFilters">Clear</Button>
            </Popover>
        </div>
    </div>

    <div id="search-form">
        <SearchForm />
    </div>
  </div>
</template>
<script lang="ts" setup>
import {computed, ref} from 'vue';
import { getI18n, userRoles } from "../utils/utils";
import { DownOutlined } from '@ant-design/icons-vue';
import type { MenuProps, SelectProps } from 'ant-design-vue';
import { message } from 'ant-design-vue';
import { Button, Menu, MenuItem, Dropdown, Popover } from 'ant-design-vue';
import SearchForm from "./SearchForm.vue";
import { useLeadsStore } from '../stores/leads';
const leadsStore = useLeadsStore();


const status = ref('');
const bulkAction = ref('');
const role = ref('');
const hasFilter = ref(false);

message.config({
  top: '100px',
  duration: 5,
  maxCount: 3,
  rtl: true,
  prefixCls: 'wwlc-message',
});

const buttonDisabled = computed(() => !hasFilter.value)
const processing = computed(() => leadsStore.isProcessing);

const getBulkActionsText = () => {
    return bulkAction.value != '' ? getI18n(bulkAction.value) : getI18n('bulk_actions');
}

const getStatusButtonText = () => {
    return status.value != '' ? getI18n(status.value) : getI18n('status');
}

const getRoleFilterText = () => {
    return role.value != '' ? getI18n(role.value) : getI18n('role');
}

const handleBulkActionChanged: MenuProps['onClick'] = e => {
  bulkAction.value = e.key as string;
  hasFilter.value = true;
};

const handleStatusFilterChanged: MenuProps['onClick'] = e => {
    status.value = e.key as string;
    hasFilter.value = true;
    leadsStore.params.page = 1;
    leadsStore.pagination.page = 1;
};

const handleRoleFilterChanged: MenuProps['onClick'] = e => {
    role.value = e.key as string;
    hasFilter.value = true;
};

const applyFilters = () => {
    hasFilter.value = true;
    leadsStore.searchFilterLeads(status.value, role.value);
}

/**
 * Clear the selected filters and bulk action.
 */
const clearFilters = () => {
    hasFilter.value = false;

    status.value = '';
    role.value = '';
    bulkAction.value = '';

    leadsStore.params.page = 1;
    leadsStore.pagination.page = 1;
    leadsStore.params.status = 'all';
    leadsStore.params.roles = 'all';
    leadsStore.params.search = '';

    leadsStore.fetchLeads();
}

/**
 * Process bulk actions.
 *
 * Get selected items and selected actions and send request using the Leads store
 *
 * @return void
 */
const applyBulkActions = async () => {

    if ( bulkAction.value == '') {
        message.error( getI18n('select_action_text') );
        return;
    }

    if ( leadsStore.selectedLeads.length < 1) {
        message.error( getI18n('select_rows_text' ) )
        return;
    }

    try {
        await leadsStore.processSelectedLeads( bulkAction.value );

        if ( leadsStore.hasResults && leadsStore.message !== '' ) {
            // Clear selected leads after successful operation
            leadsStore.selectedLeads = [];
            message.success(leadsStore.message)
        } else {
            message.error(leadsStore.error);
        }
    } catch (error: any) {
        message.error(error.message);
    }
}

</script>
<style lang="less" scoped>
.bulk-actions-filters {
  display: flex;
  flex-direction: row;
  margin-bottom: 24px;
  justify-content: space-between;

  #bulk-actions-and-filters {
    display: flex;
    flex-direction: row;
    gap: 45px;
  }

  #filters {
    display: flex;
    justify-content: space-around;
  }

  #bulk-actions {
    display: flex;
    flex-direction: row;
    gap: 8px;

    #bulk-actions-trigger {
      width: 170px;
    }

    #apply-button {
      padding: 15px;
    }
  }

  button {
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    align-content: center;
    align-items: center;
    margin-right: 6px;

    &.filter-button,
    &#status-filter-trigger {
      width: 130px;
    }

    &#role-filter-trigger {
      width: 200px;
    }
  }
}
</style>
