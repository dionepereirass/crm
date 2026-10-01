import { authService } from "./auth-service";

const API_BASE = "http://localhost:8000/api/v1";

export interface MessageEvent {
  id: number;
  event_type: string;
  status: string;
  provider_message_id?: string | null;
  details?: Record<string, any> | null;
  created_at: string;
}

export interface Message {
  id: number;
  uuid: string;
  platform_id: number;
  provider_id?: number | null;
  channel: "EMAIL" | "SMS";
  recipient: string;
  masked_recipient: string;
  subject?: string | null;
  body?: string | null;
  status: "QUEUED" | "SENDING" | "SENT" | "DELIVERED" | "FAILED" | "CANCELLED" | "RATE_LIMITED";
  provider_message_id?: string | null;
  provider_driver?: string | null;
  provider_name?: string | null;
  attempts: number;
  max_attempts: number;
  error_code?: string | null;
  error_message?: string | null;
  idempotency_key: string;
  dispatched_at?: string | null;
  delivered_at?: string | null;
  failed_at?: string | null;
  created_at: string;
  updated_at: string;
  provider?: {
    id: number;
    name: string;
    driver: string;
    channel: string;
  } | null;
  events?: MessageEvent[];
}

export interface MessageListParams {
  channel?: string;
  status?: string;
  provider_id?: number | string;
  search?: string;
  page?: number;
  per_page?: number;
}

export interface MessageListResponse {
  data: Message[];
  pagination: {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
  };
}

export interface SendTestMessagePayload {
  channel: "EMAIL" | "SMS";
  recipient: string;
  subject?: string;
  html_content?: string;
  sms_content?: string;
  provider_id?: number;
  metadata?: Record<string, any>;
}

class MessageService {
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

  async list(params?: MessageListParams): Promise<MessageListResponse> {
    const query = new URLSearchParams();
    if (params?.channel && params.channel !== "ALL") query.append("channel", params.channel);
    if (params?.status && params.status !== "ALL") query.append("status", params.status);
    if (params?.provider_id) query.append("provider_id", String(params.provider_id));
    if (params?.search) query.append("search", params.search);
    if (params?.page) query.append("page", String(params.page));
    if (params?.per_page) query.append("per_page", String(params.per_page));

    const response = await fetch(`${API_BASE}/messages?${query.toString()}`, {
      headers: this.getHeaders(),
    });

    if (!response.ok) {
      const err = await response.json().catch(() => ({}));
      throw new Error(err.message || "Falha ao carregar mensagens.");
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

  async get(id: number | string): Promise<Message> {
    const response = await fetch(`${API_BASE}/messages/${id}`, {
      headers: this.getHeaders(),
    });

    if (!response.ok) {
      const err = await response.json().catch(() => ({}));
      throw new Error(err.message || "Mensagem não encontrada.");
    }

    const json = await response.json();
    return json.data;
  }

  async testSend(payload: SendTestMessagePayload): Promise<any> {
    const response = await fetch(`${API_BASE}/messages/test`, {
      method: "POST",
      headers: this.getHeaders(),
      body: JSON.stringify(payload),
    });

    const json = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(json.message || "Falha no envio de teste.");
    }

    return json;
  }

  async retry(id: number | string): Promise<Message> {
    const response = await fetch(`${API_BASE}/messages/${id}/retry`, {
      method: "POST",
      headers: this.getHeaders(),
    });

    const json = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(json.message || "Falha ao reenviar mensagem.");
    }

    return json.data;
  }

  async cancel(id: number | string): Promise<Message> {
    const response = await fetch(`${API_BASE}/messages/${id}/cancel`, {
      method: "POST",
      headers: this.getHeaders(),
    });

    const json = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(json.message || "Falha ao cancelar mensagem.");
    }

    return json.data;
  }
}

export const messageService = new MessageService();
