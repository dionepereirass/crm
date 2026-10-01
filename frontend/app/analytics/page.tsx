"use client";

import React, { useState, useEffect, useCallback } from "react";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import { useAuth } from "@/hooks/use-auth";
import {
  analyticsService,
  CohortRow,
  ChurnMetrics,
  TemplatePerformanceItem,
  ProviderHealthItem,
  PrivacyMetrics,
} from "@/services/analytics-service";
import {
  BarChart3,
  Users,
  DollarSign,
  Send,
  Zap,
  ShieldCheck,
  RefreshCw,
  TrendingUp,
  TrendingDown,
  AlertTriangle,
  Award,
  Layers,
  Calendar,
  Filter,
  Activity,
  CheckCircle2,
  XCircle,
  HelpCircle,
} from "lucide-react";

export default function DeepAnalyticsPage() {
  const { activePlatform } = useAuth(true);
  const [activeTab, setActiveTab] = useState<"players" | "finance" | "marketing" | "automations" | "privacy">("players");
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Filters
  const [period, setPeriod] = useState("30d");
  const [inactivityDays, setInactivityDays] = useState(30);

  // Tab 1: Players & Retention
  const [playerData, setPlayerData] = useState<any>(null);
  const [cohortData, setCohortData] = useState<CohortRow[]>([]);
  const [retentionCurve, setRetentionCurve] = useState<Record<string, number>>({});
  const [churnData, setChurnData] = useState<ChurnMetrics | null>(null);

  // Tab 2: Finance & Betting
  const [financeData, setFinanceData] = useState<any>(null);
  const [bettingData, setBettingData] = useState<any>(null);

  // Tab 3: Marketing & Funnels
  const [marketingData, setMarketingData] = useState<any>(null);
  const [templatePerformance, setTemplatePerformance] = useState<TemplatePerformanceItem[]>([]);

  // Tab 4: Automations & Providers
  const [automationData, setAutomationData] = useState<any>(null);
  const [providerHealth, setProviderHealth] = useState<ProviderHealthItem[]>([]);

  // Tab 5: Privacy & LGPD
  const [privacyData, setPrivacyData] = useState<PrivacyMetrics | null>(null);

  const loadTabData = useCallback(async (tab: string, isRefresh = false) => {
    if (isRefresh) setRefreshing(true);
    else setLoading(true);
    setError(null);

    try {
      if (tab === "players") {
        const [players, retention, churn] = await Promise.all([
          analyticsService.getPlayersAnalytics({ period, inactivity_days: inactivityDays }),
          analyticsService.getRetention(8),
          analyticsService.getChurn(inactivityDays),
        ]);
        setPlayerData(players);
        setCohortData(retention.cohort || []);
        setRetentionCurve(retention.curve || {});
        setChurnData(churn);
      } else if (tab === "finance") {
        const [fin, bet] = await Promise.all([
          analyticsService.getFinanceAnalytics({ period }),
          analyticsService.getBettingAnalytics({ period }),
        ]);
        setFinanceData(fin);
        setBettingData(bet);
      } else if (tab === "marketing") {
        const [mkt, tpls] = await Promise.all([
          analyticsService.getMarketingAnalytics({ period }),
          analyticsService.getTemplateAnalytics({ period }),
        ]);
        setMarketingData(mkt);
        setTemplatePerformance(tpls || []);
      } else if (tab === "automations") {
        const [auto, prov] = await Promise.all([
          analyticsService.getAutomationAnalytics({ period }),
          analyticsService.getProviderAnalytics({ period }),
        ]);
        setAutomationData(auto);
        setProviderHealth(prov || []);
      } else if (tab === "privacy") {
        const priv = await analyticsService.getPrivacyAnalytics({ period });
        setPrivacyData(priv);
      }
    } catch (err: any) {
      setError(err.message || "Erro ao carregar dados analíticos da aba.");
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [period, inactivityDays]);

  useEffect(() => {
    if (activePlatform) {
      loadTabData(activeTab);
    }
  }, [activePlatform, activeTab, loadTabData]);

  const formatCurrency = (val: number) => {
    return new Intl.NumberFormat("pt-BR", {
      style: "currency",
      currency: "BRL",
    }).format(val || 0);
  };

  const formatNumber = (val: number) => {
    return new Intl.NumberFormat("pt-BR").format(val || 0);
  };

  // Retention Cohort Color Helper
  const getCohortColor = (rate: number) => {
    if (rate >= 60) return "bg-emerald-500/30 text-emerald-300 font-bold border border-emerald-500/40";
    if (rate >= 40) return "bg-emerald-500/20 text-emerald-400";
    if (rate >= 20) return "bg-amber-500/20 text-amber-300";
    if (rate > 0) return "bg-rose-500/20 text-rose-300";
    return "bg-slate-900/60 text-slate-600";
  };

  return (
    <AppLayout
      title="Portal de Analytics Profundo"
      badge="ANÁLISE & COHORTS"
      actions={
        <div className="flex items-center space-x-2">
          <select
            value={period}
            onChange={(e) => setPeriod(e.target.value)}
            className="bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-emerald-500"
          >
            <option value="7d">Últimos 7 dias</option>
            <option value="30d">Últimos 30 dias</option>
            <option value="90d">Últimos 90 dias</option>
            <option value="this_month">Este Mês</option>
            <option value="last_month">Mês Anterior</option>
          </select>

          <button
            onClick={() => loadTabData(activeTab, true)}
            disabled={refreshing}
            className="p-1.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-lg text-slate-300 transition"
          >
            <RefreshCw className={`w-4 h-4 ${refreshing ? "animate-spin text-emerald-400" : ""}`} />
          </button>
        </div>
      }
    >
      <div className="p-6 space-y-6 max-w-7xl mx-auto">
        {/* Navigation Tabs */}
        <div className="flex border-b border-slate-800 space-x-2">
          <button
            onClick={() => setActiveTab("players")}
            className={`flex items-center space-x-2 px-4 py-3 text-xs font-bold border-b-2 transition ${
              activeTab === "players"
                ? "border-emerald-500 text-emerald-400 bg-emerald-500/10"
                : "border-transparent text-slate-400 hover:text-slate-200 hover:bg-slate-800/40"
            }`}
          >
            <Users className="w-4 h-4" />
            <span>Jogadores & Cohorts</span>
          </button>

          <button
            onClick={() => setActiveTab("finance")}
            className={`flex items-center space-x-2 px-4 py-3 text-xs font-bold border-b-2 transition ${
              activeTab === "finance"
                ? "border-emerald-500 text-emerald-400 bg-emerald-500/10"
                : "border-transparent text-slate-400 hover:text-slate-200 hover:bg-slate-800/40"
            }`}
          >
            <DollarSign className="w-4 h-4" />
            <span>Finanças & Apostas</span>
          </button>

          <button
            onClick={() => setActiveTab("marketing")}
            className={`flex items-center space-x-2 px-4 py-3 text-xs font-bold border-b-2 transition ${
              activeTab === "marketing"
                ? "border-emerald-500 text-emerald-400 bg-emerald-500/10"
                : "border-transparent text-slate-400 hover:text-slate-200 hover:bg-slate-800/40"
            }`}
          >
            <Send className="w-4 h-4" />
            <span>Funis & Canais</span>
          </button>

          <button
            onClick={() => setActiveTab("automations")}
            className={`flex items-center space-x-2 px-4 py-3 text-xs font-bold border-b-2 transition ${
              activeTab === "automations"
                ? "border-emerald-500 text-emerald-400 bg-emerald-500/10"
                : "border-transparent text-slate-400 hover:text-slate-200 hover:bg-slate-800/40"
            }`}
          >
            <Zap className="w-4 h-4" />
            <span>Automações & Provedores</span>
          </button>

          <button
            onClick={() => setActiveTab("privacy")}
            className={`flex items-center space-x-2 px-4 py-3 text-xs font-bold border-b-2 transition ${
              activeTab === "privacy"
                ? "border-emerald-500 text-emerald-400 bg-emerald-500/10"
                : "border-transparent text-slate-400 hover:text-slate-200 hover:bg-slate-800/40"
            }`}
          >
            <ShieldCheck className="w-4 h-4" />
            <span>Governança & LGPD</span>
          </button>
        </div>

        {/* Global Error Banner */}
        {error && (
          <div className="bg-rose-950/40 border border-rose-500/40 rounded-xl p-4 text-xs text-rose-300">
            {error}
          </div>
        )}

        {/* Loading Spinner */}
        {loading ? (
          <div className="min-h-[350px] flex flex-col items-center justify-center space-y-3 text-slate-400">
            <div className="w-8 h-8 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin" />
            <p className="text-xs font-medium">Carregando métricas analíticas...</p>
          </div>
        ) : (
          <>
            {/* TAB 1: PLAYERS & COHORTS */}
            {activeTab === "players" && (
              <div className="space-y-6">
                {/* Risk Classification & Churn Header */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  {/* Risk Classification Distribution */}
                  <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-5 space-y-4">
                    <h3 className="text-xs font-bold uppercase tracking-wider text-slate-300">
                      Classificação Operacional de Risco
                    </h3>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                      <div className="bg-emerald-950/20 border border-emerald-500/30 rounded-lg p-3 text-center">
                        <span className="text-[10px] text-emerald-400 font-bold block">ATIVO</span>
                        <span className="text-lg font-bold text-white">
                          {formatNumber(playerData?.risk_distribution?.ATIVO || 0)}
                        </span>
                        <span className="text-[9px] text-slate-500 block">&le; 7 dias sem log</span>
                      </div>

                      <div className="bg-amber-950/20 border border-amber-500/30 rounded-lg p-3 text-center">
                        <span className="text-[10px] text-amber-400 font-bold block">ATENÇÃO</span>
                        <span className="text-lg font-bold text-white">
                          {formatNumber(playerData?.risk_distribution?.ATENCAO || 0)}
                        </span>
                        <span className="text-[9px] text-slate-500 block">8 a 14 dias</span>
                      </div>

                      <div className="bg-rose-950/20 border border-rose-500/30 rounded-lg p-3 text-center">
                        <span className="text-[10px] text-rose-400 font-bold block">RISCO</span>
                        <span className="text-lg font-bold text-white">
                          {formatNumber(playerData?.risk_distribution?.RISCO || 0)}
                        </span>
                        <span className="text-[9px] text-slate-500 block">15 a 30 dias</span>
                      </div>

                      <div className="bg-slate-900 border border-slate-800 rounded-lg p-3 text-center">
                        <span className="text-[10px] text-slate-400 font-bold block">INATIVO</span>
                        <span className="text-lg font-bold text-slate-300">
                          {formatNumber(playerData?.risk_distribution?.INATIVO || 0)}
                        </span>
                        <span className="text-[9px] text-slate-500 block">&gt; 30 dias</span>
                      </div>
                    </div>
                  </div>

                  {/* Churn Threshold Simulator */}
                  <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-5 space-y-4">
                    <div className="flex items-center justify-between">
                      <h3 className="text-xs font-bold uppercase tracking-wider text-slate-300">
                        Análise de Churn Configurável
                      </h3>
                      <div className="flex items-center space-x-1">
                        {[7, 14, 30, 60].map((days) => (
                          <button
                            key={days}
                            onClick={() => setInactivityDays(days)}
                            className={`px-2 py-1 rounded text-[10px] font-bold transition ${
                              inactivityDays === days
                                ? "bg-emerald-500 text-slate-950 font-bold"
                                : "bg-slate-800 text-slate-400 hover:text-white"
                            }`}
                          >
                            {days}d
                          </button>
                        ))}
                      </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3 text-xs">
                      <div className="p-3 bg-slate-900/60 rounded-lg border border-slate-800 space-y-1">
                        <span className="text-[11px] text-slate-400">Taxa de Churn ({inactivityDays} dias)</span>
                        <div className="text-xl font-bold text-rose-400">
                          {churnData?.churn_rate || 0}%
                        </div>
                        <span className="text-[10px] text-slate-500">
                          {formatNumber(churnData?.churned_count || 0)} jogadores considerados churned
                        </span>
                      </div>

                      <div className="p-3 bg-slate-900/60 rounded-lg border border-slate-800 space-y-1">
                        <span className="text-[11px] text-slate-400">Taxa de Retenção Ativa</span>
                        <div className="text-xl font-bold text-emerald-400">
                          {churnData?.retention_rate || 0}%
                        </div>
                        <span className="text-[10px] text-slate-500">
                          {formatNumber(churnData?.active_count || 0)} jogadores engajados
                        </span>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Cohort Matrix D1 to D90 */}
                <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-5 space-y-4">
                  <div className="flex items-center justify-between">
                    <div>
                      <h3 className="text-xs font-bold uppercase tracking-wider text-slate-300">
                        Matriz de Retenção por Cohort Semanal (D1 a D90)
                      </h3>
                      <p className="text-[11px] text-slate-500">
                        Percentual de jogadores que retornaram à plataforma após o cadastro
                      </p>
                    </div>
                    <div className="flex items-center space-x-2 text-[10px] font-mono text-slate-400">
                      <span className="flex items-center space-x-1">
                        <span className="w-2.5 h-2.5 rounded bg-emerald-500/40 inline-block" />
                        <span>Alta (&ge;40%)</span>
                      </span>
                      <span className="flex items-center space-x-1">
                        <span className="w-2.5 h-2.5 rounded bg-amber-500/30 inline-block" />
                        <span>Média (&ge;20%)</span>
                      </span>
                      <span className="flex items-center space-x-1">
                        <span className="w-2.5 h-2.5 rounded bg-rose-500/30 inline-block" />
                        <span>Baixa (&lt;20%)</span>
                      </span>
                    </div>
                  </div>

                  {cohortData.length === 0 ? (
                    <div className="p-8 text-center text-xs text-slate-500">
                      Sem dados de cohort suficientes para compor a matriz semanal.
                    </div>
                  ) : (
                    <div className="overflow-x-auto">
                      <table className="w-full text-center text-xs font-mono">
                        <thead>
                          <tr className="border-b border-slate-800 text-[10px] text-slate-400 uppercase">
                            <th className="text-left pb-2">Semana do Cohort</th>
                            <th className="pb-2">Tamanho</th>
                            <th className="pb-2">D1</th>
                            <th className="pb-2">D7</th>
                            <th className="pb-2">D14</th>
                            <th className="pb-2">D30</th>
                            <th className="pb-2">D60</th>
                            <th className="pb-2">D90</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-800/40 text-[11px]">
                          {cohortData.map((row) => (
                            <tr key={row.cohort_week} className="hover:bg-slate-800/20">
                              <td className="text-left py-2.5 font-sans font-medium text-slate-300">
                                {row.cohort_week}
                              </td>
                              <td className="py-2.5 font-bold text-white">
                                {formatNumber(row.size)}
                              </td>
                              <td className="py-2.5 px-1">
                                <span className={`px-2 py-1 rounded ${getCohortColor(row.periods.D1)}`}>
                                  {row.periods.D1}%
                                </span>
                              </td>
                              <td className="py-2.5 px-1">
                                <span className={`px-2 py-1 rounded ${getCohortColor(row.periods.D7)}`}>
                                  {row.periods.D7}%
                                </span>
                              </td>
                              <td className="py-2.5 px-1">
                                <span className={`px-2 py-1 rounded ${getCohortColor(row.periods.D14)}`}>
                                  {row.periods.D14}%
                                </span>
                              </td>
                              <td className="py-2.5 px-1">
                                <span className={`px-2 py-1 rounded ${getCohortColor(row.periods.D30)}`}>
                                  {row.periods.D30}%
                                </span>
                              </td>
                              <td className="py-2.5 px-1">
                                <span className={`px-2 py-1 rounded ${getCohortColor(row.periods.D60)}`}>
                                  {row.periods.D60}%
                                </span>
                              </td>
                              <td className="py-2.5 px-1">
                                <span className={`px-2 py-1 rounded ${getCohortColor(row.periods.D90)}`}>
                                  {row.periods.D90}%
                                </span>
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  )}
                </div>
              </div>
            )}

            {/* TAB 2: FINANCE & BETTING */}
            {activeTab === "finance" && (
              <div className="space-y-6">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-5 space-y-3">
                    <span className="text-xs text-slate-400">Total Depositado no Período</span>
                    <div className="text-2xl font-bold text-emerald-400">
                      {formatCurrency(financeData?.summary?.total_deposits_amount || 0)}
                    </div>
                    <div className="text-[11px] text-slate-500">
                      {formatNumber(financeData?.summary?.total_deposits_count || 0)} depósitos concluídos
                    </div>
                  </div>

                  <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-5 space-y-3">
                    <span className="text-xs text-slate-400">Total de Saques no Período</span>
                    <div className="text-2xl font-bold text-slate-200">
                      {formatCurrency(financeData?.summary?.total_withdrawals_amount || 0)}
                    </div>
                    <div className="text-[11px] text-slate-500">
                      {formatNumber(financeData?.summary?.total_withdrawals_count || 0)} saques efetivados
                    </div>
                  </div>

                  <div className="bg-[#0d131f] border border-emerald-500/20 rounded-xl p-5 space-y-3 bg-gradient-to-br from-emerald-950/20 to-transparent">
                    <span className="text-xs text-emerald-300 font-semibold">GGR / Saldo Operacional Líquido</span>
                    <div className="text-2xl font-bold text-emerald-400">
                      {formatCurrency(financeData?.summary?.net_balance || 0)}
                    </div>
                    <div className="text-[11px] text-slate-500">
                      Ticket Médio: {formatCurrency(financeData?.summary?.average_deposit_ticket || 0)}
                    </div>
                  </div>
                </div>

                {/* Depositors Breakdown */}
                <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-5 space-y-4">
                  <h3 className="text-xs font-bold uppercase tracking-wider text-slate-300">
                    Comportamento de Depositantes
                  </h3>
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="p-4 bg-slate-900/60 rounded-lg border border-slate-800 space-y-1">
                      <span className="text-xs text-slate-400">Depositantes Únicos</span>
                      <div className="text-lg font-bold text-white">
                        {formatNumber(financeData?.summary?.unique_depositors || 0)}
                      </div>
                    </div>
                    <div className="p-4 bg-slate-900/60 rounded-lg border border-slate-800 space-y-1">
                      <span className="text-xs text-slate-400">Primeiros Depósitos (FTDs)</span>
                      <div className="text-lg font-bold text-indigo-400">
                        {formatNumber(financeData?.summary?.first_time_depositors || 0)}
                      </div>
                    </div>
                    <div className="p-4 bg-slate-900/60 rounded-lg border border-slate-800 space-y-1">
                      <span className="text-xs text-slate-400">Depositantes Recorrentes</span>
                      <div className="text-lg font-bold text-cyan-400">
                        {formatNumber(financeData?.summary?.recurring_depositors || 0)}
                      </div>
                    </div>
                  </div>
                </div>

                {/* Betting Section */}
                <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-5 space-y-4">
                  <h3 className="text-xs font-bold uppercase tracking-wider text-slate-300">
                    Métricas de Apostas e Jogos
                  </h3>

                  {!bettingData?.has_data ? (
                    <div className="p-6 text-center text-xs text-slate-500 border border-dashed border-slate-800 rounded-xl">
                      Sem dados de apostas registrados para este período.
                    </div>
                  ) : (
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                      <div className="p-3 bg-slate-900/60 rounded-lg border border-slate-800">
                        <span className="text-xs text-slate-400">Turnover</span>
                        <div className="text-lg font-bold text-purple-400">
                          {formatCurrency(bettingData?.turnover?.current || 0)}
                        </div>
                      </div>
                      <div className="p-3 bg-slate-900/60 rounded-lg border border-slate-800">
                        <span className="text-xs text-slate-400">Apostas Feitas</span>
                        <div className="text-lg font-bold text-white">
                          {formatNumber(bettingData?.total_bets?.current || 0)}
                        </div>
                      </div>
                      <div className="p-3 bg-slate-900/60 rounded-lg border border-slate-800">
                        <span className="text-xs text-slate-400">Apostas Ganhas</span>
                        <div className="text-lg font-bold text-emerald-400">
                          {formatNumber(bettingData?.total_won?.current || 0)}
                        </div>
                      </div>
                      <div className="p-3 bg-slate-900/60 rounded-lg border border-slate-800">
                        <span className="text-xs text-slate-400">Win Rate</span>
                        <div className="text-lg font-bold text-amber-400">
                          {bettingData?.win_rate?.current || 0}%
                        </div>
                      </div>
                    </div>
                  )}
                </div>
              </div>
            )}

            {/* TAB 3: MARKETING & FUNNELS */}
            {activeTab === "marketing" && (
              <div className="space-y-6">
                {/* Channel Comparison */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-5 space-y-3">
                    <div className="flex items-center justify-between">
                      <h4 className="text-xs font-bold text-indigo-400 uppercase">Canal: E-mail</h4>
                      <span className="text-[10px] text-slate-500">Desempenho</span>
                    </div>
                    <div className="grid grid-cols-3 gap-2 text-center text-xs">
                      <div className="p-2 bg-slate-900 rounded border border-slate-800">
                        <span className="text-[10px] text-slate-400 block">Envios</span>
                        <span className="font-bold text-white">
                          {formatNumber(marketingData?.by_channel?.EMAIL?.sent || 0)}
                        </span>
                      </div>
                      <div className="p-2 bg-slate-900 rounded border border-slate-800">
                        <span className="text-[10px] text-slate-400 block">Abertura</span>
                        <span className="font-bold text-emerald-400">
                          {marketingData?.by_channel?.EMAIL?.open_rate || 0}%
                        </span>
                      </div>
                      <div className="p-2 bg-slate-900 rounded border border-slate-800">
                        <span className="text-[10px] text-slate-400 block">CTR</span>
                        <span className="font-bold text-cyan-400">
                          {marketingData?.by_channel?.EMAIL?.ctr || 0}%
                        </span>
                      </div>
                    </div>
                  </div>

                  <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-5 space-y-3">
                    <div className="flex items-center justify-between">
                      <h4 className="text-xs font-bold text-cyan-400 uppercase">Canal: SMS</h4>
                      <span className="text-[10px] text-slate-500">Desempenho</span>
                    </div>
                    <div className="grid grid-cols-3 gap-2 text-center text-xs">
                      <div className="p-2 bg-slate-900 rounded border border-slate-800">
                        <span className="text-[10px] text-slate-400 block">Envios</span>
                        <span className="font-bold text-white">
                          {formatNumber(marketingData?.by_channel?.SMS?.sent || 0)}
                        </span>
                      </div>
                      <div className="p-2 bg-slate-900 rounded border border-slate-800">
                        <span className="text-[10px] text-slate-400 block">Entrega</span>
                        <span className="font-bold text-emerald-400">
                          {marketingData?.by_channel?.SMS?.delivery_rate || 0}%
                        </span>
                      </div>
                      <div className="p-2 bg-slate-900 rounded border border-slate-800">
                        <span className="text-[10px] text-slate-400 block">Cliques</span>
                        <span className="font-bold text-cyan-400">
                          {formatNumber(marketingData?.by_channel?.SMS?.clicks || 0)}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Templates Performance Table */}
                <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-5 space-y-4">
                  <h3 className="text-xs font-bold uppercase tracking-wider text-slate-300">
                    Performance Comparativa de Templates
                  </h3>

                  {templatePerformance.length === 0 ? (
                    <div className="p-6 text-center text-xs text-slate-500">
                      Nenhum template com disparos registrados neste período.
                    </div>
                  ) : (
                    <div className="overflow-x-auto">
                      <table className="w-full text-left text-xs">
                        <thead>
                          <tr className="border-b border-slate-800 text-[10px] text-slate-400 uppercase font-mono">
                            <th className="pb-2">Template</th>
                            <th className="pb-2">Canal</th>
                            <th className="pb-2 text-right">Enviadas</th>
                            <th className="pb-2 text-right">Entregues</th>
                            <th className="pb-2 text-right">Aberturas</th>
                            <th className="pb-2 text-right">Cliques</th>
                            <th className="pb-2 text-right">Open Rate</th>
                            <th className="pb-2 text-right">CTR</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-800/60 font-mono text-[11px]">
                          {templatePerformance.map((tpl) => (
                            <tr key={tpl.id} className="hover:bg-slate-800/20">
                              <td className="py-2.5 font-sans font-medium text-slate-200">{tpl.name}</td>
                              <td className="py-2.5">
                                <span className={`px-1.5 py-0.5 rounded text-[10px] font-bold ${
                                  tpl.channel === "EMAIL" ? "bg-indigo-500/20 text-indigo-300" : "bg-cyan-500/20 text-cyan-300"
                                }`}>
                                  {tpl.channel}
                                </span>
                              </td>
                              <td className="py-2.5 text-right text-slate-300">{formatNumber(tpl.total_sent)}</td>
                              <td className="py-2.5 text-right text-emerald-400">{formatNumber(tpl.total_delivered)}</td>
                              <td className="py-2.5 text-right text-white">{formatNumber(tpl.total_opened)}</td>
                              <td className="py-2.5 text-right text-cyan-400">{formatNumber(tpl.total_clicked)}</td>
                              <td className="py-2.5 text-right text-emerald-400 font-bold">{tpl.open_rate}%</td>
                              <td className="py-2.5 text-right text-cyan-400 font-bold">{tpl.click_rate}%</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  )}
                </div>
              </div>
            )}

            {/* TAB 4: AUTOMATIONS & PROVIDERS */}
            {activeTab === "automations" && (
              <div className="space-y-6">
                {/* Automations KPIs */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                  <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-4 space-y-1">
                    <span className="text-xs text-slate-400">Jornadas Ativas</span>
                    <div className="text-xl font-bold text-white">
                      {formatNumber(automationData?.active_count || 0)}
                    </div>
                  </div>
                  <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-4 space-y-1">
                    <span className="text-xs text-slate-400">Total de Execuções</span>
                    <div className="text-xl font-bold text-indigo-400">
                      {formatNumber(automationData?.total_runs || 0)}
                    </div>
                  </div>
                  <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-4 space-y-1">
                    <span className="text-xs text-slate-400">Concluídas com Sucesso</span>
                    <div className="text-xl font-bold text-emerald-400">
                      {formatNumber(automationData?.completed_runs || 0)}
                    </div>
                  </div>
                  <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-4 space-y-1">
                    <span className="text-xs text-slate-400">Taxa de Sucesso</span>
                    <div className="text-xl font-bold text-cyan-400">
                      {automationData?.success_rate || 0}%
                    </div>
                  </div>
                </div>

                {/* Providers Health Table */}
                <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-5 space-y-4">
                  <h3 className="text-xs font-bold uppercase tracking-wider text-slate-300">
                    Saúde Operacional & Circuit Breakers dos Provedores
                  </h3>

                  {providerHealth.length === 0 ? (
                    <div className="p-6 text-center text-xs text-slate-500">
                      Nenhum provedor configurado para esta plataforma.
                    </div>
                  ) : (
                    <div className="overflow-x-auto">
                      <table className="w-full text-left text-xs">
                        <thead>
                          <tr className="border-b border-slate-800 text-[10px] text-slate-400 uppercase font-mono">
                            <th className="pb-2">Provedor</th>
                            <th className="pb-2">Canal</th>
                            <th className="pb-2">Driver</th>
                            <th className="pb-2">Saúde</th>
                            <th className="pb-2">Circuit Breaker</th>
                            <th className="pb-2 text-right">Taxa Sucesso</th>
                            <th className="pb-2 text-right">Latência Média</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-800/60 font-mono text-[11px]">
                          {providerHealth.map((prov) => (
                            <tr key={prov.id} className="hover:bg-slate-800/20">
                              <td className="py-2.5 font-sans font-medium text-slate-200">{prov.name}</td>
                              <td className="py-2.5 text-slate-400">{prov.channel}</td>
                              <td className="py-2.5 text-slate-400">{prov.driver}</td>
                              <td className="py-2.5">
                                <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${
                                  prov.health_status === "ACTIVE"
                                    ? "bg-emerald-500/20 text-emerald-400"
                                    : "bg-rose-500/20 text-rose-400"
                                }`}>
                                  {prov.health_status}
                                </span>
                              </td>
                              <td className="py-2.5">
                                <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${
                                  prov.circuit_breaker_status === "CLOSED"
                                    ? "bg-slate-800 text-slate-300"
                                    : "bg-rose-500/30 text-rose-300 animate-pulse"
                                }`}>
                                  {prov.circuit_breaker_status}
                                </span>
                              </td>
                              <td className="py-2.5 text-right text-emerald-400 font-bold">{prov.success_rate}%</td>
                              <td className="py-2.5 text-right text-slate-300">{prov.average_latency_ms} ms</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  )}
                </div>
              </div>
            )}

            {/* TAB 5: PRIVACY & LGPD */}
            {activeTab === "privacy" && (
              <div className="space-y-6">
                <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
                  <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-4 space-y-1">
                    <span className="text-xs text-slate-400">Consentimentos Ativos</span>
                    <div className="text-xl font-bold text-emerald-400">
                      {formatNumber(privacyData?.consents?.active || 0)}
                    </div>
                    <span className="text-[10px] text-slate-500">
                      Taxa de adesão: {privacyData?.consents?.grant_rate || 0}%
                    </span>
                  </div>

                  <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-4 space-y-1">
                    <span className="text-xs text-slate-400">Solicitações DSR Abertas</span>
                    <div className="text-xl font-bold text-amber-400">
                      {formatNumber(privacyData?.requests?.open || 0)}
                    </div>
                    <span className="text-[10px] text-slate-500">
                      {privacyData?.requests?.near_sla || 0} próximas do limite SLA
                    </span>
                  </div>

                  <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-4 space-y-1">
                    <span className="text-xs text-slate-400">Jogadores Anonimizados</span>
                    <div className="text-xl font-bold text-slate-200">
                      {formatNumber(privacyData?.anonymized_players || 0)}
                    </div>
                    <span className="text-[10px] text-slate-500">Exclusão irreversível</span>
                  </div>

                  <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-4 space-y-1">
                    <span className="text-xs text-slate-400">Trilhas de Auditoria</span>
                    <div className="text-xl font-bold text-cyan-400">
                      {formatNumber(privacyData?.audit_logs_count || 0)}
                    </div>
                    <span className="text-[10px] text-slate-500">Registros imutáveis</span>
                  </div>
                </div>

                <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-5 space-y-3">
                  <h4 className="text-xs font-bold uppercase tracking-wider text-slate-300">
                    Conformidade e Governança de Dados
                  </h4>
                  <p className="text-xs text-slate-400 leading-relaxed">
                    O BET CRM opera em conformidade estrita com a LGPD (Lei nº 13.709/2018). As solicitações dos titulares (DSR) possuem contagem regressiva de SLA, logs de auditoria detalhados e sanitização de dados pessoais em exportações analíticas para perfis operacionais.
                  </p>
                  <div className="pt-2">
                    <Link
                      href="/privacy"
                      className="text-xs font-semibold px-3 py-1.5 bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500/30 border border-emerald-500/30 rounded-lg inline-flex items-center space-x-1 transition"
                    >
                      <span>Abrir Painel Completo de Privacidade & LGPD</span>
                    </Link>
                  </div>
                </div>
              </div>
            )}
          </>
        )}
      </div>
    </AppLayout>
  );
}
