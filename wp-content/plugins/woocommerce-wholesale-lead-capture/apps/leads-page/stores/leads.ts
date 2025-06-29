import { defineStore } from "pinia";
import axiosClient from "../utils/axiosClient";

import { getI18n, handleError, handleResponse } from "../utils/utils";
import {
  LeadsResponse,
  Lead,
  LeadsParams,
  LeadsStatistics,
} from "../utils/interface";
import { Key } from "../utils/types";
import { message } from "ant-design-vue";
import { useRouter } from "vue-router";

const leadsPerPage = (window as any).wwlc_lap.leads_per_page;

export const useLeadsStore = defineStore("leads", {
  state: () => ({
    isLoading: true,
    isProcessing: false,
    hasResults: false,
    pagination: {
      page: 1,
      pageSize: leadsPerPage,
      total: 0,
    },
    params: {
      number: leadsPerPage,
      page: 1,
      offset: 0,
      status: "all",
      roles: "all",
      search: "",
    },
    leads: [] as Lead[],
    statistics: {
      all: 0,
      pending: 0,
      approved: 0,
      rejected: 0,
    } as LeadsStatistics,
    error: null,
    message: "",
    selectedLeads: <Key[]>[],
    allLeads: <Lead[]>[],
    columns: [
      {
        title: getI18n("name"),
        dataIndex: "name",
        key: "name",
      },
      {
        title: getI18n("email_address"),
        dataIndex: "wwlc_email",
        key: "wwlc_email",
      },
      {
        title: getI18n("role"),
        dataIndex: "role",
        key: "role",
      },
      {
        title: getI18n("registration_date"),
        key: "registered",
        dataIndex: "registered",
      },
      {
        title: getI18n("status"),
        key: "status",
        dataIndex: "status",
      },
      {
        title: getI18n("action"),
        key: "action",
      },
    ],
  }),

  actions: {
    /**
     * Fetch all leads from the database.
     *
     * @param page The page number to fetch.
     * @param pageSize The number of leads to fetch per page.
     *
     * @return Lead[]
     */
    async fetchLeads(page = 1, pageSize = leadsPerPage) {
      this.isLoading = true;
      this.params.page = page;
      this.params.number = pageSize;
      this.params.offset = (page - 1) * pageSize;

      try {
        const response = await axiosClient.get("/leads/list", {
          params: this.params,
        });
        const responseData = handleResponse(response, this);
        if (this.hasResults && responseData) {
          const data = responseData as LeadsResponse;
          this.leads = data.leads;

          this.statistics = data.statistics as LeadsStatistics;
          this.pagination = {
            page: data.page,
            pageSize: data.pageSize,
            total: data.total,
          };
        }
      } catch (error) {
        handleError(error, this);
      } finally {
        this.isLoading = false;
      }
    },

    /**
     * Set the filter for the leads.
     *
     * @param status the user status to filter by.
     */
    setFilter(status: string) {
      this.params.status = status;
      this.params.roles = "all";
      this.params.page = 1;
      this.params.number = this.pagination.pageSize;
      this.pagination.pageSize = this.pagination.pageSize;
      this.fetchLeads(1, this.pagination.pageSize);
    },

    /**
     * Filter the leads based on selected status, role, or both if they are not empty.
     *
     * @param status the user status to filter by.
     * @param role the user's role.
     */
    async searchFilterLeads(status: string, role: string) {
      this.params.status = status;
      this.params.roles = role;
      this.params.page = 1;
      this.params.number = this.pagination.pageSize;

      console.log(
        "Search filter leads: ",
        this.params.roles,
        this.params.status
      );

      try {
        this.isLoading = true;

        const response = await axiosClient.get("/leads/filter", {
          params: this.params,
        });

        const responseData = handleResponse(response, this);

        if (this.hasResults && responseData) {
          const data = responseData as LeadsResponse;
          this.leads = data.leads;
          //this.statistics = data.statistics;
          this.pagination = {
            page: data.page,
            pageSize: data.pageSize,
            total: data.total,
          };
        } else {
          this.hasResults = false;
          this.error = response.data.message;
          message.error(this.error);
        }
      } catch (error) {
        handleError(error, this);
      } finally {
        this.isLoading = false;
      }
    },

    /**
     * Search for leads by name or email.
     *
     * @param string query The search string.
     * @returns []
     */
    async searchLeads(query: string) {
      if (!query.trim()) {
        // Reset to initial state and fetch leads
        this.params.search = "";
        return this.fetchLeads(1, this.pagination.pageSize);
      }

      this.params.search = query;
      try {
        this.isLoading = true;
        const response = await axiosClient.get("/leads/search", {
          params: this.params,
        });
        const responseData = handleResponse(response, this);

        if (this.hasResults && responseData) {
          const data = responseData as LeadsResponse;
          this.leads = data.leads;
          this.pagination = {
            page: data.page,
            pageSize: data.pageSize,
            total: data.total,
          };
        } else {
          this.error = response.data.data.message;
          message.error(this.error);
        }
      } catch (error) {
        handleError(error, this);
      } finally {
        this.isLoading = false;
      }
    },

    /**
     * Bulk process leads.
     *
     * @param action The action to perform on the selected leads. approve,activate,reject,deactivate.
     */
    async processSelectedLeads(action: string) {
      this.isProcessing = true;

      try {
        const response = await axiosClient.post("/leads/process", {
          leads: this.selectedLeads,
          action: action,
        });

        if (!response.data.error) {
          this.selectedLeads = [];
          this.message = response.data.message;

          // Get processed leads data.
          const processedLeads = response.data.data.processed;

          // Create a reactive copy of the leads array.
          const updatedLeads = [...this.leads];

          // Loop through processed leads and update the corresponding leads.
          Object.keys(processedLeads).forEach((userId) => {
            const processedLead = processedLeads[userId];
            const index = updatedLeads.findIndex(
              (lead) => lead.ID === processedLead.ID
            );

            if (index !== -1) {
              // Replace the lead with the processed one.
              updatedLeads[index] = processedLead;
            }
          });

          // Replace the entire leads array.
          this.leads = updatedLeads;

          // Refresh the statistics if needed.
          if (["approve", "reject", "deactivate"].includes(action)) {
            await this.fetchLeads(
              this.pagination.page,
              this.pagination.pageSize
            );
          }

          this.hasResults = true;
        } else {
          this.error = response.data.message;
          message.error(this.error);
        }
      } catch (error) {
        handleError(error, this);
      } finally {
        this.isProcessing = false;
      }
    },

    /**
     * Filter the leads based on selected status, role, or both if they are not empty.
     *
     * @param status the user status to filter by.
     * @param role the user's role.
     */
    filterLeads(status: string, role: string) {
      if (this.allLeads.length === 0) {
        this.allLeads = this.leads;
      }
      this.leads = this.allLeads;

      const newLeads = this.leads.filter((lead) => {
        const matchesStatus = status ? lead.status === status : false;
        const matchesRole = role ? lead.role === role : false;
        return matchesStatus || matchesRole;
      });

      this.leads = newLeads;
      this.hasResults = newLeads.length > 0;
    },

    /**
     * Reset all filters and return to default state
     */
    async resetFilters() {
      // Reset all filter parameters to defaults
      this.params = {
        number: leadsPerPage,
        page: 1,
        offset: 0,
        status: "all",
        roles: "all",
        search: "",
      };

      // Update router query if using vue-router
      const router = useRouter();
      if (router) {
        try {
          await router.replace({
            query: {
              page: "1",
              pageSize: leadsPerPage.toString(),
            },
          });
        } catch (error) {
          //console.error('Navigation error:', error);
        }
      }

      // Fetch leads with reset parameters
      await this.fetchLeads(1, leadsPerPage);
    },
  },
});
