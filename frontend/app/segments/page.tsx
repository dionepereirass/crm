"use client";

import React, { useState, useEffect, useCallback } from "react";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import { segmentService, Segment } from "@/services/segment-service";
import { useAuth } from "@/hooks/use-auth";
import {
  Layers,
  Plus,
  Search,
  RefreshCw,
  Eye,
  Edit,
  Trash2,
  Copy,
  CheckCircle,
  XCircle,
  Users,
  Clock,
  ExternalLink,
  RotateCw,
} from "lucide-react";

export default function SegmentsListPage() {
  const { activePlatform } = useAuth(true);
  const [segments, setSegments] = useState<Segment[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Filters
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalCount, setTotalCount] = useState(0);

  // Action states
  const [refreshingId, setRefreshingId] = useState<number | null>(null);
  const [actionSuccess, setActionSuccess] = useState<string | null>(null);

  const fetchSegments = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await segmentService.getSegments({
        search: search || undefined,
        status: status || undefined,
        page,
        per_page: 15,
      });

      setSegments(res.data);
      if (res.meta) {
        setTotalPages(res.meta.last_page || 1);
        setTotalCount(res.meta.total || 0);
      }
    } catch (err: any) {
      setError(err.message || "Erro ao carregar segmentos da plataforma.");
    } finally {
      setLoading(false);
    }
  }, [search, status, page, activePlatform?.id]);

  useEffect(() => {
    fetchSegments();
  }, [fetchSegments]);

  const handleRefreshCount = async (id: number) => {
    setRefreshingId(id);
    try {
      const res = await segmentService.refreshSegment(id);
      setSegments((prev) =>
        prev.map((s) => (s.id === id ? { ...s, cached_count: res.count, cached_at: res.cached_at } : s))
      );
      setActionSuccess(`Contagem do segmento #${id} atualizada: ${res.count} membros.`);
      setTimeout(() => setActionSuccess(null), 3000);
    } catch (err: any) {
      alert(err.message || "Erro ao atualizar contagem.");
    } finally {
      setRefreshingId(null);
    }
  };

  const handleToggleStatus = async (segment: Segment) => {
    try {
      if (segment.status === "ACTIVE") {
        await segmentService.deactivateSegment(segment.id);
        setActionSuccess(`Segmento #${segment.id} desativado.`);
      } else {
        await segmentService.activateSegment(segment.id);
        setActionSuccess(`Segmento #${segment.id} ativado.`);
      }
      fetchSegments();
      setTimeout(() => setActionSuccess(null), 3000);
    } catch (err: any) {
      alert(err.message || "Erro ao alterar status.");
    }
  };

  const handleDuplicate = async (id: number) => {
    try {
      const clone = await segmentService.duplicateSegment(id);
      setActionSuccess(`Segmento duplicado com sucesso: "${clone.name}".`);
      fetchSegments();
      setTimeout(() => setActionSuccess(null), 3000);
    } catch (err: any) {
      alert(err.message || "Erro ao duplicar segmento.");
    }
  };

  const handleDelete = async (id: number, name: string) => {
    if (!confirm(`Deseja realmente excluir o segmento "${name}"?`)) return;

    try {
      await segmentService.deleteSegment(id);
      setActionSuccess(`Segmento excluído com sucesso.`);
      fetchSegments();
      setTimeout(() => setActionSuccess(null), 3000);
    } catch (err: any) {
      alert(err.message || "Erro ao excluir segmento.");
    }
  };

  return (
    <AppLayout
      title="Motor de Segmentação Dinâmica"
      badge="Filtros & Audiências"
      actions={
        <Link
          href="/segments/new"
          className="inline-flex items-center gap-2 px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white shadow-md transition-all"
        >
          <Plus className="w-3.5 h-3.5" />
          Novo Segmento
        </Link>
      }
    >
      <div className="space-y-4">
        {/* Alerts */}
        {actionSuccess && (
          <div className="p-3 rounded-lg border border-emerald-500/30 bg-emerald-500/10 text-emerald-300 text-xs flex items-center gap-2">
            <CheckCircle className="w-4 h-4 text-emerald-400" />
            {actionSuccess}
          </div>
        )}

        {/* Filters Bar */}
        <div className="rounded-xl border border-slate-800 bg-[#0d1322] p-3.5 flex flex-wrap items-center justify-between gap-3">
          <div className="flex flex-wrap items-center gap-2.5 flex-1">
            <div className="relative min-w-[240px] flex-1 max-w-sm">
              <Search className="w-3.5 h-3.5 absolute left-3 top-2.5 text-slate-500" />
              <input
                type="text"
                placeholder="Buscar por nome ou descrição..."
                value={search}
                onChange={(e) => {
                  setSearch(e.target.value);
                  setPage(1);
                }}
                className="w-full text-xs bg-slate-900 border border-slate-700 rounded-lg pl-9 pr-3 py-2 text-slate-200 focus:outline-none focus:border-emerald-500"
              />
            </div>

            <select
              value={status}
              onChange={(e) => {
                setStatus(e.target.value);
                setPage(1);
              }}
              className="text-xs bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-200 focus:outline-none focus:border-emerald-500"
            >
              <option value="">Todos os Status</option>
              <option value="ACTIVE">Ativos</option>
              <option value="DRAFT">Rascunhos (Draft)</option>
              <option value="INACTIVE">Inativos</option>
            </select>
          </div>

          <div className="flex items-center gap-2">
            <button
              onClick={() => fetchSegments()}
              disabled={loading}
              className="p-2 rounded-lg border border-slate-700 bg-slate-800 text-slate-300 hover:bg-slate-700 text-xs transition-colors"
              title="Recarregar lista"
            >
              <RefreshCw className={`w-3.5 h-3.5 ${loading ? "animate-spin" : ""}`} />
            </button>
            <span className="text-xs text-slate-400">
              Total: <strong className="text-slate-200">{totalCount}</strong> segmentos
            </span>
          </div>
        </div>

        {/* Segments Table */}
        <div className="rounded-xl border border-slate-800 bg-[#0d1322] overflow-hidden">
          {loading ? (
            <div className="py-16 text-center text-xs text-slate-400">
              <div className="w-6 h-6 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin mx-auto mb-2" />
              Carregando segmentos da plataforma...
            </div>
          ) : error ? (
            <div className="py-12 text-center text-xs text-rose-400">{error}</div>
          ) : segments.length === 0 ? (
            <div className="py-16 text-center text-xs text-slate-400">
              <Layers className="w-8 h-8 text-slate-600 mx-auto mb-2" />
              Nenhum segmento encontrado para os filtros selecionados.
              <div className="mt-3">
                <Link
                  href="/segments/new"
                  className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-medium"
                >
                  <Plus className="w-3.5 h-3.5" />
                  Criar Primeiro Segmento
                </Link>
              </div>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead className="bg-slate-900/80 text-slate-400 border-b border-slate-800">
                  <tr>
                    <th className="py-3 px-4 font-semibold">Nome do Segmento</th>
                    <th className="py-3 px-4 font-semibold">Status</th>
                    <th className="py-3 px-4 font-semibold">Audiência Estimada</th>
                    <th className="py-3 px-4 font-semibold">Último Refresh</th>
                    <th className="py-3 px-4 font-semibold">Criado Por</th>
                    <th className="py-3 px-4 text-right font-semibold">Ações</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800/60">
                  {segments.map((seg) => (
                    <tr key={seg.id} className="hover:bg-slate-900/40 transition-colors">
                      <td className="py-3 px-4">
                        <Link
                          href={`/segments/${seg.id}`}
                          className="font-medium text-slate-100 hover:text-emerald-400 transition-colors flex items-center gap-1.5"
                        >
                          {seg.name}
                        </Link>
                        {seg.description && (
                          <div className="text-[11px] text-slate-400 line-clamp-1 mt-0.5">
                            {seg.description}
                          </div>
                        )}
                      </td>

                      <td className="py-3 px-4">
                        <span
                          className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase ${
                            seg.status === "ACTIVE"
                              ? "bg-emerald-500/10 text-emerald-400 border border-emerald-500/30"
                              : seg.status === "DRAFT"
                              ? "bg-amber-500/10 text-amber-400 border border-amber-500/30"
                              : "bg-slate-500/10 text-slate-400 border border-slate-500/30"
                          }`}
                        >
                          {seg.status === "ACTIVE" ? (
                            <CheckCircle className="w-3 h-3" />
                          ) : (
                            <XCircle className="w-3 h-3" />
                          )}
                          {seg.status}
                        </span>
                      </td>

                      <td className="py-3 px-4">
                        <div className="flex items-center gap-2">
                          <span className="font-bold text-slate-200 font-mono text-sm">
                            {seg.cached_count.toLocaleString("pt-BR")}
                          </span>
                          <span className="text-[10px] text-slate-500 uppercase font-semibold">
                            jogadores
                          </span>
                          <button
                            onClick={() => handleRefreshCount(seg.id)}
                            disabled={refreshingId === seg.id}
                            className="p-1 text-slate-400 hover:text-emerald-400 transition-colors"
                            title="Recalcular contagem com base em tempo real"
                          >
                            <RotateCw
                              className={`w-3 h-3 ${refreshingId === seg.id ? "animate-spin text-emerald-400" : ""}`}
                            />
                          </button>
                        </div>
                      </td>

                      <td className="py-3 px-4 text-slate-400 text-[11px]">
                        {seg.cached_at ? (
                          <div className="flex items-center gap-1">
                            <Clock className="w-3 h-3 text-slate-500" />
                            {new Date(seg.cached_at).toLocaleString("pt-BR")}
                          </div>
                        ) : (
                          <span className="text-slate-600 italic">Pendente</span>
                        )}
                      </td>

                      <td className="py-3 px-4 text-slate-400 text-[11px]">
                        {seg.creator?.name || "Sistema"}
                      </td>

                      <td className="py-3 px-4 text-right">
                        <div className="inline-flex items-center gap-1">
                          <Link
                            href={`/segments/${seg.id}`}
                            className="p-1.5 text-slate-400 hover:text-slate-200 hover:bg-slate-800 rounded-lg transition-colors"
                            title="Ver Detalhes e Membros"
                          >
                            <Eye className="w-3.5 h-3.5" />
                          </Link>

                          <Link
                            href={`/segments/${seg.id}/edit`}
                            className="p-1.5 text-slate-400 hover:text-emerald-400 hover:bg-slate-800 rounded-lg transition-colors"
                            title="Editar Regras"
                          >
                            <Edit className="w-3.5 h-3.5" />
                          </Link>

                          <button
                            onClick={() => handleDuplicate(seg.id)}
                            className="p-1.5 text-slate-400 hover:text-blue-400 hover:bg-slate-800 rounded-lg transition-colors"
                            title="Duplicar Segmento"
                          >
                            <Copy className="w-3.5 h-3.5" />
                          </button>

                          <button
                            onClick={() => handleToggleStatus(seg)}
                            className={`p-1.5 rounded-lg transition-colors ${
                              seg.status === "ACTIVE"
                                ? "text-slate-400 hover:text-amber-400 hover:bg-slate-800"
                                : "text-slate-400 hover:text-emerald-400 hover:bg-slate-800"
                            }`}
                            title={seg.status === "ACTIVE" ? "Desativar" : "Ativar"}
                          >
                            {seg.status === "ACTIVE" ? (
                              <XCircle className="w-3.5 h-3.5" />
                            ) : (
                              <CheckCircle className="w-3.5 h-3.5" />
                            )}
                          </button>

                          <button
                            onClick={() => handleDelete(seg.id, seg.name)}
                            className="p-1.5 text-slate-400 hover:text-rose-400 hover:bg-slate-800 rounded-lg transition-colors"
                            title="Excluir Segmento"
                          >
                            <Trash2 className="w-3.5 h-3.5" />
                          </button>
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
            <div className="p-3 border-t border-slate-800/80 flex items-center justify-between text-xs text-slate-400">
              <span>
                Página {page} de {totalPages}
              </span>
              <div className="flex items-center gap-1.5">
                <button
                  disabled={page <= 1}
                  onClick={() => setPage((p) => p - 1)}
                  className="px-2.5 py-1 rounded bg-slate-800 disabled:opacity-40 hover:bg-slate-700 text-slate-300"
                >
                  Anterior
                </button>
                <button
                  disabled={page >= totalPages}
                  onClick={() => setPage((p) => p + 1)}
                  className="px-2.5 py-1 rounded bg-slate-800 disabled:opacity-40 hover:bg-slate-700 text-slate-300"
                >
                  Próxima
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </AppLayout>
  );
}
