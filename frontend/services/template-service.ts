import { authService } from "./auth-service";

const API_BASE = "http://localhost:8000/api/v1";

export interface TemplateVariable {
  key: string;
  label: string;
  category: "PLAYER" | "ACCOUNT" | "FINANCIAL" | "SEGMENTATION" | "PLATFORM" | "GENERAL";
  type: string;
  required?: boolean;
  example: string;
  description: string;
  channels: ("EMAIL" | "SMS")[];
  fallback?: string | null;
}

export interface SmsMetrics {
  char_count: number;
  encoding: "GSM-7" | "UNICODE";
  segments: number;
  segment_limit: number;
  chars_remaining: number;
  is_multipart: boolean;
  has_unicode_chars: boolean;
}

export interface TemplateVersion {
  id: number;
  uuid: string;
  template_id: number;
  version: number;
  status: "DRAFT" | "PUBLISHED" | "ARCHIVED";
  subject?: string | null;
  preheader?: string | null;
  html_content?: string | null;
  text_content?: string | null;
  sms_content?: string | null;
  variables_schema: { raw: string; key: string; fallback?: string | null }[];
  metadata?: Record<string, any> | null;
  changelog?: string | null;
  creator?: {
    id: number;
    name: string;
    email: string;
  } | null;
  created_at: string;
  updated_at: string;
}

export interface Template {
  id: number;
  uuid: string;
  platform_id: number;
  name: string;
  slug: string;
  description?: string | null;
  channel: "EMAIL" | "SMS";
  status: "DRAFT" | "ACTIVE" | "ARCHIVED";
  category: string;
  current_version_id?: number | null;
  versions_count?: number;
  current_version?: TemplateVersion | null;
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
  created_at: string;
  updated_at: string;
}

export interface TemplateListParams {
  channel?: string;
  status?: string;
  category?: string;
  search?: string;
  page?: number;
  per_page?: number;
}

export interface TemplateListResponse {
  data: Template[];
  pagination: {
    per_page: number;
    total: number;
    current_page: number;
    last_page: number;
  };
}

export interface TemplatePreviewResponse {
  channel: "EMAIL" | "SMS";
  rendered: {
    subject?: string;
    preheader?: string;
    html_content?: string;
    text_content?: string;
    sms_content?: string;
  };
  metrics?: SmsMetrics;
  sample_context: Record<string, any>;
}

export interface CreateTemplatePayload {
  name: string;
  description?: string;
  channel: "EMAIL" | "SMS";
  category?: string;
  status?: "DRAFT" | "ACTIVE" | "ARCHIVED";
  subject?: string;
  preheader?: string;
  html_content?: string;
  text_content?: string;
  sms_content?: string;
}

export interface UpdateTemplatePayload {
  name?: string;
  description?: string;
  status?: "DRAFT" | "ACTIVE" | "ARCHIVED";
  category?: string;
  subject?: string;
  preheader?: string;
  html_content?: string;
  text_content?: string;
  sms_content?: string;
  changelog?: string;
}

class TemplateService {
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

