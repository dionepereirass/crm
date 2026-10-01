"use client";

import React, { useEffect, useState } from "react";
import { useParams, useRouter } from "next/navigation";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import {
  automationService,
  Automation,
  AutomationMetrics,
  AutomationRun,
  AutomationNode,
} from "@/services/automation-service";
import {
  Zap,
  ArrowLeft,
  Edit,
  Play,
  Pause,
  Ban,
  Activity,
  CheckCircle2,
  XCircle,
  AlertTriangle,
  Clock,
  RefreshCw,
  GitBranch,
  Shield,
  Layers,
  Send,
  Tag,
  ArrowRight,
} from "lucide-react";

export default function AutomationDetailPage() {
  const params = useParams();
  const router = useRouter();
  const id = Number(params?.id);

  const [automation, setAutomation] = useState<Automation | null>(null);
  const [metrics, setMetrics] = useState<AutomationMetrics | null>(null);
  const [recentRuns, setRecentRuns] = useState<AutomationRun[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);
  const [actionBusy, setActionBusy] = useState(false);

  // Validation & Preview state
  const [validating, setValidating] = useState(false);
  const [validationResult, setValidationResult] = useState<{ valid: boolean; errors: any[] } | null>(null);

  const loadData = async () => {
    if (!id) return;
    try {
      setLoading(true);
      setError(null);

      const [autoRes, runsRes] = await Promise.all([
        automationService.get(id),
        automationService.listRuns(id, { page: 1 }),
      ]);

      setAutomation(autoRes.data);
      setMetrics(autoRes.metrics);
      setRecentRuns(runsRes.data.slice(0, 5));
    } catch (err: any) {
      setError(err.message || "Falha ao carregar detalhes da automação.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, [id]);

  const handleActivate = async () => {
    try {
      setActionBusy(true);
      setError(null);
      await automationService.activate(id);
      setSuccessMsg("Automação ativada com sucesso!");
      await loadData();
    } catch (err: any) {
      setError(err.message || "Falha ao ativar.");
    } finally {
      setActionBusy(false);
    }
  };

  const handlePause = async () => {
    try {
      setActionBusy(true);
      setError(null);
      await automationService.pause(id);
      setSuccessMsg("Automação pausada.");
      await loadData();
    } catch (err: any) {
      setError(err.message || "Falha ao pausar.");
    } finally {
      setActionBusy(false);
    }
  };

  const handleDeactivate = async () => {
    try {
      setActionBusy(true);
      setError(null);
      await automationService.deactivate(id);
      setSuccessMsg("Automação desativada.");
      await loadData();
    } catch (err: any) {
      setError(err.message || "Falha ao desativar.");
    } finally {
      setActionBusy(false);
    }
  };

  const handleValidate = async () => {
    try {
      setValidating(true);
      const res = await automationService.validate(id);
      setValidationResult(res);
      if (res.valid) {
        setSuccessMsg("Grafo validado com sucesso! Nenhuma inconsistência encontrada.");
      } else {
        setError(`Grafo possui ${res.errors.length} erro(s) de validação.`);
      }
    } catch (err: any) {
      setError(err.message || "Erro na validação.");
    } finally {
      setValidating(false);
    }
  };

  const getNodeIcon = (type: string) => {
    switch (type) {
      case "TRIGGER":
        return <Zap className="w-4 h-4 text-amber-400" />;
      case "CONDITION":
        return <GitBranch className="w-4 h-4 text-purple-400" />;
      case "ACTION":
        return <Send className="w-4 h-4 text-emerald-400" />;
      case "WAIT":
        return <Clock className="w-4 h-4 text-blue-400" />;
      default:
        return <Layers className="w-4 h-4 text-slate-400" />;
    }
  };

  if (loading) {
    return (
      <AppLayout title="Detalhes da Automação">
        <div className="p-16 text-center text-slate-400">
          <RefreshCw className="w-8 h-8 animate-spin mx-auto mb-3 text-emerald-500" />
          Carregando informações da automação...
        </div>
      </AppLayout>
    );
  }

  if (!automation) {
    return (
      <AppLayout title="Automação não encontrada">
        <div className="p-16 text-center text-slate-400">
          <AlertTriangle className="w-10 h-10 mx-auto mb-3 text-amber-500" />
          <p className="text-base text-slate-200">Automação #{id} não encontrada ou sem permissão de acesso.</p>
          <Link
            href="/automations"
            className="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-sm transition"
          >
            <ArrowLeft className="w-4 h-4" />
            Voltar para lista
          </Link>
        </div>
      </AppLayout>
    );
  }

  return (
    <AppLayout
      title={automation.name}
      badge={`FASE 10 • ${automation.status}`}
      actions={
        <div className="flex items-center gap-2">
          <Link
            href="/automations"
            className="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-sm border border-slate-700 flex items-center gap-1.5 transition"
          >
            <ArrowLeft className="w-4 h-4" />
            Voltar
          </Link>

          <button
            onClick={handleValidate}
            disabled={validating}
            className="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-sm border border-slate-700 flex items-center gap-1.5 transition"
          >
            <CheckCircle2 className={`w-4 h-4 ${validating ? "animate-spin" : "text-emerald-400"}`} />
            Validar Grafo
          </button>

          <Link
            href={`/automations/${id}/edit`}
            className="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-semibold flex items-center gap-1.5 transition shadow-sm shadow-emerald-900/40"
          >
            <Edit className="w-4 h-4" />
            Editar Grafo
          </Link>
        </div>
      }
    >
      <div className="space-y-6">
        {/* Alerts */}
        {error && (
          <div className="p-4 bg-rose-950/60 border border-rose-800 rounded-lg text-rose-200 text-sm flex items-center gap-2">
            <AlertTriangle className="w-5 h-5 flex-shrink-0 text-rose-400" />
            <span>{error}</span>
          </div>
        )}

        {successMsg && (
          <div className="p-4 bg-emerald-950/60 border border-emerald-800 rounded-lg text-emerald-200 text-sm flex items-center gap-2">
            <CheckCircle2 className="w-5 h-5 flex-shrink-0 text-emerald-400" />
            <span>{successMsg}</span>
          </div>
        )}

        {/* Validation Errors Panel */}
        {validationResult && !validationResult.valid && (
          <div className="p-4 bg-rose-950/70 border border-rose-800 rounded-xl space-y-2">
            <div className="flex items-center gap-2 text-rose-300 font-semibold text-sm">
              <XCircle className="w-4 h-4" />
              Inconsistências no Grafo de Execução:
            </div>
            <ul className="text-xs text-rose-200 space-y-1 list-disc list-inside">
              {validationResult.errors.map((e, idx) => (
                <li key={idx}>
                  <strong className="font-mono text-rose-100">[{e.node}]</strong> {e.message}
                </li>
              ))}
            </ul>
          </div>
        )}

        {/* Header Overview Card */}
        <div className="p-6 bg-slate-900 border border-slate-800 rounded-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
          <div className="space-y-2">
            <div className="flex items-center gap-3">
              <span className="text-xs font-mono px-2.5 py-1 rounded bg-slate-950 border border-slate-800 text-amber-400 flex items-center gap-1.5">
                <Zap className="w-3.5 h-3.5" />
                Gatilho: {automation.trigger_type}
              </span>
              <span
                className={`text-xs font-bold px-2.5 py-1 rounded border ${
                  automation.status === "ACTIVE"
                    ? "bg-emerald-950 text-emerald-300 border-emerald-800"
                    : automation.status === "PAUSED"
                    ? "bg-amber-950 text-amber-300 border-amber-800"
                    : automation.status === "INACTIVE"
                    ? "bg-rose-950 text-rose-300 border-rose-800"
                    : "bg-slate-800 text-slate-300 border-slate-700"
                }`}
              >
                {automation.status}
              </span>
            </div>
            <p className="text-sm text-slate-400">{automation.description || "Nenhuma descrição informada."}</p>
          </div>

          {/* Quick Lifecycle Controls */}
          <div className="flex items-center gap-2">
            {automation.status === "ACTIVE" ? (
              <button
                onClick={handlePause}
                disabled={actionBusy}
                className="px-4 py-2 bg-amber-900/40 hover:bg-amber-900/60 text-amber-300 border border-amber-700 rounded-lg text-sm font-semibold flex items-center gap-2 transition disabled:opacity-50"
              >
                <Pause className="w-4 h-4" />
                Pausar Jornada
              </button>
            ) : (
              <button
                onClick={handleActivate}
                disabled={actionBusy}
                className="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-semibold flex items-center gap-2 transition disabled:opacity-50"
              >
                <Play className="w-4 h-4" />
                Ativar Jornada
              </button>
            )}

            {automation.status !== "INACTIVE" && (
              <button
                onClick={handleDeactivate}
                disabled={actionBusy}
                className="px-4 py-2 bg-slate-800 hover:bg-rose-950 text-slate-300 hover:text-rose-300 border border-slate-700 rounded-lg text-sm font-semibold flex items-center gap-2 transition disabled:opacity-50"
              >
                <Ban className="w-4 h-4" />
                Desativar
              </button>
            )}
          </div>
        </div>

        {/* Metrics Grid */}
        {metrics && (
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div className="p-4 bg-slate-900 border border-slate-800 rounded-xl space-y-1">
              <div className="text-slate-400 text-xs uppercase tracking-wider font-semibold">Total de Execuções</div>
              <div className="text-2xl font-bold text-white">{metrics.total_runs.toLocaleString("pt-BR")}</div>
              <div className="text-[11px] text-slate-500">Instâncias processadas</div>
            </div>

            <div className="p-4 bg-slate-900 border border-slate-800 rounded-xl space-y-1">
              <div className="text-slate-400 text-xs uppercase tracking-wider font-semibold">Taxa de Sucesso</div>
              <div className="text-2xl font-bold text-emerald-400">{metrics.success_rate.toFixed(1)}%</div>
              <div className="text-[11px] text-slate-500">{metrics.completed} concluídas</div>
            </div>

            <div className="p-4 bg-slate-900 border border-slate-800 rounded-xl space-y-1">
              <div className="text-slate-400 text-xs uppercase tracking-wider font-semibold">Falhas / Erros</div>
              <div className="text-2xl font-bold text-rose-400">{metrics.failed}</div>
              <div className="text-[11px] text-slate-500">Paradas por erro</div>
            </div>

            <div className="p-4 bg-slate-900 border border-slate-800 rounded-xl space-y-1">
              <div className="text-slate-400 text-xs uppercase tracking-wider font-semibold">Em Andamento / Espera</div>
              <div className="text-2xl font-bold text-blue-400">{metrics.running + metrics.waiting}</div>
              <div className="text-[11px] text-slate-500">
                {metrics.waiting} aguardando timer, {metrics.running} executando
              </div>
            </div>
          </div>
        )}

        {/* Graph Nodes Summary */}
        <div className="p-6 bg-slate-900 border border-slate-800 rounded-xl space-y-4">
          <div className="flex items-center justify-between pb-3 border-b border-slate-800">
            <div className="flex items-center gap-2">
              <GitBranch className="w-5 h-5 text-emerald-400" />
              <h3 className="font-semibold text-white text-sm">Estrutura do Grafo ({automation.nodes?.length || 0} Nós)</h3>
            </div>
            <Link
              href={`/automations/${id}/edit`}
              className="text-xs text-emerald-400 hover:text-emerald-300 font-semibold flex items-center gap-1"
            >
              Abrir Canvas Completo
              <ArrowRight className="w-3.5 h-3.5" />
            </Link>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            {automation.nodes && automation.nodes.length > 0 ? (
              automation.nodes.map((node, i) => (
                <div
                  key={node.node_key || i}
                  className="p-3.5 bg-slate-950 border border-slate-800 rounded-lg space-y-1.5"
                >
                  <div className="flex items-center justify-between">
                    <span className="flex items-center gap-1.5 text-xs font-semibold text-slate-200">
                      {getNodeIcon(node.node_type)}
                      {node.name || node.node_key}
                    </span>
                    <span className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-900 text-slate-400 border border-slate-800">
                      {node.node_type}
                    </span>
                  </div>
                  <div className="text-xs text-slate-500 font-mono truncate">
                    key: {node.node_key}
                  </div>
                </div>
              ))
            ) : (
              <div className="col-span-full py-6 text-center text-slate-500 text-sm">
                Nenhum nó configurado ainda. Abra o editor de grafo para desenhar o fluxo.
              </div>
            )}
          </div>
        </div>

        {/* Recent Runs Table */}
        <div className="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden space-y-3">
          <div className="p-4 flex items-center justify-between border-b border-slate-800">
            <div className="flex items-center gap-2">
              <Activity className="w-4 h-4 text-blue-400" />
              <h3 className="font-semibold text-white text-sm">Últimas Execuções</h3>
            </div>
            <Link
              href={`/automations/${id}/runs`}
              className="text-xs text-blue-400 hover:text-blue-300 font-semibold flex items-center gap-1"
            >
              Ver Todas as Execuções
              <ArrowRight className="w-3.5 h-3.5" />
            </Link>
          </div>

          {recentRuns.length === 0 ? (
            <div className="p-8 text-center text-slate-500 text-xs">
              Nenhuma execução registrada até o momento.
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead className="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold border-b border-slate-800">
                  <tr>
                    <th className="py-2.5 px-4">ID Run</th>
                    <th className="py-2.5 px-4">Jogador</th>
                    <th className="py-2.5 px-4">Status</th>
                    <th className="py-2.5 px-4">Nó Atual</th>
                    <th className="py-2.5 px-4">Iniciado em</th>
                    <th className="py-2.5 px-4 text-right">Ação</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800/60">
                  {recentRuns.map((run) => (
                    <tr key={run.id} className="hover:bg-slate-800/40 transition">
                      <td className="py-3 px-4 font-mono font-semibold text-slate-200">#{run.id}</td>
                      <td className="py-3 px-4">
                        <span className="font-medium text-slate-100">{run.player?.name || `Player #${run.player_id}`}</span>
                        <div className="text-[11px] text-slate-500">{run.player?.email}</div>
                      </td>
                      <td className="py-3 px-4">
                        <span
                          className={`inline-block px-2 py-0.5 rounded text-[10px] font-bold ${
                            run.status === "COMPLETED"
                              ? "bg-emerald-950 text-emerald-300 border border-emerald-800"
                              : run.status === "FAILED"
                              ? "bg-rose-950 text-rose-300 border border-rose-800"
                              : run.status === "WAITING"
                              ? "bg-blue-950 text-blue-300 border border-blue-800"
                              : run.status === "RUNNING"
                              ? "bg-amber-950 text-amber-300 border border-amber-800"
                              : "bg-slate-800 text-slate-400"
                          }`}
                        >
                          {run.status}
                        </span>
                      </td>
                      <td className="py-3 px-4 font-mono text-slate-400">
                        {run.currentNode?.name || run.current_node_id || "-"}
                      </td>
                      <td className="py-3 px-4 text-slate-400">
                        {run.started_at ? new Date(run.started_at).toLocaleString("pt-BR") : "-"}
                      </td>
                      <td className="py-3 px-4 text-right">
                        <Link
                          href={`/automations/${id}/runs?highlight=${run.id}`}
                          className="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded text-[11px] font-medium"
                        >
                          Inspecionar
                        </Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </AppLayout>
  );
}
