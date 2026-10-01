"use client";

import React, { useEffect, useState } from "react";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import {
  providerService,
  Provider,
} from "@/services/provider-service";
import {
  Globe,
  Mail,
  MessageSquare,
  Plus,
  RefreshCw,
  Search,
  CheckCircle2,
  XCircle,
  AlertTriangle,
  Shield,
  Activity,
  Zap,
  Sliders,
  Play,
  Edit,
  Trash2,
} from "lucide-react";

export default function ProvidersPage() {
  const [providers, setProviders] = useState<Provider[]>([]);
  const [loading, setLoading] = useState(true);
  const [channelFilter, setChannelFilter] = useState("ALL");
  const [statusFilter, setStatusFilter] = useState("ALL");
  const [search, setSearch] = useState("");
  const [checkingHealthId, setCheckingHealthId] = useState<number | null>(null);
  const [healthResults, setHealthResults] = useState<Record<number, { healthy: boolean; latency: number; message: string }>>({});
  const [actionError, setActionError] = useState<string | null>(null);
  const [actionSuccess, setActionSuccess] = useState<string | null>(null);

  const fetchProviders = async () => {
    try {
      setLoading(true);
      setActionError(null);
      const res = await providerService.list({
        channel: channelFilter,
        status: statusFilter,
        search: search || undefined,
      });
      setProviders(res.data);
    } catch (err: any) {
      setActionError(err.message || "Falha ao carregar provedores.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchProviders();
  }, [channelFilter, statusFilter]);

  const handleHealthCheck = async (provider: Provider) => {
    try {
      setCheckingHealthId(provider.id);
      setActionError(null);
      setActionSuccess(null);
      const res = await providerService.healthCheck(provider.id);
      setHealthResults((prev) => ({
        ...prev,
        [provider.id]: {
          healthy: res.healthy,
          latency: res.latency_ms,
          message: res.message,
        },
      }));
      setActionSuccess(`Health Check concluído para '${provider.name}': ${res.healthy ? "ONLINE" : "FALHA"} (${res.latency_ms}ms)`);
      fetchProviders();
    } catch (err: any) {
      setActionError(err.message || "Erro ao checar conectividade.");
    } finally {
      setCheckingHealthId(null);
    }
  };

  const handleToggleStatus = async (provider: Provider) => {
    try {
      setActionError(null);
      if (provider.status === "ACTIVE") {
        await providerService.deactivate(provider.id);
        setActionSuccess(`Provedor '${provider.name}' desativado.`);
      } else {
        await providerService.activate(provider.id);
        setActionSuccess(`Provedor '${provider.name}' ativado com sucesso.`);
      }
      fetchProviders();
    } catch (err: any) {
      setActionError(err.message || "Erro ao alternar status do provedor.");
    }
  };

  const handleDelete = async (provider: Provider) => {
    if (!confirm(`Deseja realmente remover o provedor '${provider.name}'?`)) return;
    try {
      setActionError(null);
      await providerService.delete(provider.id);
      setActionSuccess(`Provedor '${provider.name}' removido.`);
      fetchProviders();
    } catch (err: any) {
      setActionError(err.message || "Erro ao excluir provedor.");
    }
  };

  const defaultEmail = providers.find((p) => p.channel === "EMAIL" && p.is_default);
  const defaultSms = providers.find((p) => p.channel === "SMS" && p.is_default);
  const activeCount = providers.filter((p) => p.status === "ACTIVE").length;

  return (
    <AppLayout
      title="Provedores de Mensageria"
      badge="E-mail & SMS"
      actions={
        <div className="flex items-center space-x-3">
          <Link
            href="/messages"
            className="flex items-center space-x-1.5 px-3 py-1.5 rounded-lg border border-slate-700 bg-slate-800 text-xs font-medium text-slate-200 hover:bg-slate-700 transition"
          >
            <Zap className="w-3.5 h-3.5 text-amber-400" />
            <span>Fila de Mensagens</span>
          </Link>
          <Link
            href="/providers/new"
            className="flex items-center space-x-1.5 px-3.5 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-xs font-semibold text-slate-950 transition shadow-lg shadow-emerald-500/20"
          >
            <Plus className="w-4 h-4" />
            <span>Novo Provedor</span>
          </Link>
        </div>
      }
    >
      <div className="p-8 space-y-6">
        {/* Alerts */}
        {actionError && (
          <div className="p-4 rounded-xl bg-red-500/10 border border-red-500/30 flex items-center space-x-3 text-red-400 text-xs">
            <XCircle className="w-4 h-4 shrink-0" />
            <span>{actionError}</span>
          </div>
        )}
        {actionSuccess && (
          <div className="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center space-x-3 text-emerald-400 text-xs">
            <CheckCircle2 className="w-4 h-4 shrink-0" />
            <span>{actionSuccess}</span>
          </div>
        )}

        {/* Top Metric Cards */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div className="bg-[#0f172a] border border-slate-800 rounded-xl p-4 flex items-center justify-between">
            <div>
              <p className="text-[11px] font-medium text-slate-400">Total Provedores</p>
              <p className="text-2xl font-bold text-white mt-1">{providers.length}</p>
            </div>
            <div className="w-10 h-10 rounded-lg bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-400">
              <Globe className="w-5 h-5" />
            </div>
          </div>

          <div className="bg-[#0f172a] border border-slate-800 rounded-xl p-4 flex items-center justify-between">
            <div>
              <p className="text-[11px] font-medium text-slate-400">Provedores Ativos</p>
              <p className="text-2xl font-bold text-emerald-400 mt-1">{activeCount}</p>
            </div>
            <div className="w-10 h-10 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
              <Activity className="w-5 h-5" />
            </div>
          </div>

          <div className="bg-[#0f172a] border border-slate-800 rounded-xl p-4 flex items-center justify-between">
            <div className="overflow-hidden pr-2">
              <p className="text-[11px] font-medium text-slate-400">E-mail Principal</p>
              <p className="text-sm font-semibold text-white mt-1 truncate">
                {defaultEmail ? defaultEmail.name : "Nenhum Padrão"}
              </p>
              <p className="text-[10px] text-slate-500 font-mono">
                {defaultEmail ? defaultEmail.driver.toUpperCase() : "Configure em Provedores"}
              </p>
            </div>
            <div className="w-10 h-10 rounded-lg bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-400 shrink-0">
              <Mail className="w-5 h-5" />
            </div>
          </div>

          <div className="bg-[#0f172a] border border-slate-800 rounded-xl p-4 flex items-center justify-between">
            <div className="overflow-hidden pr-2">
              <p className="text-[11px] font-medium text-slate-400">SMS Principal</p>
              <p className="text-sm font-semibold text-white mt-1 truncate">
                {defaultSms ? defaultSms.name : "Nenhum Padrão"}
              </p>
              <p className="text-[10px] text-slate-500 font-mono">
                {defaultSms ? defaultSms.driver.toUpperCase() : "Configure em Provedores"}
              </p>
            </div>
            <div className="w-10 h-10 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 shrink-0">
              <MessageSquare className="w-5 h-5" />
            </div>
          </div>
        </div>

        {/* Filter Bar */}
        <div className="flex flex-col md:flex-row items-center justify-between gap-4 bg-[#0f172a] border border-slate-800 rounded-xl p-4">
          <div className="flex items-center space-x-3 w-full md:w-auto">
            {/* Channel filter */}
            <div className="flex rounded-lg bg-slate-900 border border-slate-800 p-0.5 text-xs">
              {["ALL", "EMAIL", "SMS"].map((ch) => (
                <button
                  key={ch}
                  onClick={() => setChannelFilter(ch)}
                  className={`px-3 py-1 rounded-md transition font-medium ${
                    channelFilter === ch
                      ? "bg-emerald-500 text-slate-950 font-semibold"
                      : "text-slate-400 hover:text-slate-200"
                  }`}
                >
                  {ch === "ALL" ? "Todos Canais" : ch}
                </button>
              ))}
            </div>

            {/* Status filter */}
            <div className="flex rounded-lg bg-slate-900 border border-slate-800 p-0.5 text-xs">
              {["ALL", "ACTIVE", "INACTIVE", "ERROR"].map((st) => (
                <button
                  key={st}
                  onClick={() => setStatusFilter(st)}
                  className={`px-3 py-1 rounded-md transition font-medium ${
                    statusFilter === st
                      ? "bg-slate-700 text-white font-semibold"
                      : "text-slate-400 hover:text-slate-200"
                  }`}
                >
                  {st === "ALL" ? "Todos Status" : st}
                </button>
              ))}
            </div>
          </div>

          {/* Search box & Refresh */}
          <div className="flex items-center space-x-2 w-full md:w-auto">
            <div className="relative flex-1 md:w-64">
              <Search className="w-4 h-4 text-slate-500 absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                type="text"
                placeholder="Buscar por nome ou driver..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                onKeyDown={(e) => e.key === "Enter" && fetchProviders()}
                className="w-full pl-9 pr-3 py-1.5 rounded-lg bg-slate-900 border border-slate-800 text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-emerald-500"
              />
            </div>
            <button
              onClick={fetchProviders}
              disabled={loading}
              className="p-2 rounded-lg bg-slate-900 border border-slate-800 text-slate-400 hover:text-slate-200 transition"
              title="Recarregar"
            >
              <RefreshCw className={`w-4 h-4 ${loading ? "animate-spin" : ""}`} />
            </button>
          </div>
        </div>

        {/* Providers Table / Cards */}
        {loading ? (
          <div className="p-12 text-center text-slate-500 text-xs">
            <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2 text-emerald-500" />
            Carregando provedores e status de conectividade...
          </div>
        ) : providers.length === 0 ? (
          <div className="p-12 text-center bg-[#0f172a] border border-slate-800 rounded-xl space-y-3">
            <Globe className="w-10 h-10 text-slate-600 mx-auto" />
            <h3 className="text-sm font-semibold text-slate-300">Nenhum provedor configurado</h3>
            <p className="text-xs text-slate-500 max-w-sm mx-auto">
              Configure provedores de mensageria oficiais (Brevo, Zenvia) ou simuladores seguros (Fake Providers) para esta plataforma.
            </p>
            <Link
              href="/providers/new"
              className="inline-flex items-center space-x-2 px-4 py-2 rounded-lg bg-emerald-500 text-slate-950 font-semibold text-xs hover:bg-emerald-600 transition"
            >
              <Plus className="w-4 h-4" />
              <span>Configurar Primeiro Provedor</span>
            </Link>
          </div>
        ) : (
          <div className="bg-[#0f172a] border border-slate-800 rounded-xl overflow-hidden shadow-xl">
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs text-slate-300">
                <thead className="bg-slate-900/80 text-[11px] text-slate-400 uppercase tracking-wider border-b border-slate-800 font-semibold">
                  <tr>
                    <th className="py-3 px-4">Provedor & Driver</th>
                    <th className="py-3 px-4">Canal</th>
                    <th className="py-3 px-4">Prioridade</th>
                    <th className="py-3 px-4">Segurança / Credenciais</th>
                    <th className="py-3 px-4">Status & Conexão</th>
                    <th className="py-3 px-4 text-right">Ações</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800/60 font-sans">
                  {providers.map((p) => {
                    const health = healthResults[p.id];
                    return (
                      <tr key={p.id} className="hover:bg-slate-800/30 transition">
                        {/* Name & Driver */}
                        <td className="py-3.5 px-4">
                          <div className="flex items-center space-x-3">
                            <div
                              className={`w-9 h-9 rounded-lg flex items-center justify-center shrink-0 border ${
                                p.channel === "EMAIL"
                                  ? "bg-purple-500/10 border-purple-500/20 text-purple-400"
                                  : "bg-emerald-500/10 border-emerald-500/20 text-emerald-400"
                              }`}
                            >
                              {p.channel === "EMAIL" ? <Mail className="w-4 h-4" /> : <MessageSquare className="w-4 h-4" />}
                            </div>
                            <div>
                              <div className="flex items-center space-x-2">
                                <Link
                                  href={`/providers/${p.id}`}
                                  className="font-semibold text-white hover:text-emerald-400 transition"
                                >
                                  {p.name}
                                </Link>
                                {p.is_default && (
                                  <span className="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                    PADRÃO
                                  </span>
                                )}
                                {p.is_fallback && (
                                  <span className="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-800 text-slate-300 border border-slate-700">
                                    FALLBACK
                                  </span>
                                )}
                              </div>
                              <div className="flex items-center space-x-2 mt-0.5">
                                <span className="text-[10px] font-mono uppercase text-slate-400">{p.driver}</span>
                                <span className="text-slate-600">•</span>
                                <span className="text-[10px] text-slate-500">
                                  Limite: {p.rate_limit_per_minute}/min
                                </span>
                              </div>
                            </div>
                          </div>
                        </td>

                        {/* Channel */}
                        <td className="py-3.5 px-4">
                          <span
                            className={`px-2 py-0.5 rounded-full text-[10px] font-semibold tracking-wide border ${
                              p.channel === "EMAIL"
                                ? "bg-purple-500/10 text-purple-300 border-purple-500/30"
                                : "bg-emerald-500/10 text-emerald-300 border-emerald-500/30"
                            }`}
                          >
                            {p.channel}
                          </span>
                        </td>

                        {/* Priority */}
                        <td className="py-3.5 px-4 font-mono text-slate-300">
                          <span className="px-2 py-0.5 rounded bg-slate-900 border border-slate-800 text-xs">
                            #{p.priority}
                          </span>
                        </td>

                        {/* Credentials Security */}
                        <td className="py-3.5 px-4">
                          {p.credentials_configured ? (
                            <span className="inline-flex items-center space-x-1.5 text-xs text-emerald-400">
                              <Shield className="w-3.5 h-3.5" />
                              <span className="text-[11px] font-medium">AES-256 Configurada</span>
                            </span>
                          ) : (
                            <span className="inline-flex items-center space-x-1.5 text-xs text-amber-400">
                              <AlertTriangle className="w-3.5 h-3.5" />
                              <span className="text-[11px] font-medium">Não configurada</span>
                            </span>
                          )}
                        </td>

                        {/* Health Status & Connection */}
                        <td className="py-3.5 px-4">
                          <div className="space-y-1">
                            <div className="flex items-center space-x-2">
                              <span
                                className={`w-2 h-2 rounded-full ${
                                  p.status === "ACTIVE" ? "bg-emerald-400 animate-pulse" : "bg-slate-600"
                                }`}
                              />
                              <span
                                className={`text-[11px] font-semibold ${
                                  p.status === "ACTIVE"
                                    ? "text-emerald-400"
                                    : p.status === "ERROR"
                                    ? "text-red-400"
                                    : "text-slate-400"
                                }`}
                              >
                                {p.status}
                              </span>
                            </div>

                            {/* Health check badge */}
                            {health ? (
                              <div className="text-[10px] text-slate-400 font-mono">
                                <span className={health.healthy ? "text-emerald-400" : "text-red-400"}>
                                  {health.healthy ? "HEALTHY" : "DOWN"} ({health.latency}ms)
                                </span>
                              </div>
                            ) : p.last_health_check_at ? (
                              <div className="text-[10px] text-slate-500 font-mono">
                                Checado {new Date(p.last_health_check_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                              </div>
                            ) : null}
                          </div>
                        </td>

                        {/* Actions */}
                        <td className="py-3.5 px-4 text-right">
                          <div className="flex items-center justify-end space-x-1.5">
                            {/* Health check runner */}
                            <button
                              onClick={() => handleHealthCheck(p)}
                              disabled={checkingHealthId === p.id}
                              className="p-1.5 rounded-lg bg-slate-900 border border-slate-800 text-slate-400 hover:text-emerald-400 transition"
                              title="Executar Health Check de Conexão"
                            >
                              <Activity className={`w-3.5 h-3.5 ${checkingHealthId === p.id ? "animate-spin text-emerald-400" : ""}`} />
                            </button>

                            {/* Toggle Active */}
                            <button
                              onClick={() => handleToggleStatus(p)}
                              className={`px-2 py-1 rounded-md text-[10px] font-semibold border transition ${
                                p.status === "ACTIVE"
                                  ? "bg-slate-900 border-slate-800 text-slate-400 hover:text-amber-400"
                                  : "bg-emerald-500/10 border-emerald-500/30 text-emerald-400 hover:bg-emerald-500/20"
                              }`}
                            >
                              {p.status === "ACTIVE" ? "Desativar" : "Ativar"}
                            </button>

                            {/* Test dispatch */}
                            <Link
                              href={`/providers/${p.id}`}
                              className="p-1.5 rounded-lg bg-slate-900 border border-slate-800 text-slate-400 hover:text-blue-400 transition"
                              title="Testar Envio / Detalhes"
                            >
                              <Play className="w-3.5 h-3.5" />
                            </Link>

                            {/* Edit */}
                            <Link
                              href={`/providers/${p.id}/edit`}
                              className="p-1.5 rounded-lg bg-slate-900 border border-slate-800 text-slate-400 hover:text-white transition"
                              title="Editar Provedor"
                            >
                              <Edit className="w-3.5 h-3.5" />
                            </Link>

                            {/* Delete */}
                            <button
                              onClick={() => handleDelete(p)}
                              className="p-1.5 rounded-lg bg-slate-900 border border-slate-800 text-slate-500 hover:text-red-400 transition"
                              title="Excluir Provedor"
                            >
                              <Trash2 className="w-3.5 h-3.5" />
                            </button>
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
