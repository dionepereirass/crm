"use client";

import React, { useEffect, useState } from "react";
import { useParams, useSearchParams } from "next/navigation";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import {
  automationService,
  Automation,
  AutomationRun,
  AutomationStep,
  AutomationLog,
} from "@/services/automation-service";
import {
  Activity,
  ArrowLeft,
  RefreshCw,
  Search,
  CheckCircle2,
  XCircle,
  Clock,
  AlertTriangle,
  Play,
  Ban,
  ChevronRight,
  Eye,
  Layers,
  Code,
  Shield,
  Zap,
} from "lucide-react";

export default function AutomationRunsPage() {
  const params = useParams();
  const searchParams = useSearchParams();
  const id = Number(params?.id);
  const highlightRunId = searchParams.get("highlight");

  const [automation, setAutomation] = useState<Automation | null>(null);
  const [runs, setRuns] = useState<AutomationRun[]>([]);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState("ALL");
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalCount, setTotalCount] = useState(0);

  // Inspector Modal / Drawer
  const [selectedRun, setSelectedRun] = useState<AutomationRun | null>(null);
  const [loadingRunDetail, setLoadingRunDetail] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [actionSuccess, setActionSuccess] = useState<string | null>(null);

  const fetchRuns = async () => {
    if (!id) return;
    try {
      setLoading(true);
      setActionError(null);

      const [autoRes, runsRes] = await Promise.all([
        automationService.get(id),
        automationService.listRuns(id, {
          status: statusFilter,
          page,
        }),
      ]);

      setAutomation(autoRes.data);
      setRuns(runsRes.data);
      setTotalPages(runsRes.last_page);
      setTotalCount(runsRes.total);
    } catch (err: any) {
      setActionError(err.message || "Falha ao listar execuções.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRuns();
  }, [id, statusFilter, page]);

  // Inspect run details
  const handleInspectRun = async (runId: number) => {
    try {
      setLoadingRunDetail(true);
      const res = await automationService.getRun(id, runId);
      setSelectedRun(res.data);
    } catch (err: any) {
      setActionError(err.message || `Erro ao carregar execução #${runId}`);
    } finally {
      setLoadingRunDetail(false);
    }
  };

  useEffect(() => {
    if (highlightRunId && id) {
      handleInspectRun(Number(highlightRunId));
    }
  }, [highlightRunId, id]);

  const handleCancelRun = async (runId: number) => {
    if (!confirm(`Deseja cancelar imediatamente a execução #${runId}?`)) return;

    try {
      setActionError(null);
      await automationService.cancelRun(id, runId);
      setActionSuccess(`Execução #${runId} cancelada com sucesso.`);
      if (selectedRun?.id === runId) {
        await handleInspectRun(runId);
      }
      await fetchRuns();
    } catch (err: any) {
      setActionError(err.message || `Erro ao cancelar execução #${runId}`);
    }
  };

  const getStatusBadge = (status: string) => {
    switch (status) {
      case "COMPLETED":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-950 text-emerald-300 border border-emerald-800">
            <CheckCircle2 className="w-3 h-3 mr-1 text-emerald-400" />
            CONCLUÍDA
          </span>
        );
      case "WAITING":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-950 text-blue-300 border border-blue-800">
            <Clock className="w-3 h-3 mr-1 text-blue-400 animate-pulse" />
            EM ESPERA (TIMER)
          </span>
        );
      case "RUNNING":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-950 text-amber-300 border border-amber-800">
            <Play className="w-3 h-3 mr-1 text-amber-400" />
            PROCESSANDO
          </span>
        );
      case "FAILED":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-950 text-rose-300 border border-rose-800">
            <XCircle className="w-3 h-3 mr-1 text-rose-400" />
            FALHA
          </span>
        );
      case "CANCELLED":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-800 text-slate-400 border border-slate-700">
            <Ban className="w-3 h-3 mr-1 text-slate-500" />
            CANCELADA
          </span>
        );
      case "SKIPPED":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-800 text-slate-300 border border-slate-700">
            IGNORADA (LGPD)
          </span>
        );
      default:
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-800 text-slate-400">
            {status}
          </span>
        );
    }
  };

  return (
    <AppLayout
      title={`Execuções • ${automation?.name || "Automação"}`}
      badge="FASE 10 • HISTÓRICO & AUDITORIA"
      actions={
        <div className="flex items-center gap-2">
          <Link
            href={`/automations/${id}`}
            className="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-sm border border-slate-700 flex items-center gap-1.5 transition"
          >
            <ArrowLeft className="w-4 h-4" />
            Voltar para Automação
          </Link>

          <button
            onClick={fetchRuns}
            disabled={loading}
            className="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-sm border border-slate-700 flex items-center gap-1.5 transition disabled:opacity-50"
          >
            <RefreshCw className={`w-4 h-4 ${loading ? "animate-spin" : ""}`} />
            Atualizar
          </button>
        </div>
      }
    >
      <div className="space-y-6">
        {/* Alerts */}
        {actionError && (
          <div className="p-4 bg-rose-950/60 border border-rose-800 rounded-lg text-rose-200 text-sm flex items-center gap-2">
            <AlertTriangle className="w-5 h-5 flex-shrink-0 text-rose-400" />
            <span>{actionError}</span>
          </div>
        )}

        {actionSuccess && (
          <div className="p-4 bg-emerald-950/60 border border-emerald-800 rounded-lg text-emerald-200 text-sm flex items-center gap-2">
            <CheckCircle2 className="w-5 h-5 flex-shrink-0 text-emerald-400" />
            <span>{actionSuccess}</span>
          </div>
        )}

        {/* Filter bar */}
        <div className="p-4 bg-slate-900 border border-slate-800 rounded-xl flex items-center justify-between">
          <div className="flex items-center gap-2 text-xs text-slate-400">
            <span>Total de registros:</span>
            <strong className="text-white font-mono">{totalCount}</strong>
          </div>

          <div className="flex items-center gap-3">
            <label className="text-xs text-slate-400">Filtrar por Status:</label>
            <select
              value={statusFilter}
              onChange={(e) => {
                setStatusFilter(e.target.value);
                setPage(1);
              }}
              className="px-3 py-1.5 bg-slate-950 border border-slate-800 rounded-lg text-xs text-slate-200 focus:outline-none focus:border-emerald-500"
            >
              <option value="ALL">Todos os Status</option>
              <option value="RUNNING">Em Processamento</option>
              <option value="WAITING">Em Espera (Timer)</option>
              <option value="COMPLETED">Concluídas</option>
              <option value="FAILED">Com Falhas</option>
              <option value="CANCELLED">Canceladas</option>
              <option value="SKIPPED">Ignoradas</option>
            </select>
          </div>
        </div>

        {/* Runs Table */}
        <div className="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
          {loading ? (
            <div className="p-12 text-center text-slate-400 text-sm">
              <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2 text-emerald-500" />
              Carregando histórico de execuções...
            </div>
          ) : runs.length === 0 ? (
            <div className="p-12 text-center text-slate-500 text-sm">
              <Activity className="w-8 h-8 mx-auto mb-2 text-slate-600" />
              Nenhuma execução encontrada para os filtros selecionados.
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead className="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold border-b border-slate-800">
                  <tr>
                    <th className="py-3 px-4">ID Run</th>
                    <th className="py-3 px-4">Jogador</th>
                    <th className="py-3 px-4">Status</th>
                    <th className="py-3 px-4">Nó Atual</th>
                    <th className="py-3 px-4">Passos</th>
                    <th className="py-3 px-4">Iniciado em</th>
                    <th className="py-3 px-4">Concluído em</th>
                    <th className="py-3 px-4 text-right">Ações</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800/60">
                  {runs.map((run) => (
                    <tr key={run.id} className="hover:bg-slate-800/40 transition">
                      <td className="py-3 px-4 font-mono font-bold text-slate-200">#{run.id}</td>
                      <td className="py-3 px-4">
                        <span className="font-semibold text-white">{run.player?.name || `Player #${run.player_id}`}</span>
                        <div className="text-[11px] text-slate-400">
                          {run.player?.masked_email || run.player?.email || "-"}
                        </div>
                      </td>
                      <td className="py-3 px-4">{getStatusBadge(run.status)}</td>
                      <td className="py-3 px-4 font-mono text-slate-300">
                        {run.currentNode?.name || run.current_node_id || "Finalizado"}
                      </td>
                      <td className="py-3 px-4 text-slate-300">
                        <span className="font-mono">{run.steps_count ?? "-"}</span> passos
                      </td>
                      <td className="py-3 px-4 text-slate-400">
                        {run.started_at ? new Date(run.started_at).toLocaleString("pt-BR") : "-"}
                      </td>
                      <td className="py-3 px-4 text-slate-400">
                        {run.completed_at ? new Date(run.completed_at).toLocaleString("pt-BR") : "-"}
                      </td>
                      <td className="py-3 px-4 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          <button
                            type="button"
                            onClick={() => handleInspectRun(run.id)}
                            className="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded text-[11px] font-medium transition flex items-center gap-1"
                          >
                            <Eye className="w-3.5 h-3.5 text-blue-400" />
                            Inspecionar
                          </button>

                          {(run.status === "RUNNING" || run.status === "WAITING") && (
                            <button
                              type="button"
                              onClick={() => handleCancelRun(run.id)}
                              className="px-2 py-1 bg-slate-800 hover:bg-rose-950 text-rose-400 rounded text-[11px] font-medium transition"
                              title="Cancelar Execução"
                            >
                              <Ban className="w-3.5 h-3.5" />
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {/* Pagination */}
          {totalPages > 1 && (
            <div className="p-4 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400">
              <span>
                Página {page} de {totalPages}
              </span>
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  disabled={page <= 1}
                  onClick={() => setPage((p) => p - 1)}
                  className="px-3 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded disabled:opacity-50"
                >
                  Anterior
                </button>
                <button
                  type="button"
                  disabled={page >= totalPages}
                  onClick={() => setPage((p) => p + 1)}
                  className="px-3 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded disabled:opacity-50"
                >
                  Próxima
                </button>
              </div>
            </div>
          )}
        </div>

        {/* Modal: Run Inspection Details */}
        {selectedRun && (
          <div className="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4">
            <div className="bg-slate-900 border border-slate-800 rounded-xl max-w-4xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden">
              {/* Modal Header */}
              <div className="p-5 border-b border-slate-800 flex items-center justify-between bg-slate-950">
                <div className="space-y-1">
                  <div className="flex items-center gap-2">
                    <h3 className="text-base font-semibold text-white">Execução #{selectedRun.id}</h3>
                    {getStatusBadge(selectedRun.status)}
                  </div>
                  <p className="text-xs text-slate-400">
                    Jogador: <strong className="text-slate-200">{selectedRun.player?.name}</strong> • Email:{" "}
                    {selectedRun.player?.masked_email || selectedRun.player?.email}
                  </p>
                </div>
                <button
                  type="button"
                  onClick={() => setSelectedRun(null)}
                  className="text-slate-500 hover:text-white text-lg p-2"
                >
                  ✕
                </button>
              </div>

              {/* Modal Body */}
              <div className="p-6 overflow-y-auto space-y-6 text-xs flex-1">
                {/* Error Banner if run failed */}
                {selectedRun.last_error && (
                  <div className="p-4 bg-rose-950/70 border border-rose-800 rounded-xl text-rose-200 space-y-1">
                    <span className="font-semibold block text-sm">Erro de Execução:</span>
                    <pre className="font-mono text-xs whitespace-pre-wrap">{selectedRun.last_error}</pre>
                  </div>
                )}

                {/* Steps Timeline */}
                <div className="space-y-3">
                  <h4 className="text-xs uppercase tracking-wider font-semibold text-slate-400 flex items-center gap-1.5">
                    <Layers className="w-4 h-4 text-emerald-400" />
                    Passos da Execução ({selectedRun.steps?.length || 0})
                  </h4>

                  <div className="space-y-2">
                    {selectedRun.steps && selectedRun.steps.length > 0 ? (
                      selectedRun.steps.map((step, idx) => (
                        <div
                          key={step.id || idx}
                          className="p-3.5 bg-slate-950 border border-slate-800 rounded-lg space-y-2"
                        >
                          <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                              <span className="font-mono text-slate-500 text-xs">#{idx + 1}</span>
                              <span className="font-semibold text-slate-200">
                                {step.node?.name || `Nó ID ${step.node_id}`}
                              </span>
                              <span className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-900 text-slate-400 border border-slate-800">
                                {step.node?.node_type || "NODE"}
                              </span>
                            </div>

                            <span
                              className={`px-2 py-0.5 rounded text-[10px] font-bold ${
                                step.status === "COMPLETED"
                                  ? "bg-emerald-950 text-emerald-300 border border-emerald-800"
                                  : step.status === "FAILED"
                                  ? "bg-rose-950 text-rose-300 border border-rose-800"
                                  : step.status === "WAITING"
                                  ? "bg-blue-950 text-blue-300 border border-blue-800"
                                  : step.status === "SKIPPED"
                                  ? "bg-slate-800 text-slate-400"
                                  : "bg-amber-950 text-amber-300"
                              }`}
                            >
                              {step.status}
                            </span>
                          </div>

                          {/* Error message */}
                          {step.error && (
                            <div className="text-rose-400 font-mono text-[11px] bg-rose-950/40 p-2 rounded border border-rose-900/60">
                              {step.error}
                            </div>
                          )}

                          {/* Output Payload preview */}
                          {step.output && Object.keys(step.output).length > 0 && (
                            <details className="text-[11px] text-slate-400">
                              <summary className="cursor-pointer hover:text-slate-200">
                                Ver Resultado / Output JSON
                              </summary>
                              <pre className="mt-1 p-2 bg-slate-900 border border-slate-800 rounded text-slate-300 font-mono overflow-x-auto">
                                {JSON.stringify(step.output, null, 2)}
                              </pre>
                            </details>
                          )}
                        </div>
                      ))
                    ) : (
                      <div className="py-4 text-center text-slate-500">Nenhum passo registrado ainda.</div>
                    )}
                  </div>
                </div>

                {/* Audit Logs */}
                <div className="space-y-3">
                  <h4 className="text-xs uppercase tracking-wider font-semibold text-slate-400 flex items-center gap-1.5">
                    <Shield className="w-4 h-4 text-blue-400" />
                    Logs de Auditoria ({selectedRun.logs?.length || 0})
                  </h4>

                  <div className="p-3 bg-slate-950 border border-slate-800 rounded-lg space-y-2 max-h-48 overflow-y-auto font-mono text-[11px]">
                    {selectedRun.logs && selectedRun.logs.length > 0 ? (
                      selectedRun.logs.map((log) => (
                        <div key={log.id} className="flex items-start gap-2 text-slate-300">
                          <span className="text-slate-500 shrink-0">
                            {new Date(log.created_at).toLocaleTimeString("pt-BR")}
                          </span>
                          <span
                            className={`font-bold shrink-0 ${
                              log.level === "ERROR"
                                ? "text-rose-400"
                                : log.level === "WARNING"
                                ? "text-amber-400"
                                : "text-emerald-400"
                            }`}
                          >
                            [{log.level}]
                          </span>
                          <span className="shrink-0 text-slate-400">[{log.event}]:</span>
                          <span className="text-slate-200">{log.message}</span>
                        </div>
                      ))
                    ) : (
                      <div className="text-slate-500">Nenhum log registrado para esta execução.</div>
                    )}
                  </div>
                </div>
              </div>

              {/* Modal Footer */}
              <div className="p-4 border-t border-slate-800 bg-slate-950 flex items-center justify-between">
                <div>
                  {(selectedRun.status === "RUNNING" || selectedRun.status === "WAITING") && (
                    <button
                      type="button"
                      onClick={() => handleCancelRun(selectedRun.id)}
                      className="px-3 py-1.5 bg-rose-900 hover:bg-rose-800 text-rose-100 rounded text-xs font-semibold flex items-center gap-1.5"
                    >
                      <Ban className="w-3.5 h-3.5" />
                      Cancelar Execução
                    </button>
                  )}
                </div>
                <button
                  type="button"
                  onClick={() => setSelectedRun(null)}
                  className="px-4 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded text-xs font-semibold"
                >
                  Fechar
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
