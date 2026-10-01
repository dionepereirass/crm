"use client";

import React, { useEffect, useState } from "react";
import { useParams, useRouter } from "next/navigation";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import {
  privacyService,
  DataSubjectRequest,
} from "@/services/privacy-service";
import {
  FileText,
  ArrowLeft,
  CheckCircle2,
  XCircle,
  AlertTriangle,
  Clock,
  User,
  Shield,
  Download,
  Lock,
  Ban,
  RefreshCw,
  Send,
} from "lucide-react";

export default function PrivacyRequestDetailPage() {
  const params = useParams();
  const router = useRouter();
  const id = Number(params?.id);

  const [request, setRequest] = useState<DataSubjectRequest | null>(null);
  const [loading, setLoading] = useState(true);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);
  const [actionBusy, setActionBusy] = useState(false);

  // Resolution Modal state
  const [showResolutionModal, setShowResolutionModal] = useState(false);
  const [modalMode, setModalMode] = useState<"COMPLETE" | "REJECT">("COMPLETE");
  const [resolutionText, setResolutionText] = useState("");

  // Export Data Preview modal
  const [exportedData, setExportedData] = useState<any>(null);

  const loadData = async () => {
    if (!id) return;
    try {
      setLoading(true);
      setErrorMsg(null);
      const res = await privacyService.getRequest(id);
      setRequest(res.data);
    } catch (err: any) {
      setErrorMsg(err.message || "Erro ao carregar detalhes da solicitação.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, [id]);

  const handleAssignToMe = async () => {
    try {
      setActionBusy(true);
      await privacyService.assignRequest(id);
      setSuccessMsg("Solicitação atribuída ao seu usuário com sucesso.");
      await loadData();
    } catch (err: any) {
      setErrorMsg(err.message || "Erro ao atribuir solicitação.");
    } finally {
      setActionBusy(false);
    }
  };

  const handleStartProcess = async () => {
    try {
      setActionBusy(true);
      await privacyService.processRequest(id);
      setSuccessMsg("Solicitação alterada para EM ANÁLISE.");
      await loadData();
    } catch (err: any) {
      setErrorMsg(err.message || "Erro ao iniciar análise.");
    } finally {
      setActionBusy(false);
    }
  };

  const handleExportData = async () => {
    try {
      setActionBusy(true);
      const res = await privacyService.exportRequest(id);
      setExportedData(res.data);
      setSuccessMsg("Pacote de dados pessoais compilado com sucesso.");
    } catch (err: any) {
      setErrorMsg(err.message || "Erro ao compilar dados.");
    } finally {
      setActionBusy(false);
    }
  };

  const handleAnonymizePlayer = async () => {
    if (!request?.player_id) return;
    if (!confirm("ATENÇÃO: A anonimização é irreversível e substituirá dados pessoais por hashes e máscaras de conformidade. Confirmar?")) {
      return;
    }

    try {
      setActionBusy(true);
      await privacyService.anonymizePlayer(request.player_id, true, `Execução do DSR #${id}`);
      setSuccessMsg("Jogador anonimizado irreversivelmente com sucesso!");
      await loadData();
    } catch (err: any) {
      setErrorMsg(err.message || "Falha na anonimização.");
    } finally {
      setActionBusy(false);
    }
  };

  const handleSubmitResolution = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      setActionBusy(true);
      if (modalMode === "COMPLETE") {
        await privacyService.completeRequest(id, resolutionText);
        setSuccessMsg("Solicitação finalizada como CONCLUÍDA.");
      } else {
        await privacyService.rejectRequest(id, resolutionText);
        setSuccessMsg("Solicitação rejeitada com justificativa registrada.");
      }
      setShowResolutionModal(false);
      await loadData();
    } catch (err: any) {
      setErrorMsg(err.message || "Erro ao registrar resolução.");
    } finally {
      setActionBusy(false);
    }
  };

  if (loading) {
    return (
      <AppLayout title="Solicitação de Titular">
        <div className="p-16 text-center text-slate-400">
          <RefreshCw className="w-8 h-8 animate-spin mx-auto mb-3 text-emerald-500" />
          Carregando dados da solicitação...
        </div>
      </AppLayout>
    );
  }

  if (!request) {
    return (
      <AppLayout title="Não Encontrada">
        <div className="p-16 text-center text-slate-400">
          <AlertTriangle className="w-10 h-10 mx-auto mb-3 text-amber-500" />
          <p className="text-base text-white">Solicitação #{id} não encontrada ou sem permissão de acesso.</p>
          <Link
            href="/privacy/requests"
            className="mt-4 inline-flex items-center gap-1.5 px-4 py-2 bg-slate-800 text-slate-200 rounded-lg text-sm"
          >
            <ArrowLeft className="w-4 h-4" />
            Voltar para Solicitações
          </Link>
        </div>
      </AppLayout>
    );
  }

  return (
    <AppLayout
      title={`Solicitação #${request.id} • ${request.type}`}
      badge={`FASE 11 • STATUS: ${request.status}`}
      actions={
        <div className="flex items-center gap-2">
          <Link
            href="/privacy/requests"
            className="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-sm border border-slate-700 flex items-center gap-1.5 transition"
          >
            <ArrowLeft className="w-4 h-4" />
            Voltar
          </Link>

          {(request.type === "ACCESS" || request.type === "PORTABILITY") && (
            <button
              onClick={handleExportData}
              disabled={actionBusy}
              className="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-sm border border-slate-700 flex items-center gap-1.5 transition"
            >
              <Download className="w-4 h-4 text-blue-400" />
              Compilar Dados do Titular
            </button>
          )}

          {(request.type === "DELETION" || request.type === "ANONYMIZATION") && request.status !== "COMPLETED" && (
            <button
              onClick={handleAnonymizePlayer}
              disabled={actionBusy}
              className="px-3 py-2 bg-rose-950/80 hover:bg-rose-900 text-rose-200 rounded-lg text-sm border border-rose-800 flex items-center gap-1.5 transition"
            >
              <Lock className="w-4 h-4 text-rose-400" />
              Executar Anonimização
            </button>
          )}
        </div>
      }
    >
      <div className="space-y-6">
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

        {/* Overview Header */}
        <div className="p-6 bg-slate-900 border border-slate-800 rounded-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
          <div className="space-y-2">
            <div className="flex items-center gap-3">
              <span className="font-mono text-xs px-2.5 py-1 rounded bg-slate-950 border border-slate-800 text-emerald-400 font-bold">
                Direito: {request.type}
              </span>
              <span className="font-mono text-xs px-2.5 py-1 rounded bg-slate-950 border border-slate-800 text-amber-400 font-bold">
                Status: {request.status}
              </span>
            </div>
            <h2 className="text-base font-semibold text-white">
              Titular: {request.player?.name} ({request.player?.email})
            </h2>
            <p className="text-xs text-slate-400">
              Solicitado em: {new Date(request.requested_at).toLocaleString("pt-BR")} por{" "}
              <strong className="text-slate-300">{request.requested_by || "Titular"}</strong>
            </p>
          </div>

          {/* Workflow Action Buttons */}
          <div className="flex flex-wrap items-center gap-2">
            {request.status === "OPEN" && (
              <>
                <button
                  onClick={handleAssignToMe}
                  disabled={actionBusy}
                  className="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition"
                >
                  <User className="w-3.5 h-3.5 text-blue-400" />
                  Assumir Solicitação
                </button>
                <button
                  onClick={handleStartProcess}
                  disabled={actionBusy}
                  className="px-3 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-semibold flex items-center gap-1.5 transition"
                >
                  Iniciar Análise
                </button>
              </>
            )}

            {request.status === "IN_PROGRESS" && (
              <>
                <button
                  onClick={() => {
                    setModalMode("COMPLETE");
                    setResolutionText("");
                    setShowResolutionModal(true);
                  }}
                  disabled={actionBusy}
                  className="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-semibold flex items-center gap-1.5 transition"
                >
                  <CheckCircle2 className="w-3.5 h-3.5" />
                  Concluir com Parecer
                </button>
                <button
                  onClick={() => {
                    setModalMode("REJECT");
                    setResolutionText("");
                    setShowResolutionModal(true);
                  }}
                  disabled={actionBusy}
                  className="px-3 py-2 bg-rose-950 hover:bg-rose-900 text-rose-300 border border-rose-800 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition"
                >
                  <XCircle className="w-3.5 h-3.5" />
                  Rejeitar Pedido
                </button>
              </>
            )}
          </div>
        </div>

        {/* Details Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
          {/* Box 1: Descrição e Motivo */}
          <div className="p-5 bg-slate-900 border border-slate-800 rounded-xl space-y-3">
            <h3 className="font-semibold text-white text-sm">Motivação da Requisição</h3>
            <div className="p-3 bg-slate-950 border border-slate-800 rounded-lg text-slate-300 whitespace-pre-wrap">
              {request.reason || "Nenhum detalhe adicional informado no momento da abertura."}
            </div>

            <div className="space-y-1 text-slate-400 pt-2 border-t border-slate-800">
              <div>
                Prazo Limite Legal (SLA):{" "}
                <strong className="text-slate-200">
                  {request.due_at ? new Date(request.due_at).toLocaleDateString("pt-BR") : "15 dias"}
                </strong>
              </div>
              <div>
                Operador Responsável:{" "}
                <strong className="text-slate-200">
                  {request.assignedUser?.name || "Não atribuído"}
                </strong>
              </div>
            </div>
          </div>

          {/* Box 2: Parecer / Resolução */}
          <div className="p-5 bg-slate-900 border border-slate-800 rounded-xl space-y-3">
            <h3 className="font-semibold text-white text-sm">Parecer do Encarregado (DPO) / Resolução</h3>
            {request.resolution ? (
              <div className="p-3 bg-slate-950 border border-slate-800 rounded-lg text-emerald-300 whitespace-pre-wrap">
                {request.resolution}
              </div>
            ) : (
              <div className="p-6 text-center text-slate-500">
                Aguardando conclusão da análise jurídica/técnica.
              </div>
            )}

            {request.completed_at && (
              <div className="text-slate-400 pt-2 border-t border-slate-800">
                Finalizado em:{" "}
                <strong className="text-slate-200">
                  {new Date(request.completed_at).toLocaleString("pt-BR")}
                </strong>
              </div>
            )}
          </div>
        </div>

        {/* Exported Data Preview Drawer/Card */}
        {exportedData && (
          <div className="p-5 bg-slate-900 border border-slate-800 rounded-xl space-y-3">
            <div className="flex items-center justify-between border-b border-slate-800 pb-3">
              <div className="flex items-center gap-2">
                <Download className="w-4 h-4 text-emerald-400" />
                <h3 className="font-semibold text-white text-sm">
                  Pacote de Dados Pessoais Estruturado (JSON LGPD)
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setExportedData(null)}
                className="text-slate-500 hover:text-white text-xs"
              >
                Fechar
              </button>
            </div>
            <pre className="p-4 bg-slate-950 border border-slate-800 rounded-lg text-xs font-mono text-emerald-300 max-h-96 overflow-y-auto whitespace-pre-wrap">
              {JSON.stringify(exportedData, null, 2)}
            </pre>
          </div>
        )}

        {/* Modal: Resolution */}
        {showResolutionModal && (
          <div className="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4">
            <div className="bg-slate-900 border border-slate-800 rounded-xl max-w-md w-full p-6 space-y-4 shadow-2xl">
              <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 className="text-sm font-semibold text-white">
                  {modalMode === "COMPLETE" ? "Concluir Solicitação de Titular" : "Rejeitar Solicitação"}
                </h3>
                <button
                  type="button"
                  onClick={() => setShowResolutionModal(false)}
                  className="text-slate-500 hover:text-white"
                >
                  ✕
                </button>
              </div>

              <form onSubmit={handleSubmitResolution} className="space-y-4 text-xs">
                <div>
                  <label className="block text-slate-400 mb-1">
                    {modalMode === "COMPLETE" ? "Parecer de Conclusão *" : "Justificativa Legal da Rejeição *"}
                  </label>
                  <textarea
                    required
                    rows={4}
                    placeholder={
                      modalMode === "COMPLETE"
                        ? "Descreva as ações realizadas para o atendimento integral deste direito..."
                        : "Informe a fundamentação legal (ex: obrigação legal de retenção financeira Art. 16, I)..."
                    }
                    value={resolutionText}
                    onChange={(e) => setResolutionText(e.target.value)}
                    className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-white focus:outline-none focus:border-emerald-500"
                  />
                </div>

                <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-800">
                  <button
                    type="button"
                    onClick={() => setShowResolutionModal(false)}
                    className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded font-medium"
                  >
                    Cancelar
                  </button>
                  <button
                    type="submit"
                    disabled={actionBusy}
                    className={`px-4 py-2 text-white rounded font-semibold ${
                      modalMode === "COMPLETE"
                        ? "bg-emerald-600 hover:bg-emerald-500"
                        : "bg-rose-600 hover:bg-rose-500"
                    }`}
                  >
                    Confirmar Resolução
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
