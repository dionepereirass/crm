import { authService } from "./auth-service";

const API_BASE =
  process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api/v1";

export interface PrivacyMetrics {
  total_players: number;
  anonymized_players: number;
  active_consents: number;
  revoked_consents: number;
  open_requests: number;
  in_progress_requests: number;
  completed_requests: number;
  near_sla_requests: number;
  expired_requests: number;
  active_retention_policies: number;
}

export interface ConsentRecord {
  id: number;
  player_id: number;
  platform_id: number;
  channel: string;
  type: string;
  status: 'GRANTED' | 'REVOKED';
  is_granted: boolean;
  consent_source?: string;
  consent_version?: string;
  consent_ip?: string;
  user_agent?: string;
  evidence?: Record<string, any>;
  evidence_hash?: string;
  granted_at?: string;
  revoked_at?: string;
  created_at: string;
  updated_at: string;
  player?: {
    id: number;
    name: string;
    email: string;
    cpf?: string;
  };
}

export interface ConsentHistoryRecord {
  id: number;
  consent_id: number;
  player_id: number;
  previous_status?: string;
  new_status: string;
  action: string;
  source?: string;
  version?: string;
  ip_address?: string;
  user_agent?: string;
  evidence_hash?: string;
  performed_at: string;
}

export interface DataSubjectRequest {
  id: number;
  platform_id: number;
  player_id: number;
  type: 'ACCESS' | 'CORRECTION' | 'PORTABILITY' | 'DELETION' | 'REVOCATION' | 'INFORMATION' | 'ANONYMIZATION';
  status: 'OPEN' | 'IN_PROGRESS' | 'COMPLETED' | 'REJECTED' | 'CANCELLED';
  requested_at: string;
  due_at?: string;
  completed_at?: string;
  requested_by?: string;
  assigned_to?: number;
  reason?: string;
  resolution?: string;
  metadata?: Record<string, any>;
  created_at: string;
  updated_at: string;
  player?: {
    id: number;
    name: string;
    email: string;
    cpf?: string;
  };
  assignedUser?: {
    id: number;
    name: string;
    email: string;
  };
}

export interface RetentionPolicyRecord {
  id: number;
  platform_id: number;
  data_category: string;
  retention_days: number;
  action: 'DELETE' | 'ANONYMIZE' | 'RETAIN';
  active: boolean;
  created_at: string;
  updated_at: string;
}

export interface AuditLogRecord {
  id: number;
  platform_id?: number;
  actor_type: string;
  actor_id?: string;
  user_id?: number;
  user?: {
    id: number;
    name: string;
    email: string;
  };
  action: string;
  resource_type?: string;
  resource_id?: string;
  old_values?: Record<string, any>;
  new_values?: Record<string, any>;
  ip_address?: string;
  user_agent?: string;
  request_id?: string;
  created_at: string;
}

class PrivacyService {
  private getHeaders(): HeadersInit {
    const token = typeof window !== 'undefined' ? localStorage.getItem('bet_crm_token') : null;
    const platformId = typeof window !== 'undefined' ? localStorage.getItem('bet_crm_platform_id') || '1' : '1';

    return {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Authorization': `Bearer ${token || ''}`,
      'X-Platform-Id': platformId,
    };
  }

  async getDashboard(): Promise<{ metrics: PrivacyMetrics; recent_requests: DataSubjectRequest[]; recent_audit: AuditLogRecord[]; categories: Record<string, any> }> {
    const res = await fetch(`${API_BASE}/privacy/dashboard`, {
      headers: this.getHeaders(),
    });
    if (!res.ok) throw new Error('Falha ao carregar dashboard de privacidade');
    return res.json();
  }

