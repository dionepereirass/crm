import { authService } from "./auth-service";

const API_BASE = "http://localhost:8000/api/v1";

export interface SegmentConditionItem {
  type?: "condition";
  field: string;
  operator: string;
  value?: any;
  value_type?: string;
  event_type?: string;
  period_value?: number;
  period_unit?: string;
  metadata?: Record<string, any>;
}

export interface SegmentGroupItem {
  type?: "group";
  operator: "AND" | "OR";
  children: (SegmentConditionItem | SegmentGroupItem)[];
}

export type RulesTree = SegmentGroupItem;

export interface Segment {
  id: number;
  uuid: string;
  platform_id: number;
  name: string;
  slug: string;
  description?: string | null;
  status: "DRAFT" | "ACTIVE" | "INACTIVE";
  rules_tree: RulesTree;
  cached_count: number;
  cached_at?: string | null;
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

export interface SegmentPreviewResult {
  count: number;
  execution_time_ms: number;
  sample: any[];
}

export interface SegmentFieldDefinition {
  key?: string;
  label: string;
  category: string;
  type: string;
  operators: string[];
  options?: Record<string, string>;
  supports_period?: boolean;
}

export interface SegmentOperatorDefinition {
  key?: string;
  name: string;
  type: string;
  description: string;
}

export interface SegmentFilters {
  status?: string;
  search?: string;
  page?: number;
  per_page?: number;
}

class SegmentService {
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

  async getSegments(filters: SegmentFilters = {}): Promise<{
    data: Segment[];
    meta: {
      total: number;
      per_page: number;
      current_page: number;
      last_page: number;
    };
  }> {
    const params = new URLSearchParams();
    if (filters.status) params.append("status", filters.status);
    if (filters.search) params.append("search", filters.search);
    if (filters.page) params.append("page", String(filters.page));
    if (filters.per_page) params.append("per_page", String(filters.per_page));

    const res = await fetch(`${API_BASE}/segments?${params.toString()}`, {
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      throw new Error(`Erro ao buscar segmentos: ${res.statusText}`);
    }

    const json = await res.json();
    return {
      data: json.data || [],
      meta: json.pagination || { total: 0, per_page: 15, current_page: 1, last_page: 1 },
    };
  }

  async getSegment(id: number | string): Promise<Segment> {
    const res = await fetch(`${API_BASE}/segments/${id}`, {
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      throw new Error(`Erro ao buscar segmento #${id}`);
    }

    const json = await res.json();
    return json.data;
  }

  async createSegment(payload: {
    name: string;
    description?: string;
    status?: string;
    rules_tree: RulesTree;
  }): Promise<Segment> {
    const res = await fetch(`${API_BASE}/segments`, {
      method: "POST",
      headers: this.getHeaders(),
      body: JSON.stringify(payload),
    });

    if (!res.ok) {
      const errorJson = await res.json().catch(() => ({}));
      throw new Error(errorJson.message || "Erro ao criar segmento.");
    }

    const json = await res.json();
    return json.data;
  }

  async updateSegment(
    id: number | string,
    payload: Partial<{
      name: string;
      description?: string;
      status?: string;
      rules_tree: RulesTree;
    }>
  ): Promise<Segment> {
    const res = await fetch(`${API_BASE}/segments/${id}`, {
      method: "PUT",
      headers: this.getHeaders(),
      body: JSON.stringify(payload),
    });

    if (!res.ok) {
      const errorJson = await res.json().catch(() => ({}));
      throw new Error(errorJson.message || "Erro ao atualizar segmento.");
    }

    const json = await res.json();
    return json.data;
  }

  async deleteSegment(id: number | string): Promise<boolean> {
    const res = await fetch(`${API_BASE}/segments/${id}`, {
      method: "DELETE",
      headers: this.getHeaders(),
    });

    return res.ok;
  }

  async activateSegment(id: number | string): Promise<Segment> {
    const res = await fetch(`${API_BASE}/segments/${id}/activate`, {
      method: "POST",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      throw new Error("Erro ao ativar segmento.");
    }

    const json = await res.json();
    return json.data;
  }

  async deactivateSegment(id: number | string): Promise<Segment> {
    const res = await fetch(`${API_BASE}/segments/${id}/deactivate`, {
      method: "POST",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      throw new Error("Erro ao desativar segmento.");
    }

    const json = await res.json();
    return json.data;
  }

  async duplicateSegment(id: number | string): Promise<Segment> {
    const res = await fetch(`${API_BASE}/segments/${id}/duplicate`, {
      method: "POST",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      throw new Error("Erro ao duplicar segmento.");
    }

    const json = await res.json();
    return json.data;
  }

  async refreshSegment(id: number | string): Promise<{ count: number; cached_at: string; segment: Segment }> {
    const res = await fetch(`${API_BASE}/segments/${id}/refresh`, {
      method: "POST",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      throw new Error("Erro ao atualizar contagem do segmento.");
    }

    const json = await res.json();
    return {
      count: json.cached_count,
      cached_at: json.cached_at,
      segment: json.data,
    };
  }

  async getMembers(
    id: number | string,
    page: number = 1,
    perPage: number = 25
  ): Promise<{
    data: any[];
    meta: {
      total: number;
      per_page: number;
      current_page: number;
      last_page: number;
    };
  }> {
    const res = await fetch(`${API_BASE}/segments/${id}/members?page=${page}&per_page=${perPage}`, {
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      throw new Error("Erro ao buscar membros do segmento.");
    }

    const json = await res.json();
    return {
      data: json.data || [],
      meta: json.pagination || { total: 0, per_page: perPage, current_page: page, last_page: 1 },
    };
  }

  async previewRules(rulesTree: RulesTree, limit: number = 10): Promise<SegmentPreviewResult> {
    const res = await fetch(`${API_BASE}/segments/preview`, {
      method: "POST",
      headers: this.getHeaders(),
      body: JSON.stringify({ rules_tree: rulesTree, limit }),
    });

    if (!res.ok) {
      const errorJson = await res.json().catch(() => ({}));
      throw new Error(errorJson.message || "Erro ao calcular pré-visualização das regras.");
    }

    const json = await res.json();
    return {
      count: json.count,
      execution_time_ms: json.execution_time_ms,
      sample: json.sample || [],
    };
  }

  async previewExisting(id: number | string, limit: number = 10): Promise<SegmentPreviewResult> {
    const res = await fetch(`${API_BASE}/segments/${id}/preview?limit=${limit}`, {
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      throw new Error("Erro ao calcular pré-visualização.");
    }

    const json = await res.json();
    return {
      count: json.count,
      execution_time_ms: json.execution_time_ms,
      sample: json.sample || [],
    };
  }

  async getFields(): Promise<{
    data: Record<string, Record<string, SegmentFieldDefinition>>;
    all: Record<string, SegmentFieldDefinition>;
  }> {
    const res = await fetch(`${API_BASE}/segment-fields`, {
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      throw new Error("Erro ao carregar catálogo de campos.");
    }

    const json = await res.json();
    return {
      data: json.data || {},
      all: json.all || {},
    };
  }

  async getOperators(): Promise<{
    data: Record<string, Record<string, SegmentOperatorDefinition>>;
    all: Record<string, SegmentOperatorDefinition>;
  }> {
    const res = await fetch(`${API_BASE}/segment-operators`, {
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      throw new Error("Erro ao carregar catálogo de operadores.");
    }

    const json = await res.json();
    return {
      data: json.data || {},
      all: json.all || {},
    };
  }
}

export const segmentService = new SegmentService();
