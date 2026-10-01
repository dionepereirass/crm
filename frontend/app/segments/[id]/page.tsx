"use client";

import React, { useState, useEffect, useCallback, use } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { AppLayout } from "@/components/layout/app-layout";
import { SegmentBuilder } from "@/components/segments/segment-builder";
import { segmentService, Segment } from "@/services/segment-service";
import {
  ArrowLeft,
  Edit,
  RotateCw,
  Copy,
  Trash2,
  Users,
  Clock,
  CheckCircle,
  XCircle,
  Layers,
  ChevronRight,
  ExternalLink,
  ShieldAlert,
} from "lucide-react";

export default function SegmentDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const resolvedParams = use(params);
  const segmentId = resolvedParams.id;
  const router = useRouter();

  const [segment, setSegment] = useState<Segment | null>(null);
  const [members, setMembers] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadingMembers, setLoadingMembers] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Members Pagination
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalMembers, setTotalMembers] = useState(0);

  // Action states
  const [refreshing, setRefreshing] = useState(false);
  const [feedbackMessage, setFeedbackMessage] = useState<string | null>(null);

  const fetchSegment = useCallback(async () => {
    try {
      const data = await segmentService.getSegment(segmentId);
      setSegment(data);
    } catch (err: any) {
      setError(err.message || "Erro ao carregar detalhes do segmento.");
    } finally {
      setLoading(false);
    }
  }, [segmentId]);

  const fetchMembers = useCallback(async () => {
    setLoadingMembers(true);
    try {
      const res = await segmentService.getMembers(segmentId, page, 20);
      setMembers(res.data);
      if (res.meta) {
        setTotalPages(res.meta.last_page || 1);
        setTotalMembers(res.meta.total || 0);
      }
    } catch (err: any) {
      console.error("Erro ao carregar membros do segmento:", err);
    } finally {
      setLoadingMembers(false);
    }
  }, [segmentId, page]);

  useEffect(() => {
    fetchSegment();
  }, [fetchSegment]);

  useEffect(() => {
    fetchMembers();
  }, [fetchMembers]);

  const handleRefresh = async () => {
    setRefreshing(true);
    try {
      const res = await segmentService.refreshSegment(segmentId);
      setSegment((prev) =>
        prev ? { ...prev, cached_count: res.count, cached_at: res.cached_at } : null
      );
      setFeedbackMessage(`Contagem atualizada com sucesso: ${res.count} jogadores qualificados.`);
      fetchMembers();
      setTimeout(() => setFeedbackMessage(null), 3500);
    } catch (err: any) {
      alert(err.message || "Erro ao atualizar contagem.");
    } finally {
      setRefreshing(false);
    }
  };

  const handleToggleStatus = async () => {
    if (!segment) return;
    try {
      let updated: Segment;
      if (segment.status === "ACTIVE") {
        updated = await segmentService.deactivateSegment(segment.id);
        setFeedbackMessage("Segmento desativado.");
      } else {
        updated = await segmentService.activateSegment(segment.id);
        setFeedbackMessage("Segmento ativado.");
      }
      setSegment(updated);
      setTimeout(() => setFeedbackMessage(null), 3500);
    } catch (err: any) {
      alert(err.message || "Erro ao alterar status.");
    }
  };

  const handleDuplicate = async () => {
    try {
      const clone = await segmentService.duplicateSegment(segmentId);
      router.push(`/segments/${clone.id}`);
    } catch (err: any) {
      alert(err.message || "Erro ao duplicar segmento.");
    }
  };

  const handleDelete = async () => {
    if (!segment) return;
    if (!confirm(`Deseja realmente excluir o segmento "${segment.name}"?`)) return;

    try {
      await segmentService.deleteSegment(segment.id);
      router.push("/segments");
    } catch (err: any) {
      alert(err.message || "Erro ao excluir segmento.");
    }
  };

  if (loading) {
    return (
      <AppLayout title="Detalhes do Segmento">
        <div className="py-20 text-center text-xs text-slate-400">
          <div className="w-6 h-6 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin mx-auto mb-2" />
          Carregando informações do segmento...
        </div>
      </AppLayout>
    );
  }

  if (error || !segment) {
    return (
      <AppLayout title="Detalhes do Segmento">
        <div className="py-16 text-center text-xs text-rose-400 max-w-md mx-auto space-y-3">
          <ShieldAlert className="w-10 h-10 mx-auto text-rose-500" />
          <p>{error || "Segmento não encontrado."}</p>
          <Link
            href="/segments"
            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 text-slate-300 text-xs hover:bg-slate-700"
          >
            <ArrowLeft className="w-3.5 h-3.5" />
            Voltar para Segmentos
          </Link>
        </div>
      </AppLayout>
    );
  }

  return (
    <AppLayout
      title={segment.name}
      badge={segment.status}
      actions={
        <div className="flex items-center gap-2">
          <Link
            href="/segments"
            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 transition-colors"
          >
            <ArrowLeft className="w-3.5 h-3.5" />
            Voltar
          </Link>

          <Link
            href={`/segments/${segment.id}/edit`}
            className="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white shadow-md transition-all"
          >
            <Edit className="w-3.5 h-3.5" />
            Editar Regras
          </Link>
        </div>
      }
    >
      <div className="space-y-6 max-w-6xl mx-auto">
        {feedbackMessage && (
          <div className="p-3.5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-300 text-xs flex items-center gap-2">
            <CheckCircle className="w-4 h-4 text-emerald-400" />
            {feedbackMessage}
          </div>
        )}

        {/* Top Summary Card */}
        <div className="rounded-xl border border-slate-800 bg-[#0d1322] p-5">
          <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
            <div className="space-y-2 flex-1">
              <div className="flex items-center gap-2">
                <span
                  className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase ${
                    segment.status === "ACTIVE"
                      ? "bg-emerald-500/10 text-emerald-400 border border-emerald-500/30"
                      : segment.status === "DRAFT"
                      ? "bg-amber-500/10 text-amber-400 border border-amber-500/30"
                      : "bg-slate-500/10 text-slate-400 border border-slate-500/30"
                  }`}
                >
                  {segment.status === "ACTIVE" ? (
                    <CheckCircle className="w-3 h-3" />
                  ) : (
                    <XCircle className="w-3 h-3" />
                  )}
                  {segment.status}
                </span>

                <span className="text-[11px] font-mono text-slate-500">
                  UUID: {segment.uuid}
                </span>
              </div>

              <h2 className="text-lg font-bold text-slate-100">{segment.name}</h2>
              <p className="text-xs text-slate-400">
                {segment.description || "Nenhuma descrição informada para este segmento."}
              </p>

              <div className="pt-2 flex flex-wrap items-center gap-4 text-[11px] text-slate-500">
                <span>
                  Criado em:{" "}
                  <strong className="text-slate-400">
                    {new Date(segment.created_at).toLocaleString("pt-BR")}
                  </strong>
                </span>
                <span>
                  Autor:{" "}
                  <strong className="text-slate-400">
                    {segment.creator?.name || "Sistema"}
                  </strong>
                </span>
                {segment.updater && (
                  <span>
                    Última alteração por:{" "}
                    <strong className="text-slate-400">
                      {segment.updater.name}
                    </strong>
                  </span>
                )}
              </div>
            </div>

            {/* Audience KPI & Refresh */}
            <div className="flex items-center gap-4 p-4 rounded-xl bg-slate-900/60 border border-slate-800">
              <div className="text-center px-2">
                <div className="text-3xl font-extrabold text-emerald-400 font-mono tracking-tight">
                  {segment.cached_count.toLocaleString("pt-BR")}
                </div>
                <div className="text-[10px] uppercase font-bold text-slate-500 tracking-wider">
                  Membros Qualificados
                </div>
                <div className="text-[10px] text-slate-500 mt-1 flex items-center justify-center gap-1">
                  <Clock className="w-2.5 h-2.5" />
                  {segment.cached_at
                    ? new Date(segment.cached_at).toLocaleTimeString("pt-BR")
                    : "Pendente"}
                </div>
              </div>

              <button
                type="button"
                onClick={handleRefresh}
                disabled={refreshing}
                className="inline-flex flex-col items-center justify-center p-3 rounded-lg border border-slate-700 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium transition-colors"
                title="Recalcular contagem contra a base completa"
              >
                <RotateCw
                  className={`w-4 h-4 mb-1 text-emerald-400 ${
                    refreshing ? "animate-spin" : ""
                  }`}
                />
                Recalcular
              </button>
            </div>
          </div>

          {/* Quick Action Buttons */}
          <div className="mt-5 pt-4 border-t border-slate-800/80 flex flex-wrap items-center justify-end gap-2">
            <button
              onClick={handleDuplicate}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg border border-slate-700 bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium transition-colors"
            >
              <Copy className="w-3.5 h-3.5" />
              Duplicar Segmento
            </button>

            <button
              onClick={handleToggleStatus}
              className={`inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg font-medium transition-colors ${
                segment.status === "ACTIVE"
                  ? "border border-amber-500/30 bg-amber-500/10 text-amber-400 hover:bg-amber-500/20"
                  : "border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20"
              }`}
            >
              {segment.status === "ACTIVE" ? (
                <>
                  <XCircle className="w-3.5 h-3.5" />
                  Desativar
                </>
              ) : (
                <>
                  <CheckCircle className="w-3.5 h-3.5" />
                  Ativar Segmento
                </>
              )}
            </button>

            <button
              onClick={handleDelete}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg border border-rose-500/30 bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 font-medium transition-colors"
            >
              <Trash2 className="w-3.5 h-3.5" />
              Excluir
            </button>
          </div>
        </div>

        {/* Readable Rule Tree */}
        <div className="space-y-2">
          <div className="flex items-center justify-between">
            <div className="text-xs font-semibold text-slate-300 uppercase tracking-wider">
              Regras e Condições Ativas do Segmento
            </div>
            <Link
              href={`/segments/${segment.id}/edit`}
              className="text-xs text-emerald-400 hover:underline flex items-center gap-1 font-medium"
            >
              <Edit className="w-3 h-3" />
              Modificar Regras
            </Link>
          </div>

          <SegmentBuilder initialRules={segment.rules_tree} readOnly={true} />
        </div>

        {/* Segment Members Table */}
        <div className="rounded-xl border border-slate-800 bg-[#0d1322] overflow-hidden space-y-2">
          <div className="p-4 border-b border-slate-800 flex items-center justify-between">
            <div className="flex items-center gap-2 font-semibold text-xs text-slate-300 uppercase tracking-wider">
              <Users className="w-4 h-4 text-emerald-400" />
              Jogadores Elegíveis no Segmento ({totalMembers})
            </div>
            <span className="text-[11px] text-slate-500">
              Paginação oficial sincronizada
            </span>
          </div>

          {loadingMembers ? (
            <div className="py-12 text-center text-xs text-slate-400">
              <div className="w-5 h-5 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin mx-auto mb-2" />
              Carregando lista de jogadores membros...
            </div>
          ) : members.length === 0 ? (
            <div className="py-12 text-center text-xs text-slate-500">
              Nenhum jogador na base qualifica para os critérios atuais deste segmento.
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead className="bg-slate-900/60 text-slate-400 border-b border-slate-800">
                  <tr>
                    <th className="py-2.5 px-4 font-semibold">ID Externo</th>
                    <th className="py-2.5 px-4 font-semibold">Nome</th>
                    <th className="py-2.5 px-4 font-semibold">E-mail</th>
                    <th className="py-2.5 px-4 font-semibold">Telefone</th>
                    <th className="py-2.5 px-4 font-semibold">UF / Cidade</th>
                    <th className="py-2.5 px-4 font-semibold">Status</th>
                    <th className="py-2.5 px-4 font-semibold">Tags</th>
                    <th className="py-2.5 px-4 text-right font-semibold">Ficha 360°</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800/60">
                  {members.map((p) => (
                    <tr key={p.id} className="hover:bg-slate-900/40 transition-colors">
                      <td className="py-2.5 px-4 font-mono font-medium text-emerald-400">
                        {p.external_id}
                      </td>
                      <td className="py-2.5 px-4 text-slate-200 font-medium">
                        {p.name}
                      </td>
                      <td className="py-2.5 px-4 text-slate-400">{p.email || "—"}</td>
                      <td className="py-2.5 px-4 text-slate-400">{p.phone || "—"}</td>
                      <td className="py-2.5 px-4 text-slate-400">
                        {p.state ? `${p.state}${p.city ? ` / ${p.city}` : ""}` : "—"}
                      </td>
                      <td className="py-2.5 px-4">
                        <span className="px-2 py-0.5 text-[10px] font-semibold rounded bg-slate-800 text-slate-300">
                          {p.status}
                        </span>
                      </td>
                      <td className="py-2.5 px-4">
                        {p.tags && p.tags.length > 0 ? (
                          <div className="flex flex-wrap gap-1">
                            {p.tags.map((t: any) => (
                              <span
                                key={t.id}
                                className="px-1.5 py-0.5 rounded text-[10px] font-medium"
                                style={{
                                  backgroundColor: `${t.color || "#10b981"}20`,
                                  color: t.color || "#10b981",
                                }}
                              >
                                {t.name}
                              </span>
                            ))}
                          </div>
                        ) : (
                          <span className="text-slate-600">—</span>
                        )}
                      </td>
                      <td className="py-2.5 px-4 text-right">
                        <Link
                          href={`/players/${p.id}`}
                          className="inline-flex items-center gap-1 text-[11px] text-emerald-400 hover:text-emerald-300 font-medium"
                        >
                          Ver Perfil
                          <ExternalLink className="w-3 h-3" />
                        </Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {/* Members Pagination */}
          {totalPages > 1 && (
            <div className="p-3 border-t border-slate-800/80 flex items-center justify-between text-xs text-slate-400">
              <span>
                Página {page} de {totalPages} ({totalMembers} membros no total)
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
