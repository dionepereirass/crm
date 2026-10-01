"use client";

import React, { useState, useEffect, useCallback } from "react";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import { useAuth } from "@/hooks/use-auth";
import {
  analyticsService,
  DashboardData,
  KpiItem,
} from "@/services/analytics-service";
import {
  Users,
  DollarSign,
  TrendingUp,
  TrendingDown,
  Send,
  Zap,
  RefreshCw,
  AlertTriangle,
  ArrowRight,
  ShieldAlert,
  CheckCircle2,
  Calendar,
  Layers,
  FileSpreadsheet,
  Activity,
  Award,
  Sparkles,
} from "lucide-react";

export default function DashboardPage() {
  const { activePlatform } = useAuth(true);
  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Filters
  const [period, setPeriod] = useState("30d");
  const [grouping, setGrouping] = useState("day");

  const loadDashboard = useCallback(async (isRefresh = false) => {
    if (isRefresh) setRefreshing(true);
    else setLoading(true);
    setError(null);

    try {
      const res = await analyticsService.getDashboard({
        period,
        grouping,
      });
      setData(res);
    } catch (err: any) {
      setError(err.message || "Erro ao carregar dados consolidados do Dashboard.");
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [period, grouping]);

  useEffect(() => {
    if (activePlatform) {
      loadDashboard();
    }
  }, [activePlatform, loadDashboard]);

  const handleInvalidateCache = async () => {
    try {
      setRefreshing(true);
      await analyticsService.invalidateCache();
      await loadDashboard(true);
    } catch (err: any) {
      setError(err.message || "Falha ao limpar cache.");
    } finally {
      setRefreshing(false);
    }
  };

  const formatCurrency = (val: number) => {
    return new Intl.NumberFormat("pt-BR", {
      style: "currency",
      currency: "BRL",
    }).format(val || 0);
  };

  const formatNumber = (val: number) => {
    return new Intl.NumberFormat("pt-BR").format(val || 0);
  };

  const renderBadge = (kpi: KpiItem | { percentage_change: number }) => {
    const change = kpi?.percentage_change ?? 0;
    const isPositive = change > 0;
    const isZero = change === 0;

    return (
      <span
        className={`inline-flex items-center text-[10px] font-bold px-1.5 py-0.5 rounded-full ${
          isZero
            ? "bg-slate-800 text-slate-400"
            : isPositive
            ? "bg-emerald-500/10 text-emerald-400 border border-emerald-500/30"
            : "bg-rose-500/10 text-rose-400 border border-rose-500/30"
        }`}
      >
        {isZero ? (
          "0%"
        ) : isPositive ? (
          <>
            <TrendingUp className="w-2.5 h-2.5 mr-0.5" />
            +{change}%
          </>
        ) : (
          <>
            <TrendingDown className="w-2.5 h-2.5 mr-0.5" />
            {change}%
          </>
        )}
      </span>
    );
  };

  const kpis = data?.kpis;
  const activeAlerts = data?.active_alerts || [];

  return (
    <AppLayout
      title="Dashboard Executivo"
      badge="INTELIGÊNCIA OPERACIONAL"
      actions={
        <div className="flex items-center space-x-2">
          <select
            value={period}
            onChange={(e) => setPeriod(e.target.value)}
            className="bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-emerald-500"
          >
            <option value="today">Hoje</option>
            <option value="yesterday">Ontem</option>
            <option value="7d">Últimos 7 dias</option>
            <option value="30d">Últimos 30 dias</option>
            <option value="90d">Últimos 90 dias</option>
            <option value="this_month">Este Mês</option>
            <option value="last_month">Mês Anterior</option>
          </select>

          <select
            value={grouping}
            onChange={(e) => setGrouping(e.target.value)}
            className="bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-emerald-500"
          >
            <option value="day">Agrupamento: Diário</option>
            <option value="week">Agrupamento: Semanal</option>
            <option value="month">Agrupamento: Mensal</option>
          </select>

          <button
            onClick={() => loadDashboard(true)}
            disabled={refreshing}
            title="Atualizar dados"
            className="p-1.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-lg text-slate-300 transition"
          >
            <RefreshCw className={`w-4 h-4 ${refreshing ? "animate-spin text-emerald-400" : ""}`} />
          </button>

          <button
            onClick={handleInvalidateCache}
            disabled={refreshing}
            className="text-[11px] font-medium px-2.5 py-1.5 bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 rounded-lg transition"
          >
            Limpar Cache
          </button>
        </div>
      }
    >
      <div className="p-6 space-y-6 max-w-7xl mx-auto">
        {/* Active Operational Alerts Banner */}
        {activeAlerts.length > 0 && (
          <div className="bg-amber-950/30 border border-amber-500/40 rounded-xl p-4 flex items-center justify-between">
            <div className="flex items-center space-x-3">
              <div className="w-10 h-10 rounded-lg bg-amber-500/20 border border-amber-500/40 flex items-center justify-center shrink-0">
                <AlertTriangle className="w-5 h-5 text-amber-400" />
              </div>
              <div>
                <h4 className="text-sm font-bold text-amber-200 flex items-center space-x-2">
                  <span>{activeAlerts.length} Alerta(s) Operacional(is) Ativo(s)</span>
                  <span className="text-[10px] px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 font-mono">
                    Requer Atenção
                  </span>
                </h4>
                <p className="text-xs text-amber-300/80 mt-0.5">
                  {activeAlerts[0]?.title}: {activeAlerts[0]?.message}
                </p>
              </div>
            </div>
            <Link
              href="/alerts"
              className="text-xs font-semibold px-3 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 rounded-lg transition flex items-center space-x-1 shrink-0 ml-4"
            >
              <span>Gerenciar Alertas</span>
              <ArrowRight className="w-3.5 h-3.5" />
            </Link>
          </div>
        )}

        {/* Global Error Banner */}
        {error && (
          <div className="bg-rose-950/40 border border-rose-500/40 rounded-xl p-4 text-xs text-rose-300 flex items-center space-x-2">
            <ShieldAlert className="w-4 h-4 text-rose-400 shrink-0" />
            <span>{error}</span>
          </div>
        )}

        {/* Loading Spinner */}
        {loading ? (
          <div className="min-h-[400px] flex flex-col items-center justify-center space-y-3 text-slate-400">
            <div className="w-8 h-8 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin" />
            <p className="text-xs font-medium">Consolidando inteligência analítica em tempo real...</p>
          </div>
        ) : !kpis ? (
          <div className="min-h-[300px] flex flex-col items-center justify-center text-slate-400 space-y-2 bg-[#0d131f] border border-slate-800 rounded-2xl p-8">
            <Activity className="w-10 h-10 text-slate-600" />
            <p className="text-sm font-semibold text-slate-300">Sem dados suficientes para o período selecionado.</p>
            <p className="text-xs text-slate-500">Tente expandir o filtro para 30 ou 90 dias ou verificar eventos registrados.</p>
          </div>
        ) : (
          <>
            {/* Quick Navigation Cards */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
              <Link
                href="/analytics"
                className="p-3 bg-[#0d131f] hover:bg-[#131b2e] border border-slate-800 hover:border-slate-700 rounded-xl transition flex items-center space-x-3 group"
              >
                <div className="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 group-hover:scale-105 transition">
                  <Activity className="w-4 h-4" />
                </div>
                <div>
                  <span className="block text-xs font-bold text-white">Analytics Profundo</span>
                  <span className="block text-[10px] text-slate-400">Cohorts, funis e canais</span>
                </div>
              </Link>

              <Link
                href="/reports"
                className="p-3 bg-[#0d131f] hover:bg-[#131b2e] border border-slate-800 hover:border-slate-700 rounded-xl transition flex items-center space-x-3 group"
              >
                <div className="w-8 h-8 rounded-lg bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400 group-hover:scale-105 transition">
                  <FileSpreadsheet className="w-4 h-4" />
                </div>
                <div>
                  <span className="block text-xs font-bold text-white">Relatórios & Export</span>
                  <span className="block text-[10px] text-slate-400">CSV e agendamentos</span>
                </div>
              </Link>

              <Link
                href="/alerts"
                className="p-3 bg-[#0d131f] hover:bg-[#131b2e] border border-slate-800 hover:border-slate-700 rounded-xl transition flex items-center space-x-3 group"
              >
                <div className="w-8 h-8 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 group-hover:scale-105 transition">
                  <AlertTriangle className="w-4 h-4" />
                </div>
                <div>
                  <span className="block text-xs font-bold text-white">Motor de Alertas</span>
                  <span className="block text-[10px] text-slate-400">Monitoramento e regras</span>
                </div>
              </Link>

              <Link
                href="/campaigns"
                className="p-3 bg-[#0d131f] hover:bg-[#131b2e] border border-slate-800 hover:border-slate-700 rounded-xl transition flex items-center space-x-3 group"
              >
                <div className="w-8 h-8 rounded-lg bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 group-hover:scale-105 transition">
                  <Send className="w-4 h-4" />
                </div>
                <div>
                  <span className="block text-xs font-bold text-white">Campanhas Ativas</span>
                  <span className="block text-[10px] text-slate-400">Disparos e réguas</span>
                </div>
              </Link>
            </div>

            {/* GROUP 1: FINANCIAL & GGR (Real Database Data) */}
            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center space-x-1.5">
                  <DollarSign className="w-3.5 h-3.5 text-emerald-400" />
                  <span>Performance Financeira (Eventos Validados)</span>
                </h3>
                <span className="text-[10px] text-slate-500 font-mono">
                  Período: {data?.period?.from} até {data?.period?.to}
                </span>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3">
                <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-4 space-y-2">
                  <div className="flex items-center justify-between text-slate-400 text-xs">
                    <span>Total Depositado</span>
                    {renderBadge(kpis.financial.total_deposits_amount)}
                  </div>
                  <div className="text-lg font-bold text-emerald-400">
                    {formatCurrency(kpis.financial.total_deposits_amount.current)}
                  </div>
                  <div className="text-[10px] text-slate-500 flex justify-between">
                    <span>{formatNumber(kpis.financial.total_deposits_count.current)} depósitos</span>
                    <span>Anterior: {formatCurrency(kpis.financial.total_deposits_amount.previous)}</span>
                  </div>
                </div>

                <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-4 space-y-2">
                  <div className="flex items-center justify-between text-slate-400 text-xs">
                    <span>Total Saques</span>
                    {renderBadge(kpis.financial.total_withdrawals_amount)}
                  </div>
                  <div className="text-lg font-bold text-slate-200">
                    {formatCurrency(kpis.financial.total_withdrawals_amount.current)}
                  </div>
                  <div className="text-[10px] text-slate-500 flex justify-between">
                    <span>{formatNumber(kpis.financial.total_withdrawals_count.current)} saques</span>
                    <span>Anterior: {formatCurrency(kpis.financial.total_withdrawals_amount.previous)}</span>
                  </div>
                </div>

                <div className="bg-[#0d131f] border border-emerald-500/20 rounded-xl p-4 space-y-2 bg-gradient-to-br from-emerald-950/20 to-transparent">
                  <div className="flex items-center justify-between text-slate-400 text-xs">
                    <span className="text-emerald-300 font-semibold">Saldo Líquido</span>
                    {renderBadge(kpis.financial.net_balance)}
                  </div>
                  <div className={`text-lg font-bold ${kpis.financial.net_balance.current >= 0 ? "text-emerald-400" : "text-rose-400"}`}>
                    {formatCurrency(kpis.financial.net_balance.current)}
                  </div>
                  <div className="text-[10px] text-slate-500">
                    Depósitos - Saques homologados
                  </div>
                </div>

                <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-4 space-y-2">
                  <div className="flex items-center justify-between text-slate-400 text-xs">
                    <span>Ticket Médio Depósito</span>
                    {renderBadge(kpis.financial.average_deposit_ticket)}
                  </div>
                  <div className="text-lg font-bold text-slate-200">
                    {formatCurrency(kpis.financial.average_deposit_ticket.current)}
                  </div>
                  <div className="text-[10px] text-slate-500">
                    Por transação aprovada
                  </div>
                </div>

                <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-4 space-y-2">
                  <div className="flex items-center justify-between text-slate-400 text-xs">
                    <span>Novos Depositantes (FTD)</span>
                    {renderBadge(kpis.financial.first_time_depositors)}
                  </div>
                  <div className="text-lg font-bold text-indigo-400">
                    {formatNumber(kpis.financial.first_time_depositors.current)}
                  </div>
                  <div className="text-[10px] text-slate-500">
                    Primeiro depósito no período
                  </div>
                </div>
              </div>
            </div>

            {/* GROUP 2: PLAYERS & CHURN */}
            <div className="space-y-3">
              <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center space-x-1.5">
                <Users className="w-3.5 h-3.5 text-cyan-400" />
                <span>Base de Jogadores & Retenção</span>
              </h3>

              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3">
                <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-4 space-y-2">
                  <div className="flex items-center justify-between text-slate-400 text-xs">
                    <span>Total Cadastrados</span>
                    {renderBadge(kpis.players.total_players)}
                  </div>
                  <div className="text-lg font-bold text-white">
                    {formatNumber(kpis.players.total_players.current)}
                  </div>
                  <div className="text-[10px] text-slate-500">
                    Base total da plataforma
                  </div>
                </div>

                <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-4 space-y-2">
                  <div className="flex items-center justify-between text-slate-400 text-xs">
                    <span>Novos Jogadores</span>
                    {renderBadge(kpis.players.new_players)}
                  </div>
                  <div className="text-lg font-bold text-cyan-400">
                    {formatNumber(kpis.players.new_players.current)}
                  </div>
                  <div className="text-[10px] text-slate-500">
                    Cadastrados no período
                  </div>
                </div>

                <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-4 space-y-2">
                  <div className="flex items-center justify-between text-slate-400 text-xs">
                    <span>Jogadores Ativos</span>
                    {renderBadge(kpis.players.active_players)}
                  </div>
                  <div className="text-lg font-bold text-emerald-400">
                    {formatNumber(kpis.players.active_players.current)}
                  </div>
                  <div className="text-[10px] text-slate-500">
                    Com eventos recentes
                  </div>
                </div>

                <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-4 space-y-2">
                  <div className="flex items-center justify-between text-slate-400 text-xs">
                    <span>Jogadores Inativos</span>
                    {renderBadge(kpis.players.inactive_players)}
                  </div>
                  <div className="text-lg font-bold text-slate-400">
                    {formatNumber(kpis.players.inactive_players.current)}
                  </div>
                  <div className="text-[10px] text-slate-500">
                    Sem atividade recente
                  </div>
                </div>

                <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-4 space-y-2">
                  <div className="flex items-center justify-between text-slate-400 text-xs">
                    <span>Taxa de Churn Estimada</span>
                    {renderBadge(kpis.players.churn_rate)}
                  </div>
                  <div className="text-lg font-bold text-amber-400">
                    {kpis.players.churn_rate.current}%
                  </div>
                  <div className="text-[10px] text-slate-500">
                    Critério 30 dias de inatividade
                  </div>
                </div>
              </div>
            </div>

            {/* GROUP 3: BETTING / APOSTAS (Real Database vs Clean Empty State) */}
            <div className="space-y-3">
              <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center space-x-1.5">
                <Award className="w-3.5 h-3.5 text-purple-400" />
                <span>Atividade de Apostas</span>
              </h3>

              {!kpis.betting.has_data ? (
                <div className="bg-[#0d131f] border border-dashed border-slate-800 rounded-xl p-6 text-center text-slate-400 space-y-1">
                  <p className="text-xs font-medium text-slate-300">
                    Sem dados suficientes de apostas para o período selecionado.
                  </p>
                  <p className="text-[11px] text-slate-500">
                    Eventos do tipo BET_PLACED ou BET_SETTLED aparecerão aqui assim que forem ingeridos pela plataforma.
                  </p>
                </div>
              ) : (
                <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
                  <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-4 space-y-1">
                    <span className="text-xs text-slate-400">Turnover (Volume)</span>
                    <div className="text-lg font-bold text-purple-400">
                      {formatCurrency(kpis.betting.turnover.current)}
                    </div>
                  </div>
                  <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-4 space-y-1">
                    <span className="text-xs text-slate-400">Total de Apostas</span>
                    <div className="text-lg font-bold text-white">
                      {formatNumber(kpis.betting.total_bets.current)}
                    </div>
                  </div>
                  <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-4 space-y-1">
                    <span className="text-xs text-slate-400">Apostas Ganhas / Perdidas</span>
                    <div className="text-sm font-bold text-slate-200">
                      <span className="text-emerald-400">{formatNumber(kpis.betting.total_won.current)}</span> /{" "}
                      <span className="text-rose-400">{formatNumber(kpis.betting.total_lost.current)}</span>
                    </div>
                  </div>
                  <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-4 space-y-1">
                    <span className="text-xs text-slate-400">Win Rate</span>
                    <div className="text-lg font-bold text-amber-400">
                      {kpis.betting.win_rate.current}%
                    </div>
                  </div>
                </div>
              )}
            </div>

            {/* GROUP 4: MARKETING & CAMPAIGNS FUNNEL */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
              {/* Marketing KPIs Cards */}
              <div className="lg:col-span-2 space-y-3">
                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center space-x-1.5">
                  <Send className="w-3.5 h-3.5 text-indigo-400" />
                  <span>Mensageria & Campanhas</span>
                </h3>

                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                  <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-3 space-y-1">
                    <span className="text-[11px] text-slate-400">Campanhas</span>
                    <div className="text-base font-bold text-white">
                      {formatNumber(kpis.marketing.campaigns_count.current)}
                    </div>
                  </div>

                  <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-3 space-y-1">
                    <span className="text-[11px] text-slate-400">Enviadas</span>
                    <div className="text-base font-bold text-indigo-400">
                      {formatNumber(kpis.marketing.messages_sent.current)}
                    </div>
                  </div>

                  <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-3 space-y-1">
                    <span className="text-[11px] text-slate-400">Taxa de Entrega</span>
                    <div className="text-base font-bold text-emerald-400">
                      {kpis.marketing.delivery_rate.current}%
                    </div>
                  </div>

                  <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-3 space-y-1">
                    <span className="text-[11px] text-slate-400">CTR (Click Through)</span>
                    <div className="text-base font-bold text-cyan-400">
                      {kpis.marketing.ctr.current}%
                    </div>
                  </div>
                </div>

                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                  <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-3 space-y-1">
                    <span className="text-[11px] text-slate-400">Aberturas</span>
                    <div className="text-base font-bold text-emerald-400">
                      {formatNumber(kpis.marketing.messages_opened.current)}
                    </div>
                    <span className="text-[10px] text-slate-500">{kpis.marketing.open_rate.current}% open rate</span>
                  </div>

                  <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-3 space-y-1">
                    <span className="text-[11px] text-slate-400">Cliques</span>
                    <div className="text-base font-bold text-cyan-400">
                      {formatNumber(kpis.marketing.messages_clicked.current)}
                    </div>
                    <span className="text-[10px] text-slate-500">{kpis.marketing.click_rate.current}% click rate</span>
                  </div>

                  <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-3 space-y-1">
                    <span className="text-[11px] text-slate-400">Descadastros</span>
                    <div className="text-base font-bold text-slate-300">
                      {formatNumber(kpis.marketing.unsubscribes.current)}
                    </div>
                    <span className="text-[10px] text-slate-500">Opt-outs</span>
                  </div>

                  <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-3 space-y-1">
                    <span className="text-[11px] text-slate-400">Provedores</span>
                    <div className="text-base font-bold text-emerald-400 flex items-center space-x-1">
                      <span>{kpis.providers.active_providers}</span>
                      <span className="text-xs text-slate-500">/ {kpis.providers.total_providers}</span>
                    </div>
                    <span className="text-[10px] text-slate-500">
                      {kpis.providers.circuit_open_providers > 0 ? (
                        <span className="text-rose-400">{kpis.providers.circuit_open_providers} circuito aberto</span>
                      ) : (
                        "100% Saudáveis"
                      )}
                    </span>
                  </div>
                </div>
              </div>

              {/* 6-Stage Marketing Funnel */}
              <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-4 space-y-3">
                <div className="flex items-center justify-between">
                  <h4 className="text-xs font-bold uppercase tracking-wider text-slate-300">
                    Funil de Mensageria
                  </h4>
                  <span className="text-[10px] text-slate-500">6 Estágios</span>
                </div>

                <div className="space-y-2">
                  {(data?.funnel || []).map((step, idx) => (
                    <div key={step.stage} className="space-y-1">
                      <div className="flex items-center justify-between text-[11px]">
                        <span className="text-slate-400 font-mono">
                          {idx + 1}. {step.stage}
                        </span>
                        <div className="flex items-center space-x-2">
                          <span className="font-bold text-white">{formatNumber(step.count)}</span>
                          <span className="text-[10px] text-slate-400 font-mono">
                            {step.conversion_rate}%
                          </span>
                        </div>
                      </div>
                      <div className="w-full bg-slate-900 rounded-full h-1.5 overflow-hidden">
                        <div
                          className="bg-gradient-to-r from-emerald-500 to-cyan-500 h-1.5 rounded-full transition-all duration-500"
                          style={{ width: `${Math.max(2, Math.min(100, step.conversion_rate))}%` }}
                        />
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            {/* GROUP 5: TIME EVOLUTION & CONTINUOUS TIMESERIES */}
            <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-5 space-y-4">
              <div className="flex items-center justify-between">
                <div>
                  <h3 className="text-xs font-bold uppercase tracking-wider text-slate-300 flex items-center space-x-1.5">
                    <Activity className="w-3.5 h-3.5 text-emerald-400" />
                    <span>Série Temporal Contínua (Evolução Diária)</span>
                  </h3>
                  <p className="text-[11px] text-slate-500">
                    Sincronização contínua de depósitos, novos jogadores e mensagens processadas
                  </p>
                </div>
                <span className="text-[10px] text-slate-400 bg-slate-900 px-2 py-1 rounded border border-slate-800 font-mono">
                  {data?.evolution?.length || 0} intervalos
                </span>
              </div>

              {(!data?.evolution || data.evolution.length === 0) ? (
                <div className="p-8 text-center text-xs text-slate-500">
                  Sem dados temporais disponíveis no intervalo selecionado.
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs">
                    <thead>
                      <tr className="border-b border-slate-800 text-[10px] text-slate-400 uppercase font-mono">
                        <th className="pb-2">Data / Intervalo</th>
                        <th className="pb-2 text-right">Depósitos (R$)</th>
                        <th className="pb-2 text-right">Saques (R$)</th>
                        <th className="pb-2 text-right">Novos Jogadores</th>
                        <th className="pb-2 text-right">Mensagens Enviadas</th>
                        <th className="pb-2 text-right">Entregues</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-800/60 font-mono text-[11px]">
                      {data.evolution.map((point) => (
                        <tr key={point.date} className="hover:bg-slate-800/30 transition">
                          <td className="py-2 text-slate-300 font-medium">{point.date}</td>
                          <td className="py-2 text-right text-emerald-400 font-bold">
                            {formatCurrency(point.deposits_amount)}
                          </td>
                          <td className="py-2 text-right text-slate-400">
                            {formatCurrency(point.withdrawals_amount)}
                          </td>
                          <td className="py-2 text-right text-cyan-400 font-bold">
                            {formatNumber(point.new_players)}
                          </td>
                          <td className="py-2 text-right text-indigo-300">
                            {formatNumber(point.messages_sent)}
                          </td>
                          <td className="py-2 text-right text-emerald-300">
                            {formatNumber(point.messages_delivered)}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </>
        )}
      </div>
    </AppLayout>
  );
}
