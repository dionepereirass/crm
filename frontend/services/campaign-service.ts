import { authService } from "./auth-service";

const API_BASE =
  process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api/v1";

export interface Campaign {
  id: number;
  uuid: string;
  platform_id: number;
  name: string;
  description?: string | null;
  channel: "EMAIL" | "SMS";
  status:
    | "DRAFT"
    | "VALIDATING"
    | "READY"
    | "SCHEDULED"
    | "PROCESSING"
    | "PAUSED"
    | "COMPLETED"
    | "CANCELLED"
    | "FAILED";
  segment_id: number;
  template_id: number;
  template_version_id: number;
  provider_id?: number | null;
  scheduled_at?: string | null;
  from_name?: string | null;
  from_email?: string | null;
  total_recipients: number;
  messages_created: number;
  messages_sent: number;
  messages_delivered: number;
  messages_failed: number;
  progress_percentage: number;
  started_at?: string | null;
  completed_at?: string | null;
  paused_at?: string | null;
  cancelled_at?: string | null;
  created_at: string;
  updated_at: string;
  segment?: {
    id: number;
    name: string;
    slug: string;
  } | null;
  template?: {
    id: number;
    name: string;
    channel: string;
  } | null;
  template_version?: {
    id: number;
    version: number;
    status: string;
  } | null;
  provider?: {
    id: number;
    name: string;
    driver: string;
  } | null;
  creator?: {
    id: number;
    name: string;
    email: string;
  } | null;
  updater?: {
    id: number;
    name: string;
    email: string;
  } | null;
}

export interface CampaignRecipient {
  id: number;
  uuid: string;
  campaign_id: number;
  player_id: number;
  channel: "EMAIL" | "SMS";
  recipient: string;
  status: "PENDING" | "QUEUED" | "SENT" | "DELIVERED" | "FAILED" | "SKIPPED";
  reason?: string | null;
  created_at: string;
  updated_at: string;
  player?: {
    id: number;
    uuid: string;
    name: string;
    email?: string | null;
    phone?: string | null;
  } | null;
}

export interface CampaignStats {
  campaign_id: number;
  status: string;
  total_recipients: number;
  messages_created: number;
  messages_sent: number;
  messages_delivered: number;
  messages_failed: number;
  progress_percentage: number;
  delivery_rate_percentage: number;
  failure_rate_percentage: number;
  duration_seconds?: number | null;
}

export interface CampaignValidationResult {
  is_valid: boolean;
  errors: string[];
  audience_summary?: {
    total_segment: number;
    valid_contact: number;
    with_consent: number;
    blocked: number;
    eligible: number;
    sample: any[];
  } | null;
}

export interface CampaignPreviewResult {
  campaign_id: number;
  channel: string;
  subject?: string | null;
  html?: string | null;
  text?: string | null;
  preview_player?: {
    id: number;
    name: string;
    email?: string | null;
    phone?: string | null;
  } | null;
}

export interface CampaignListParams {
  channel?: string;
  status?: string;
  search?: string;
  page?: number;
  per_page?: number;
}

export interface CampaignListResponse {
  data: Campaign[];
  pagination: {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
  };
}

export interface CampaignFormData {
  name: string;
  description?: string;
  channel: "EMAIL" | "SMS";
  segment_id: number;
  template_id: number;
  template_version_id: number;
  provider_id?: number | null;
  scheduled_at?: string | null;
  from_name?: string | null;
  from_email?: string | null;
}

class CampaignService {
  private getHeaders(extraHeaders: Record<string, string> = {}): HeadersInit {
    const token = authService.getToken();
    const platform = authService.getActivePlatform();

    const headers: Record<string, string> = {
      "Content-Type": "application/json",
      Accept: "application/json",
      ...extraHeaders,
    };

    if (token) {
      headers["Authorization"] = `Bearer ${token}`;
    }

    if (platform?.id) {
      headers["X-Platform-Id"] = platform.id.toString();
    }

    return headers;
  }

