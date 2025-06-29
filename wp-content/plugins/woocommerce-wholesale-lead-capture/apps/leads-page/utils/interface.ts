export interface QueryResponse {
  leads: any[];
  total: number;
  page: number;
  pageSize: number;
}

export interface LeadsResponse {
  leads: any[],
  statistics: LeadsStatistics,
  total: number,
  page: number,
  pageSize: number
}

export interface Lead {
  ID: number;
  user_id: number;
  name: string;
  wwlc_email: string;
  role: string;
  status: string;
  registered: string;
}


export interface LeadsParams {
  page: number;
  pageSize: number;
  status: string;
  roles: string;
}

export interface LeadsStatistics {
  all: number;
  pending: number;
  approved: number;
  rejected: number;
}