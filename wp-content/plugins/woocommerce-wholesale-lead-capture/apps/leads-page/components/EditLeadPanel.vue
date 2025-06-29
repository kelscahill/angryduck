<template>

    <div>
        <Popover :title="getI18n('edit_popup_title')">
            <template #content>
                <p>{{ getI18n('edit_popup_text') }}</p>
            </template>
            <EditTwoTone
                class="action-icon"
                :style="{ fontSize: '18.75px' }"
                @click="editLead"
                :two-tone-color="hoveredEditId === props.leadId ? '#0F62FE' : '#8c8c8c'"
                @mouseenter="hoveredEditId = props.leadId"
                @mouseleave="hoveredEditId = null"
            />
        </Popover>

        <Drawer
            :width="720"
            :open="open"
            :closable="false"
            :rootStyle="{ marginTop: '24px'}"
            :body-style="{ paddingBottom: '80px', marginTop: '24px' }"
            :footer-style="{ textAlign: 'right' }"
            @close="onClose"
            @keydown.esc="onClose"
        >
            <template #title>
                <div id="title">
                    <div class="wwlc-lead-drawer-header">
                        <div class="header-title">
                            <div id="drawer-title-icon">
                                <EditTwoTone />
                            </div>
                            <h3>{{ getI18n('edit_user') }}</h3>
                            <div><Tag :color="getUserStatusColor(lead.status, lead.role)">{{ lead.status }}</Tag></div>
                            <div id="drawer-lead-actions">
                                <Button v-if="lead.status === 'pending' || lead.status === 'rejected'"
                                    id="approve-lead-action"
                                    type="primary"
                                    @click="approveUserConfirm"
                                    :loading="isApproving"
                                    >
                                    <template #icon>
                                        <CheckSquareOutlined />
                                    </template>
                                    {{ getI18n('approve') }}
                                </Button>

                                <Button v-if="lead.status === 'pending'" id="reject-lead-action" @click="rejectUserConfirm" danger :loading="isRejecting">
                                    <template #icon>
                                        <CloseSquareOutlined />
                                    </template>
                                    {{ getI18n('reject') }}
                                </Button>

                                <Button
                                    v-if="lead.status === 'approved' || lead.status === 'rejected'"
                                    id="deactivate-lead-action"
                                    type="text"
                                    @click="deactivateUserConfirm"
                                    :loading="isDeactivating"
                                    danger>
                                    <template #icon>
                                        <UserSwitchOutlined />
                                    </template>
                                    {{ getI18n('deactivate') }}
                                </Button>
                            </div>
                        </div>
                        <span><CloseOutlined class="close-button" @click="onClose"/></span>
                    </div>

                </div>
            </template>

            <Spin :spinning="isLoading" size="large">
                <Form v-if="lead !== null && ! isLoading" :model="lead" layout="vertical" ref="formRef">
                    <Row :gutter="16">
                        <Col :span="12">
                            <FormItem :label="getI18n('status')" name="status">
                                <Select v-model:value="lead.status" :readonly="readonly" @change="updateLeadStatus" class="wwlc-ant-select" size="large">
                                    <SelectOption key="approved" value="approved">{{ getI18n('approved') }}</SelectOption>
                                    <SelectOption key="pending" value="pending">{{ getI18n('pending') }}</SelectOption>
                                    <SelectOption key="inactive" value="inactive">{{ getI18n('inactive')}}</SelectOption>
                                    <SelectOption key="rejected" value="rejected">{{ getI18n('rejected')}}</SelectOption>
                                </Select>
                            </FormItem>
                        </Col>
                        <Col :span="12">
                            <FormItem :label="getI18n('role')" name="role">
                                <Select v-model:value="lead.role" :readonly="readonly" class="wwlc-ant-select" size="large" @change="updateLeadRole">
                                    <SelectOption v-for="(roleName, roleKey ) in userRoles()" :key="roleKey" :value="roleKey">
                                        {{ roleName }}
                                    </SelectOption>
                                </Select>
                            </FormItem>
                        </Col>
                    </Row>

                    <Row :gutter="16">
                        <Col :span="12">
                            <FormItem :label="getI18n('first_name')" name="first_name">
                                <Input v-model:value="lead.first_name" :placeholder="getI18n('first_name')" :readonly="readonly" />
                            </FormItem>
                        </Col>
                        <Col :span="12">
                            <FormItem :label="getI18n('last_name')" :placeholder="getI18n('last_name')" name="last_name">
                                <Input v-model:value="lead.last_name" :placeholder="getI18n('last_name')" :readonly="readonly" />
                            </FormItem>
                        </Col>
                    </Row>

                    <Row :gutter="16">
                        <Col :span="12">
                            <FormItem :label="getI18n('email_address')" name="email_address">
                                <Input v-model:value="lead.wwlc_email" :placeholder="getI18n('email_addrress')" :readonly="readonly" />
                            </FormItem>
                        </Col>
                        <Col :span="12">
                            <FormItem :label="getI18n('website_url')" name="user_url">
                                <Input v-model:value="lead.user_url" :placeholder="getI18n('website_url')" :readonly="readonly" />
                            </FormItem>
                        </Col>
                    </Row>

                    <div v-if="isAddressEnabled()">
                        <TypographyTitle :level="3">{{ getI18n('billing_address' ) }}</TypographyTitle>
                        <LeadAddressForm
                            :address_one="lead.wwlc_address"
                            :address_two="lead.wwlc_address_2"
                            :country="lead.wwlc_country"
                            :state="lead.wwlc_state"
                            :city="lead.wwlc_city"
                            :postcode="lead.wwlc_postcode"
                            :phone="lead.wwlc_phone"
                            :company="lead.wwlc_company_name"
                            :readonly="readonly"
                            @update:address_one="lead.wwlc_address = $event"
                            @update:address_two="lead.wwlc_address_2 = $event"
                            @update:country="lead.wwlc_country = $event"
                            @update:state="lead.wwlc_state = $event"
                            @update:city="lead.wwlc_city = $event"
                            @update:postcode="lead.wwlc_postcode = $event"
                            @update:phone="lead.wwlc_phone = $event"
                            @update:company="lead.wwlc_company_name = $event"
                        />

                        <Divider />

                        <TypographyTitle :level="3">{{ getI18n('shipping_address' ) }}</TypographyTitle>
                        <Checkbox :v-model="sameAsBilling" @click="toggleSameAsBilling" :checked="sameAsBilling">{{ getI18n('same_as_billing') }}</Checkbox>
                        <LeadAddressForm
                            v-if="!sameAsBilling"
                            :address_one="sameAsBilling ? lead.wwlc_address : lead.shipping_address.address_1"
                            :address_two="sameAsBilling ? lead.wwlc_address_2 : lead.shipping_address.address_2"
                            :country="sameAsBilling ? lead.wwlc_country : lead.shipping_address.country"
                            :state="sameAsBilling ? lead.wwlc_state : lead.shipping_address.state"
                            :city="sameAsBilling ? lead.wwlc_city : lead.shipping_address.city"
                            :postcode="sameAsBilling ? lead.wwlc_postcode : lead.shipping_address.postcode"
                            :phone="sameAsBilling ? lead.wwlc_phone : lead.shipping_address.phone"
                            :company="sameAsBilling ? lead.wwlc_company_name : lead.shipping_address.company"
                            :readonly="readonly"
                            @update:address_one="lead.shipping_address.address = $event"
                            @update:address_two="lead.shipping_address.address_2 = $event"
                            @update:country="lead.shipping_address.country = $event"
                            @update:state="lead.shipping_address.state = $event"
                            @update:city="lead.shipping_address.city = $event"
                            @update:postcode="lead.shipping_address.postcode = $event"
                            @update:phone="lead.shipping_address.phone = $event"
                            @update:company="lead.shipping_address.company = $event"
                        />
                    </div>

                    <div id="custom_fields_container" v-if="hasCustomFields">
                        <TypographyTitle :level="3">{{ getI18n('custom_fields' ) }}</TypographyTitle>
                        <FormItem v-for="(customField, fieldKey) in customFields" :key="fieldKey" :label="customField.label" :name="customField.name">
                            <CustomField :field="customField" :value="lead[customField.name]" @update:value="updateCustomField(customField.name, $event)" />
                        </FormItem>
                    </div>

                    <Divider />

                    <Row :gutter="16">
                        <Col :span="12">
                            <Button
                            type="primary"
                            size="large"
                            :loading="isSending"
                            :disabled="!hasChanges"
                            @click="updateLead"
                            >
                                {{ isSending ? getI18n('loading') : getI18n('save_user') }}
                            </Button>
                        </Col>
                        <Col :span="12">
                            <Button type="default" size="large" @click="onClose">{{ getI18n('cancel') }}</Button>
                        </Col>
                    </Row>
                </Form>
            </Spin>

        </Drawer>
    </div>