  async listConsents(params: { type?: string; status?: string; search?: string; page?: number } = {}): Promise<{ data: ConsentRecord[]; total: number; current_page: number; last_page: number }> {
    const query = new URLSearchParams();
    if (params.type && params.type !== 'ALL') query.append('type', params.type);
    if (params.status && params.status !== 'ALL') query.append('status', params.status);
    if (params.search) query.append('search', params.search);
    if (params.page) query.append('page', params.page.toString());

    const res = await fetch(`${API_BASE}/privacy/consents?${query.toString()}`, {
      headers: this.getHeaders(),
    });
    if (!res.ok) throw new Error('Falha ao listar consentimentos');
    return res.json();
  }

  async grantConsent(payload: { player_id: number; type: string; source?: string; version?: string }): Promise<{ data: ConsentRecord }> {
    const res = await fetch(`${API_BASE}/privacy/consents/grant`, {
      method: 'POST',
      headers: this.getHeaders(),
      body: JSON.stringify(payload),
    });
    if (!res.ok) throw new Error('Falha ao conceder consentimento');
    return res.json();
  }

  async revokeConsent(payload: { player_id: number; type: string; reason?: string }): Promise<{ data: ConsentRecord }> {
    const res = await fetch(`${API_BASE}/privacy/consents/revoke`, {
      method: 'POST',
      headers: this.getHeaders(),
      body: JSON.stringify(payload),
    });
    if (!res.ok) throw new Error('Falha ao revogar consentimento');
    return res.json();
  }

  async getConsentHistory(id: number): Promise<{ consent: ConsentRecord; history: ConsentHistoryRecord[] }> {
    const res = await fetch(`${API_BASE}/privacy/consents/${id}/history`, {
      headers: this.getHeaders(),
    });
    if (!res.ok) throw new Error('Falha ao carregar histórico de consentimento');
    return res.json();
  }

  async listRequests(params: { type?: string; status?: string; search?: string; page?: number } = {}): Promise<{ data: DataSubjectRequest[]; total: number; current_page: number; last_page: number }> {
    const query = new URLSearchParams();
    if (params.type && params.type !== 'ALL') query.append('type', params.type);
    if (params.status && params.status !== 'ALL') query.append('status', params.status);
    if (params.search) query.append('search', params.search);
    if (params.page) query.append('page', params.page.toString());

    const res = await fetch(`${API_BASE}/privacy/requests?${query.toString()}`, {
      headers: this.getHeaders(),
    });
    if (!res.ok) throw new Error('Falha ao listar solicitações de privacidade');
    return res.json();
  }

