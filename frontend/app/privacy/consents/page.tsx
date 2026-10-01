"use client";

import React, { useState, useEffect } from "react";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import {
  privacyService,
  ConsentRecord,
  ConsentHistoryRecord,
} from "@/services/privacy-service";
import {
  ShieldCheck,
  CheckCircle2,
  XCircle,
  Clock,
  History,
  Search,
  Filter,
  PlusCircle,
  Hash,
  User,
  ArrowRight,
  RefreshCw,
  AlertCircle,
  Lock,
} from "lucide-react";

export default function ConsentsPage() {
  const [consents, setConsents] = useState<ConsentRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [typeFilter, setTypeFilter] = useState("ALL");
  const [statusFilter, setStatusFilter] = useState("ALL");
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalCount, setTotalCount] = useState(0);

  // History Modal
  const [historyModalOpen, setHistoryModalOpen] = useState(false);
  const [selectedConsent, setSelectedConsent] = useState<ConsentRecord | null>(null);
  const [consentHistory, setConsentHistory] = useState<ConsentHistoryRecord[]>([]);
  const [historyLoading, setHistoryLoading] = useState(false);

  // Grant/Revoke Action Modal
  const [actionModalOpen, setActionModalOpen] = useState(false);
  const [actionType, setActionType] = useState<"GRANT" | "REVOKE">("GRANT");
  const [actionPlayerId, setActionPlayerId] = useState("");
  const [actionConsentType, setActionConsentType] = useState("MARKETING_EMAIL");
  const [actionSource, setActionSource] = useState("manual_admin");
  const [actionReason, setActionReason] = useState("");
  const [actionSubmitting, setActionSubmitting] = useState(false);
  const [actionError, setActionError] = useState("");

  const loadConsents = async () => {
    setLoading(true);
    try {
      const res = await privacyService.listConsents({
        type: typeFilter,
        status: statusFilter,
        search,
        page,
      });
      setConsents(res.data);
      setTotalPages(res.last_page);
      setTotalCount(res.total);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadConsents();
  }, [typeFilter, statusFilter, page]);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setPage(1);
    loadConsents();
  };

  const openHistory = async (consent: ConsentRecord) => {
    setSelectedConsent(consent);
    setHistoryModalOpen(true);
    setHistoryLoading(true);
    try {
      const res = await privacyService.getConsentHistory(consent.id);
      setConsentHistory(res.history);
    } catch (err) {
      console.error(err);
    } finally {
      setHistoryLoading(false);
    }
  };

  const handleOpenActionModal = (type: "GRANT" | "REVOKE", consent?: ConsentRecord) => {
    setActionType(type);
    if (consent) {
      setActionPlayerId(consent.player_id.toString());
      setActionConsentType(consent.type);
    } else {
      setActionPlayerId("");
      setActionConsentType("MARKETING_EMAIL");
    }
    setActionSource("manual_admin");
    setActionReason("");
    setActionError("");
    setActionModalOpen(true);
  };

  const handleActionSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!actionPlayerId) {
      setActionError("Informe o ID do Jogador");
      return;
    }

    setActionSubmitting(true);
    setActionError("");

    try {
      if (actionType === "GRANT") {
        await privacyService.grantConsent({
          player_id: parseInt(actionPlayerId),
          type: actionConsentType,
          source: actionSource,
          version: "v1.0",
        });
      } else {
        await privacyService.revokeConsent({
          player_id: parseInt(actionPlayerId),
          type: actionConsentType,
          reason: actionReason || "Revogado via painel de governança",
        });
      }
      setActionModalOpen(false);
      loadConsents();
    } catch (err: any) {
      setActionError(err.message || "Falha ao processar consentimento");
    } finally {
      setActionSubmitting(false);
    }
  };

  return (
    <AppLayout
      title="Registro de Consentimentos (LGPD)"
      badge="GOVERNANÇA & PRIVACIDADE"
      actions={
        <div className="flex items-center space-x-2">
          <Link
            href="/privacy"
            className="px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 transition"
          >
            ← Painel Geral
          </Link>
          <button
            onClick={() => handleOpenActionModal("GRANT")}
            className="flex items-center space-x-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition"
          >
            <PlusCircle className="w-3.5 h-3.5" />
            <span>Novo Consentimento</span>
          </button>
        </div>
      }
    >
      <div className="space-y-6">
        {/* Intro Banner */}
        <div className="bg-slate-900/60 border border-slate-800 rounded-xl p-4 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
          <div className="flex items-start space-x-3">
            <div className="w-10 h-10 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center shrink-0">
              <ShieldCheck className="w-5 h-5 text-emerald-400" />
            </div>
            <div>
              <h2 className="text-sm font-semibold text-white">
                Base Central de Consentimentos & Opt-In / Opt-Out
              </h2>
              <p className="text-xs text-slate-400 mt-0.5">
                Histórico imutável de autorizações de comunicação em conformidade com o Art. 8º da LGPD. Toda alteração gera registro criptográfico e evidência de hash SHA-256.
              </p>
            </div>
          </div>
          <div className="flex items-center space-x-3 shrink-0 text-xs">
            <span className="text-slate-400 font-mono">Total de Registros: <strong className="text-white">{totalCount}</strong></span>
          </div>
        </div>

        {/* Filters and Search Bar */}
        <div className="bg-slate-900/40 border border-slate-800/80 rounded-xl p-4 space-y-3">
          <form onSubmit={handleSearchSubmit} className="flex flex-col md:flex-row gap-3">
            <div className="relative flex-1">
              <Search className="w-4 h-4 text-slate-500 absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Buscar por nome, email ou CPF do jogador..."
                className="w-full pl-9 pr-3 py-2 bg-slate-950/70 border border-slate-800 rounded-lg text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-emerald-500 transition"
              />
            </div>

            <div className="flex items-center gap-2">
              <select
                value={typeFilter}
                onChange={(e) => {
                  setTypeFilter(e.target.value);
                  setPage(1);
                }}
                className="px-3 py-2 bg-slate-950/70 border border-slate-800 rounded-lg text-xs text-slate-200 focus:outline-none focus:border-emerald-500"
              >
                <option value="ALL">Todos os Tipos</option>
                <option value="MARKETING_EMAIL">E-mail Marketing</option>
                <option value="MARKETING_SMS">SMS Marketing</option>
                <option value="MARKETING_WHATSAPP">WhatsApp Marketing</option>
                <option value="MARKETING_PUSH">Push Notification</option>
                <option value="TERMS_OF_SERVICE">Termos de Uso</option>
                <option value="PRIVACY_POLICY">Política de Privacidade</option>
                <option value="DATA_SHARING">Compartilhamento de Dados</option>
                <option value="ANALYTICS_TRACKING">Rastreamento Analítico</option>
              </select>

              <select
                value={statusFilter}
                onChange={(e) => {
                  setStatusFilter(e.target.value);
                  setPage(1);
                }}
                className="px-3 py-2 bg-slate-950/70 border border-slate-800 rounded-lg text-xs text-slate-200 focus:outline-none focus:border-emerald-500"
              >
                <option value="ALL">Todos os Status</option>
                <option value="GRANTED">Concedido (Opt-In)</option>
                <option value="REVOKED">Revogado (Opt-Out)</option>
              </select>

              <button
                type="submit"
                className="px-3 py-2 bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 rounded-lg text-xs font-medium transition"
              >
                Filtrar
              </button>
            </div>
          </form>
        </div>

        {/* Consents Table */}
        <div className="bg-slate-900/40 border border-slate-800 rounded-xl overflow-hidden">
          {loading ? (
            <div className="p-8 text-center text-xs text-slate-400 flex items-center justify-center space-x-2">
              <RefreshCw className="w-4 h-4 animate-spin text-emerald-400" />
              <span>Carregando consentimentos...</span>
            </div>
          ) : consents.length === 0 ? (
            <div className="p-12 text-center text-xs text-slate-500">
              Nenhum registro de consentimento localizado para os filtros informados.
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs border-collapse">
                <thead>
                  <tr className="bg-slate-950/50 border-b border-slate-800 text-slate-400 uppercase tracking-wider text-[10px]">
                    <th className="py-3 px-4 font-semibold">Jogador</th>
                    <th className="py-3 px-4 font-semibold">Tipo / Finalidade</th>
                    <th className="py-3 px-4 font-semibold">Status</th>
                    <th className="py-3 px-4 font-semibold">Origem / Versão</th>
                    <th className="py-3 px-4 font-semibold">Hash de Evidência</th>
                    <th className="py-3 px-4 font-semibold">Data da Ação</th>
                    <th className="py-3 px-4 font-semibold text-right">Ações</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800/60 text-slate-300">
                  {consents.map((consent) => (
                    <tr key={consent.id} className="hover:bg-slate-800/30 transition">
                      <td className="py-3 px-4">
                        <div className="font-medium text-white">
                          {consent.player?.name || `Jogador #${consent.player_id}`}
                        </div>
                        <div className="text-[11px] text-slate-400 font-mono">
                          {consent.player?.email || "Sem e-mail"}
                        </div>
                      </td>

                      <td className="py-3 px-4">
                        <span className="font-mono text-[11px] text-slate-200 bg-slate-800/80 px-2 py-0.5 rounded border border-slate-700/60">
                          {consent.type}
                        </span>
                        <div className="text-[10px] text-slate-500 mt-0.5">
                          Canal: {consent.channel}
                        </div>
                      </td>

                      <td className="py-3 px-4">
                        {consent.status === "GRANTED" ? (
                          <span className="inline-flex items-center space-x-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            <CheckCircle2 className="w-3 h-3" />
                            <span>Concedido</span>
                          </span>
                        ) : (
                          <span className="inline-flex items-center space-x-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                            <XCircle className="w-3 h-3" />
                            <span>Revogado</span>
                          </span>
                        )}
                      </td>

                      <td className="py-3 px-4">
                        <div className="text-slate-300 font-mono text-[11px]">
                          {consent.consent_source || "plataforma"}
                        </div>
                        <div className="text-[10px] text-slate-500">
                          {consent.consent_version || "v1.0"}
                        </div>
                      </td>

                      <td className="py-3 px-4 font-mono text-[10px] text-slate-400">
                        {consent.evidence_hash ? (
                          <span
                            className="bg-slate-950 px-1.5 py-0.5 rounded border border-slate-800 truncate max-w-[140px] inline-block align-middle"
                            title={consent.evidence_hash}
                          >
                            {consent.evidence_hash.substring(0, 12)}...
                          </span>
                        ) : (
                          <span className="text-slate-600">—</span>
                        )}
                      </td>

                      <td className="py-3 px-4 text-slate-400 text-[11px]">
                        {consent.status === "GRANTED"
                          ? consent.granted_at
                            ? new Date(consent.granted_at).toLocaleString("pt-BR")
                            : "—"
                          : consent.revoked_at
                          ? new Date(consent.revoked_at).toLocaleString("pt-BR")
                          : "—"}
                      </td>

                      <td className="py-3 px-4 text-right space-x-2">
                        <button
                          onClick={() => openHistory(consent)}
                          className="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded text-[11px] transition inline-flex items-center space-x-1"
                        >
                          <History className="w-3 h-3" />
                          <span>Histórico</span>
                        </button>

                        {consent.status === "GRANTED" ? (
                          <button
                            onClick={() => handleOpenActionModal("REVOKE", consent)}
                            className="px-2 py-1 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 rounded text-[11px] transition inline-flex items-center space-x-1"
                          >
                            <XCircle className="w-3 h-3" />
                            <span>Revogar</span>
                          </button>
                        ) : (
                          <button
                            onClick={() => handleOpenActionModal("GRANT", consent)}
                            className="px-2 py-1 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/20 rounded text-[11px] transition inline-flex items-center space-x-1"
                          >
                            <CheckCircle2 className="w-3 h-3" />
                            <span>Conceder</span>
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {/* Pagination */}
          {totalPages > 1 && (
            <div className="p-3 border-t border-slate-800 bg-slate-950/40 flex items-center justify-between text-xs text-slate-400">
              <span>
                Página {page} de {totalPages} ({totalCount} consentimentos)
              </span>
              <div className="flex items-center space-x-2">
                <button
                  disabled={page <= 1}
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  className="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 disabled:opacity-40 rounded transition"
                >
                  Anterior
                </button>
                <button
                  disabled={page >= totalPages}
                  onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                  className="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 disabled:opacity-40 rounded transition"
                >
                  Próxima
                </button>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* History Modal */}
      {historyModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-xl max-w-2xl w-full max-h-[85vh] flex flex-col shadow-2xl">
            <div className="p-4 border-b border-slate-800 flex items-center justify-between">
              <div className="flex items-center space-x-2">
                <Lock className="w-4 h-4 text-emerald-400" />
                <h3 className="font-semibold text-sm text-white">
                  Histórico Imutável de Auditoria — Consentimento #{selectedConsent?.id}
                </h3>
              </div>
              <button
                onClick={() => setHistoryModalOpen(false)}
                className="text-slate-400 hover:text-white text-xs px-2 py-1"
              >
                ✕ Fechar
              </button>
            </div>

            <div className="p-4 overflow-y-auto space-y-4">
              <div className="p-3 bg-slate-950/60 rounded-lg border border-slate-800/80 text-xs space-y-1">
                <div className="text-slate-400">Jogador: <strong className="text-white">{selectedConsent?.player?.name}</strong> ({selectedConsent?.player?.email})</div>
                <div className="text-slate-400">Tipo: <strong className="text-emerald-400 font-mono">{selectedConsent?.type}</strong></div>
                <div className="text-[11px] text-slate-500">
                  Tabela append-only protegida contra exclusão e edição por trigger/runtime.
                </div>
              </div>

              {historyLoading ? (
                <div className="p-8 text-center text-xs text-slate-400 flex items-center justify-center space-x-2">
                  <RefreshCw className="w-4 h-4 animate-spin text-emerald-400" />
                  <span>Carregando trilha de auditoria...</span>
                </div>
              ) : consentHistory.length === 0 ? (
                <div className="p-6 text-center text-xs text-slate-500">
                  Nenhum registro histórico adicional localizado.
                </div>
              ) : (
                <div className="space-y-3">
                  {consentHistory.map((item, idx) => (
                    <div
                      key={item.id || idx}
                      className="p-3 bg-slate-950/40 rounded-lg border border-slate-800/60 text-xs space-y-2"
                    >
                      <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-2">
                          <span
                            className={`px-1.5 py-0.5 rounded text-[10px] font-bold ${
                              item.new_status === "GRANTED"
                                ? "bg-emerald-500/10 text-emerald-400 border border-emerald-500/30"
                                : "bg-rose-500/10 text-rose-400 border border-rose-500/30"
                            }`}
                          >
                            {item.new_status === "GRANTED" ? "CONCEDIDO" : "REVOGADO"}
                          </span>
                          <span className="text-slate-400 font-mono text-[11px]">
                            Ação: {item.action}
                          </span>
                        </div>
                        <span className="text-[11px] text-slate-500 font-mono">
                          {new Date(item.performed_at).toLocaleString("pt-BR")}
                        </span>
                      </div>

                      <div className="grid grid-cols-2 gap-2 text-[11px] text-slate-400">
                        <div>Origem: <span className="text-slate-200">{item.source || "—"}</span></div>
                        <div>Versão: <span className="text-slate-200">{item.version || "—"}</span></div>
                        <div>IP: <span className="text-slate-200 font-mono">{item.ip_address || "—"}</span></div>
                        <div>Agente: <span className="text-slate-200 truncate inline-block max-w-[150px] align-bottom">{item.user_agent || "—"}</span></div>
                      </div>

                      {item.evidence_hash && (
                        <div className="text-[10px] text-slate-500 font-mono bg-slate-900/90 p-1.5 rounded border border-slate-800 break-all">
                          SHA256: {item.evidence_hash}
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>

            <div className="p-3 border-t border-slate-800 bg-slate-950/40 text-right">
              <button
                onClick={() => setHistoryModalOpen(false)}
                className="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs rounded transition"
              >
                Concluir Visualização
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Grant/Revoke Action Modal */}
      {actionModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-xl max-w-md w-full shadow-2xl p-5 space-y-4">
            <div className="flex items-center justify-between border-b border-slate-800 pb-3">
              <div className="flex items-center space-x-2">
                {actionType === "GRANT" ? (
                  <CheckCircle2 className="w-5 h-5 text-emerald-400" />
                ) : (
                  <XCircle className="w-5 h-5 text-rose-400" />
                )}
                <h3 className="font-semibold text-sm text-white">
                  {actionType === "GRANT" ? "Conceder Consentimento" : "Revogar Consentimento"}
                </h3>
              </div>
              <button
                onClick={() => setActionModalOpen(false)}
                className="text-slate-400 hover:text-white text-xs px-2 py-1"
              >
                ✕
              </button>
            </div>

            {actionError && (
              <div className="p-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 rounded-lg text-xs flex items-center space-x-2">
                <AlertCircle className="w-4 h-4 shrink-0" />
                <span>{actionError}</span>
              </div>
            )}

            <form onSubmit={handleActionSubmit} className="space-y-3 text-xs">
              <div>
                <label className="block text-slate-400 mb-1 font-medium">ID do Jogador</label>
                <input
                  type="number"
                  value={actionPlayerId}
                  onChange={(e) => setActionPlayerId(e.target.value)}
                  placeholder="Ex: 1"
                  required
                  className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200 focus:outline-none focus:border-emerald-500"
                />
              </div>

              <div>
                <label className="block text-slate-400 mb-1 font-medium">Tipo de Consentimento</label>
                <select
                  value={actionConsentType}
                  onChange={(e) => setActionConsentType(e.target.value)}
                  className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200 focus:outline-none focus:border-emerald-500"
                >
                  <option value="MARKETING_EMAIL">E-mail Marketing</option>
                  <option value="MARKETING_SMS">SMS Marketing</option>
                  <option value="MARKETING_WHATSAPP">WhatsApp Marketing</option>
                  <option value="MARKETING_PUSH">Push Notification</option>
                  <option value="TERMS_OF_SERVICE">Termos de Uso</option>
                  <option value="PRIVACY_POLICY">Política de Privacidade</option>
                  <option value="DATA_SHARING">Compartilhamento de Dados</option>
                  <option value="ANALYTICS_TRACKING">Rastreamento Analítico</option>
                </select>
              </div>

              {actionType === "GRANT" ? (
                <div>
                  <label className="block text-slate-400 mb-1 font-medium">Origem do Consentimento</label>
                  <input
                    type="text"
                    value={actionSource}
                    onChange={(e) => setActionSource(e.target.value)}
                    placeholder="Ex: signup_form, app_settings, admin"
                    className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200 focus:outline-none focus:border-emerald-500"
                  />
                </div>
              ) : (
                <div>
                  <label className="block text-slate-400 mb-1 font-medium">Motivo da Revogação</label>
                  <textarea
                    value={actionReason}
                    onChange={(e) => setActionReason(e.target.value)}
                    rows={2}
                    placeholder="Ex: Titular solicitou opt-out via suporte..."
                    className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200 focus:outline-none focus:border-emerald-500"
                  />
                </div>
              )}

              <div className="pt-2 border-t border-slate-800 flex justify-end space-x-2">
                <button
                  type="button"
                  onClick={() => setActionModalOpen(false)}
                  className="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded transition"
                >
                  Cancelar
                </button>
                <button
                  type="submit"
                  disabled={actionSubmitting}
                  className={`px-4 py-1.5 font-semibold rounded transition text-slate-950 ${
                    actionType === "GRANT"
                      ? "bg-emerald-500 hover:bg-emerald-400"
                      : "bg-rose-500 hover:bg-rose-400 text-white"
                  }`}
                >
                  {actionSubmitting
                    ? "Processando..."
                    : actionType === "GRANT"
                    ? "Confirmar Opt-In"
                    : "Confirmar Revogação"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </AppLayout>
  );
}