</template>
<script lang="ts" setup>
import { reactive, ref, computed, defineProps, onMounted, watch } from 'vue';
import { Input, Form, Row, Col, Select, SelectOption, Spin, Drawer, Divider, TypographyTitle, Popover, Button, Checkbox, FormItem, Tag } from 'ant-design-vue';
import type { SelectValue } from 'ant-design-vue/es/select';
import { EditTwoTone, CloseOutlined, UserSwitchOutlined, QuestionCircleTwoTone, CheckSquareOutlined, CloseSquareOutlined } from '@ant-design/icons-vue';
import { getI18n, userRoles, getUserStatusColor } from "../utils/utils";
import { FormMode } from "../utils/enums";
import CustomField from './CustomField.vue';
import { useLeadsStore } from '../stores/leads';
import LeadAddressForm from './LeadAddressForm.vue';
import { createVNode } from 'vue';
import { Modal, message } from 'ant-design-vue';

const leadsStore = useLeadsStore();


import { useLeadStore } from "../stores/lead";
const leadStore = useLeadStore();

const props = defineProps({
  leadId: {
    type: Number,
    required: true,
  },
});

const customFields = (window as any).wwlc_lap.custom_fields || {};

const open = ref<boolean>(false);
const sameAsBilling = ref<boolean>(true);

