import { defineStore } from 'pinia';
import axiosClient from '../utils/axiosClient';
import { handleError, handleResponse } from '../utils/utils';
import { useLeadsStore } from './leads';
import { Lead } from '../utils/interface';

export const useLeadStore = defineStore('lead', {
  state: () => ({
    isLoading: false,
    isSending: false,
    message: null,
    hasResult: false,
    lead: null,
    error: null,
  }),
  actions: {
    /**
     * Get a lead from the database
     *
     * @param number leadId The lead's ID.
     */
    async getLead(leadId: number) {
      this.isLoading = true;
      try {
        const response = await axiosClient.get(`/leads/${leadId}/`);

        if ( ! response.data.error ) {
          this.lead = handleResponse(response, this);
        } else {
          handleError(response.data, this);
        }
      } catch (error) {
        handleError(error, this);
      } finally {
        this.isLoading = false;
      }
    },

    /**
     * Update a lead via the API.
     *
     * @param lead The lead to update.
     */
    async updateLead(lead: Lead) {
      const user_id = lead['ID'] ?? lead['user_id'];
      this.isSending = true;

      try {
        const response = await axiosClient.patch(`/leads/${user_id}/`, lead);
        if (!response.data.error) {
          this.lead = response.data.data;
          this.message = response.data.message;
          this.hasResult = true;

          // Update lead in leads store
          const leadsStore = useLeadsStore();
          const index = leadsStore.leads.findIndex((l: Lead) => l.ID === user_id);
          if (index !== -1 && this.lead) {
            leadsStore.leads[index] = this.lead as Lead;
          }
        } else {
          this.error = response.data.message;
          this.hasResult = false;
        }
      } catch (error: any) {
        this.error = error.data?.message || 'An error occurred';
        this.hasResult = false;
      } finally {
        this.isSending = false;
      }
    },
  },
});