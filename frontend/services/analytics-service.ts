import { authService } from "./auth-service";

const API_BASE =
  process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api/v1";

// ============================================================================
// Types - Executive Dashboard & KPIs
// ============================================================================

export interface KpiItem {
  current: number;
  previous: number;
  percentage_change: number;
}

export interface PlayerKpis {
  total_players: KpiItem;
  new_players: KpiItem;
  active_players: KpiItem;
  inactive_players: KpiItem;
  churn_rate: {
    current: number;
    previous: number;
    percentage_change: number;
  };
}

export interface FinancialKpis {
  total_deposits_count: KpiItem;
  total_deposits_amount: KpiItem;
  total_withdrawals_count: KpiItem;
  total_withdrawals_amount: KpiItem;
  net_balance: KpiItem;
  average_deposit_ticket: KpiItem;
  first_time_depositors: KpiItem;
}

export interface BettingKpis {
  has_data: boolean;
  total_bets: KpiItem;
  turnover: KpiItem;
  total_won: KpiItem;
  total_lost: KpiItem;
  win_rate: {
    current: number;
    previous: number;
    percentage_change: number;
  };
}

export interface MarketingKpis {
  campaigns_count: KpiItem;
  messages_sent: KpiItem;
  messages_delivered: KpiItem;
  messages_opened: KpiItem;
  messages_clicked: KpiItem;
  delivery_rate: {
    current: number;
    previous: number;
    percentage_change: number;
  };
  open_rate: {
    current: number;
    previous: number;
    percentage_change: number;
  };
  click_rate: {
    current: number;
    previous: number;
    percentage_change: number;
  };
  ctr: {
    current: number;
    previous: number;
    percentage_change: number;
  };
  unsubscribes: KpiItem;
}

export interface AutomationKpis {
  active_automations: KpiItem;
  total_runs: KpiItem;
  completed_runs: KpiItem;
  failed_runs: KpiItem;
  success_rate: {
    current: number;
    previous: number;
    percentage_change: number;
  };
}

export interface ProviderKpis {
  active_providers: number;
  inactive_providers: number;
  error_providers: number;
  circuit_open_providers: number;
  total_providers: number;
}

export interface DashboardEvolutionPoint {
  date: string;
  new_players: number;
  active_players: number;
  deposits_amount: number;
  withdrawals_amount: number;
  messages_sent: number;
  messages_delivered: number;
}

export interface OperationalAlertItem {
  id: number;
  platform_id: number;
  alert_rule_id?: number;
  metric: string;
  severity: "INFO" | "WARNING" | "CRITICAL";
  status: "TRIGGERED" | "ACKNOWLEDGED" | "RESOLVED";
  title: string;
  message: string;
  current_value: number;
  threshold_value: number;
  triggered_at: string;
  acknowledged_at?: string;
  acknowledged_by?: number;
  resolved_at?: string;
  resolved_by?: number;
  resolution_notes?: string;
  metadata?: any;
}

export interface DashboardData {
  period: {
    type: string;
    from: string;
    to: string;
    grouping: string;
    prior_from: string;
    prior_to: string;
  };
  kpis: {
    players: PlayerKpis;
    financial: FinancialKpis;
    betting: BettingKpis;
    marketing: MarketingKpis;
    automations: AutomationKpis;
    providers: ProviderKpis;
  };
  funnel: Array<{ stage: string; count: number; conversion_rate: number }>;
  evolution: DashboardEvolutionPoint[];
  active_alerts: OperationalAlertItem[];
}

// ============================================================================
// Types - Deep Analytics
// ============================================================================

export interface PlayerRiskDistribution {
  ATIVO: number;
  ATENCAO: number;
  RISCO: number;
  INATIVO: number;
}

export interface ChurnMetrics {
  inactivity_days_threshold: number;
  total_players: number;
  active_count: number;
  at_risk_count: number;
  churned_count: number;
  churn_rate: number;
  retention_rate: number;
}

export interface CohortRow {
  cohort_week: string;
  size: number;
  periods: {
    D1: number;
    D7: number;
    D14: number;
    D30: number;
    D60: number;
    D90: number;
  };
}

export interface TemplatePerformanceItem {
  id: number;
  name: string;
  channel: string;
  total_sent: number;
  total_delivered: number;
  total_opened: number;
  total_clicked: number;
  open_rate: number;
  click_rate: number;
}

