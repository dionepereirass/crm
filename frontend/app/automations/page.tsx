"use client";

import React, { useEffect, useState } from "react";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import {
  automationService,
  Automation,
} from "@/services/automation-service";
import {
  Zap,
  Plus,
  RefreshCw,
  Search,
  CheckCircle2,
  AlertTriangle,
  Play,
  Pause,
  Ban,
  Trash2,
  Eye,
  Edit,
  Activity,
  Layers,
  Clock,
  ArrowRight,
} from "lucide-react";

export default function AutomationsPage() {
  const [automations, setAutomations] = useState<Automation[]>([]);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState("ALL");
  const [triggerFilter, setTriggerFilter] = useState("ALL");
  const [search, setSearch] = useState("");
  const [actionError, setActionError] = useState<string | null>(null);
  const [actionSuccess, setActionSuccess] = useState<string | null>(null);
  const [actionLoadingId, setActionLoadingId] = useState<number | null>(null);

  const fetchAutomations = async () => {
    try {
      setLoading(true);
      setActionError(null);
      const res = await automationService.list({
        status: statusFilter,
        trigger_type: triggerFilter,
        search: search || undefined,
      });
      setAutomations(res.data);
    } catch (err: any) {
      setActionError(err.message || "Falha ao carregar automações.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchAutomations();
  }, [statusFilter, triggerFilter]);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    fetchAutomations();
  };

  const handleActivate = async (id: number) => {
    try {
      setActionLoadingId(id);
      setActionError(null);
      await automationService.activate(id);
      setActionSuccess(`Automação #${id} ativada com sucesso!`);
      await fetchAutomations();
    } catch (err: any) {
      setActionError(err.message || "Falha ao ativar automação.");
    } finally {
      setActionLoadingId(null);
    }
  };

  const handlePause = async (id: number) => {
    try {
      setActionLoadingId(id);
      setActionError(null);
      await automationService.pause(id);
      setActionSuccess(`Automação #${id} pausada.`);
      await fetchAutomations();
    } catch (err: any) {
      setActionError(err.message || "Falha ao pausar automação.");
    } finally {
      setActionLoadingId(null);
    }
  };

  const handleDeactivate = async (id: number) => {
    try {
      setActionLoadingId(id);
      setActionError(null);
      await automationService.deactivate(id);
      setActionSuccess(`Automação #${id} desativada.`);
      await fetchAutomations();
    } catch (err: any) {
      setActionError(err.message || "Falha ao desativar automação.");
    } finally {
      setActionLoadingId(null);
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm(`Tem certeza que deseja excluir permanentemente a automação #${id}?`)) {
      return;
    }
    try {
      setActionLoadingId(id);
      setActionError(null);
      await automationService.delete(id);
      setActionSuccess(`Automação #${id} excluída com sucesso.`);
      await fetchAutomations();
    } catch (err: any) {
      setActionError(err.message || "Falha ao excluir automação.");
    } finally {
      setActionLoadingId(null);
    }
  };

  // Metrics
  const totalCount = automations.length;
  const activeCount = automations.filter((a) => a.status === "ACTIVE").length;
  const pausedCount = automations.filter((a) => a.status === "PAUSED").length;
  const totalRuns = automations.reduce((acc, a) => acc + (a.total_runs || 0), 0);

  const getStatusBadge = (status: string) => {
    switch (status) {
      case "ACTIVE":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-950 text-emerald-300 border border-emerald-800">
            <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 mr-1.5 animate-pulse" />
            ATIVA
          </span>
        );
      case "PAUSED":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-950 text-amber-300 border border-amber-800">
            <span className="w-1.5 h-1.5 rounded-full bg-amber-400 mr-1.5" />
            PAUSADA
          </span>
        );
      case "INACTIVE":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-950 text-rose-300 border border-rose-800">
            INATIVA
          </span>
        );
      case "DRAFT":
      default:
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-800 text-slate-300 border border-slate-700">
            RASCUNHO
          </span>
        );
    }
  };

  const getTriggerLabel = (type: string) => {
    const labels: Record<string, string> = {
      PLAYER_CREATED: "Novo Cadastro",
      PLAYER_VERIFIED: "Conta Verificada",
      PASSWORD_RESET: "Redefinição de Senha",
      LOGIN_FAILED: "Falha de Login",
      DEPOSIT_INITIATED: "Depósito Iniciado",
      DEPOSIT_SUCCESS: "Depósito Aprovado",
      DEPOSIT_FAILED: "Depósito Falhou",
      WITHDRAWAL_INITIATED: "Saque Solicitado",
      WITHDRAWAL_SUCCESS: "Saque Concluído",
      WITHDRAWAL_FAILED: "Saque Falhou",
      BET_PLACED: "Aposta Realizada",
      BET_WON: "Aposta Ganha",
      BET_LOST: "Aposta Perdida",
      PLAYER_INACTIVITY: "Inatividade de Jogador",
    };
    return labels[type] || type;
  };

  return (
    <AppLayout
      title="Automações & Jornadas"
      badge="FASE 10 • ENGINE DETERMINÍSTICO"
      actions={
        <div className="flex items-center gap-3">
          <button
            onClick={fetchAutomations}
            disabled={loading}
            className="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-sm font-medium border border-slate-700 flex items-center gap-2 transition disabled:opacity-50"
          >
            <RefreshCw className={`w-4 h-4 ${loading ? "animate-spin" : ""}`} />
            Atualizar
          </button>
          <Link
            href="/automations/new"
            className="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-semibold flex items-center gap-2 transition shadow-sm shadow-emerald-900/50"
          >
            <Plus className="w-4 h-4" />
            Nova Automação
          </Link>
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

        {/* Metrics Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div className="p-4 bg-slate-900 border border-slate-800 rounded-xl space-y-1">
            <div className="flex items-center justify-between text-slate-400 text-xs uppercase tracking-wider font-semibold">
              Total de Automações
              <Layers className="w-4 h-4 text-slate-500" />
            </div>
            <div className="text-2xl font-bold text-white">{totalCount}</div>
            <div className="text-xs text-slate-500">Jornadas configuradas</div>
          </div>

          <div className="p-4 bg-slate-900 border border-slate-800 rounded-xl space-y-1">
            <div className="flex items-center justify-between text-slate-400 text-xs uppercase tracking-wider font-semibold">
              Jornadas Ativas
              <Zap className="w-4 h-4 text-emerald-500" />
            </div>
            <div className="text-2xl font-bold text-emerald-400">{activeCount}</div>
            <div className="text-xs text-slate-500">Ouvindo gatilhos em tempo real</div>
          </div>

          <div className="p-4 bg-slate-900 border border-slate-800 rounded-xl space-y-1">
            <div className="flex items-center justify-between text-slate-400 text-xs uppercase tracking-wider font-semibold">
              Jornadas Pausadas
              <Pause className="w-4 h-4 text-amber-500" />
            </div>
            <div className="text-2xl font-bold text-amber-400">{pausedCount}</div>
            <div className="text-xs text-slate-500">Suspensas temporariamente</div>
          </div>

          <div className="p-4 bg-slate-900 border border-slate-800 rounded-xl space-y-1">
            <div className="flex items-center justify-between text-slate-400 text-xs uppercase tracking-wider font-semibold">
              Execuções Registradas
              <Activity className="w-4 h-4 text-blue-500" />
            </div>
            <div className="text-2xl font-bold text-blue-400">{totalRuns.toLocaleString("pt-BR")}</div>
            <div className="text-xs text-slate-500">Histórico de players processados</div>
          </div>
        </div>

        {/* Filters */}
        <div className="p-4 bg-slate-900/80 border border-slate-800 rounded-xl flex flex-col md:flex-row gap-4 items-center justify-between">
          <form onSubmit={handleSearchSubmit} className="flex-1 w-full flex items-center gap-2">
            <div className="relative flex-1">
              <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" />
              <input
                type="text"
                placeholder="Buscar por nome da jornada..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full pl-9 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-emerald-500"
              />
            </div>
            <button
              type="submit"
              className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-sm font-medium rounded-lg border border-slate-700 transition"
            >
              Filtrar
            </button>
          </form>

          <div className="flex flex-wrap items-center gap-3 w-full md:w-auto">
            {/* Status Filter */}
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-slate-200 focus:outline-none focus:border-emerald-500"
            >
              <option value="ALL">Todos os Status</option>
              <option value="ACTIVE">Ativas</option>
              <option value="PAUSED">Pausadas</option>
              <option value="DRAFT">Rascunho</option>
              <option value="INACTIVE">Inativas</option>
            </select>

            {/* Trigger Filter */}
            <select
              value={triggerFilter}
              onChange={(e) => setTriggerFilter(e.target.value)}
              className="px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-slate-200 focus:outline-none focus:border-emerald-500"
            >
              <option value="ALL">Todos os Gatilhos</option>
              <option value="PLAYER_CREATED">Novo Cadastro</option>
              <option value="DEPOSIT_SUCCESS">Depósito Aprovado</option>
              <option value="DEPOSIT_FAILED">Depósito Falhou</option>
              <option value="BET_PLACED">Aposta Realizada</option>
              <option value="BET_WON">Aposta Ganha</option>
              <option value="BET_LOST">Aposta Perdida</option>
              <option value="PLAYER_INACTIVITY">Inatividade</option>
            </select>
          </div>
        </div>

        {/* Table */}
        <div className="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
          {loading ? (
            <div className="p-12 text-center text-slate-400">
              <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2 text-emerald-500" />
              Carregando jornadas de automação...
            </div>
          ) : automations.length === 0 ? (
            <div className="p-12 text-center text-slate-400">
              <Zap className="w-10 h-10 mx-auto mb-3 text-slate-600" />
              <p className="text-base font-medium text-slate-300">Nenhuma jornada encontrada.</p>
              <p className="text-sm mt-1 text-slate-500">Crie uma nova jornada para automatizar o relacionamento com seus jogadores.</p>
              <Link
                href="/automations/new"
                className="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-semibold transition"
              >
                <Plus className="w-4 h-4" />
                Criar Primeira Jornada
              </Link>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-950 text-slate-400 text-xs uppercase tracking-wider font-semibold border-b border-slate-800">
                  <tr>
                    <th className="py-3 px-4">Nome da Automação</th>
                    <th className="py-3 px-4">Gatilho</th>
                    <th className="py-3 px-4">Status</th>
                    <th className="py-3 px-4">Execuções</th>
                    <th className="py-3 px-4">Data de Criação</th>
                    <th className="py-3 px-4 text-right">Ações</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800/60">
                  {automations.map((a) => {
                    const isBusy = actionLoadingId === a.id;
                    return (
                      <tr key={a.id} className="hover:bg-slate-800/40 transition">
                        <td className="py-4 px-4">
                          <Link href={`/automations/${a.id}`} className="group block">
                            <span className="font-semibold text-slate-100 group-hover:text-emerald-400 transition flex items-center gap-2">
                              {a.name}
                              <ArrowRight className="w-3.5 h-3.5 opacity-0 group-hover:opacity-100 transition" />
                            </span>
                            {a.description && (
                              <p className="text-xs text-slate-400 line-clamp-1 mt-0.5">{a.description}</p>
                            )}
                          </Link>
                        </td>

                        <td className="py-4 px-4">
                          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-mono bg-slate-950 border border-slate-800 text-slate-300">
                            <Zap className="w-3 h-3 text-amber-400" />
                            {getTriggerLabel(a.trigger_type)}
                          </span>
                        </td>

                        <td className="py-4 px-4">{getStatusBadge(a.status)}</td>

                        <td className="py-4 px-4">
                          <div className="text-xs space-y-0.5">
                            <span className="font-semibold text-slate-200">
                              {(a.total_runs || 0).toLocaleString("pt-BR")} runs
                            </span>
                            <div className="flex items-center gap-2 text-[11px] text-slate-400">
                              <span className="text-emerald-400">{(a.completed_runs || 0)} ok</span>
                              <span>•</span>
                              <span className="text-rose-400">{(a.failed_runs || 0)} err</span>
                            </div>
                          </div>
                        </td>

                        <td className="py-4 px-4 text-xs text-slate-400">
                          <span className="flex items-center gap-1">
                            <Clock className="w-3 h-3 text-slate-500" />
                            {new Date(a.created_at).toLocaleDateString("pt-BR")}
                          </span>
                        </td>

                        <td className="py-4 px-4 text-right">
                          <div className="flex items-center justify-end gap-1">
                            <Link
                              href={`/automations/${a.id}`}
                              title="Visualizar Detalhes"
                              className="p-1.5 hover:bg-slate-800 text-slate-300 hover:text-white rounded-md transition"
                            >
                              <Eye className="w-4 h-4" />
                            </Link>

                            <Link
                              href={`/automations/${a.id}/edit`}
                              title="Editar Grafo / Nós"
                              className="p-1.5 hover:bg-slate-800 text-slate-300 hover:text-emerald-400 rounded-md transition"
                            >
                              <Edit className="w-4 h-4" />
                            </Link>

                            <Link
                              href={`/automations/${a.id}/runs`}
                              title="Ver Execuções"
                              className="p-1.5 hover:bg-slate-800 text-slate-300 hover:text-blue-400 rounded-md transition"
                            >
                              <Activity className="w-4 h-4" />
                            </Link>

                            {/* Lifecycle controls */}
                            {a.status === "ACTIVE" ? (
                              <button
                                onClick={() => handlePause(a.id)}
                                disabled={isBusy}
                                title="Pausar Automação"
                                className="p-1.5 hover:bg-slate-800 text-amber-400 hover:text-amber-300 rounded-md transition disabled:opacity-50"
                              >
                                <Pause className="w-4 h-4" />
                              </button>
                            ) : (
                              <button
                                onClick={() => handleActivate(a.id)}
                                disabled={isBusy}
                                title="Ativar Automação"
                                className="p-1.5 hover:bg-slate-800 text-emerald-400 hover:text-emerald-300 rounded-md transition disabled:opacity-50"
                              >
                                <Play className="w-4 h-4" />
                              </button>
                            )}

                            {a.status !== "INACTIVE" && (
                              <button
                                onClick={() => handleDeactivate(a.id)}
                                disabled={isBusy}
                                title="Desativar Automação"
                                className="p-1.5 hover:bg-slate-800 text-slate-400 hover:text-rose-400 rounded-md transition disabled:opacity-50"
                              >
                                <Ban className="w-4 h-4" />
                              </button>
                            )}

                            {a.status !== "ACTIVE" && (
                              <button
                                onClick={() => handleDelete(a.id)}
                                disabled={isBusy}
                                title="Excluir Permanentemente"
                                className="p-1.5 hover:bg-slate-800 text-slate-400 hover:text-rose-400 rounded-md transition disabled:opacity-50"
                              >
                                <Trash2 className="w-4 h-4" />
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </AppLayout>
  );
}