  async getTemplates(params: TemplateListParams = {}): Promise<TemplateListResponse> {
    const query = new URLSearchParams();
    if (params.channel && params.channel !== "ALL") query.append("channel", params.channel);
    if (params.status && params.status !== "ALL") query.append("status", params.status);
    if (params.category && params.category !== "ALL") query.append("category", params.category);
    if (params.search) query.append("search", params.search);
    if (params.page) query.append("page", String(params.page));
    if (params.per_page) query.append("per_page", String(params.per_page));

    const res = await fetch(`${API_BASE}/templates?${query.toString()}`, {
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      throw new Error("Erro ao buscar templates");
    }

    return res.json();
  }

  async getTemplate(id: number | string): Promise<Template> {
    const res = await fetch(`${API_BASE}/templates/${id}`, {
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      throw new Error(`Erro ao buscar template #${id}`);
    }

    const json = await res.json();
    return json.data;
  }

  async createTemplate(payload: CreateTemplatePayload): Promise<Template> {
    const res = await fetch(`${API_BASE}/templates`, {
      method: "POST",
      headers: this.getHeaders(),
      body: JSON.stringify(payload),
    });

    if (!res.ok) {
      const errorJson = await res.json().catch(() => ({}));
      throw new Error(errorJson.message || "Erro ao criar template.");
    }

    const json = await res.json();
    return json.data;
  }

  async updateTemplate(id: number | string, payload: UpdateTemplatePayload): Promise<Template> {
    const res = await fetch(`${API_BASE}/templates/${id}`, {
      method: "PUT",
      headers: this.getHeaders(),
      body: JSON.stringify(payload),
    });

    if (!res.ok) {
      const errorJson = await res.json().catch(() => ({}));
      throw new Error(errorJson.message || "Erro ao atualizar template.");
    }

    const json = await res.json();
    return json.data;
  }

  async deleteTemplate(id: number | string): Promise<void> {
    const res = await fetch(`${API_BASE}/templates/${id}`, {
      method: "DELETE",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const errorJson = await res.json().catch(() => ({}));
      throw new Error(errorJson.message || "Erro ao excluir template.");
    }
  }

  async publishTemplate(id: number | string): Promise<Template> {
    const res = await fetch(`${API_BASE}/templates/${id}/publish`, {
      method: "POST",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const errorJson = await res.json().catch(() => ({}));
      throw new Error(errorJson.message || "Erro ao publicar template.");
    }

    const json = await res.json();
    return json.data;
  }

  async archiveTemplate(id: number | string): Promise<Template> {
    const res = await fetch(`${API_BASE}/templates/${id}/archive`, {
      method: "POST",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const errorJson = await res.json().catch(() => ({}));
      throw new Error(errorJson.message || "Erro ao arquivar template.");
    }

    const json = await res.json();
    return json.data;
  }

  async duplicateTemplate(id: number | string): Promise<Template> {
    const res = await fetch(`${API_BASE}/templates/${id}/duplicate`, {
      method: "POST",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const errorJson = await res.json().catch(() => ({}));
      throw new Error(errorJson.message || "Erro ao duplicar template.");
    }

    const json = await res.json();
    return json.data;
  }

  async previewTemplate(
    id: number | string,
    payload: {
      version?: number;
      sample_data?: Record<string, any>;
      override_content?: {
        subject?: string;
        preheader?: string;
        html_content?: string;
        text_content?: string;
        sms_content?: string;
      };
    } = {}
  ): Promise<TemplatePreviewResponse> {
    const res = await fetch(`${API_BASE}/templates/${id}/preview`, {
      method: "POST",
      headers: this.getHeaders(),
      body: JSON.stringify(payload),
    });

    if (!res.ok) {
      const errorJson = await res.json().catch(() => ({}));
      throw new Error(errorJson.message || "Erro ao gerar pré-visualização.");
    }

    const json = await res.json();
    return json.data;
  }

  async getVersions(templateId: number | string): Promise<TemplateVersion[]> {
    const res = await fetch(`${API_BASE}/templates/${templateId}/versions`, {
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      throw new Error("Erro ao carregar histórico de versões.");
    }

    const json = await res.json();
    return json.data;
  }

  async getVersion(templateId: number | string, versionNumber: number): Promise<TemplateVersion> {
    const res = await fetch(`${API_BASE}/templates/${templateId}/versions/${versionNumber}`, {
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      throw new Error(`Erro ao carregar versão #${versionNumber}`);
    }

    const json = await res.json();
    return json.data;
  }

  async createVersion(
    templateId: number | string,
    payload: {
      subject?: string;
      preheader?: string;
      html_content?: string;
      text_content?: string;
      sms_content?: string;
      changelog?: string;
    }
  ): Promise<TemplateVersion> {
    const res = await fetch(`${API_BASE}/templates/${templateId}/versions`, {
      method: "POST",
      headers: this.getHeaders(),
      body: JSON.stringify(payload),
    });

    if (!res.ok) {
      const errorJson = await res.json().catch(() => ({}));
      throw new Error(errorJson.message || "Erro ao criar nova versão do template.");
    }

    const json = await res.json();
    return json.data;
  }

  async publishVersion(templateId: number | string, versionNumber: number): Promise<TemplateVersion> {
    const res = await fetch(`${API_BASE}/templates/${templateId}/versions/${versionNumber}/publish`, {
      method: "POST",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const errorJson = await res.json().catch(() => ({}));
      throw new Error(errorJson.message || "Erro ao publicar versão.");
    }

    const json = await res.json();
    return json.data;
  }

  async restoreVersion(templateId: number | string, versionNumber: number): Promise<TemplateVersion> {
    const res = await fetch(`${API_BASE}/templates/${templateId}/versions/${versionNumber}/restore`, {
      method: "POST",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const errorJson = await res.json().catch(() => ({}));
      throw new Error(errorJson.message || "Erro ao restaurar versão.");
    }

    const json = await res.json();
    return json.data;
  }

  async getVariablesCatalog(channel?: string): Promise<Record<string, TemplateVariable[]>> {
    const url = channel
      ? `${API_BASE}/templates/variables/catalog?channel=${encodeURIComponent(channel)}`
      : `${API_BASE}/templates/variables/catalog`;

    const res = await fetch(url, {
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      throw new Error("Erro ao obter catálogo de variáveis.");
    }

    const json = await res.json();
    return json.data;
  }

  async getCategories(): Promise<string[]> {
    const res = await fetch(`${API_BASE}/templates/categories/list`, {
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      return ["WELCOME", "DEPOSIT", "WITHDRAWAL", "REACTIVATION", "PROMOTIONAL", "RETENTION", "VIP", "TRANSACTIONAL", "GENERAL"];
    }

    const json = await res.json();
    return json.data;
  }
}

export const templateService = new TemplateService();