const isDeactivating = ref<boolean>(false);
const isApproving = ref<boolean>(false);
const isRejecting = ref<boolean>(false);
const hoveredEditId = ref<number | null>(null);

const isAddressEnabled = () => {
    const addressEnabled = (window as any).wwlc_lap?.address_enabled;

    if ( addressEnabled === 'yes' ) {
        return true;
    }

    return false;
}

const toggleSameAsBilling = () => {
  sameAsBilling.value = !sameAsBilling.value;
};

const formMode = ref<FormMode>(FormMode.CREATE);
const readonly = computed(() => formMode.value === FormMode.READONLY);

const isLoading = computed(() => leadStore.isLoading)
const isSending = computed( ()=> leadStore.isSending)
const hasCustomFields = computed(() => Object.keys(customFields).length > 0);
const hasChanges = computed(() => {
  if (!Object.keys(originalLead.value).length) return false;

  return Object.keys(lead).some(key => {
    if (typeof lead[key] === 'object' && lead[key] !== null) {
      return JSON.stringify(lead[key]) !== JSON.stringify((originalLead.value as Record<string, any>)[key]);
    }
    return lead[key] !== (originalLead.value as Record<string, any>)[key];
  });
});
const formRef = ref<HTMLFormElement | null>(null);

const lead:any = reactive({ ...leadStore.lead as any });

const originalLead = ref({});

const editLead = async () => {
    showDrawer();
    await leadStore.getLead(props.leadId);
    originalLead.value = JSON.parse(JSON.stringify(lead));
}

const showDrawer = () => {
    open.value = true;
};

const onClose = () => {
    Object.assign(lead, originalLead.value);
    open.value = false;
};

const updateLeadStatus = (value: SelectValue) => {
    lead.status = value as string;
};

const updateLeadRole = (value: SelectValue) => {
    lead.role = value as string;
};


watch(() => leadStore.lead, (newLead) => {
    if (newLead) {
        Object.assign(lead, newLead);
        originalLead.value = JSON.parse(JSON.stringify(newLead));
    }
}, { deep: true });

/**
 * Update lead
 *
 * Sends a request to update the lead and displays a message to the user.
 *
 * @since 2.0.0
 */
const updateLead = async () => {
    await leadStore.updateLead(lead);

    if (leadStore.hasResult) {
        message.success(leadStore.message);

        if (leadStore.lead) {
            Object.assign(lead, leadStore.lead);
            originalLead.value = JSON.parse(JSON.stringify(lead));
        }
    } else {
        message.error(leadStore.error);
    }
}

/**
 * Approve user confirm modal
 *
 * @since 2.0.0
 */
