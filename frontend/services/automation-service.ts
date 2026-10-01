import { authService } from "./auth-service";

const API_BASE = "http://localhost:8000/api/v1";

export interface AutomationSettings {
  reentry_policy?: 'ALLOW_REENTRY' | 'BLOCK_REENTRY' | 'REENTRY_AFTER';
  reentry_after_days?: number;
  cooldown_days?: number;
  max_steps_per_run?: number;
}

export interface Automation {
  id: number;
  platform_id: number;
  name: string;
  description: string | null;
  status: 'DRAFT' | 'ACTIVE' | 'PAUSED' | 'INACTIVE';
  trigger_type: string;
  settings: AutomationSettings;
  created_at: string;
  updated_at: string;
  total_runs?: number;
  completed_runs?: number;
  failed_runs?: number;
  creator?: { id: number; name: string; email: string };
  updater?: { id: number; name: string; email: string };
  nodes?: AutomationNode[];
  edges?: AutomationEdge[];
}

export interface AutomationNode {
  id?: number;
  node_key: string;
  node_type: 'TRIGGER' | 'CONDITION' | 'ACTION' | 'WAIT';
  name: string;
  configuration: Record<string, any>;
  position_x: number;
  position_y: number;
}

export interface AutomationEdge {
  id?: number;
  source_node_id?: number;
  source_node_key?: string;
  target_node_id?: number;
  target_node_key?: string;
  condition_key?: string | null;
}

export interface AutomationStep {
  id: number;
  automation_run_id: number;
  node_id: number;
  status: 'PENDING' | 'PROCESSING' | 'COMPLETED' | 'FAILED' | 'SKIPPED' | 'WAITING';
  input?: Record<string, any>;
  output?: Record<string, any>;
  error?: string | null;
  started_at?: string;
  completed_at?: string;
  node?: AutomationNode;
}

export interface AutomationLog {
  id: number;
  automation_id: number;
  automation_run_id?: number;
  automation_step_id?: number;
  level: 'INFO' | 'WARNING' | 'ERROR';
  event: string;
  message: string;
  metadata?: Record<string, any>;
  created_at: string;
}

export interface AutomationRun {
  id: number;
  automation_id: number;
  platform_id: number;
  player_id: number;
  status: 'RUNNING' | 'WAITING' | 'COMPLETED' | 'FAILED' | 'CANCELLED' | 'SKIPPED';
  current_node_id?: number;
  started_at?: string;
  completed_at?: string;
  last_error?: string | null;
  retry_count: number;
  player?: {
    id: number;
    name: string;
    email: string;
    phone: string;
    masked_email?: string;
    masked_phone?: string;
  };
  currentNode?: AutomationNode;
  steps?: AutomationStep[];
  logs?: AutomationLog[];
  steps_count?: number;
}

export interface AutomationMetrics {
  total_runs: number;
  completed: number;
  failed: number;
  cancelled: number;
  waiting: number;
  running: number;
  skipped: number;
  success_rate: number;
}

export interface ValidationResult {
  valid: boolean;
  errors: Array<{
    node: string;
    code: string;
    message: string;
  }>;
}

class AutomationService {
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

  async list(params: { status?: string; trigger_type?: string; search?: string; page?: number } = {}): Promise<{ data: Automation[]; total: number; current_page: number; last_page: number }> {
    const query = new URLSearchParams();
    if (params.status && params.status !== 'ALL') query.append('status', params.status);
    if (params.trigger_type && params.trigger_type !== 'ALL') query.append('trigger_type', params.trigger_type);
    if (params.search) query.append('search', params.search);
    if (params.page) query.append('page', params.page.toString());

    const res = await fetch(`${API_BASE}/automations?${query.toString()}`, {
      headers: this.getHeaders(),
    });

    if (!res.ok) throw new Error('Falha ao listar automações');
    return res.json();
  }

  async get(id: number): Promise<{ data: Automation; metrics: AutomationMetrics }> {
    const res = await fetch(`${API_BASE}/automations/${id}`, {
      headers: this.getHeaders(),
    });

    if (!res.ok) throw new Error(`Falha ao obter automação #${id}`);
    return res.json();
  }

  async create(payload: { name: string; description?: string; trigger_type: string; settings?: AutomationSettings }): Promise<{ data: Automation }> {
    const res = await fetch(`${API_BASE}/automations`, {
      method: 'POST',
      headers: this.getHeaders(),
      body: JSON.stringify(payload),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || 'Falha ao criar automação');
    }
    return res.json();
  }

