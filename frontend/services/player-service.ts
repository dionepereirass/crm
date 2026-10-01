import { authService, Platform } from "./auth-service";

const API_BASE =
  process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api/v1";

export interface Tag {
  id: number;
  platform_id: number;
  name: string;
  slug: string;
  color?: string;
  description?: string;
  players_count?: number;
  created_at?: string;
  updated_at?: string;
}

export interface Consent {
  id: number;
  type: string;
  channel: string;
  status: "granted" | "revoked";
  ip_address?: string;
  user_agent?: string;
  granted_at?: string;
  revoked_at?: string;
  created_at?: string;
}

export interface Player {
  id: number;
  uuid: string;
  platform_id: number;
  external_id: string;
  name: string;
  email: string;
  phone?: string | null;
  cpf?: string | null;
  birth_date?: string | null;
  gender?: string | null;
  state?: string | null;
  city?: string | null;
  affiliate?: string | null;
  status: "active" | "inactive" | "churned" | "blocked" | "pending";
  custom_fields?: Record<string, any>;
  last_login_at?: string | null;
  created_at: string;
  updated_at: string;
  tags?: Tag[];
  platform?: Platform;
}

export interface Player360 {
  id: number;
  uuid: string;
  external_id: string;
  status: string;
  personal: {
    name: string;
    birth_date?: string;
    gender?: string;
    cpf_masked?: string;
  };
  contact: {
    email_masked: string;
    phone_masked?: string;
    city?: string;
    state?: string;
  };
  platform: {
    id: number;
    name: string;
    slug: string;
  };
  acquisition: {
    affiliate?: string;
    created_at: string;
  };
  activity: {
    last_login_at?: string;
    updated_at: string;
  };
  tags: Tag[];
  custom_fields: Record<string, any>;
  consents: Consent[];
  timeline: Array<{
    id: string;
    type: string;
    title: string;
    description: string;
    timestamp: string;
    metadata?: Record<string, any>;
  }>;
}

export interface PlayerFilters {
  search?: string;
  status?: string;
  tag_id?: number | string;
  state?: string;
  page?: number;
  per_page?: number;
  cursor?: string;
  pagination?: "offset" | "cursor";
}

export interface PaginatedResult<T> {
  data: T[];
  meta?: {
    total?: number;
    current_page?: number;
    last_page?: number;
    per_page?: number;
    from?: number;
    to?: number;
    next_cursor?: string;
    prev_cursor?: string;
  };
  links?: {
    first?: string;
    last?: string;
    prev?: string;
    next?: string;
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

export const playerService = {
  async getPlayers(filters: PlayerFilters = {}): Promise<PaginatedResult<Player>> {
    const params = new URLSearchParams();
    if (filters.search) params.append("search", filters.search);
    if (filters.status) params.append("status", filters.status);
    if (filters.tag_id) params.append("tag_id", String(filters.tag_id));
    if (filters.state) params.append("state", filters.state);
    if (filters.page) params.append("page", String(filters.page));
    if (filters.per_page) params.append("per_page", String(filters.per_page));
    if (filters.cursor) params.append("cursor", filters.cursor);
    if (filters.pagination) params.append("pagination", filters.pagination);

    const queryString = params.toString() ? `?${params.toString()}` : "";
    const res = await fetch(`${API_BASE}/players${queryString}`, {
      headers: getAuthHeaders(),
    });

    const json = await res.json();
    if (!res.ok || !json.success) {
      throw new Error(json.message || "Falha ao carregar jogadores.");
    }

    return {
      data: json.data || [],
      meta: json.meta,
      links: json.links,
    };
  },

  async getPlayer(id: number | string): Promise<Player> {
    const res = await fetch(`${API_BASE}/players/${id}`, {
      headers: getAuthHeaders(),
    });

    const json = await res.json();
    if (!res.ok || !json.success) {
      throw new Error(json.message || `Jogador #${id} não encontrado.`);
    }

    return json.data;
  },

  async getPlayer360(id: number | string): Promise<Player360> {
    const res = await fetch(`${API_BASE}/players/${id}/360`, {
      headers: getAuthHeaders(),
    });

    const json = await res.json();
    if (!res.ok || !json.success) {
      throw new Error(json.message || `Ficha 360° do jogador #${id} não encontrada.`);
    }

    return json.data;
  },

  async createPlayer(data: Partial<Player> & { tag_ids?: number[] }): Promise<Player> {
    const res = await fetch(`${API_BASE}/players`, {
      method: "POST",
      headers: getAuthHeaders(),
      body: JSON.stringify(data),
    });

    const json = await res.json();
    if (!res.ok || !json.success) {
      const err = new Error(json.message || "Erro ao cadastrar jogador.");
      (err as any).errors = json.errors;
      throw err;
    }

    return json.data;
  },

  async updatePlayer(id: number | string, data: Partial<Player>): Promise<Player> {
    const res = await fetch(`${API_BASE}/players/${id}`, {
      method: "PUT",
      headers: getAuthHeaders(),
      body: JSON.stringify(data),
    });

    const json = await res.json();
    if (!res.ok || !json.success) {
      const err = new Error(json.message || "Erro ao atualizar jogador.");
      (err as any).errors = json.errors;
      throw err;
    }

    return json.data;
  },

  async deletePlayer(id: number | string): Promise<void> {
    const res = await fetch(`${API_BASE}/players/${id}`, {
      method: "DELETE",
      headers: getAuthHeaders(),
    });

    const json = await res.json();
    if (!res.ok || !json.success) {
      throw new Error(json.message || "Erro ao excluir jogador.");
    }
  },

  async getTags(): Promise<Tag[]> {
    const res = await fetch(`${API_BASE}/tags`, {
      headers: getAuthHeaders(),
    });

    const json = await res.json();
    if (!res.ok || !json.success) {
      throw new Error(json.message || "Erro ao carregar tags.");
    }

    return json.data || [];
  },

  async createTag(data: { name: string; color?: string; description?: string }): Promise<Tag> {
    const res = await fetch(`${API_BASE}/tags`, {
      method: "POST",
      headers: getAuthHeaders(),
      body: JSON.stringify(data),
    });

    const json = await res.json();
    if (!res.ok || !json.success) {
      throw new Error(json.message || "Erro ao criar tag.");
    }

    return json.data;
  },

  async attachTag(playerId: number | string, tagId: number | string): Promise<void> {
    const res = await fetch(`${API_BASE}/players/${playerId}/tags`, {
      method: "POST",
      headers: getAuthHeaders(),
      body: JSON.stringify({ tag_id: Number(tagId) }),
    });

    const json = await res.json();
    if (!res.ok || !json.success) {
      throw new Error(json.message || "Erro ao associar tag ao jogador.");
    }
  },

  async detachTag(playerId: number | string, tagId: number | string): Promise<void> {
    const res = await fetch(`${API_BASE}/players/${playerId}/tags/${tagId}`, {
      method: "DELETE",
      headers: getAuthHeaders(),
    });

    const json = await res.json();
    if (!res.ok || !json.success) {
      throw new Error(json.message || "Erro ao desassociar tag do jogador.");
    }
  },
};