export interface ProviderHealthItem {
  id: number;
  name: string;
  driver: string;
  channel: string;
  health_status: string;
  circuit_breaker_status: string;
  success_rate: number;
  total_messages: number;
  sent_count: number;
  delivered_count: number;
  failed_count: number;
  average_latency_ms: number;
}

export interface PrivacyMetrics {
  consents: {
    total: number;
    active: number;
    revoked: number;
    grant_rate: number;
  };
  requests: {
    total: number;
    open: number;
    completed: number;
    near_sla: number;
    expired_sla: number;
    compliance_rate: number;
  };
  anonymized_players: number;
  audit_logs_count: number;
}

// ============================================================================
// Types - Scheduled Reports & Alerts
// ============================================================================

export interface ScheduledReportItem {
  id: number;
  platform_id: number;
  name: string;
  report_type: "PLAYERS" | "FINANCIAL" | "MARKETING" | "AUTOMATIONS" | "PRIVACY";
  frequency: "DAILY" | "WEEKLY" | "MONTHLY";
  recipients: string[];
  filters: any;
  format: "CSV" | "JSON";
  active: boolean;
  last_run_at?: string;
  next_run_at?: string;
  created_at: string;
  creator?: {
    id: number;
    name: string;
    email: string;
  };
}

export interface AlertRuleItem {
  id: number;
  platform_id: number;
  name: string;
  metric: string;
  operator: "GT" | "GTE" | "LT" | "LTE" | "EQ";
  threshold: number;
  severity: "INFO" | "WARNING" | "CRITICAL";
  cooldown_minutes: number;
  active: boolean;
  last_evaluated_at?: string;
  last_triggered_at?: string;
  alerts_count?: number;
}

// ============================================================================
// Legacy Types (Fase 9)
// ============================================================================

export interface CampaignMetricsData {
  audience: number;
  eligible: number;
  queued: number;
  sent: number;
  delivered: number;
  failed: number;
  bounced: number;
  opened: number;
  unique_openers: number;
  clicked: number;
  unique_clickers: number;
  unsubscribed: number;
  delivery_rate: number;
  open_rate: number;
  click_rate: number;
  bounce_rate: number;
  failure_rate: number;
  unsubscribe_rate: number;
  updated_at?: string;
}

export interface FunnelStep {
  key: string;
  label: string;
  count: number;
}

export interface CampaignAnalyticsResponse {
  campaign_id: number;
  campaign_name: string;
  channel: string;
  status: string;
  metrics: CampaignMetricsData;
  funnel: FunnelStep[];
  providers: any[];
  timeline: any[];
}

export interface CampaignAnalyticsListItem {
  id: number;
  name: string;
  channel: string;
  status: string;
  created_at: string;
  provider?: any;
  segment?: any;
  metric?: any;
}

export interface CampaignEventItem {
  id: number;
  message_id: number;
  event_type: string;
  provider_event_id?: string;
  occurred_at: string;
  created_at: string;
  recipient: string;
  channel: string;
  message?: {
    id: number;
    provider?: {
      name: string;
      driver: string;
    };
  };
}

export interface AnalyticsOverview {
  total_campaigns: number;
  total_audience: number;
  total_messages: number;
  total_sent: number;
  total_delivered: number;
  total_failed: number;
  total_bounced: number;
  total_opened: number;
  total_unique_openers: number;
  total_clicked: number;
  total_unique_clickers: number;
  total_unsubscribed: number;
  delivery_rate: number;
  open_rate: number;
  click_rate: number;
  bounce_rate: number;
  failure_rate: number;
  unsubscribe_rate: number;
}

// ============================================================================
// Service Class
// ============================================================================