function approveUserConfirm() {
    leadsStore.selectedLeads = [props.leadId];
    isApproving.value = true;

    Modal.confirm({
        title: getI18n('approve_title'),
        icon: createVNode(QuestionCircleTwoTone),
        content: getI18n('approve_text'),
        okText: getI18n('ok'),
        cancelText: getI18n('cancel'),
        async onOk() {
            try {
                await leadsStore.processSelectedLeads('approve');
                const updatedLead = leadsStore.leads.find(lead => lead.ID === props.leadId);
                if (updatedLead) {
                    Object.assign(lead, updatedLead);
                }
                return true;
            } catch {
                return message.error('Oops errors!');
            } finally {
                isApproving.value = false;
            }
        },
        onCancel() {
            isApproving.value = false;
        },
    });
}

/**
 * Reject user confirm
 *
 * @since 2.0.0
 */
function rejectUserConfirm() {
    leadsStore.selectedLeads = [props.leadId];
    isRejecting.value = true;

    Modal.confirm({
        title: getI18n('reject_title'),
        icon: createVNode(QuestionCircleTwoTone),
        content: getI18n('reject_text'),
        okText: getI18n('ok'),
        cancelText: getI18n('cancel'),
        async onOk() {
            try {
                await leadsStore.processSelectedLeads('reject');
                const updatedLead = leadsStore.leads.find(lead => lead.ID === props.leadId);
                if (updatedLead) {
                    Object.assign(lead, updatedLead);
                }
                return true;
            } catch {
                return message.error('Oops errors!');
            } finally {
                isRejecting.value = false;
            }
        },
        onCancel() {
            isRejecting.value = false;
        },
    });
}

/**
 * Deactivate user confirm
 *
 * @since 2.0.0
 */
function deactivateUserConfirm() {
    leadsStore.selectedLeads = [props.leadId];
    isDeactivating.value = true;

    Modal.confirm({
        title: getI18n('deactivate_title'),
        icon: createVNode(QuestionCircleTwoTone),
        content: getI18n('deactivate_text'),
        okText: getI18n('ok'),
        cancelText: getI18n('cancel'),
        async onOk() {
            try {
                await leadsStore.processSelectedLeads('deactivate');
                const updatedLead = leadsStore.leads.find(lead => lead.ID === props.leadId);
                if (updatedLead) {
                    Object.assign(lead, updatedLead);
                }
                return true;
            } catch {
                return message.error('Oops errors!');
            } finally {
                isDeactivating.value = false;
            }
        },
        onCancel() {
            isDeactivating.value = false;
        },
    });
}

const updateCustomField = (fieldName: string, value: any) => {
    // Make Vue detect this change by directly setting the property
    if (typeof value === 'object' && value !== null) {
        // For objects, we need to create a new reference
        lead[fieldName] = { ...value };
    } else {
        lead[fieldName] = value;
    }
}
</script>
<style lang="less" scoped>
#title {
  .wwlc-lead-drawer-header {
    display: flex;
    align-content: center;
    justify-content: space-between;
    padding-top: 16px;

    .header-title {
      display: flex;
      align-content: center;
      justify-content: center;

      h3 {
        margin: 5px 0 0 15px;
      }

      .ant-tag {
        margin: 5px 0 0 15px;
        text-transform: capitalize;
        padding: 6px 12px;
        border-radius: 8px;
        border: 1px solid;
      }

      #drawer-title-icon {
        width: 40px;
        height: 40px;
        border-radius: 40px;
        background: #D4ECFF;
        border: 1px solid #F5FAFF;
        display: flex;
        align-content: center;
        align-items: center;
        justify-content: center;

        .ant-icon {
          width: 32px;
          height: 32px;
        }
      }
    }

    .close-button {
        cursor: pointer;
        align-content: center;
        align-items: center;
        justify-content: center;
    }
  }

  #drawer-lead-actions {
    display: flex;
    align-content: center;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-left: 1rem;
  }
}

.ant-input,
.ant-btn-lg {
  padding: 8px;
}

.ant-btn-lg {
  width: 100%;
}

.action-icon {
  width: 1.3em;
  height: 1.3em;
  margin-right: 4px;
  display: inline-block;
}

.wwlc-lead-drawer-header #drawer-lead-actions #deactivate-lead-action:hover {
  background-color: transparent;
}
</style>