  async update(id: number, payload: Partial<Automation>): Promise<{ data: Automation }> {
    const res = await fetch(`${API_BASE}/automations/${id}`, {
      method: 'PUT',
      headers: this.getHeaders(),
      body: JSON.stringify(payload),
    });

    if (!res.ok) throw new Error(`Falha ao atualizar automação #${id}`);
    return res.json();
  }

  async delete(id: number): Promise<void> {
    const res = await fetch(`${API_BASE}/automations/${id}`, {
      method: 'DELETE',
      headers: this.getHeaders(),
    });

    if (!res.ok) throw new Error(`Falha ao excluir automação #${id}`);
  }

  async activate(id: number): Promise<{ data: Automation }> {
    const res = await fetch(`${API_BASE}/automations/${id}/activate`, {
      method: 'POST',
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || 'Falha ao ativar automação');
    }
    return res.json();
  }

  async pause(id: number): Promise<{ data: Automation }> {
    const res = await fetch(`${API_BASE}/automations/${id}/pause`, {
      method: 'POST',
      headers: this.getHeaders(),
    });

    if (!res.ok) throw new Error(`Falha ao pausar automação #${id}`);
    return res.json();
  }

  async deactivate(id: number): Promise<{ data: Automation }> {
    const res = await fetch(`${API_BASE}/automations/${id}/deactivate`, {
      method: 'POST',
      headers: this.getHeaders(),
    });

    if (!res.ok) throw new Error(`Falha ao desativar automação #${id}`);
    return res.json();
  }

  async getGraph(id: number): Promise<{ automation: any; nodes: AutomationNode[]; edges: AutomationEdge[] }> {
    const res = await fetch(`${API_BASE}/automations/${id}/graph`, {
      headers: this.getHeaders(),
    });

    if (!res.ok) throw new Error(`Falha ao obter grafo da automação #${id}`);
    return res.json();
  }

  async saveGraph(id: number, nodes: AutomationNode[], edges: AutomationEdge[]): Promise<{ data: any }> {
    const res = await fetch(`${API_BASE}/automations/${id}/graph`, {
      method: 'PUT',
      headers: this.getHeaders(),
      body: JSON.stringify({ nodes, edges }),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || `Falha ao salvar grafo da automação #${id}`);
    }
    return res.json();
  }

  async validate(id: number): Promise<ValidationResult> {
    const res = await fetch(`${API_BASE}/automations/${id}/validate`, {
      method: 'POST',
      headers: this.getHeaders(),
    });

    if (!res.ok) throw new Error(`Falha ao validar automação #${id}`);
    return res.json();
  }

  async preview(id: number): Promise<any> {
    const res = await fetch(`${API_BASE}/automations/${id}/preview`, {
      method: 'POST',
      headers: this.getHeaders(),
    });

    if (!res.ok) throw new Error(`Falha ao gerar prévia da automação #${id}`);
    return res.json();
  }

  async getMetrics(id: number): Promise<{ metrics: AutomationMetrics }> {
    const res = await fetch(`${API_BASE}/automations/${id}/metrics`, {
      headers: this.getHeaders(),
    });

    if (!res.ok) throw new Error(`Falha ao carregar métricas da automação #${id}`);
    return res.json();
  }

  async listRuns(id: number, params: { status?: string; page?: number } = {}): Promise<{ data: AutomationRun[]; total: number; current_page: number; last_page: number }> {
    const query = new URLSearchParams();
    if (params.status && params.status !== 'ALL') query.append('status', params.status);
    if (params.page) query.append('page', params.page.toString());

    const res = await fetch(`${API_BASE}/automations/${id}/runs?${query.toString()}`, {
      headers: this.getHeaders(),
    });

    if (!res.ok) throw new Error(`Falha ao listar execuções da automação #${id}`);
    return res.json();
  }

  async getRun(id: number, runId: number): Promise<{ data: AutomationRun }> {
    const res = await fetch(`${API_BASE}/automations/${id}/runs/${runId}`, {
      headers: this.getHeaders(),
    });

    if (!res.ok) throw new Error(`Falha ao carregar detalhes da execução #${runId}`);
    return res.json();
  }

  async cancelRun(id: number, runId: number): Promise<{ data: AutomationRun }> {
    const res = await fetch(`${API_BASE}/automations/${id}/runs/${runId}/cancel`, {
      method: 'POST',
      headers: this.getHeaders(),
    });

    if (!res.ok) throw new Error(`Falha ao cancelar execução #${runId}`);
    return res.json();
  }
}

export const automationService = new AutomationService();
