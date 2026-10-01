import { authService } from "./auth-service";

const API_BASE =
  process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api/v1";

export interface ProviderLog {
  id: number;
  uuid: string;
  action: string;
  status: string;
  response_code?: number | null;
  latency_ms?: number | null;
  sanitized_request?: Record<string, any> | null;
  sanitized_response?: Record<string, any> | null;
  error_message?: string | null;
  created_at: string;
}

export interface Provider {
  id: number;
  uuid: string;
  platform_id: number;
  name: string;
  channel: "EMAIL" | "SMS";
  driver: "fake_email" | "fake_sms" | "brevo" | "zenvia" | string;
  status: "ACTIVE" | "INACTIVE" | "ERROR";
  is_default: boolean;
  priority: number;
  is_fallback: boolean;
  rate_limit_per_minute: number;
  configuration?: Record<string, any> | null;
  credentials_configured: boolean;
  last_health_check_at?: string | null;
  health_status?: "HEALTHY" | "DEGRADED" | "DOWN" | "UNKNOWN" | string | null;
  created_at: string;
  updated_at: string;
  logs?: ProviderLog[];
}

export interface ProviderListParams {
  channel?: string;
  status?: string;
  driver?: string;
  search?: string;
  page?: number;
  per_page?: number;
}

export interface ProviderListResponse {
  data: Provider[];
  pagination: {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
  };
}

export interface HealthCheckResult {
  healthy: boolean;
  status: "HEALTHY" | "DEGRADED" | "DOWN";
  latency_ms: number;
  message: string;
  timestamp: string;
  details?: Record<string, any>;
}

export interface CreateProviderPayload {
  name: string;
  channel: "EMAIL" | "SMS";
  driver: string;
  status?: "ACTIVE" | "INACTIVE";
  is_default?: boolean;
  priority?: number;
  is_fallback?: boolean;
  rate_limit_per_minute?: number;
  configuration?: Record<string, any>;
  api_key?: string;
  api_token?: string;
  credentials?: Record<string, string>;
}

export interface UpdateProviderPayload {
  name?: string;
  status?: "ACTIVE" | "INACTIVE";
  is_default?: boolean;
  priority?: number;
  is_fallback?: boolean;
  rate_limit_per_minute?: number;
  configuration?: Record<string, any>;
  api_key?: string;
  api_token?: string;
  credentials?: Record<string, string>;
}

export interface TestProviderPayload {
  recipient: string;
  subject?: string;
  html_content?: string;
  sms_content?: string;
}

class ProviderService {
  private getHeaders(): Record<string, string> {
    const token = authService.getToken();
    const activePlatform = authService.getActivePlatform();

    return {
      "Content-Type": "application/json",
      Accept: "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(activePlatform?.id ? { "X-Platform-Id": String(activePlatform.id) } : {}),
    };
  }

  async list(params?: ProviderListParams): Promise<ProviderListResponse> {
    const query = new URLSearchParams();
    if (params?.channel && params.channel !== "ALL") query.append("channel", params.channel);
    if (params?.status && params.status !== "ALL") query.append("status", params.status);
    if (params?.driver && params.driver !== "ALL") query.append("driver", params.driver);
    if (params?.search) query.append("search", params.search);
    if (params?.page) query.append("page", String(params.page));
    if (params?.per_page) query.append("per_page", String(params.per_page));

    const response = await fetch(`${API_BASE}/providers?${query.toString()}`, {
      headers: this.getHeaders(),
    });

    if (!response.ok) {
      const err = await response.json().catch(() => ({}));
      throw new Error(err.message || "Falha ao carregar provedores.");
    }

    const json = await response.json();
    return {
      data: json.data || [],
      pagination: json.pagination || {
        total: json.meta?.total || (json.data || []).length,
        per_page: json.meta?.per_page || 15,
        current_page: json.meta?.current_page || 1,
        last_page: json.meta?.last_page || 1,
      },
    };
  }

  async get(id: number | string): Promise<Provider> {
    const response = await fetch(`${API_BASE}/providers/${id}`, {
      headers: this.getHeaders(),
    });

    if (!response.ok) {
      const err = await response.json().catch(() => ({}));
      throw new Error(err.message || "Provedor não encontrado.");
    }

    const json = await response.json();
    return json.data;
  }

  async create(payload: CreateProviderPayload): Promise<Provider> {
    const response = await fetch(`${API_BASE}/providers`, {
      method: "POST",
      headers: this.getHeaders(),
      body: JSON.stringify(payload),
    });

    if (!response.ok) {
      const err = await response.json().catch(() => ({}));
      throw new Error(err.message || "Erro ao salvar provedor.");
    }

    const json = await response.json();
    return json.data;
  }

  async update(id: number | string, payload: UpdateProviderPayload): Promise<Provider> {
    const response = await fetch(`${API_BASE}/providers/${id}`, {
      method: "PUT",
      headers: this.getHeaders(),
      body: JSON.stringify(payload),
    });

    if (!response.ok) {
      const err = await response.json().catch(() => ({}));
      throw new Error(err.message || "Erro ao atualizar provedor.");
    }

    const json = await response.json();
    return json.data;
  }

  async delete(id: number | string): Promise<void> {
    const response = await fetch(`${API_BASE}/providers/${id}`, {
      method: "DELETE",
      headers: this.getHeaders(),
    });

    if (!response.ok) {
      const err = await response.json().catch(() => ({}));
      throw new Error(err.message || "Erro ao excluir provedor.");
    }
  }

  async activate(id: number | string): Promise<Provider> {
    const response = await fetch(`${API_BASE}/providers/${id}/activate`, {
      method: "POST",
      headers: this.getHeaders(),
    });

    if (!response.ok) {
      const err = await response.json().catch(() => ({}));
      throw new Error(err.message || "Erro ao ativar provedor.");
    }

    const json = await response.json();
    return json.data;
  }

  async deactivate(id: number | string): Promise<Provider> {
    const response = await fetch(`${API_BASE}/providers/${id}/deactivate`, {
      method: "POST",
      headers: this.getHeaders(),
    });

    if (!response.ok) {
      const err = await response.json().catch(() => ({}));
      throw new Error(err.message || "Erro ao desativar provedor.");
    }

    const json = await response.json();
    return json.data;
  }

  async healthCheck(id: number | string): Promise<HealthCheckResult> {
    const response = await fetch(`${API_BASE}/providers/${id}/health`, {
      headers: this.getHeaders(),
    });

    if (!response.ok) {
      const err = await response.json().catch(() => ({}));
      throw new Error(err.message || "Falha na verificação de conectividade.");
    }

    const json = await response.json();
    return json.data;
  }

  async testSend(id: number | string, payload: TestProviderPayload): Promise<any> {
    const response = await fetch(`${API_BASE}/providers/${id}/test`, {
      method: "POST",
      headers: this.getHeaders(),
      body: JSON.stringify(payload),
    });

    const json = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(json.message || "Falha ao enviar mensagem de teste.");
    }

    return json;
  }
}

export const providerService = new ProviderService();