class AnalyticsService {
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
      headers["X-Platform-ID"] = platform.id.toString();
    }

    return headers;
  }

  // ==========================================================================
  // Executive Dashboard & Consolidations (Fase 12)
  // ==========================================================================

  async getDashboard(filters?: {
    period?: string;
    grouping?: string;
    date_from?: string;
    date_to?: string;
    inactivity_days?: number;
  }): Promise<DashboardData> {
    const params = new URLSearchParams();
    if (filters?.period) params.append("period", filters.period);
    if (filters?.grouping) params.append("grouping", filters.grouping);
    if (filters?.date_from) params.append("date_from", filters.date_from);
    if (filters?.date_to) params.append("date_to", filters.date_to);
    if (filters?.inactivity_days) params.append("inactivity_days", filters.inactivity_days.toString());

    const query = params.toString() ? `?${params.toString()}` : "";
    const res = await fetch(`${API_BASE}/analytics/dashboard${query}`, {
      method: "GET",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Erro ao carregar Dashboard Executivo.");
    }

    const json = await res.json();
    const raw = json.data || {};
    const rawKpis = raw.kpis || {};
    const fin = rawKpis.financial || {};
    const ply = rawKpis.players || {};
    const bet = rawKpis.betting || {};
    const mkt = rawKpis.marketing || {};
    const aut = rawKpis.automations || {};

    const toKpi = (item: any): KpiItem => ({
      current: Number(item?.current ?? item?.value ?? 0),
      previous: Number(item?.previous ?? item?.prior_value ?? 0),
      percentage_change: Number(item?.percentage_change ?? item?.change_percentage ?? 0),
    });

    const normalizedData: DashboardData = {
      period: {
        type: raw.period?.key || "custom",
        from: raw.period?.current?.from || "",
        to: raw.period?.current?.to || "",
        grouping: raw.period?.grouping || "day",
        prior_from: raw.period?.prior?.from || "",
        prior_to: raw.period?.prior?.to || "",
      },
      kpis: {
        players: {
          total_players: toKpi(ply.total_players),
          new_players: toKpi(ply.new_players),
          active_players: toKpi(ply.active_players),
          inactive_players: toKpi(ply.inactive_players),
          churn_rate: {
            current: Number(ply.churn_rate?.value ?? ply.churn_rate?.current ?? 0),
            previous: Number(ply.churn_rate?.prior_value ?? ply.churn_rate?.previous ?? 0),
            percentage_change: Number(ply.churn_rate?.change_percentage ?? ply.churn_rate?.percentage_change ?? 0),
          },
        },
        financial: {
          total_deposits_count: toKpi(fin.deposit_count ?? fin.total_deposits_count),
          total_deposits_amount: toKpi(fin.total_deposit_amount ?? fin.total_deposits_amount),
          total_withdrawals_count: toKpi(fin.withdrawal_count ?? fin.total_withdrawals_count),
          total_withdrawals_amount: toKpi(fin.total_withdrawal_amount ?? fin.total_withdrawals_amount),
          net_balance: toKpi(fin.net_revenue ?? fin.net_balance),
          average_deposit_ticket: toKpi(fin.average_deposit_ticket),
          first_time_depositors: toKpi(fin.first_time_depositors),
        },
        betting: {
          has_data: Boolean(bet.total_bets?.value || bet.total_bets?.current || bet.turnover?.value),
          total_bets: toKpi(bet.total_bets),
          turnover: toKpi(bet.turnover),
          total_won: toKpi(bet.bets_won ?? bet.total_won),
          total_lost: toKpi(bet.bets_lost ?? bet.total_lost),
          win_rate: {
            current: Number(bet.win_rate?.value ?? bet.win_rate?.current ?? 0),
            previous: Number(bet.win_rate?.prior_value ?? bet.win_rate?.previous ?? 0),
            percentage_change: Number(bet.win_rate?.change_percentage ?? bet.win_rate?.percentage_change ?? 0),
          },
        },
        marketing: {
          campaigns_count: toKpi(mkt.campaigns_count),
          messages_sent: toKpi(mkt.messages_sent),
          messages_delivered: toKpi(mkt.messages_delivered),
          messages_opened: toKpi(mkt.messages_opened),
          messages_clicked: toKpi(mkt.messages_clicked),
          delivery_rate: {
            current: Number(mkt.delivery_rate?.value ?? mkt.delivery_rate?.current ?? 0),
            previous: Number(mkt.delivery_rate?.prior_value ?? mkt.delivery_rate?.previous ?? 0),
            percentage_change: Number(mkt.delivery_rate?.change_percentage ?? mkt.delivery_rate?.percentage_change ?? 0),
          },
          open_rate: {
            current: Number(mkt.open_rate?.value ?? mkt.open_rate?.current ?? 0),
            previous: Number(mkt.open_rate?.prior_value ?? mkt.open_rate?.previous ?? 0),
            percentage_change: Number(mkt.open_rate?.change_percentage ?? mkt.open_rate?.percentage_change ?? 0),
          },
          click_rate: {
            current: Number(mkt.click_through_rate?.value ?? mkt.click_rate?.current ?? 0),
            previous: Number(mkt.click_through_rate?.prior_value ?? mkt.click_rate?.previous ?? 0),
            percentage_change: Number(mkt.click_through_rate?.change_percentage ?? mkt.click_rate?.percentage_change ?? 0),
          },
          ctr: {
            current: Number(mkt.click_through_rate?.value ?? mkt.ctr?.current ?? 0),
            previous: Number(mkt.click_through_rate?.prior_value ?? mkt.ctr?.previous ?? 0),
            percentage_change: Number(mkt.click_through_rate?.change_percentage ?? mkt.ctr?.percentage_change ?? 0),
          },
          unsubscribes: toKpi(mkt.unsubscribes),
        },
        automations: {
          active_automations: toKpi(aut.active_automations),
          total_runs: toKpi(aut.total_runs),
          completed_runs: toKpi(aut.completed_runs),
          failed_runs: toKpi(aut.failed_runs),
          success_rate: {
            current: Number(aut.success_rate ?? 100),
            previous: 100,
            percentage_change: 0,
          },
        },
        providers: rawKpis.providers || {
          active_providers: 0,
          inactive_providers: 0,
          error_providers: 0,
          circuit_open_providers: 0,
          total_providers: 0,
        },
      },
      funnel: (raw.marketing_funnel || []).map((f: any) => ({
        stage: f.stage || "",
        count: Number(f.count || 0),
        conversion_rate: Number(f.percentage || 0),
      })),
      evolution: (raw.charts?.combined_evolution || []).map((e: any) => ({
        date: e.date || e.key || "",
        new_players: Number(e.new_players || 0),
        active_players: 0,
        deposits_amount: Number(e.deposits || 0),
        withdrawals_amount: Number(e.withdrawals || 0),
        messages_sent: 0,
        messages_delivered: 0,
      })),
      active_alerts: raw.active_alerts || [],
    };

    return normalizedData;
  }

  // ==========================================================================
  // Deep Analytics Endpoints (Fase 12)
  // ==========================================================================

  async getPlayersAnalytics(filters?: {
    period?: string;
    grouping?: string;
    date_from?: string;
    date_to?: string;
    inactivity_days?: number;
  }): Promise<any> {
    const params = new URLSearchParams();
    if (filters?.period) params.append("period", filters.period);
    if (filters?.grouping) params.append("grouping", filters.grouping);
    if (filters?.date_from) params.append("date_from", filters.date_from);
    if (filters?.date_to) params.append("date_to", filters.date_to);
    if (filters?.inactivity_days) params.append("inactivity_days", filters.inactivity_days.toString());

    const query = params.toString() ? `?${params.toString()}` : "";
    const res = await fetch(`${API_BASE}/analytics/players${query}`, {
      method: "GET",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Erro ao carregar analítica de jogadores.");
    }

    const json = await res.json();
    return json.data;
  }

  async getRetention(weeks: number = 8): Promise<{ cohort: CohortRow[]; curve: Record<string, number> }> {
    const res = await fetch(`${API_BASE}/analytics/retention?weeks=${weeks}`, {
      method: "GET",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Erro ao carregar matriz de retenção.");
    }

    const json = await res.json();
    return json.data;
  }

  async getChurn(inactivityDays: number = 30): Promise<ChurnMetrics> {
    const res = await fetch(`${API_BASE}/analytics/churn?inactivity_days=${inactivityDays}`, {
      method: "GET",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Erro ao carregar métricas de churn.");
    }

    const json = await res.json();
    return json.data;
  }

  async getFinanceAnalytics(filters?: {
    period?: string;
    grouping?: string;
    date_from?: string;
    date_to?: string;
  }): Promise<any> {
    const params = new URLSearchParams();
    if (filters?.period) params.append("period", filters.period);
    if (filters?.grouping) params.append("grouping", filters.grouping);
    if (filters?.date_from) params.append("date_from", filters.date_from);
    if (filters?.date_to) params.append("date_to", filters.date_to);

    const query = params.toString() ? `?${params.toString()}` : "";
    const res = await fetch(`${API_BASE}/analytics/finance${query}`, {
      method: "GET",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Erro ao carregar dados financeiros.");
    }

    const json = await res.json();
    return json.data;
  }

  async getBettingAnalytics(filters?: {
    period?: string;
    grouping?: string;
    date_from?: string;
    date_to?: string;
  }): Promise<any> {
    const params = new URLSearchParams();
    if (filters?.period) params.append("period", filters.period);
    if (filters?.grouping) params.append("grouping", filters.grouping);
    if (filters?.date_from) params.append("date_from", filters.date_from);
    if (filters?.date_to) params.append("date_to", filters.date_to);

    const query = params.toString() ? `?${params.toString()}` : "";
    const res = await fetch(`${API_BASE}/analytics/betting${query}`, {
      method: "GET",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Erro ao carregar dados de apostas.");
    }

    const json = await res.json();
    return json.data;
  }

  async getMarketingAnalytics(filters?: {
    period?: string;
    date_from?: string;
    date_to?: string;
  }): Promise<any> {
    const params = new URLSearchParams();
    if (filters?.period) params.append("period", filters.period);
    if (filters?.date_from) params.append("date_from", filters.date_from);
    if (filters?.date_to) params.append("date_to", filters.date_to);

    const query = params.toString() ? `?${params.toString()}` : "";
    const res = await fetch(`${API_BASE}/analytics/marketing${query}`, {
      method: "GET",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Erro ao carregar analytics de marketing.");
    }

    const json = await res.json();
    return json.data;
  }

  async getTemplateAnalytics(filters?: { period?: string }): Promise<TemplatePerformanceItem[]> {
    const params = new URLSearchParams();
    if (filters?.period) params.append("period", filters.period);

    const query = params.toString() ? `?${params.toString()}` : "";
    const res = await fetch(`${API_BASE}/analytics/templates${query}`, {
      method: "GET",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Erro ao carregar performance de templates.");
    }

    const json = await res.json();
    return json.data;
  }

  async getProviderAnalytics(filters?: { period?: string }): Promise<ProviderHealthItem[]> {
    const params = new URLSearchParams();
    if (filters?.period) params.append("period", filters.period);

    const query = params.toString() ? `?${params.toString()}` : "";
    const res = await fetch(`${API_BASE}/analytics/providers${query}`, {
      method: "GET",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Erro ao carregar saúde dos provedores.");
    }

    const json = await res.json();
    return json.data;
  }

  async getAutomationAnalytics(filters?: { period?: string }): Promise<any> {
    const params = new URLSearchParams();
    if (filters?.period) params.append("period", filters.period);

    const query = params.toString() ? `?${params.toString()}` : "";
    const res = await fetch(`${API_BASE}/analytics/automations${query}`, {
      method: "GET",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Erro ao carregar analytics de automações.");
    }

    const json = await res.json();
    return json.data;
  }

  async getPrivacyAnalytics(filters?: { period?: string }): Promise<PrivacyMetrics> {
    const params = new URLSearchParams();
    if (filters?.period) params.append("period", filters.period);

    const query = params.toString() ? `?${params.toString()}` : "";
    const res = await fetch(`${API_BASE}/analytics/privacy${query}`, {
      method: "GET",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Erro ao carregar indicadores LGPD.");
    }

    const json = await res.json();
    return json.data;
  }

  async getSegmentAnalytics(filters?: { period?: string }): Promise<any> {
    const params = new URLSearchParams();
    if (filters?.period) params.append("period", filters.period);

    const query = params.toString() ? `?${params.toString()}` : "";
    const res = await fetch(`${API_BASE}/analytics/segments${query}`, {
      method: "GET",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Erro ao carregar analytics de segmentos.");
    }

    const json = await res.json();
    return json.data;
  }

  async invalidateCache(metric?: string): Promise<void> {
    const res = await fetch(`${API_BASE}/analytics/cache/invalidate`, {
      method: "POST",
      headers: this.getHeaders(),
      body: JSON.stringify({ metric }),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Falha ao invalidar cache analítico.");
    }
  }

  // ==========================================================================
  // Relatórios & Exportações (Fase 12)
  // ==========================================================================

  async getReportPreview(
    reportType: string,
    filters?: { period?: string; date_from?: string; date_to?: string }
  ): Promise<{ data: any[]; total_rows: number }> {
    const params = new URLSearchParams();
    params.append("report_type", reportType);
    if (filters?.period) params.append("period", filters.period);
    if (filters?.date_from) params.append("date_from", filters.date_from);
    if (filters?.date_to) params.append("date_to", filters.date_to);

    const res = await fetch(`${API_BASE}/reports?${params.toString()}`, {
      method: "GET",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Falha ao gerar prévia do relatório.");
    }

    return await res.json();
  }

  async exportReport(
    reportType: string,
    format: "CSV" | "JSON" = "CSV",
    filters?: { period?: string; date_from?: string; date_to?: string },
    isAsync: boolean = false
  ): Promise<{ isAsync: boolean; blob?: Blob; exportId?: string }> {
    const res = await fetch(`${API_BASE}/reports/export`, {
      method: "POST",
      headers: this.getHeaders(),
      body: JSON.stringify({
        report_type: reportType,
        format,
        period: filters?.period,
        date_from: filters?.date_from,
        date_to: filters?.date_to,
        async: isAsync,
      }),
    });

    if (res.status === 202) {
      const json = await res.json();
      return { isAsync: true, exportId: json.export_id };
    }

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Falha ao exportar relatório.");
    }

    const blob = await res.blob();
    return { isAsync: false, blob };
  }

  async listScheduledReports(): Promise<ScheduledReportItem[]> {
    const res = await fetch(`${API_BASE}/reports/scheduled`, {
      method: "GET",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Erro ao carregar relatórios agendados.");
    }

    const json = await res.json();
    return json.data;
  }

  async createScheduledReport(payload: {
    name: string;
    report_type: string;
    frequency: string;
    recipients: string[];
    format?: string;
    filters?: any;
  }): Promise<ScheduledReportItem> {
    const res = await fetch(`${API_BASE}/reports/scheduled`, {
      method: "POST",
      headers: this.getHeaders(),
      body: JSON.stringify(payload),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Falha ao agendar relatório.");
    }

    const json = await res.json();
    return json.data;
  }

  async deleteScheduledReport(id: number): Promise<void> {
    const res = await fetch(`${API_BASE}/reports/scheduled/${id}`, {
      method: "DELETE",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Falha ao excluir agendamento.");
    }
  }

  // ==========================================================================
  // Alertas Operacionais (Fase 12)
  // ==========================================================================

  async listAlerts(
    filters?: { status?: string; severity?: string; metric?: string },
    page: number = 1,
    perPage: number = 20
  ): Promise<{ data: OperationalAlertItem[]; pagination: any }> {
    const params = new URLSearchParams();
    if (filters?.status && filters.status !== "ALL") params.append("status", filters.status);
    if (filters?.severity && filters.severity !== "ALL") params.append("severity", filters.severity);
    if (filters?.metric && filters.metric !== "ALL") params.append("metric", filters.metric);
    params.append("page", page.toString());
    params.append("per_page", perPage.toString());

    const res = await fetch(`${API_BASE}/alerts?${params.toString()}`, {
      method: "GET",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Falha ao listar alertas.");
    }

    return await res.json();
  }

  async acknowledgeAlert(id: number): Promise<OperationalAlertItem> {
    const res = await fetch(`${API_BASE}/alerts/${id}/acknowledge`, {
      method: "POST",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Falha ao reconhecer alerta.");
    }

    const json = await res.json();
    return json.data;
  }

  async resolveAlert(id: number, notes?: string): Promise<OperationalAlertItem> {
    const res = await fetch(`${API_BASE}/alerts/${id}/resolve`, {
      method: "POST",
      headers: this.getHeaders(),
      body: JSON.stringify({ notes }),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Falha ao resolver alerta.");
    }

    const json = await res.json();
    return json.data;
  }

  async listAlertRules(): Promise<AlertRuleItem[]> {
    const res = await fetch(`${API_BASE}/alerts/rules`, {
      method: "GET",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Falha ao listar regras de alerta.");
    }

    const json = await res.json();
    return json.data;
  }

  async createAlertRule(payload: {
    name: string;
    metric: string;
    operator: string;
    threshold: number;
    severity?: string;
    cooldown_minutes?: number;
    active?: boolean;
  }): Promise<AlertRuleItem> {
    const res = await fetch(`${API_BASE}/alerts/rules`, {
      method: "POST",
      headers: this.getHeaders(),
      body: JSON.stringify(payload),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Falha ao criar regra de alerta.");
    }

    const json = await res.json();
    return json.data;
  }

  async updateAlertRule(
    id: number,
    payload: Partial<{
      name: string;
      operator: string;
      threshold: number;
      severity: string;
      cooldown_minutes: number;
      active: boolean;
    }>
  ): Promise<AlertRuleItem> {
    const res = await fetch(`${API_BASE}/alerts/rules/${id}`, {
      method: "PUT",
      headers: this.getHeaders(),
      body: JSON.stringify(payload),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Falha ao atualizar regra de alerta.");
    }

    const json = await res.json();
    return json.data;
  }

  async deleteAlertRule(id: number): Promise<void> {
    const res = await fetch(`${API_BASE}/alerts/rules/${id}`, {
      method: "DELETE",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Falha ao excluir regra de alerta.");
    }
  }

  async evaluateAlerts(): Promise<{ evaluated_rules: number; new_alerts_count: number }> {
    const res = await fetch(`${API_BASE}/alerts/evaluate`, {
      method: "POST",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Falha ao avaliar regras.");
    }

    const json = await res.json();
    return json.data;
  }

  // ==========================================================================
  // Legacy Methods (Fase 9)
  // ==========================================================================

  async getCampaignAnalytics(campaignId: number | string): Promise<CampaignAnalyticsResponse> {
    const res = await fetch(`${API_BASE}/campaigns/${campaignId}/analytics`, {
      method: "GET",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Erro ao carregar analytics da campanha.");
    }

    const data = await res.json();
    return data.data;
  }

  async getCampaignEvents(
    campaignId: number | string,
    page: number = 1,
    perPage: number = 25
  ): Promise<{ data: any[]; pagination: any }> {
    const res = await fetch(
      `${API_BASE}/campaigns/${campaignId}/events?page=${page}&per_page=${perPage}`,
      {
        method: "GET",
        headers: this.getHeaders(),
      }
    );

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Erro ao carregar eventos da campanha.");
    }

    return await res.json();
  }

  async rebuildCampaignAnalytics(campaignId: number | string): Promise<any> {
    const res = await fetch(`${API_BASE}/campaigns/${campaignId}/rebuild-analytics`, {
      method: "POST",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Erro ao recalcular métricas.");
    }

    const data = await res.json();
    return data.data;
  }

  async getOverview(filters?: { date_from?: string; date_to?: string }): Promise<AnalyticsOverview> {
    const params = new URLSearchParams();
    if (filters?.date_from) params.append("date_from", filters.date_from);
    if (filters?.date_to) params.append("date_to", filters.date_to);

    const query = params.toString() ? `?${params.toString()}` : "";
    const res = await fetch(`${API_BASE}/analytics/overview${query}`, {
      method: "GET",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Erro ao carregar visão geral analítica.");
    }

    const data = await res.json();
    return data.data;
  }

  async getCampaignsAnalytics(filters?: {
    channel?: string;
    status?: string;
    provider_id?: number | string;
    date_from?: string;
    date_to?: string;
    page?: number;
    per_page?: number;
  }): Promise<{ data: CampaignAnalyticsListItem[]; pagination: any }> {
    const params = new URLSearchParams();
    if (filters?.channel && filters.channel !== "ALL") params.append("channel", filters.channel);
    if (filters?.status && filters.status !== "ALL") params.append("status", filters.status);
    if (filters?.provider_id) params.append("provider_id", filters.provider_id.toString());
    if (filters?.date_from) params.append("date_from", filters.date_from);
    if (filters?.date_to) params.append("date_to", filters.date_to);
    if (filters?.page) params.append("page", filters.page.toString());
    if (filters?.per_page) params.append("per_page", filters.per_page.toString());

    const query = params.toString() ? `?${params.toString()}` : "";
    const res = await fetch(`${API_BASE}/analytics/campaigns${query}`, {
      method: "GET",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Erro ao carregar lista analítica de campanhas.");
    }

    return await res.json();
  }

  async exportCsv(filters?: {
    channel?: string;
    status?: string;
    provider_id?: number | string;
    date_from?: string;
    date_to?: string;
  }): Promise<Blob> {
    const params = new URLSearchParams();
    if (filters?.channel && filters.channel !== "ALL") params.append("channel", filters.channel);
    if (filters?.status && filters.status !== "ALL") params.append("status", filters.status);
    if (filters?.provider_id) params.append("provider_id", filters.provider_id.toString());
    if (filters?.date_from) params.append("date_from", filters.date_from);
    if (filters?.date_to) params.append("date_to", filters.date_to);

    const query = params.toString() ? `?${params.toString()}` : "";
    const res = await fetch(`${API_BASE}/analytics/campaigns/export${query}`, {
      method: "GET",
      headers: this.getHeaders(),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || "Falha ao exportar CSV de analytics.");
    }

    return await res.blob();
  }
}

export const analyticsService = new AnalyticsService();
