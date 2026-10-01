"use client";

import React, { useEffect, useState } from "react";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import {
  privacyService,
  DataSubjectRequest,
} from "@/services/privacy-service";
import {
  FileText,
  Plus,
  RefreshCw,
  Search,
  CheckCircle2,
  AlertTriangle,
  Clock,
  ArrowRight,
  Eye,
  Shield,
  Filter,
} from "lucide-react";

export default function PrivacyRequestsPage() {
  const [requests, setRequests] = useState<DataSubjectRequest[]>([]);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState("ALL");
  const [typeFilter, setTypeFilter] = useState("ALL");
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalCount, setTotalCount] = useState(0);

  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);

  // New Request Modal state
  const [showModal, setShowModal] = useState(false);
  const [modalPlayerId, setModalPlayerId] = useState("");
  const [modalType, setModalType] = useState("ACCESS");
  const [modalReason, setModalReason] = useState("");
  const [modalRequestedBy, setModalRequestedBy] = useState("");
  const [submitting, setSubmitting] = useState(false);

  const fetchRequests = async () => {
    try {
      setLoading(true);
      setErrorMsg(null);
      const res = await privacyService.listRequests({
        status: statusFilter,
        type: typeFilter,
        search: search || undefined,
        page,
      });
      setRequests(res.data);
      setTotalPages(res.last_page);
      setTotalCount(res.total);
    } catch (err: any) {
      setErrorMsg(err.message || "Erro ao carregar solicitações de titulares.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRequests();
  }, [statusFilter, typeFilter, page]);

  const handleCreateRequest = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!modalPlayerId) return;

    try {
      setSubmitting(true);
      setErrorMsg(null);
      await privacyService.createRequest({
        player_id: Number(modalPlayerId),
        type: modalType,
        reason: modalReason || undefined,
        requested_by: modalRequestedBy || undefined,
      });
      setSuccessMsg("Solicitação de titular registrada com sucesso!");
      setShowModal(false);
      setModalPlayerId("");
      setModalReason("");
      setModalRequestedBy("");
      await fetchRequests();
    } catch (err: any) {
      setErrorMsg(err.message || "Falha ao registrar solicitação.");
    } finally {
      setSubmitting(false);
    }
  };

  const getStatusBadge = (status: string) => {
    switch (status) {
      case "COMPLETED":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-950 text-emerald-300 border border-emerald-800">
            CONCLUÍDA
          </span>
        );
      case "IN_PROGRESS":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-950 text-blue-300 border border-blue-800">
            EM ANÁLISE
          </span>
        );
      case "OPEN":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-950 text-amber-300 border border-amber-800">
            ABERTA
          </span>
        );
      case "REJECTED":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-950 text-rose-300 border border-rose-800">
            REJEITADA
          </span>
        );
      case "CANCELLED":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-800 text-slate-400">
            CANCELADA
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

  const getSlaBadge = (dueAt?: string, status?: string) => {
    if (status === "COMPLETED" || status === "CANCELLED" || !dueAt) {
      return <span className="text-slate-500">-</span>;
    }

    const dueDate = new Date(dueAt);
    const now = new Date();
    const isPast = dueDate < now;

    if (isPast) {
      return (
        <span className="inline-flex items-center gap-1 text-rose-400 font-semibold text-[11px]">
          <Clock className="w-3 h-3 text-rose-400 animate-pulse" />
          VENCIDA
        </span>
      );
    }

    const diffDays = Math.ceil((dueDate.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
    if (diffDays <= 3) {
      return (
        <span className="inline-flex items-center gap-1 text-amber-400 font-semibold text-[11px]">
          <Clock className="w-3 h-3 text-amber-400" />
          {diffDays}d restantes (SLA)
        </span>
      );
    }

    return (
      <span className="text-slate-400 text-[11px]">
        {diffDays} dias restantes
      </span>
    );
  };

  return (
    <AppLayout
      title="Solicitações dos Titulares (DSR)"
      badge="FASE 11 • DIREITOS DO TITULAR (LGPD)"
      actions={
        <div className="flex items-center gap-2">
          <button
            onClick={fetchRequests}
            disabled={loading}
            className="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-sm border border-slate-700 flex items-center gap-1.5 transition"
          >
            <RefreshCw className={`w-4 h-4 ${loading ? "animate-spin" : ""}`} />
            Atualizar
          </button>

          <button
            onClick={() => setShowModal(true)}
            className="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-semibold flex items-center gap-1.5 transition shadow-sm shadow-emerald-900/40"
          >
            <Plus className="w-4 h-4" />
            Nova Solicitação
          </button>
        </div>
      }
    >
      <div className="space-y-6">
        {/* Navigation Tabs */}
        <div className="flex items-center gap-2 border-b border-slate-800 pb-3 text-sm">
          <Link
            href="/privacy"
            className="px-3 py-1.5 text-slate-400 hover:text-white hover:bg-slate-800/60 rounded-lg transition"
          >
            Visão Geral
          </Link>
          <Link
            href="/privacy/consents"
            className="px-3 py-1.5 text-slate-400 hover:text-white hover:bg-slate-800/60 rounded-lg transition"
          >
            Consentimentos
          </Link>
          <Link
            href="/privacy/requests"
            className="px-3 py-1.5 bg-slate-800 text-emerald-400 font-semibold rounded-lg border border-slate-700"
          >
            Direitos do Titular (DSR)
          </Link>
          <Link
            href="/privacy/retention"
            className="px-3 py-1.5 text-slate-400 hover:text-white hover:bg-slate-800/60 rounded-lg transition"
          >
            Políticas de Retenção
          </Link>
          <Link
            href="/privacy/audit"
            className="px-3 py-1.5 text-slate-400 hover:text-white hover:bg-slate-800/60 rounded-lg transition"
          >
            Auditoria & Acessos
          </Link>
        </div>

        {errorMsg && (
          <div className="p-4 bg-rose-950/60 border border-rose-800 rounded-lg text-rose-200 text-sm flex items-center gap-2">
            <AlertTriangle className="w-5 h-5 flex-shrink-0 text-rose-400" />
            <span>{errorMsg}</span>
          </div>
        )}

        {successMsg && (
          <div className="p-4 bg-emerald-950/60 border border-emerald-800 rounded-lg text-emerald-200 text-sm flex items-center gap-2">
            <CheckCircle2 className="w-5 h-5 flex-shrink-0 text-emerald-400" />
            <span>{successMsg}</span>
          </div>
        )}

        {/* Filters */}
        <div className="p-4 bg-slate-900 border border-slate-800 rounded-xl flex flex-col md:flex-row gap-4 items-center justify-between">
          <form
            onSubmit={(e) => {
              e.preventDefault();
              setPage(1);
              fetchRequests();
            }}
            className="flex-1 w-full flex items-center gap-2"
          >
            <div className="relative flex-1">
              <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" />
              <input
                type="text"
                placeholder="Buscar por nome ou e-mail do titular..."
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
            <select
              value={statusFilter}
              onChange={(e) => {
                setStatusFilter(e.target.value);
                setPage(1);
              }}
              className="px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-slate-200 focus:outline-none focus:border-emerald-500"
            >
              <option value="ALL">Todos os Status</option>
              <option value="OPEN">Abertas</option>
              <option value="IN_PROGRESS">Em Análise</option>
              <option value="COMPLETED">Concluídas</option>
              <option value="REJECTED">Rejeitadas</option>
              <option value="CANCELLED">Canceladas</option>
            </select>

            <select
              value={typeFilter}
              onChange={(e) => {
                setTypeFilter(e.target.value);
                setPage(1);
              }}
              className="px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-slate-200 focus:outline-none focus:border-emerald-500"
            >
              <option value="ALL">Todos os Direitos</option>
              <option value="ACCESS">Acesso aos Dados (ACCESS)</option>
              <option value="PORTABILITY">Portabilidade (PORTABILITY)</option>
              <option value="CORRECTION">Correção (CORRECTION)</option>
              <option value="DELETION">Exclusão (DELETION)</option>
              <option value="REVOCATION">Revogação (REVOCATION)</option>
              <option value="ANONYMIZATION">Anonimização (ANONYMIZATION)</option>
              <option value="INFORMATION">Informação (INFORMATION)</option>
            </select>
          </div>
        </div>

        {/* Requests Table */}
        <div className="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
          {loading ? (
            <div className="p-12 text-center text-slate-400 text-sm">
              <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2 text-emerald-500" />
              Carregando solicitações de privacidade...
            </div>
          ) : requests.length === 0 ? (
            <div className="p-12 text-center text-slate-500 text-sm">
              <FileText className="w-10 h-10 mx-auto mb-3 text-slate-600" />
              <p className="text-base text-slate-300 font-semibold">Nenhuma solicitação encontrada.</p>
              <p className="text-xs text-slate-500 mt-1">
                Todas as solicitações de titulares dos direitos LGPD serão listadas aqui com seus prazos legais.
              </p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead className="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold border-b border-slate-800">
                  <tr>
                    <th className="py-3 px-4">Protocolo</th>
                    <th className="py-3 px-4">Titular (Jogador)</th>
                    <th className="py-3 px-4">Direito Solicitado</th>
                    <th className="py-3 px-4">Status</th>
                    <th className="py-3 px-4">SLA Legal</th>
                    <th className="py-3 px-4">Data Abertura</th>
                    <th className="py-3 px-4 text-right">Ação</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800/60">
                  {requests.map((req) => (
                    <tr key={req.id} className="hover:bg-slate-800/40 transition">
                      <td className="py-3 px-4 font-mono font-bold text-slate-200">#{req.id}</td>
                      <td className="py-3 px-4">
                        <Link href={`/privacy/requests/${req.id}`} className="hover:underline">
                          <span className="font-semibold text-white">{req.player?.name}</span>
                          <div className="text-[11px] text-slate-400">{req.player?.email}</div>
                        </Link>
                      </td>
                      <td className="py-3 px-4">
                        <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-slate-950 border border-slate-800 font-mono text-slate-200">
                          {req.type}
                        </span>
                      </td>
                      <td className="py-3 px-4">{getStatusBadge(req.status)}</td>
                      <td className="py-3 px-4">{getSlaBadge(req.due_at, req.status)}</td>
                      <td className="py-3 px-4 text-slate-400">
                        {new Date(req.requested_at).toLocaleDateString("pt-BR")}
                      </td>
                      <td className="py-3 px-4 text-right">
                        <Link
                          href={`/privacy/requests/${req.id}`}
                          className="px-3 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded text-[11px] font-semibold transition inline-flex items-center gap-1"
                        >
                          <Eye className="w-3.5 h-3.5 text-emerald-400" />
                          Gerenciar
                        </Link>
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
                Página {page} de {totalPages} ({totalCount} registros)
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

        {/* Modal: New DSR Request */}
        {showModal && (
          <div className="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4">
            <div className="bg-slate-900 border border-slate-800 rounded-xl max-w-md w-full p-6 space-y-4 shadow-2xl">
              <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 className="text-sm font-semibold text-white flex items-center gap-2">
                  <FileText className="w-4 h-4 text-emerald-400" />
                  Registrar Nova Solicitação de Titular (LGPD)
                </h3>
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  className="text-slate-500 hover:text-white"
                >
                  ✕
                </button>
              </div>

              <form onSubmit={handleCreateRequest} className="space-y-4 text-xs">
                <div>
                  <label className="block text-slate-400 mb-1">ID do Jogador (Player ID) *</label>
                  <input
                    type="number"
                    required
                    placeholder="Ex: 1"
                    value={modalPlayerId}
                    onChange={(e) => setModalPlayerId(e.target.value)}
                    className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-white focus:outline-none focus:border-emerald-500"
                  />
                </div>

                <div>
                  <label className="block text-slate-400 mb-1">Direito Requisitado (LGPD) *</label>
                  <select
                    value={modalType}
                    onChange={(e) => setModalType(e.target.value)}
                    className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-white focus:outline-none focus:border-emerald-500"
                  >
                    <option value="ACCESS">Acesso aos Dados (Art. 18, II)</option>
                    <option value="PORTABILITY">Portabilidade dos Dados (Art. 18, V)</option>
                    <option value="CORRECTION">Correção de Dados Incompletos (Art. 18, III)</option>
                    <option value="ANONYMIZATION">Anonimização ou Bloqueio (Art. 18, IV)</option>
                    <option value="DELETION">Eliminação dos Dados Pessoais (Art. 18, VI)</option>
                    <option value="REVOCATION">Revogação do Consentimento (Art. 18, IX)</option>
                    <option value="INFORMATION">Informações sobre Compartilhamento (Art. 18, VII)</option>
                  </select>
                </div>

                <div>
                  <label className="block text-slate-400 mb-1">Solicitado Por</label>
                  <input
                    type="text"
                    placeholder="Ex: Titular via Chat, DPO Externo, E-mail"
                    value={modalRequestedBy}
                    onChange={(e) => setModalRequestedBy(e.target.value)}
                    className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-white focus:outline-none focus:border-emerald-500"
                  />
                </div>

                <div>
                  <label className="block text-slate-400 mb-1">Motivo / Descrição do Pedido</label>
                  <textarea
                    rows={3}
                    placeholder="Descreva a requisição enviada pelo titular..."
                    value={modalReason}
                    onChange={(e) => setModalReason(e.target.value)}
                    className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-white focus:outline-none focus:border-emerald-500"
                  />
                </div>

                <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-800">
                  <button
                    type="button"
                    onClick={() => setShowModal(false)}
                    className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded font-medium"
                  >
                    Cancelar
                  </button>
                  <button
                    type="submit"
                    disabled={submitting}
                    className="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded font-semibold flex items-center gap-1.5 disabled:opacity-50"
                  >
                    {submitting ? "Registrando..." : "Registrar Solicitação"}
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