  async createRequest(payload: { player_id: number; type: string; reason?: string; requested_by?: string }): Promise<{ data: DataSubjectRequest }> {
    const res = await fetch(`${API_BASE}/privacy/requests`, {
      method: 'POST',
      headers: this.getHeaders(),
      body: JSON.stringify(payload),
    });
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || 'Falha ao criar solicitação de privacidade');
    }
    return res.json();
  }

  async getRequest(id: number): Promise<{ data: DataSubjectRequest }> {
    const res = await fetch(`${API_BASE}/privacy/requests/${id}`, {
      headers: this.getHeaders(),
    });
    if (!res.ok) throw new Error(`Falha ao obter solicitação #${id}`);
    return res.json();
  }

  async assignRequest(id: number, userId?: number): Promise<{ data: DataSubjectRequest }> {
    const res = await fetch(`${API_BASE}/privacy/requests/${id}/assign`, {
      method: 'POST',
      headers: this.getHeaders(),
      body: JSON.stringify({ user_id: userId }),
    });
    if (!res.ok) throw new Error(`Falha ao atribuir solicitação #${id}`);
    return res.json();
  }

  async processRequest(id: number): Promise<{ data: DataSubjectRequest }> {
    const res = await fetch(`${API_BASE}/privacy/requests/${id}/process`, {
      method: 'POST',
      headers: this.getHeaders(),
    });
    if (!res.ok) throw new Error(`Falha ao iniciar processamento da solicitação #${id}`);
    return res.json();
  }

  async completeRequest(id: number, resolution?: string): Promise<{ data: DataSubjectRequest }> {
    const res = await fetch(`${API_BASE}/privacy/requests/${id}/complete`, {
      method: 'POST',
      headers: this.getHeaders(),
      body: JSON.stringify({ resolution }),
    });
    if (!res.ok) throw new Error(`Falha ao concluir solicitação #${id}`);
    return res.json();
  }

  async rejectRequest(id: number, resolution: string): Promise<{ data: DataSubjectRequest }> {
    const res = await fetch(`${API_BASE}/privacy/requests/${id}/reject`, {
      method: 'POST',
      headers: this.getHeaders(),
      body: JSON.stringify({ resolution }),
    });
    if (!res.ok) throw new Error(`Falha ao rejeitar solicitação #${id}`);
    return res.json();
  }

  async cancelRequest(id: number, reason?: string): Promise<{ data: DataSubjectRequest }> {
    const res = await fetch(`${API_BASE}/privacy/requests/${id}/cancel`, {
      method: 'POST',
      headers: this.getHeaders(),
      body: JSON.stringify({ reason }),
    });
    if (!res.ok) throw new Error(`Falha ao cancelar solicitação #${id}`);
    return res.json();
  }

  async exportRequest(id: number): Promise<{ data: any }> {
    const res = await fetch(`${API_BASE}/privacy/requests/${id}/export`, {
      method: 'POST',
      headers: this.getHeaders(),
    });
    if (!res.ok) throw new Error(`Falha ao exportar dados da solicitação #${id}`);
    return res.json();
  }

  async exportPlayer(playerId: number): Promise<{ data: any }> {
    const res = await fetch(`${API_BASE}/privacy/players/${playerId}/export`, {
      headers: this.getHeaders(),
    });
    if (!res.ok) throw new Error(`Falha ao exportar dados do jogador #${playerId}`);
    return res.json();
  }

  async anonymizePlayer(playerId: number, confirmed: boolean, reason?: string): Promise<{ message: string }> {
    const res = await fetch(`${API_BASE}/privacy/players/${playerId}/anonymize`, {
      method: 'POST',
      headers: this.getHeaders(),
      body: JSON.stringify({ confirmed, reason }),
    });
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || `Falha ao anonimizar jogador #${playerId}`);
    }
    return res.json();
  }

  async listRetentionPolicies(): Promise<{ data: RetentionPolicyRecord[] }> {
    const res = await fetch(`${API_BASE}/privacy/retention`, {
      headers: this.getHeaders(),
    });
    if (!res.ok) throw new Error('Falha ao listar políticas de retenção');
    return res.json();
  }

  async saveRetentionPolicy(payload: { data_category: string; retention_days: number; action: string; active?: boolean }): Promise<{ data: RetentionPolicyRecord }> {
    const res = await fetch(`${API_BASE}/privacy/retention`, {
      method: 'POST',
      headers: this.getHeaders(),
      body: JSON.stringify(payload),
    });
    if (!res.ok) throw new Error('Falha ao salvar política de retenção');
    return res.json();
  }

  async processRetention(): Promise<{ summary: any }> {
    const res = await fetch(`${API_BASE}/privacy/retention/process`, {
      method: 'POST',
      headers: this.getHeaders(),
    });
    if (!res.ok) throw new Error('Falha ao executar políticas de retenção');
    return res.json();
  }

  async listAuditLogs(params: { action?: string; resource_type?: string; actor_id?: string; page?: number } = {}): Promise<{ data: AuditLogRecord[]; total: number; current_page: number; last_page: number }> {
    const query = new URLSearchParams();
    if (params.action) query.append('action', params.action);
    if (params.resource_type) query.append('resource_type', params.resource_type);
    if (params.actor_id) query.append('actor_id', params.actor_id);
    if (params.page) query.append('page', params.page.toString());

    const res = await fetch(`${API_BASE}/privacy/audit?${query.toString()}`, {
      headers: this.getHeaders(),
    });
    if (!res.ok) throw new Error('Falha ao listar logs de auditoria');
    return res.json();
  }
}

export const privacyService = new PrivacyService();