  async list(params: CampaignListParams = {}): Promise<CampaignListResponse> {
    const query = new URLSearchParams();
    if (params.channel && params.channel !== "ALL") query.append("channel", params.channel);
    if (params.status && params.status !== "ALL") query.append("status", params.status);
    if (params.search) query.append("search", params.search);
    if (params.page) query.append("page", params.page.toString());
    if (params.per_page) query.append("per_page", params.per_page.toString());

    const res = await fetch(`${API_BASE}/campaigns?${query.toString()}`, {
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      throw new Error(`Erro ao listar campanhas: ${res.statusText}`);
    }

    return res.json();
  }

  async getById(id: number | string): Promise<Campaign> {
    const res = await fetch(`${API_BASE}/campaigns/${id}`, {
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      throw new Error(`Erro ao buscar campanha #${id}: ${res.statusText}`);
    }

    const json = await res.json();
    return json.data;
  }

  async create(data: CampaignFormData): Promise<Campaign> {
    const res = await fetch(`${API_BASE}/campaigns`, {
      method: "POST",
      headers: this.getHeaders(),
      body: JSON.stringify(data),
    });

    const json = await res.json();
    if (!res.ok) {
      throw new Error(json.message || "Erro ao criar campanha.");
    }

    return json.data;
  }

  async update(id: number | string, data: Partial<CampaignFormData>): Promise<Campaign> {
    const res = await fetch(`${API_BASE}/campaigns/${id}`, {
      method: "PUT",
      headers: this.getHeaders(),
      body: JSON.stringify(data),
    });

    const json = await res.json();
    if (!res.ok) {
      throw new Error(json.message || "Erro ao atualizar campanha.");
    }

    return json.data;
  }

  async delete(id: number | string): Promise<void> {
    const res = await fetch(`${API_BASE}/campaigns/${id}`, {
      method: "DELETE",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const json = await res.json().catch(() => ({}));
      throw new Error(json.message || "Erro ao excluir campanha.");
    }
  }

  async validate(id: number | string): Promise<CampaignValidationResult> {
    const res = await fetch(`${API_BASE}/campaigns/${id}/validate`, {
      method: "POST",
      headers: this.getHeaders(),
    });

    const json = await res.json();
    if (!res.ok) {
      throw new Error(json.message || "Erro ao validar campanha.");
    }

    return json.data;
  }

  async preview(id: number | string, playerId?: number): Promise<CampaignPreviewResult> {
    const res = await fetch(`${API_BASE}/campaigns/${id}/preview`, {
      method: "POST",
      headers: this.getHeaders(),
      body: JSON.stringify(playerId ? { player_id: playerId } : {}),
    });

    const json = await res.json();
    if (!res.ok) {
      throw new Error(json.message || "Erro ao gerar pré-visualização da campanha.");
    }

    return json.data;
  }

  async testSend(id: number | string, recipient: string, playerId?: number): Promise<{ success: boolean; message: string }> {
    const res = await fetch(`${API_BASE}/campaigns/${id}/test`, {
      method: "POST",
      headers: this.getHeaders(),
      body: JSON.stringify({ recipient, player_id: playerId }),
    });

    const json = await res.json();
    if (!res.ok) {
      throw new Error(json.message || "Erro ao enviar mensagem de teste.");
    }

    return json;
  }

  async launch(id: number | string, confirmation: boolean = true): Promise<Campaign> {
    const res = await fetch(`${API_BASE}/campaigns/${id}/launch`, {
      method: "POST",
      headers: this.getHeaders(),
      body: JSON.stringify({ confirmation }),
    });

    const json = await res.json();
    if (!res.ok) {
      throw new Error(json.message || "Erro ao disparar campanha.");
    }

    return json.data;
  }

  async pause(id: number | string): Promise<Campaign> {
    const res = await fetch(`${API_BASE}/campaigns/${id}/pause`, {
      method: "POST",
      headers: this.getHeaders(),
    });

    const json = await res.json();
    if (!res.ok) {
      throw new Error(json.message || "Erro ao pausar campanha.");
    }

    return json.data;
  }

  async resume(id: number | string): Promise<Campaign> {
    const res = await fetch(`${API_BASE}/campaigns/${id}/resume`, {
      method: "POST",
      headers: this.getHeaders(),
    });

    const json = await res.json();
    if (!res.ok) {
      throw new Error(json.message || "Erro ao retomar campanha.");
    }

    return json.data;
  }

  async cancel(id: number | string): Promise<Campaign> {
    const res = await fetch(`${API_BASE}/campaigns/${id}/cancel`, {
      method: "POST",
      headers: this.getHeaders(),
    });

    const json = await res.json();
    if (!res.ok) {
      throw new Error(json.message || "Erro ao cancelar campanha.");
    }

    return json.data;
  }

  async getStats(id: number | string): Promise<CampaignStats> {
    const res = await fetch(`${API_BASE}/campaigns/${id}/stats`, {
      headers: this.getHeaders(),
    });

    const json = await res.json();
    if (!res.ok) {
      throw new Error(json.message || "Erro ao carregar estatísticas da campanha.");
    }

    return json.data;
  }

  async getRecipients(
    id: number | string,
    params: { status?: string; search?: string; page?: number; per_page?: number } = {}
  ): Promise<{ data: CampaignRecipient[]; pagination: any }> {
    const query = new URLSearchParams();
    if (params.status && params.status !== "ALL") query.append("status", params.status);
    if (params.search) query.append("search", params.search);
    if (params.page) query.append("page", params.page.toString());
    if (params.per_page) query.append("per_page", params.per_page.toString());

    const res = await fetch(`${API_BASE}/campaigns/${id}/recipients?${query.toString()}`, {
      headers: this.getHeaders(),
    });

    const json = await res.json();
    if (!res.ok) {
      throw new Error(json.message || "Erro ao carregar destinatários da campanha.");
    }

    return json;
  }

  async getMessages(
    id: number | string,
    params: { status?: string; page?: number; per_page?: number } = {}
  ): Promise<{ data: any[]; pagination: any }> {
    const query = new URLSearchParams();
    if (params.status && params.status !== "ALL") query.append("status", params.status);
    if (params.page) query.append("page", params.page.toString());
    if (params.per_page) query.append("per_page", params.per_page.toString());

    const res = await fetch(`${API_BASE}/campaigns/${id}/messages?${query.toString()}`, {
      headers: this.getHeaders(),
    });

    const json = await res.json();
    if (!res.ok) {
      throw new Error(json.message || "Erro ao carregar mensagens da campanha.");
    }

    return json;
  }
}

export const campaignService = new CampaignService();
