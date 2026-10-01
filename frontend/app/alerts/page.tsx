"use client";

import React, { useState, useEffect, useCallback } from "react";
import { AppLayout } from "@/components/layout/app-layout";
import { useAuth } from "@/hooks/use-auth";
import {
  analyticsService,
  OperationalAlertItem,
  AlertRuleItem,
} from "@/services/analytics-service";
import {
  Bell,
  AlertTriangle,
  ShieldAlert,
  CheckCircle2,
  RefreshCw,
  Plus,
  Play,
  Trash2,
  Eye,
  Check,
  Settings,
  Flame,
  Clock,
  Filter,
} from "lucide-react";

export default function AlertsPage() {
  const { activePlatform } = useAuth(true);
  const [activeTab, setActiveTab] = useState<"alerts" | "rules">("alerts");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);

  // Alerts Feed State
  const [alerts, setAlerts] = useState<OperationalAlertItem[]>([]);
  const [statusFilter, setStatusFilter] = useState<string>("ALL");
  const [severityFilter, setSeverityFilter] = useState<string>("ALL");
  const [evaluating, setEvaluating] = useState<boolean>(false);

  // Resolve Modal State
  const [selectedAlertForResolve, setSelectedAlertForResolve] = useState<OperationalAlertItem | null>(null);
  const [resolutionNotes, setResolutionNotes] = useState<string>("");
  const [resolving, setResolving] = useState<boolean>(false);

  // Rules State
  const [rules, setRules] = useState<AlertRuleItem[]>([]);
  const [showRuleModal, setShowRuleModal] = useState<boolean>(false);
  const [newRuleName, setNewRuleName] = useState<string>("");
  const [newRuleMetric, setNewRuleMetric] = useState<string>("PROVIDER_FAILURES");
  const [newRuleOperator, setNewRuleOperator] = useState<"GT" | "GTE" | "LT" | "LTE" | "EQ">("GT");
  const [newRuleThreshold, setNewRuleThreshold] = useState<number>(5);
  const [newRuleSeverity, setNewRuleSeverity] = useState<"INFO" | "WARNING" | "CRITICAL">("WARNING");
  const [newRuleCooldown, setNewRuleCooldown] = useState<number>(60);
  const [savingRule, setSavingRule] = useState<boolean>(false);

  const fetchAlerts = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await analyticsService.listAlerts({
        status: statusFilter,
        severity: severityFilter,
      });
      setAlerts(res.data || []);
    } catch (err: any) {
      setError(err.message || "Erro ao listar alertas operacionais.");
    } finally {
      setLoading(false);
    }
  }, [statusFilter, severityFilter]);

  const fetchRules = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const list = await analyticsService.listAlertRules();
      setRules(list || []);
    } catch (err: any) {
      setError(err.message || "Erro ao carregar regras de alerta.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (activePlatform) {
      if (activeTab === "alerts") {
        fetchAlerts();
      } else {
        fetchRules();
      }
    }
  }, [activePlatform, activeTab, fetchAlerts, fetchRules]);

  const handleAcknowledge = async (id: number) => {
    try {
      await analyticsService.acknowledgeAlert(id);
      setSuccessMsg("Alerta marcado como reconhecido.");
      fetchAlerts();
    } catch (err: any) {
      setError(err.message || "Falha ao reconhecer alerta.");
    }
  };

  const handleResolve = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedAlertForResolve) return;
    setResolving(true);
    try {
      await analyticsService.resolveAlert(selectedAlertForResolve.id, resolutionNotes);
      setSelectedAlertForResolve(null);
      setResolutionNotes("");
      setSuccessMsg("Alerta resolvido com sucesso.");
      fetchAlerts();
    } catch (err: any) {
      setError(err.message || "Falha ao resolver alerta.");
    } finally {
      setResolving(false);
    }
  };

  const handleEvaluateNow = async () => {
    setEvaluating(true);
    setError(null);
    setSuccessMsg(null);
    try {
      const res = await analyticsService.evaluateAlerts();
      setSuccessMsg(
        `Avaliação concluída: ${res.evaluated_rules} regras avaliadas, ${res.new_alerts_count} novo(s) alerta(s) gerado(s).`
      );
      fetchAlerts();
    } catch (err: any) {
      setError(err.message || "Erro ao avaliar regras de alerta.");
    } finally {
      setEvaluating(false);
    }
  };

  const handleCreateRule = async (e: React.FormEvent) => {
    e.preventDefault();
    setSavingRule(true);
    setError(null);
    try {
      await analyticsService.createAlertRule({
        name: newRuleName,
        metric: newRuleMetric,
        operator: newRuleOperator,
        threshold: Number(newRuleThreshold),
        severity: newRuleSeverity,
        cooldown_minutes: Number(newRuleCooldown),
      });

      setShowRuleModal(false);
      setNewRuleName("");
      setSuccessMsg("Regra de alerta cadastrada com sucesso!");
      fetchRules();
    } catch (err: any) {
      setError(err.message || "Erro ao criar regra de alerta.");
    } finally {
      setSavingRule(false);
    }
  };

  const handleToggleRule = async (rule: AlertRuleItem) => {
    try {
      await analyticsService.updateAlertRule(rule.id, { active: !rule.active });
      fetchRules();
    } catch (err: any) {
      setError(err.message || "Erro ao alterar status da regra.");
    }
  };

  const handleDeleteRule = async (id: number) => {
    if (!confirm("Deseja realmente remover esta regra de alerta?")) return;
    try {
      await analyticsService.deleteAlertRule(id);
      setSuccessMsg("Regra removida com sucesso.");
      fetchRules();
    } catch (err: any) {
      setError(err.message || "Erro ao remover regra.");
    }
  };

  const getSeverityBadge = (sev: string) => {
    switch (sev) {
      case "CRITICAL":
        return "bg-rose-500/20 text-rose-300 border-rose-500/40";
      case "WARNING":
        return "bg-amber-500/20 text-amber-300 border-amber-500/40";
      default:
        return "bg-cyan-500/20 text-cyan-300 border-cyan-500/40";
    }
  };

  const getStatusBadge = (st: string) => {
    switch (st) {
      case "TRIGGERED":
        return "bg-rose-500/20 text-rose-400 font-bold animate-pulse";
      case "ACKNOWLEDGED":
        return "bg-amber-500/20 text-amber-300 font-medium";
      case "RESOLVED":
        return "bg-emerald-500/20 text-emerald-400 font-bold";
      default:
        return "bg-slate-800 text-slate-400";
    }
  };

  return (
    <AppLayout
      title="Motor de Alertas Operacionais"
      badge="MONITORAMENTO & INCIDENTES"
      actions={
        <div className="flex items-center space-x-2">
          {activeTab === "alerts" && (
            <button
              onClick={handleEvaluateNow}
              disabled={evaluating}
              className="flex items-center space-x-1.5 px-3 py-1.5 bg-amber-500/20 hover:bg-amber-500/30 border border-amber-500/40 text-amber-300 text-xs font-bold rounded-lg transition"
            >
              <Play className={`w-3.5 h-3.5 ${evaluating ? "animate-spin" : ""}`} />
              <span>Avaliar Regras Agora</span>
            </button>
          )}

          {activeTab === "rules" && (
            <button
              onClick={() => setShowRuleModal(true)}
              className="flex items-center space-x-1 px-3 py-1.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-xs font-bold rounded-lg transition"
            >
              <Plus className="w-4 h-4" />
              <span>Nova Regra</span>
            </button>
          )}

          <button
            onClick={() => (activeTab === "alerts" ? fetchAlerts() : fetchRules())}
            disabled={loading}
            className="p-1.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-lg text-slate-300 transition"
          >
            <RefreshCw className={`w-4 h-4 ${loading ? "animate-spin text-emerald-400" : ""}`} />
          </button>
        </div>
      }
    >
      <div className="p-6 space-y-6 max-w-7xl mx-auto">
        {/* Navigation Tabs */}
        <div className="flex border-b border-slate-800 space-x-2">
          <button
            onClick={() => setActiveTab("alerts")}
            className={`flex items-center space-x-2 px-4 py-3 text-xs font-bold border-b-2 transition ${
              activeTab === "alerts"
                ? "border-emerald-500 text-emerald-400 bg-emerald-500/10"
                : "border-transparent text-slate-400 hover:text-slate-200 hover:bg-slate-800/40"
            }`}
          >
            <Bell className="w-4 h-4" />
            <span>Feed de Alertas</span>
          </button>

          <button
            onClick={() => setActiveTab("rules")}
            className={`flex items-center space-x-2 px-4 py-3 text-xs font-bold border-b-2 transition ${
              activeTab === "rules"
                ? "border-emerald-500 text-emerald-400 bg-emerald-500/10"
                : "border-transparent text-slate-400 hover:text-slate-200 hover:bg-slate-800/40"
            }`}
          >
            <Settings className="w-4 h-4" />
            <span>Regras Configuradas</span>
          </button>
        </div>

        {/* Global Feedback Messages */}
        {error && (
          <div className="bg-rose-950/40 border border-rose-500/40 rounded-xl p-4 text-xs text-rose-300">
            {error}
          </div>
        )}
        {successMsg && (
          <div className="bg-emerald-950/40 border border-emerald-500/40 rounded-xl p-4 text-xs text-emerald-300 flex items-center space-x-2">
            <CheckCircle2 className="w-4 h-4 text-emerald-400 shrink-0" />
            <span>{successMsg}</span>
          </div>
        )}

        {/* TAB 1: ALERTS FEED */}
        {activeTab === "alerts" && (
          <div className="space-y-6">
            {/* Filter Bar */}
            <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-4 flex flex-wrap items-center justify-between gap-4">
              <div className="flex flex-wrap items-center gap-3">
                <div>
                  <label className="block text-[10px] uppercase font-bold text-slate-400 mb-1">
                    Status
                  </label>
                  <select
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value)}
                    className="bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-emerald-500"
                  >
                    <option value="ALL">Todos os Status</option>
                    <option value="TRIGGERED">Disparados (Ativos)</option>
                    <option value="ACKNOWLEDGED">Reconhecidos</option>
                    <option value="RESOLVED">Resolvidos</option>
                  </select>
                </div>

                <div>
                  <label className="block text-[10px] uppercase font-bold text-slate-400 mb-1">
                    Severidade
                  </label>
                  <select
                    value={severityFilter}
                    onChange={(e) => setSeverityFilter(e.target.value)}
                    className="bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-emerald-500"
                  >
                    <option value="ALL">Todas as Severidades</option>
                    <option value="CRITICAL">Crítico</option>
                    <option value="WARNING">Aviso</option>
                    <option value="INFO">Informativo</option>
                  </select>
                </div>
              </div>

              <div className="text-xs text-slate-400 font-mono">
                {alerts.length} alerta(s) listado(s)
              </div>
            </div>

            {/* Alerts List */}
            {loading ? (
              <div className="py-12 flex justify-center text-slate-400">
                <div className="w-6 h-6 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin" />
              </div>
            ) : alerts.length === 0 ? (
              <div className="p-8 text-center text-xs text-slate-500 bg-[#0d131f] border border-dashed border-slate-800 rounded-xl space-y-1">
                <CheckCircle2 className="w-8 h-8 text-emerald-500/60 mx-auto" />
                <p className="font-semibold text-slate-300">Nenhum incidente ativo!</p>
                <p className="text-[11px] text-slate-500">
                  Todas as métricas operacionais estão dentro dos parâmetros esperados.
                </p>
              </div>
            ) : (
              <div className="space-y-3">
                {alerts.map((alert) => (
                  <div
                    key={alert.id}
                    className={`bg-[#0d131f] border rounded-xl p-4 flex flex-col md:flex-row items-start md:items-center justify-between gap-4 transition ${
                      alert.status === "TRIGGERED"
                        ? "border-rose-500/40 bg-rose-950/10"
                        : alert.status === "ACKNOWLEDGED"
                        ? "border-amber-500/30"
                        : "border-slate-800 opacity-75"
                    }`}
                  >
                    <div className="space-y-1.5 min-w-0 flex-1">
                      <div className="flex items-center space-x-2">
                        <span
                          className={`px-2 py-0.5 rounded text-[10px] font-bold border ${getSeverityBadge(
                            alert.severity
                          )}`}
                        >
                          {alert.severity}
                        </span>
                        <span
                          className={`px-2 py-0.5 rounded text-[10px] uppercase font-mono ${getStatusBadge(
                            alert.status
                          )}`}
                        >
                          {alert.status}
                        </span>
                        <span className="text-[10px] text-slate-500 font-mono">
                          Métrica: {alert.metric}
                        </span>
                      </div>

                      <h4 className="text-sm font-bold text-white">{alert.title}</h4>
                      <p className="text-xs text-slate-300 leading-relaxed">{alert.message}</p>

                      <div className="flex items-center space-x-4 text-[10px] text-slate-500 font-mono pt-1">
                        <span>Valor Atual: {alert.current_value}</span>
                        <span>Limite: {alert.threshold_value}</span>
                        <span>Disparado em: {alert.triggered_at}</span>
                      </div>
                    </div>

                    {/* Action Buttons */}
                    <div className="flex items-center space-x-2 shrink-0 self-end md:self-center">
                      {alert.status === "TRIGGERED" && (
                        <button
                          onClick={() => handleAcknowledge(alert.id)}
                          className="px-3 py-1.5 bg-amber-500/20 hover:bg-amber-500/30 border border-amber-500/40 text-amber-300 text-xs font-bold rounded-lg transition"
                        >
                          Reconhecer
                        </button>
                      )}

                      {alert.status !== "RESOLVED" && (
                        <button
                          onClick={() => setSelectedAlertForResolve(alert)}
                          className="px-3 py-1.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-xs font-bold rounded-lg transition flex items-center space-x-1"
                        >
                          <Check className="w-3.5 h-3.5" />
                          <span>Resolver</span>
                        </button>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        {/* TAB 2: ALERT RULES */}
        {activeTab === "rules" && (
          <div className="space-y-6">
            <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-5 space-y-4">
              <div className="flex items-center justify-between">
                <div>
                  <h3 className="text-xs font-bold uppercase tracking-wider text-slate-300">
                    Regras de Disparo Automático
                  </h3>
                  <p className="text-[11px] text-slate-500">
                    Gatilhos avaliados periodicamente e durante rotinas operacionais
                  </p>
                </div>
              </div>

              {loading ? (
                <div className="py-12 flex justify-center text-slate-400">
                  <div className="w-6 h-6 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin" />
                </div>
              ) : rules.length === 0 ? (
                <div className="p-8 text-center text-xs text-slate-500 border border-dashed border-slate-800 rounded-xl">
                  Nenhuma regra de alerta cadastrada.
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs">
                    <thead>
                      <tr className="border-b border-slate-800 text-[10px] text-slate-400 uppercase font-mono">
                        <th className="pb-2">Nome</th>
                        <th className="pb-2">Métrica</th>
                        <th className="pb-2">Condição</th>
                        <th className="pb-2">Severidade</th>
                        <th className="pb-2">Cooldown</th>
                        <th className="pb-2">Status</th>
                        <th className="pb-2 text-right">Ações</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-800/60 font-mono text-[11px]">
                      {rules.map((rule) => (
                        <tr key={rule.id} className="hover:bg-slate-800/20">
                          <td className="py-2.5 font-sans font-medium text-white">{rule.name}</td>
                          <td className="py-2.5 text-slate-300">{rule.metric}</td>
                          <td className="py-2.5 text-amber-400 font-bold">
                            {rule.operator} {rule.threshold}
                          </td>
                          <td className="py-2.5">
                            <span
                              className={`px-1.5 py-0.5 rounded text-[10px] font-bold border ${getSeverityBadge(
                                rule.severity
                              )}`}
                            >
                              {rule.severity}
                            </span>
                          </td>
                          <td className="py-2.5 text-slate-400">{rule.cooldown_minutes} min</td>
                          <td className="py-2.5">
                            <button
                              onClick={() => handleToggleRule(rule)}
                              className={`px-2 py-0.5 rounded text-[10px] font-bold transition ${
                                rule.active
                                  ? "bg-emerald-500/20 text-emerald-400 border border-emerald-500/30"
                                  : "bg-slate-800 text-slate-500"
                              }`}
                            >
                              {rule.active ? "ATIVO" : "PAUSADO"}
                            </button>
                          </td>
                          <td className="py-2.5 text-right">
                            <button
                              onClick={() => handleDeleteRule(rule.id)}
                              className="p-1 hover:bg-rose-500/20 rounded text-slate-400 hover:text-rose-400 transition"
                              title="Remover regra"
                            >
                              <Trash2 className="w-4 h-4" />
                            </button>
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

        {/* Modal: Resolve Alert */}
        {selectedAlertForResolve && (
          <div className="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4">
            <div className="bg-[#0f172a] border border-slate-700 rounded-2xl p-6 max-w-md w-full space-y-4">
              <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 className="text-sm font-bold text-white">Resolver Alerta Operacional</h3>
                <button
                  onClick={() => setSelectedAlertForResolve(null)}
                  className="text-slate-400 hover:text-white text-xs"
                >
                  ✕
                </button>
              </div>

              <div className="text-xs text-slate-300 space-y-1">
                <span className="font-bold text-white block">{selectedAlertForResolve.title}</span>
                <p className="text-slate-400">{selectedAlertForResolve.message}</p>
              </div>

              <form onSubmit={handleResolve} className="space-y-4 text-xs">
                <div>
                  <label className="block text-[11px] text-slate-300 font-medium mb-1">
                    Notas de Resolução (Auditoria) *
                  </label>
                  <textarea
                    required
                    rows={3}
                    value={resolutionNotes}
                    onChange={(e) => setResolutionNotes(e.target.value)}
                    placeholder="Descreva as medidas tomadas para mitigar o incidente..."
                    className="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-emerald-500"
                  />
                </div>

                <div className="flex justify-end space-x-2 pt-2 border-t border-slate-800">
                  <button
                    type="button"
                    onClick={() => setSelectedAlertForResolve(null)}
                    className="px-3 py-2 bg-slate-800 hover:bg-slate-700 rounded-lg text-slate-300 transition"
                  >
                    Cancelar
                  </button>
                  <button
                    type="submit"
                    disabled={resolving}
                    className="px-4 py-2 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold rounded-lg transition"
                  >
                    {resolving ? "Resolvendo..." : "Confirmar Resolução"}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}

        {/* Modal: Create Alert Rule */}
        {showRuleModal && (
          <div className="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4">
            <div className="bg-[#0f172a] border border-slate-700 rounded-2xl p-6 max-w-md w-full space-y-4">
              <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 className="text-sm font-bold text-white">Nova Regra de Alerta</h3>
                <button
                  onClick={() => setShowRuleModal(false)}
                  className="text-slate-400 hover:text-white text-xs"
                >
                  ✕
                </button>
              </div>

              <form onSubmit={handleCreateRule} className="space-y-4 text-xs">
                <div>
                  <label className="block text-[11px] text-slate-300 font-medium mb-1">
                    Nome da Regra *
                  </label>
                  <input
                    type="text"
                    required
                    value={newRuleName}
                    onChange={(e) => setNewRuleName(e.target.value)}
                    placeholder="Ex: Falhas Repetidas no Provedor"
                    className="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-emerald-500"
                  />
                </div>

                <div>
                  <label className="block text-[11px] text-slate-300 font-medium mb-1">
                    Métrica Monitorada *
                  </label>
                  <select
                    value={newRuleMetric}
                    onChange={(e) => setNewRuleMetric(e.target.value)}
                    className="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-emerald-500"
                  >
                    <option value="PROVIDER_FAILURES">Falhas em Provedores (PROVIDER_FAILURES)</option>
                    <option value="DELIVERY_DROP">Queda de Entregabilidade (DELIVERY_DROP)</option>
                    <option value="CHURN_INCREASE">Aumento de Churn (CHURN_INCREASE)</option>
                    <option value="INACTIVE_PLAYERS">Aumento de Inativos (INACTIVE_PLAYERS)</option>
                    <option value="AUTOMATION_FAILURES">Falhas em Automações (AUTOMATION_FAILURES)</option>
                    <option value="DSR_NEAR_SLA">DSRs Próximos ao SLA (DSR_NEAR_SLA)</option>
                    <option value="CIRCUIT_BREAKER_OPEN">Circuit Breaker Aberto (CIRCUIT_BREAKER_OPEN)</option>
                    <option value="WEBHOOK_ERRORS">Erros de Webhooks (WEBHOOK_ERRORS)</option>
                  </select>
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-[11px] text-slate-300 font-medium mb-1">
                      Operador *
                    </label>
                    <select
                      value={newRuleOperator}
                      onChange={(e) => setNewRuleOperator(e.target.value as any)}
                      className="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-emerald-500"
                    >
                      <option value="GT">Maior que (&gt;)</option>
                      <option value="GTE">Maior ou igual (&ge;)</option>
                      <option value="LT">Menor que (&lt;)</option>
                      <option value="LTE">Menor ou igual (&le;)</option>
                      <option value="EQ">Igual a (=)</option>
                    </select>
                  </div>

                  <div>
                    <label className="block text-[11px] text-slate-300 font-medium mb-1">
                      Limite (Threshold) *
                    </label>
                    <input
                      type="number"
                      step="any"
                      required
                      value={newRuleThreshold}
                      onChange={(e) => setNewRuleThreshold(parseFloat(e.target.value))}
                      className="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-emerald-500"
                    />
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-[11px] text-slate-300 font-medium mb-1">
                      Severidade *
                    </label>
                    <select
                      value={newRuleSeverity}
                      onChange={(e) => setNewRuleSeverity(e.target.value as any)}
                      className="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-emerald-500"
                    >
                      <option value="WARNING">Aviso (Warning)</option>
                      <option value="CRITICAL">Crítico (Critical)</option>
                      <option value="INFO">Informativo (Info)</option>
                    </select>
                  </div>

                  <div>
                    <label className="block text-[11px] text-slate-300 font-medium mb-1">
                      Cooldown (minutos) *
                    </label>
                    <input
                      type="number"
                      min={1}
                      required
                      value={newRuleCooldown}
                      onChange={(e) => setNewRuleCooldown(parseInt(e.target.value))}
                      className="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-emerald-500"
                    />
                  </div>
                </div>

                <div className="flex justify-end space-x-2 pt-2 border-t border-slate-800">
                  <button
                    type="button"
                    onClick={() => setShowRuleModal(false)}
                    className="px-3 py-2 bg-slate-800 hover:bg-slate-700 rounded-lg text-slate-300 transition"
                  >
                    Cancelar
                  </button>
                  <button
                    type="submit"
                    disabled={savingRule}
                    className="px-4 py-2 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold rounded-lg transition"
                  >
                    {savingRule ? "Salvando..." : "Salvar Regra"}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
