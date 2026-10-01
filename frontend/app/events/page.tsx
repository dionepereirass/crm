"use client";

import React, { useState, useEffect, useCallback } from "react";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import { eventService, CrmEvent } from "@/services/event-service";
import { useAuth } from "@/hooks/use-auth";
import {
  Activity,
  Search,
  Filter,
  RefreshCw,
  RotateCw,
  Eye,
  CheckCircle2,
  AlertTriangle,
  XCircle,
  Copy,
  ChevronLeft,
  ChevronRight,
  User,
  Clock,
  Layers,
  ExternalLink,
} from "lucide-react";

export default function EventsListPage() {
  const { activePlatform } = useAuth(true);
  const [events, setEvents] = useState<CrmEvent[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Filters
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [eventType, setEventType] = useState("");
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalCount, setTotalCount] = useState(0);

  // Detail Modal
  const [selectedEvent, setSelectedEvent] = useState<CrmEvent | null>(null);
  const [reprocessingId, setReprocessingId] = useState<number | null>(null);

  // Metrics
  const [metricCounts, setMetricCounts] = useState({
    total: 0,
    processed: 0,
    failed: 0,
    duplicate: 0,
    notFound: 0,
  });

  const fetchEvents = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await eventService.getEvents({
        search: search || undefined,
        status: status || undefined,
        event_type: eventType || undefined,
        page,
        per_page: 20,
      });

      setEvents(res.data);
      if (res.meta) {
        setTotalPages(res.meta.last_page || 1);
        setTotalCount(res.meta.total || 0);
      }
    } catch (err: any) {
      setError(err.message || "Erro ao carregar eventos.");
    } finally {
      setLoading(false);
    }
  }, [search, status, eventType, page]);

  const fetchMetrics = useCallback(async () => {
    try {
      const [all, proc, fail, dup, notf] = await Promise.all([
        eventService.getEvents({ per_page: 1 }),
        eventService.getEvents({ status: "PROCESSED", per_page: 1 }),
        eventService.getEvents({ status: "FAILED", per_page: 1 }),
        eventService.getEvents({ status: "DUPLICATE", per_page: 1 }),
        eventService.getEvents({ status: "PLAYER_NOT_FOUND", per_page: 1 }),
      ]);
      setMetricCounts({
        total: all.meta?.total || 0,
        processed: proc.meta?.total || 0,
        failed: fail.meta?.total || 0,
        duplicate: dup.meta?.total || 0,
        notFound: notf.meta?.total || 0,
      });
    } catch (e) {
      // Ignora erro em métricas secundárias
    }
  }, []);

  useEffect(() => {
    if (activePlatform) {
      fetchEvents();
      fetchMetrics();
    }
  }, [activePlatform, fetchEvents, fetchMetrics]);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setPage(1);
    fetchEvents();
  };

  const handleReprocess = async (eventId: number) => {
    setReprocessingId(eventId);
    try {
      const updated = await eventService.reprocessEvent(eventId);
      setEvents((prev) => prev.map((ev) => (ev.id === eventId ? updated : ev)));
      if (selectedEvent && selectedEvent.id === eventId) {
        setSelectedEvent(updated);
      }
      alert("Evento reenfileirado para reprocessamento.");
      await fetchEvents();
      await fetchMetrics();
    } catch (err: any) {
      alert(err.message || "Falha ao reenfileirar evento.");
    } finally {
      setReprocessingId(null);
    }
  };

  const getStatusBadge = (st: string) => {
    switch (st) {
      case "PROCESSED":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
            <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 mr-1.5 animate-pulse" />
            Processado
          </span>
        );
      case "QUEUED":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-sky-500/10 text-sky-400 border border-sky-500/20">
            Enfileirado
          </span>
        );
      case "PROCESSING":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
            Processando...
          </span>
        );
      case "DUPLICATE":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
            Duplicado
          </span>
        );
      case "PLAYER_NOT_FOUND":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-orange-500/10 text-orange-400 border border-orange-500/20">
            Jogador Ausente
          </span>
        );
      case "FAILED":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
            Falha
          </span>
        );
      default:
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-500/10 text-slate-400 border border-slate-500/20">
            {st}
          </span>
        );
    }
  };

  return (
    <AppLayout
      title="Eventos & Ingestão de Webhooks"
      badge={`${totalCount} Eventos Registrados`}
      actions={
        <div className="flex items-center space-x-2">
          <Link
            href="/webhooks"
            className="flex items-center space-x-1.5 px-3 py-1.5 rounded-md text-xs font-medium bg-slate-800 hover:bg-slate-700 text-slate-200 transition border border-slate-700"
          >
            <Activity className="w-3.5 h-3.5 text-sky-400" />
            <span>Logs de Webhooks & Tester</span>
          </Link>
          <button
            onClick={() => {
              fetchEvents();
              fetchMetrics();
            }}
            disabled={loading}
            className="flex items-center space-x-1.5 px-3 py-1.5 rounded-md text-xs font-medium bg-slate-800 hover:bg-slate-700 text-slate-200 transition border border-slate-700"
          >
            <RefreshCw className={`w-3.5 h-3.5 ${loading ? "animate-spin text-emerald-400" : ""}`} />
            <span>Atualizar</span>
          </button>
        </div>
      }
    >
      <div className="space-y-6">
        {/* KPI Cards */}
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
          <div className="p-3.5 rounded-xl border border-slate-800 bg-[#0d131f] space-y-1">
            <span className="text-slate-400 text-[11px] block">Total Ingerido</span>
            <span className="text-xl font-bold text-white">{metricCounts.total}</span>
            <span className="text-[10px] text-slate-500 block">Eventos únicos</span>
          </div>

          <div className="p-3.5 rounded-xl border border-slate-800 bg-[#0d131f] space-y-1">
            <span className="text-slate-400 text-[11px] block">Processados</span>
            <span className="text-xl font-bold text-emerald-400">{metricCounts.processed}</span>
            <span className="text-[10px] text-slate-500 block">Efeitos aplicados</span>
          </div>

          <div className="p-3.5 rounded-xl border border-slate-800 bg-[#0d131f] space-y-1">
            <span className="text-slate-400 text-[11px] block">Falhas & Erros</span>
            <span className="text-xl font-bold text-rose-400">{metricCounts.failed}</span>
            <span className="text-[10px] text-slate-500 block">Prontos p/ reprocessar</span>
          </div>

          <div className="p-3.5 rounded-xl border border-slate-800 bg-[#0d131f] space-y-1">
            <span className="text-slate-400 text-[11px] block">Duplicados</span>
            <span className="text-xl font-bold text-amber-400">{metricCounts.duplicate}</span>
            <span className="text-[10px] text-slate-500 block">Idempotência ativada</span>
          </div>

          <div className="p-3.5 rounded-xl border border-slate-800 bg-[#0d131f] space-y-1">
            <span className="text-slate-400 text-[11px] block">Jogador Não Encontrado</span>
            <span className="text-xl font-bold text-orange-400">{metricCounts.notFound}</span>
            <span className="text-[10px] text-slate-500 block">Sem cadastro prévio</span>
          </div>
        </div>

        {/* Filters */}
        <div className="p-4 rounded-xl border border-slate-800 bg-[#0f172a]/70">
          <form onSubmit={handleSearchSubmit} className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
            <div className="lg:col-span-5 relative">
              <Search className="w-4 h-4 text-slate-500 absolute left-3 top-2.5" />
              <input
                type="text"
                placeholder="Buscar por ID externo, nome do jogador ou UUID..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full pl-9 pr-3 py-2 bg-slate-900 border border-slate-700/80 rounded-lg text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 transition"
              />
            </div>

            <div className="lg:col-span-3">
              <select
                value={eventType}
                onChange={(e) => {
                  setEventType(e.target.value);
                  setPage(1);
                }}
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700/80 rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500 transition"
              >
                <option value="">Tipo de Evento: Todos</option>
                <option value="PLAYER_CREATED">PLAYER_CREATED</option>
                <option value="PLAYER_UPDATED">PLAYER_UPDATED</option>
                <option value="DEPOSIT_SUCCESS">DEPOSIT_SUCCESS</option>
                <option value="BET_PLACED">BET_PLACED</option>
                <option value="BET_SETTLED">BET_SETTLED</option>
                <option value="WITHDRAWAL_SUCCESS">WITHDRAWAL_SUCCESS</option>
                <option value="LOGIN">LOGIN</option>
              </select>
            </div>

            <div className="lg:col-span-3">
              <select
                value={status}
                onChange={(e) => {
                  setStatus(e.target.value);
                  setPage(1);
                }}
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700/80 rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500 transition"
              >
                <option value="">Status: Todos</option>
                <option value="PROCESSED">PROCESSED (Sucesso)</option>
                <option value="QUEUED">QUEUED (Na Fila)</option>
                <option value="PROCESSING">PROCESSING (Em Execução)</option>
                <option value="DUPLICATE">DUPLICATE (Idempotente)</option>
                <option value="PLAYER_NOT_FOUND">PLAYER_NOT_FOUND</option>
                <option value="FAILED">FAILED (Erro)</option>
              </select>
            </div>

            <div className="lg:col-span-1 flex items-center">
              <button
                type="submit"
                className="w-full py-2 bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 rounded-lg text-xs font-medium transition flex items-center justify-center space-x-1"
              >
                <Filter className="w-3.5 h-3.5" />
                <span>Filtrar</span>
              </button>
            </div>
          </form>
        </div>

        {/* Table Container */}
        <div className="rounded-xl border border-slate-800 bg-[#0d131f] overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs text-slate-300">
              <thead className="bg-[#0f172a] text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-800">
                <tr>
                  <th className="py-3 px-4">Evento / ID Externo</th>
                  <th className="py-3 px-4">Jogador Vinculado</th>
                  <th className="py-3 px-4">Status</th>
                  <th className="py-3 px-4">Dados Principais</th>
                  <th className="py-3 px-4">Tentativas</th>
                  <th className="py-3 px-4">Data/Hora (UTC)</th>
                  <th className="py-3 px-4 text-right">Ações</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/60 font-sans">
                {loading ? (
                  <tr>
                    <td colSpan={7} className="py-12 text-center text-slate-500">
                      <div className="flex items-center justify-center space-x-2">
                        <div className="animate-spin w-4 h-4 border-2 border-emerald-500 border-t-transparent rounded-full" />
                        <span>Carregando eventos normalizados...</span>
                      </div>
                    </td>
                  </tr>
                ) : events.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="py-12 text-center text-slate-500">
                      <div className="max-w-sm mx-auto space-y-3">
                        <Activity className="w-10 h-10 mx-auto text-slate-600" />
                        <p className="text-sm font-medium text-slate-400">Nenhum evento registrado</p>
                        <p className="text-xs text-slate-500">
                          Dispare webhooks a partir da sua plataforma de apostas ou utilize o Webhook Tester.
                        </p>
                        <Link
                          href="/webhooks"
                          className="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-md text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white transition"
                        >
                          <span>Abrir Webhook Tester</span>
                        </Link>
                      </div>
                    </td>
                  </tr>
                ) : (
                  events.map((ev) => {
                    const amount = ev.normalized_payload?.data?.amount;
                    return (
                      <tr key={ev.id} className="hover:bg-slate-900/50 transition">
                        <td className="py-3 px-4">
                          <div className="font-semibold text-slate-100 flex items-center space-x-1.5">
                            <span className="font-mono text-emerald-400 text-[11px]">
                              {ev.event_type.key}
                            </span>
                          </div>
                          <span className="block text-[10px] text-slate-500 font-mono">
                            ID: {ev.external_event_id}
                          </span>
                        </td>

                        <td className="py-3 px-4">
                          {ev.player ? (
                            <div>
                              <Link
                                href={`/players/${ev.player.id}`}
                                className="font-medium text-slate-200 hover:text-emerald-400 transition"
                              >
                                {ev.player.name}
                              </Link>
                              <span className="block text-[10px] text-slate-500 font-mono">
                                ID: {ev.player.external_id}
                              </span>
                            </div>
                          ) : (
                            <span className="text-slate-500 text-[11px] italic">
                              {ev.normalized_payload?.player?.external_id
                                ? `Não cadastrado (${ev.normalized_payload.player.external_id})`
                                : "Sem jogador"}
                            </span>
                          )}
                        </td>

                        <td className="py-3 px-4">{getStatusBadge(ev.processing_status)}</td>

                        <td className="py-3 px-4 text-[11px]">
                          {amount ? (
                            <span className="font-mono font-semibold text-slate-200">
                              R$ {numberFormat(amount)}
                            </span>
                          ) : ev.event_type.key === "LOGIN" ? (
                            <span className="text-slate-400 font-mono text-[10px]">Sessão de usuário</span>
                          ) : (
                            <span className="text-slate-500">—</span>
                          )}
                        </td>

                        <td className="py-3 px-4 text-slate-400 font-mono text-[11px]">
                          {ev.attempts}
                        </td>

                        <td className="py-3 px-4 font-mono text-[11px] text-slate-400">
                          {new Date(ev.occurred_at).toLocaleString("pt-BR")}
                        </td>

                        <td className="py-3 px-4 text-right">
                          <div className="flex items-center justify-end space-x-1.5">
                            <button
                              type="button"
                              onClick={() => setSelectedEvent(ev)}
                              className="p-1.5 rounded-md hover:bg-slate-800 text-slate-400 hover:text-sky-400 transition"
                              title="Ver Detalhes do Payload"
                            >
                              <Eye className="w-3.5 h-3.5" />
                            </button>

                            {["FAILED", "PLAYER_NOT_FOUND"].includes(ev.processing_status) && (
                              <button
                                type="button"
                                onClick={() => handleReprocess(ev.id)}
                                disabled={reprocessingId === ev.id}
                                className="p-1.5 rounded-md hover:bg-slate-800 text-slate-400 hover:text-emerald-400 transition"
                                title="Reprocessar Evento"
                              >
                                <RotateCw
                                  className={`w-3.5 h-3.5 ${
                                    reprocessingId === ev.id ? "animate-spin text-emerald-400" : ""
                                  }`}
                                />
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          {totalPages > 1 && (
            <div className="p-4 border-t border-slate-800 bg-[#0f172a] flex items-center justify-between text-xs text-slate-400">
              <div>
                Página <span className="font-semibold text-white">{page}</span> de{" "}
                <span className="font-semibold text-white">{totalPages}</span> (Total de {totalCount} eventos)
              </div>
              <div className="flex items-center space-x-2">
                <button
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  disabled={page <= 1 || loading}
                  className="px-2.5 py-1 rounded border border-slate-700 bg-slate-800 hover:bg-slate-700 text-slate-200 disabled:opacity-40 disabled:cursor-not-allowed transition flex items-center space-x-1"
                >
                  <ChevronLeft className="w-3 h-3" />
                  <span>Anterior</span>
                </button>
                <button
                  onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                  disabled={page >= totalPages || loading}
                  className="px-2.5 py-1 rounded border border-slate-700 bg-slate-800 hover:bg-slate-700 text-slate-200 disabled:opacity-40 disabled:cursor-not-allowed transition flex items-center space-x-1"
                >
                  <span>Próximo</span>
                  <ChevronRight className="w-3 h-3" />
                </button>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Event Details Drawer/Modal */}
      {selectedEvent && (
        <div className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="w-full max-w-3xl rounded-2xl border border-slate-800 bg-[#0d131f] p-6 space-y-5 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between pb-3 border-b border-slate-800">
              <div className="flex items-center space-x-3">
                <div className="p-2 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                  <Activity className="w-5 h-5" />
                </div>
                <div>
                  <h3 className="text-base font-bold text-white flex items-center space-x-2">
                    <span>{selectedEvent.event_type.key}</span>
                    {getStatusBadge(selectedEvent.processing_status)}
                  </h3>
                  <p className="text-xs text-slate-400 font-mono">
                    External ID: {selectedEvent.external_event_id} • UUID: {selectedEvent.uuid}
                  </p>
                </div>
              </div>

              <button
                type="button"
                onClick={() => setSelectedEvent(null)}
                className="text-slate-400 hover:text-white text-lg transition p-1"
              >
                ✕
              </button>
            </div>

            {selectedEvent.error_message && (
              <div className="p-3.5 rounded-lg border border-rose-500/30 bg-rose-500/10 text-rose-300 text-xs">
                <strong>Motivo / Erro:</strong> {selectedEvent.error_message}
              </div>
            )}

            {/* Event Info Grid */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
              <div className="p-3 rounded-lg border border-slate-800 bg-slate-900/60">
                <span className="text-slate-400 text-[10px] block">Ocorrido Em (UTC)</span>
                <span className="font-mono text-slate-200">
                  {new Date(selectedEvent.occurred_at).toLocaleString("pt-BR")}
                </span>
              </div>
              <div className="p-3 rounded-lg border border-slate-800 bg-slate-900/60">
                <span className="text-slate-400 text-[10px] block">Processado Em</span>
                <span className="font-mono text-slate-200">
                  {selectedEvent.processed_at
                    ? new Date(selectedEvent.processed_at).toLocaleString("pt-BR")
                    : "Pendente"}
                </span>
              </div>
              <div className="p-3 rounded-lg border border-slate-800 bg-slate-900/60">
                <span className="text-slate-400 text-[10px] block">Tentativas</span>
                <span className="font-mono text-slate-200">{selectedEvent.attempts} de 3</span>
              </div>
              <div className="p-3 rounded-lg border border-slate-800 bg-slate-900/60">
                <span className="text-slate-400 text-[10px] block">Jogador Vinculado</span>
                <span className="font-semibold text-emerald-400">
                  {selectedEvent.player ? selectedEvent.player.name : "Nenhum"}
                </span>
              </div>
            </div>

            {/* Payload Comparison Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <span className="text-xs font-semibold text-slate-300 block mb-1.5">
                  Payload Recebido (Original):
                </span>
                <pre className="p-3.5 rounded-xl border border-slate-800 bg-[#070b13] text-[11px] font-mono text-slate-300 overflow-x-auto max-h-64">
                  {JSON.stringify(selectedEvent.payload, null, 2)}
                </pre>
              </div>

              <div>
                <span className="text-xs font-semibold text-slate-300 block mb-1.5">
                  Payload Normalizado (Padrão Interno):
                </span>
                <pre className="p-3.5 rounded-xl border border-slate-800 bg-[#070b13] text-[11px] font-mono text-emerald-300 overflow-x-auto max-h-64">
                  {JSON.stringify(selectedEvent.normalized_payload, null, 2)}
                </pre>
              </div>
            </div>

            {/* Footer Actions */}
            <div className="flex items-center justify-between pt-3 border-t border-slate-800">
              <span className="text-[11px] text-slate-500 font-mono">
                Registrado em {new Date(selectedEvent.created_at).toLocaleString("pt-BR")}
              </span>

              <div className="flex items-center space-x-2">
                {["FAILED", "PLAYER_NOT_FOUND"].includes(selectedEvent.processing_status) && (
                  <button
                    type="button"
                    onClick={() => handleReprocess(selectedEvent.id)}
                    disabled={reprocessingId === selectedEvent.id}
                    className="px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white transition flex items-center space-x-1.5"
                  >
                    <RotateCw
                      className={`w-3.5 h-3.5 ${
                        reprocessingId === selectedEvent.id ? "animate-spin" : ""
                      }`}
                    />
                    <span>Reenfileirar Processamento</span>
                  </button>
                )}
                <button
                  type="button"
                  onClick={() => setSelectedEvent(null)}
                  className="px-4 py-1.5 rounded-lg text-xs font-medium bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition"
                >
                  Fechar
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </AppLayout>
  );
}

function numberFormat(val: any): string {
  const num = parseFloat(val);
  if (isNaN(num)) return "0,00";
  return num.toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
