"use client";

import React, { useEffect, useState } from "react";
import { useParams, useRouter } from "next/navigation";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import { messageService, Message } from "@/services/message-service";
import {
  ArrowLeft,
  Mail,
  MessageSquare,
  Shield,
  Activity,
  CheckCircle2,
  XCircle,
  Clock,
  RotateCcw,
  Ban,
  Server,
  FileText,
  RefreshCw,
  Send,
} from "lucide-react";

export default function MessageDetailsPage() {
  const params = useParams();
  const router = useRouter();
  const id = params?.id as string;

  const [message, setMessage] = useState<Message | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionSuccess, setActionSuccess] = useState<string | null>(null);

  const fetchMessage = async () => {
    try {
      setLoading(true);
      setError(null);
      const res = await messageService.get(id);
      setMessage(res);
    } catch (err: any) {
      setError(err.message || "Mensagem não encontrada.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (id) fetchMessage();
  }, [id]);

  const handleRetry = async () => {
    try {
      setError(null);
      await messageService.retry(id);
      setActionSuccess("Mensagem reenfileirada com sucesso.");
      fetchMessage();
    } catch (err: any) {
      setError(err.message || "Erro ao reenviar.");
    }
  };

  const handleCancel = async () => {
    if (!confirm("Deseja realmente cancelar este disparo?")) return;
    try {
      setError(null);
      await messageService.cancel(id);
      setActionSuccess("Disparo cancelado com sucesso.");
      fetchMessage();
    } catch (err: any) {
      setError(err.message || "Erro ao cancelar.");
    }
  };

  if (loading) {
    return (
      <AppLayout title="Mensagem" badge="Carregando">
        <div className="p-12 text-center text-slate-500 text-xs">
          <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2 text-emerald-500" />
          Carregando informações da mensagem...
        </div>
      </AppLayout>
    );
  }

  if (!message) {
    return (
      <AppLayout title="Mensagem" badge="Não Encontrada">
        <div className="p-12 text-center text-slate-400 text-xs space-y-4">
          <p>A mensagem solicitada não foi encontrada nesta plataforma.</p>
          <Link
            href="/messages"
            className="inline-flex items-center space-x-1.5 px-4 py-2 rounded-lg bg-slate-800 text-white text-xs hover:bg-slate-700 transition"
          >
            <ArrowLeft className="w-4 h-4" />
            <span>Voltar para Lista de Mensagens</span>
          </Link>
        </div>
      </AppLayout>
    );
  }

  return (
    <AppLayout
      title={`Mensagem #${message.id}`}
      badge={`Canal: ${message.channel} • Status: ${message.status}`}
      actions={
        <div className="flex items-center space-x-3">
          <Link
            href="/messages"
            className="flex items-center space-x-1.5 px-3 py-1.5 rounded-lg border border-slate-700 bg-slate-800 text-xs font-medium text-slate-200 hover:bg-slate-700 transition"
          >
            <ArrowLeft className="w-3.5 h-3.5" />
            <span>Voltar</span>
          </Link>

          {message.status === "FAILED" && (
            <button
              onClick={handleRetry}
              className="flex items-center space-x-1.5 px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs transition"
            >
              <RotateCcw className="w-3.5 h-3.5" />
              <span>Reenviar Disparo</span>
            </button>
          )}

          {message.status === "QUEUED" && (
            <button
              onClick={handleCancel}
              className="flex items-center space-x-1.5 px-3 py-1.5 rounded-lg bg-red-500 hover:bg-red-600 text-white font-bold text-xs transition"
            >
              <Ban className="w-3.5 h-3.5" />
              <span>Cancelar Envio</span>
            </button>
          )}
        </div>
      }
    >
      <div className="p-8 space-y-6">
        {error && (
          <div className="p-4 rounded-xl bg-red-500/10 border border-red-500/30 flex items-center space-x-3 text-red-400 text-xs">
            <XCircle className="w-4 h-4 shrink-0" />
            <span>{error}</span>
          </div>
        )}

        {actionSuccess && (
          <div className="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center space-x-3 text-emerald-400 text-xs">
            <CheckCircle2 className="w-4 h-4 shrink-0" />
            <span>{actionSuccess}</span>
          </div>
        )}

        {/* Overview Metric Cards */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div className="bg-[#0f172a] border border-slate-800 rounded-xl p-4">
            <span className="text-[11px] font-medium text-slate-400">Destinatário (LGPD)</span>
            <div className="flex items-center space-x-2 mt-1">
              <Shield className="w-3.5 h-3.5 text-emerald-400" />
              <span className="font-mono text-xs font-bold text-white">
                {message.masked_recipient || message.recipient}
              </span>
            </div>
          </div>

          <div className="bg-[#0f172a] border border-slate-800 rounded-xl p-4">
            <span className="text-[11px] font-medium text-slate-400">Canal & Provedor</span>
            <div className="flex items-center space-x-2 mt-1">
              <span className="px-2 py-0.5 rounded text-xs font-bold bg-slate-800 text-white">
                {message.channel}
              </span>
              <span className="text-xs text-slate-300 font-semibold truncate">
                {message.provider_name || message.provider_driver || "Gateway"}
              </span>
            </div>
          </div>

          <div className="bg-[#0f172a] border border-slate-800 rounded-xl p-4">
            <span className="text-[11px] font-medium text-slate-400">Status & Tentativas</span>
            <div className="flex items-center space-x-2 mt-1">
              <span
                className={`px-2 py-0.5 rounded-full text-xs font-bold ${
                  message.status === "DELIVERED" || message.status === "SENT"
                    ? "bg-emerald-500/10 text-emerald-400"
                    : message.status === "QUEUED" || message.status === "SENDING"
                    ? "bg-amber-500/10 text-amber-400"
                    : "bg-red-500/10 text-red-400"
                }`}
              >
                {message.status}
              </span>
              <span className="text-xs text-slate-400 font-mono">
                {message.attempts}/{message.max_attempts} tentativas
              </span>
            </div>
          </div>

          <div className="bg-[#0f172a] border border-slate-800 rounded-xl p-4">
            <span className="text-[11px] font-medium text-slate-400">Data de Envio</span>
            <p className="text-xs font-semibold text-white mt-1">
              {new Date(message.created_at).toLocaleString()}
            </p>
          </div>
        </div>

        {/* Content & Technical Meta */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Content Preview */}
          <div className="bg-[#0f172a] border border-slate-800 rounded-xl p-6 space-y-4 shadow-xl">
            <h3 className="text-sm font-bold text-white flex items-center space-x-2">
              <FileText className="w-4 h-4 text-emerald-400" />
              <span>Conteúdo da Mensagem Disparada</span>
            </h3>

            {message.channel === "EMAIL" && (
              <div className="space-y-1">
                <span className="text-[11px] font-medium text-slate-400">Assunto:</span>
                <p className="text-xs font-semibold text-white bg-slate-900 p-2.5 rounded-lg border border-slate-800">
                  {message.subject || "Sem assunto"}
                </p>
              </div>
            )}

            <div className="space-y-1">
              <span className="text-[11px] font-medium text-slate-400">Corpo da Mensagem:</span>
              <div className="p-3.5 rounded-lg bg-slate-900 border border-slate-800 font-mono text-xs text-slate-300 whitespace-pre-wrap max-h-64 overflow-y-auto">
                {message.body || "Nenhum conteúdo salvo."}
              </div>
            </div>
          </div>

          {/* Technical Info */}
          <div className="bg-[#0f172a] border border-slate-800 rounded-xl p-6 space-y-3 shadow-xl text-xs">
            <h3 className="text-sm font-bold text-white flex items-center space-x-2 pb-2">
              <Server className="w-4 h-4 text-purple-400" />
              <span>Metadados de Idempotência & Gateway</span>
            </h3>

            <div className="flex justify-between py-2 border-b border-slate-800">
              <span className="text-slate-400">Idempotency Key</span>
              <span className="font-mono text-slate-200">{message.idempotency_key}</span>
            </div>

            <div className="flex justify-between py-2 border-b border-slate-800">
              <span className="text-slate-400">ID Externo (Provider Message ID)</span>
              <span className="font-mono text-emerald-400">
                {message.provider_message_id || "Não atribuído"}
              </span>
            </div>

            <div className="flex justify-between py-2 border-b border-slate-800">
              <span className="text-slate-400">Driver do Provedor</span>
              <span className="font-mono uppercase text-slate-200">{message.provider_driver || "-"}</span>
            </div>

            {message.error_code && (
              <div className="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-300 space-y-1">
                <p className="font-semibold text-red-400 font-mono text-[11px]">
                  Erro: {message.error_code}
                </p>
                <p className="text-[11px] text-slate-400">{message.error_message || "Sem detalhes adicionais"}</p>
              </div>
            )}

            <div className="flex justify-between py-2 border-b border-slate-800">
              <span className="text-slate-400">Data de Disparo</span>
              <span className="text-slate-200 font-mono">
                {message.dispatched_at ? new Date(message.dispatched_at).toLocaleString() : "-"}
              </span>
            </div>

            <div className="flex justify-between py-2">
              <span className="text-slate-400">Data de Entrega</span>
              <span className="text-slate-200 font-mono">
                {message.delivered_at ? new Date(message.delivered_at).toLocaleString() : "-"}
              </span>
            </div>
          </div>
        </div>

        {/* Delivery Event Timeline */}
        <div className="bg-[#0f172a] border border-slate-800 rounded-xl p-6 space-y-6 shadow-xl">
          <div className="flex items-center justify-between border-b border-slate-800 pb-3">
            <h3 className="text-sm font-bold text-white flex items-center space-x-2">
              <Activity className="w-4 h-4 text-emerald-400" />
              <span>Linha do Tempo de Entrega (Lifecycle Progression)</span>
            </h3>
            <span className="text-xs text-slate-500 font-mono">Status: {message.status}</span>
          </div>

          {/* Stepper Pipeline: QUEUED -> SENDING -> SENT -> DELIVERED -> OPENED -> CLICKED */}
          {(() => {
            const eventTypes = (message.events || []).map((e: any) => e.event_type?.toUpperCase());
            const steps = [
              { key: "QUEUED", label: "Queued" },
              { key: "SENDING", label: "Sending" },
              { key: "SENT", label: "Sent" },
              { key: "DELIVERED", label: "Delivered" },
              { key: "OPENED", label: "Opened" },
              { key: "CLICKED", label: "Clicked" },
            ];

            const isPassed = (stepKey: string) => {
              if (eventTypes.includes(stepKey)) return true;
              if (stepKey === "QUEUED") return true;
              if (stepKey === "SENDING" && (eventTypes.includes("SENT") || eventTypes.includes("DELIVERED") || message.status === "DELIVERED" || message.status === "SENT")) return true;
              if (stepKey === "SENT" && (eventTypes.includes("DELIVERED") || message.status === "DELIVERED" || message.status === "SENT")) return true;
              if (stepKey === "DELIVERED" && (eventTypes.includes("OPENED") || eventTypes.includes("CLICKED") || message.status === "DELIVERED")) return true;
              return false;
            };

            const isFailed = ["FAILED", "BOUNCED", "REJECTED"].includes(message.status);

            return (
              <div className="p-4 bg-slate-950/60 border border-slate-800 rounded-xl space-y-3">
                <div className="flex items-center justify-between relative">
                  <div className="absolute left-0 right-0 top-1/2 -translate-y-1/2 h-1 bg-slate-800 -z-0" />
                  {steps.map((step, idx) => {
                    const active = isPassed(step.key);
                    return (
                      <div key={step.key} className="relative z-10 flex flex-col items-center">
                        <div
                          className={`w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold border-2 transition-all ${
                            active
                              ? "bg-emerald-500 border-emerald-400 text-slate-950 shadow-md shadow-emerald-500/20"
                              : "bg-slate-900 border-slate-700 text-slate-500"
                          }`}
                        >
                          {active ? "✓" : idx + 1}
                        </div>
                        <span className={`text-[10px] font-semibold mt-1.5 ${active ? "text-emerald-400" : "text-slate-500"}`}>
                          {step.label}
                        </span>
                      </div>
                    );
                  })}
                </div>

                {isFailed && (
                  <div className="pt-2 text-center text-xs text-rose-400 font-semibold flex items-center justify-center gap-1.5">
                    <XCircle className="w-4 h-4" />
                    <span>Falha de Entrega Detectada: {message.error_code || message.status}</span>
                  </div>
                )}
              </div>
            );
          })()}

          {!message.events || message.events.length === 0 ? (
            <p className="text-xs text-slate-500">Nenhum evento registrado ainda.</p>
          ) : (
            <div className="relative pl-6 space-y-6 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-800">
              {message.events.map((evt, idx) => (
                <div key={evt.id || idx} className="relative flex items-start space-x-4">
                  <div className="absolute -left-6 top-1 w-3.5 h-3.5 rounded-full bg-emerald-500 border-2 border-[#0f172a] shrink-0" />
                  <div className="flex-1 bg-slate-900 border border-slate-800 rounded-lg p-3 space-y-1 text-xs">
                    <div className="flex items-center justify-between">
                      <span className="font-bold text-white font-mono">{evt.event_type}</span>
                      <span className="text-[10px] text-slate-500 font-mono">
                        {new Date(evt.created_at).toLocaleString()}
                      </span>
                    </div>
                    <div className="flex items-center space-x-2 text-[11px] text-slate-400">
                      <span>Status: <strong className="text-emerald-400">{evt.status}</strong></span>
                      {evt.provider_message_id && (
                        <span>• Ref: <code className="text-slate-300">{evt.provider_message_id}</code></span>
                      )}
                    </div>
                    {evt.details && Object.keys(evt.details).length > 0 && (
                      <pre className="mt-1 p-2 rounded bg-slate-950 font-mono text-[10px] text-slate-400 overflow-x-auto">
                        {JSON.stringify(evt.details, null, 2)}
                      </pre>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </AppLayout>
  );
}
