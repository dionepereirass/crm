import { authService, Platform } from "./auth-service";

const API_BASE =
  process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api/v1";

export interface EventTypeInfo {
  id?: number;
  key: string;
  name: string;
}

export interface CrmEvent {
  id: number;
  uuid: string;
  platform_id: number;
  event_type: EventTypeInfo;
  external_event_id: string;
  player?: {
    id: number;
    external_id: string;
    name: string;
    email?: string;
  } | null;
  occurred_at: string;
  payload: Record<string, any>;
  normalized_payload?: Record<string, any>;
  processing_status: string;
  attempts: number;
  error_message?: string | null;
  processed_at?: string | null;
  created_at: string;
  updated_at: string;
}

export interface WebhookLogItem {
  id: number;
  uuid: string;
  platform_id: number;
  event_id?: number | null;
  external_event_id?: string | null;
  endpoint: string;
  signature_valid: boolean;
  processing_status: string;
  http_status: number;
  payload: Record<string, any>;
  headers: Record<string, any>;
  error_message?: string | null;
  ip_address?: string | null;
  user_agent?: string | null;
  received_at: string;
  processed_at?: string | null;
  created_at: string;
}

export interface EventFilters {
  status?: string;
  event_type?: string;
  player_id?: number | string;
  search?: string;
  date_from?: string;
  date_to?: string;
  page?: number;
  per_page?: number;
}

export interface WebhookLogFilters {
  status?: string;
  signature_valid?: boolean | string;
  search?: string;
  page?: number;
  per_page?: number;
}

export interface PaginatedResult<T> {
  data: T[];
  meta?: {
    total?: number;
    current_page?: number;
    last_page?: number;
    per_page?: number;
  };
}

function getAuthHeaders(platformIdOverride?: number): Record<string, string> {
  const token = authService.getToken();
  const platform = authService.getActivePlatform();
  const headers: Record<string, string> = {
    Accept: "application/json",
    "Content-Type": "application/json",
  };

  if (token) {
    headers["Authorization"] = `Bearer ${token}`;
  }

  const pid = platformIdOverride || platform?.id;
  if (pid) {
    headers["X-Platform-Id"] = String(pid);
  }

  return headers;
}

export async function computeHmacSha256(secret: string, message: string): Promise<string> {
  const enc = new TextEncoder();
  const key = await crypto.subtle.importKey(
    "raw",
    enc.encode(secret),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"]
  );
  const signature = await crypto.subtle.sign("HMAC", key, enc.encode(message));
  return Array.from(new Uint8Array(signature))
    .map((b) => b.toString(16).padStart(2, "0"))
    .join("");
}

export const eventService = {
  async getEvents(filters: EventFilters = {}): Promise<PaginatedResult<CrmEvent>> {
    const params = new URLSearchParams();
    if (filters.status) params.append("status", filters.status);
    if (filters.event_type) params.append("event_type", filters.event_type);
    if (filters.player_id) params.append("player_id", String(filters.player_id));
    if (filters.search) params.append("search", filters.search);
    if (filters.date_from) params.append("date_from", filters.date_from);
    if (filters.date_to) params.append("date_to", filters.date_to);
    if (filters.page) params.append("page", String(filters.page));
    if (filters.per_page) params.append("per_page", String(filters.per_page));

    const queryString = params.toString() ? `?${params.toString()}` : "";
    const res = await fetch(`${API_BASE}/events${queryString}`, {
      headers: getAuthHeaders(),
    });

    const json = await res.json();
    if (!res.ok || !json.success) {
      throw new Error(json.message || "Erro ao carregar eventos.");
    }

    return {
      data: json.data || [],
      meta: json.meta,
    };
  },

  async getEvent(id: number | string): Promise<CrmEvent> {
    const res = await fetch(`${API_BASE}/events/${id}`, {
      headers: getAuthHeaders(),
    });

    const json = await res.json();
    if (!res.ok || !json.success) {
      throw new Error(json.message || `Evento #${id} não encontrado.`);
    }

    return json.data;
  },

  async reprocessEvent(id: number | string): Promise<CrmEvent> {
    const res = await fetch(`${API_BASE}/events/${id}/reprocess`, {
      method: "POST",
      headers: getAuthHeaders(),
    });

    const json = await res.json();
    if (!res.ok || !json.success) {
      throw new Error(json.message || "Erro ao reenfileirar evento.");
    }

    return json.data;
  },

  async getWebhookLogs(filters: WebhookLogFilters = {}): Promise<PaginatedResult<WebhookLogItem>> {
    const params = new URLSearchParams();
    if (filters.status) params.append("status", filters.status);
    if (filters.signature_valid !== undefined && filters.signature_valid !== "") {
      params.append("signature_valid", String(filters.signature_valid));
    }
    if (filters.search) params.append("search", filters.search);
    if (filters.page) params.append("page", String(filters.page));
    if (filters.per_page) params.append("per_page", String(filters.per_page));

    const queryString = params.toString() ? `?${params.toString()}` : "";
    const res = await fetch(`${API_BASE}/webhook-logs${queryString}`, {
      headers: getAuthHeaders(),
    });

    const json = await res.json();
    if (!res.ok || !json.success) {
      throw new Error(json.message || "Erro ao carregar logs de webhook.");
    }

    return {
      data: json.data || [],
      meta: json.meta,
    };
  },

  async getWebhookLog(id: number | string): Promise<WebhookLogItem> {
    const res = await fetch(`${API_BASE}/webhook-logs/${id}`, {
      headers: getAuthHeaders(),
    });

    const json = await res.json();
    if (!res.ok || !json.success) {
      throw new Error(json.message || `Log #${id} não encontrado.`);
    }

    return json.data;
  },

  async sendTestWebhook(platformSlug: string, secret: string, payload: Record<string, any>): Promise<any> {
    const rawBody = JSON.stringify(payload);
    const signature = await computeHmacSha256(secret, rawBody);

    const res = await fetch(`${API_BASE}/webhooks/${platformSlug}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Webhook-Signature": signature,
      },
      body: rawBody,
    });

    const json = await res.json();
    return {
      httpStatus: res.status,
      signatureUsed: signature,
      response: json,
    };
  },
};
