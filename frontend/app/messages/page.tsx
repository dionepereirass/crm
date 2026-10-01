"use client";

import React, { useEffect, useState } from "react";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import { messageService, Message } from "@/services/message-service";
import {
  Send,
  Mail,
  MessageSquare,
  Search,
  RefreshCw,
  CheckCircle2,
  XCircle,
  Clock,
  RotateCcw,
  Ban,
  Shield,
  Zap,
  Eye,
  AlertTriangle,
} from "lucide-react";

export default function MessagesPage() {
  const [messages, setMessages] = useState<Message[]>([]);
  const [loading, setLoading] = useState(true);
  const [channelFilter, setChannelFilter] = useState("ALL");
  const [statusFilter, setStatusFilter] = useState("ALL");
  const [search, setSearch] = useState("");
  const [actionError, setActionError] = useState<string | null>(null);
  const [actionSuccess, setActionSuccess] = useState<string | null>(null);

  // Modal Send Test
  const [showTestModal, setShowTestModal] = useState(false);
  const [testChannel, setTestChannel] = useState<"EMAIL" | "SMS">("EMAIL");
  const [testRecipient, setTestRecipient] = useState("admin@betcrm.com");
  const [testSubject, setTestSubject] = useState("Teste Rápido BET CRM");
  const [testBody, setTestBody] = useState("Olá! Disparo teste de validação de mensageria.");
  const [sendingTest, setSendingTest] = useState(false);

  const fetchMessages = async () => {
    try {
      setLoading(true);
      setActionError(null);
      const res = await messageService.list({
        channel: channelFilter,
        status: statusFilter,
        search: search || undefined,
      });
      setMessages(res.data);
    } catch (err: any) {
      setActionError(err.message || "Erro ao carregar mensagens.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchMessages();
  }, [channelFilter, statusFilter]);

  const handleRetry = async (msg: Message) => {
    try {
      setActionError(null);
      await messageService.retry(msg.id);
      setActionSuccess(`Mensagem #${msg.id} reenfileirada com sucesso.`);
      fetchMessages();
    } catch (err: any) {
      setActionError(err.message || "Falha ao reenviar mensagem.");
    }
  };

  const handleCancel = async (msg: Message) => {
    if (!confirm(`Deseja cancelar o disparo da mensagem #${msg.id}?`)) return;
    try {
      setActionError(null);
      await messageService.cancel(msg.id);
      setActionSuccess(`Mensagem #${msg.id} cancelada.`);
      fetchMessages();
    } catch (err: any) {
      setActionError(err.message || "Falha ao cancelar mensagem.");
    }
  };

  const handleSendTestSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      setSendingTest(true);
      setActionError(null);
      await messageService.testSend({
        channel: testChannel,
        recipient: testRecipient,
        subject: testChannel === "EMAIL" ? testSubject : undefined,
        html_content: testChannel === "EMAIL" ? testBody : undefined,
        sms_content: testChannel === "SMS" ? testBody : undefined,
      });
      setActionSuccess("Mensagem de teste enviada com sucesso!");
      setShowTestModal(false);
      fetchMessages();
    } catch (err: any) {
      setActionError(err.message || "Erro ao enviar teste.");
    } finally {
      setSendingTest(false);
    }
  };

  const totalCount = messages.length;
  const sentCount = messages.filter((m) => m.status === "SENT" || m.status === "DELIVERED").length;
  const queuedCount = messages.filter((m) => m.status === "QUEUED" || m.status === "SENDING").length;
  const failedCount = messages.filter((m) => m.status === "FAILED" || m.status === "RATE_LIMITED").length;

  return (
    <AppLayout
      title="Fila de Mensageria"
      badge="E-mail & SMS Transacional"
      actions={
        <div className="flex items-center space-x-3">
          <Link
            href="/providers"
            className="flex items-center space-x-1.5 px-3 py-1.5 rounded-lg border border-slate-700 bg-slate-800 text-xs font-medium text-slate-200 hover:bg-slate-700 transition"
          >
            <span>Gerenciar Provedores</span>
          </Link>
          <button
            onClick={() => setShowTestModal(true)}
            className="flex items-center space-x-1.5 px-3.5 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-xs font-bold text-slate-950 transition shadow-lg shadow-emerald-500/20"
          >
            <Zap className="w-3.5 h-3.5" />
            <span>Disparo de Teste</span>
          </button>
        </div>
      }
    >
      <div className="p-8 space-y-6">
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
              <p className="text-[11px] font-medium text-slate-400">Total na Lista</p>
              <p className="text-2xl font-bold text-white mt-1">{totalCount}</p>
            </div>
            <div className="w-10 h-10 rounded-lg bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-400">
              <Send className="w-5 h-5" />
            </div>
          </div>

          <div className="bg-[#0f172a] border border-slate-800 rounded-xl p-4 flex items-center justify-between">
            <div>
              <p className="text-[11px] font-medium text-slate-400">Entregues / Enviadas</p>
              <p className="text-2xl font-bold text-emerald-400 mt-1">{sentCount}</p>
            </div>
            <div className="w-10 h-10 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
              <CheckCircle2 className="w-5 h-5" />
            </div>
          </div>

          <div className="bg-[#0f172a] border border-slate-800 rounded-xl p-4 flex items-center justify-between">
            <div>
              <p className="text-[11px] font-medium text-slate-400">Em Fila / Processando</p>
              <p className="text-2xl font-bold text-amber-400 mt-1">{queuedCount}</p>
            </div>
            <div className="w-10 h-10 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
              <Clock className="w-5 h-5" />
            </div>
          </div>

          <div className="bg-[#0f172a] border border-slate-800 rounded-xl p-4 flex items-center justify-between">
            <div>
              <p className="text-[11px] font-medium text-slate-400">Falhas / Rejeitadas</p>
              <p className="text-2xl font-bold text-red-400 mt-1">{failedCount}</p>
            </div>
            <div className="w-10 h-10 rounded-lg bg-red-500/10 border border-red-500/20 flex items-center justify-center text-red-400">
              <AlertTriangle className="w-5 h-5" />
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
              {["ALL", "SENT", "DELIVERED", "QUEUED", "FAILED"].map((st) => (
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

          <div className="flex items-center space-x-2 w-full md:w-auto">
            <div className="relative flex-1 md:w-64">
              <Search className="w-4 h-4 text-slate-500 absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                type="text"
                placeholder="Buscar por destinatário ou ID..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                onKeyDown={(e) => e.key === "Enter" && fetchMessages()}
                className="w-full pl-9 pr-3 py-1.5 rounded-lg bg-slate-900 border border-slate-800 text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-emerald-500"
              />
            </div>
            <button
              onClick={fetchMessages}
              disabled={loading}
              className="p-2 rounded-lg bg-slate-900 border border-slate-800 text-slate-400 hover:text-slate-200 transition"
              title="Recarregar"
            >
              <RefreshCw className={`w-4 h-4 ${loading ? "animate-spin" : ""}`} />
            </button>
          </div>
        </div>

        {/* Table */}
        {loading ? (
          <div className="p-12 text-center text-slate-500 text-xs">
            <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2 text-emerald-500" />
            Carregando mensagens da plataforma...
          </div>
        ) : messages.length === 0 ? (
          <div className="p-12 text-center bg-[#0f172a] border border-slate-800 rounded-xl space-y-3">
            <Send className="w-10 h-10 text-slate-600 mx-auto" />
            <h3 className="text-sm font-semibold text-slate-300">Nenhuma mensagem registrada</h3>
            <p className="text-xs text-slate-500 max-w-sm mx-auto">
              Realize um disparo de teste ou verifique se as filas do Horizon estão processando.
            </p>
            <button
              onClick={() => setShowTestModal(true)}
              className="inline-flex items-center space-x-2 px-4 py-2 rounded-lg bg-emerald-500 text-slate-950 font-semibold text-xs hover:bg-emerald-600 transition"
            >
              <Zap className="w-4 h-4" />
              <span>Realizar Disparo de Teste</span>
            </button>
          </div>
        ) : (
          <div className="bg-[#0f172a] border border-slate-800 rounded-xl overflow-hidden shadow-xl">
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs text-slate-300">
                <thead className="bg-slate-900/80 text-[11px] text-slate-400 uppercase tracking-wider border-b border-slate-800 font-semibold">
                  <tr>
                    <th className="py-3 px-4">Destinatário (LGPD)</th>
                    <th className="py-3 px-4">Canal & Provedor</th>
                    <th className="py-3 px-4">Assunto / Prévia</th>
                    <th className="py-3 px-4">Status</th>
                    <th className="py-3 px-4">Tentativas</th>
                    <th className="py-3 px-4">Data de Envio</th>
                    <th className="py-3 px-4 text-right">Ações</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800/60 font-sans">
                  {messages.map((m) => (
                    <tr key={m.id} className="hover:bg-slate-800/30 transition">
                      {/* Recipient */}
                      <td className="py-3.5 px-4">
                        <div className="flex items-center space-x-2">
                          <Shield className="w-3.5 h-3.5 text-slate-500 shrink-0" />
                          <span className="font-mono text-slate-200 font-semibold">
                            {m.masked_recipient || m.recipient}
                          </span>
                        </div>
                        <span className="text-[10px] text-slate-500 font-mono">#{m.id}</span>
                      </td>

                      {/* Channel & Provider */}
                      <td className="py-3.5 px-4">
                        <div className="space-y-1">
                          <span
                            className={`px-2 py-0.5 rounded text-[10px] font-semibold border ${
                              m.channel === "EMAIL"
                                ? "bg-purple-500/10 text-purple-300 border-purple-500/30"
                                : "bg-emerald-500/10 text-emerald-300 border-emerald-500/30"
                            }`}
                          >
                            {m.channel}
                          </span>
                          <p className="text-[11px] text-slate-400 truncate max-w-[140px]">
                            {m.provider_name || m.provider_driver || "Gateway"}
                          </p>
                        </div>
                      </td>

                      {/* Subject / Preview */}
                      <td className="py-3.5 px-4 max-w-xs">
                        <p className="text-white font-medium truncate">{m.subject || "Sem assunto"}</p>
                        <p className="text-[11px] text-slate-500 truncate">{m.body || "-"}</p>
                      </td>

                      {/* Status */}
                      <td className="py-3.5 px-4">
                        <span
                          className={`px-2 py-0.5 rounded-full text-[10px] font-bold tracking-wide border ${
                            m.status === "DELIVERED" || m.status === "SENT"
                              ? "bg-emerald-500/10 text-emerald-400 border-emerald-500/30"
                              : m.status === "QUEUED" || m.status === "SENDING"
                              ? "bg-amber-500/10 text-amber-400 border-amber-500/30"
                              : m.status === "CANCELLED"
                              ? "bg-slate-800 text-slate-400 border-slate-700"
                              : "bg-red-500/10 text-red-400 border-red-500/30"
                          }`}
                        >
                          {m.status}
                        </span>
                        {m.error_code && (
                          <span className="block text-[10px] text-red-400 font-mono mt-0.5">
                            {m.error_code}
                          </span>
                        )}
                      </td>

                      {/* Attempts */}
                      <td className="py-3.5 px-4 font-mono text-[11px]">
                        <span className="text-slate-300">{m.attempts}</span>
                        <span className="text-slate-600">/{m.max_attempts}</span>
                      </td>

                      {/* Date */}
                      <td className="py-3.5 px-4 text-slate-400 text-[11px]">
                        {new Date(m.created_at).toLocaleString()}
                      </td>

                      {/* Actions */}
                      <td className="py-3.5 px-4 text-right">
                        <div className="flex items-center justify-end space-x-1.5">
                          <Link
                            href={`/messages/${m.id}`}
                            className="p-1.5 rounded-lg bg-slate-900 border border-slate-800 text-slate-400 hover:text-white transition"
                            title="Ver Detalhes e Linha do Tempo"
                          >
                            <Eye className="w-3.5 h-3.5" />
                          </Link>

                          {m.status === "FAILED" && (
                            <button
                              onClick={() => handleRetry(m)}
                              className="p-1.5 rounded-lg bg-slate-900 border border-slate-800 text-amber-400 hover:bg-amber-500/10 transition"
                              title="Reenviar Mensagem"
                            >
                              <RotateCcw className="w-3.5 h-3.5" />
                            </button>
                          )}

                          {m.status === "QUEUED" && (
                            <button
                              onClick={() => handleCancel(m)}
                              className="p-1.5 rounded-lg bg-slate-900 border border-slate-800 text-red-400 hover:bg-red-500/10 transition"
                              title="Cancelar Envio"
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
          </div>
        )}

        {/* Modal Send Test */}
        {showTestModal && (
          <div className="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div className="bg-[#0f172a] border border-slate-800 rounded-2xl p-6 max-w-lg w-full space-y-4 shadow-2xl">
              <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 className="text-sm font-bold text-white flex items-center space-x-2">
                  <Zap className="w-4 h-4 text-emerald-400" />
                  <span>Enviar Mensagem de Teste</span>
                </h3>
                <button
                  onClick={() => setShowTestModal(false)}
                  className="text-slate-500 hover:text-slate-300 text-xs"
                >
                  ✕
                </button>
              </div>

              <form onSubmit={handleSendTestSubmit} className="space-y-4">
                <div className="grid grid-cols-2 gap-3">
                  <button
                    type="button"
                    onClick={() => {
                      setTestChannel("EMAIL");
                      setTestRecipient("admin@betcrm.com");
                    }}
                    className={`p-3 rounded-lg border flex items-center space-x-2 text-xs font-semibold ${
                      testChannel === "EMAIL"
                        ? "bg-purple-500/10 border-purple-500 text-purple-300"
                        : "bg-slate-900 border-slate-800 text-slate-400"
                    }`}
                  >
                    <Mail className="w-4 h-4" />
                    <span>E-mail</span>
                  </button>

                  <button
                    type="button"
                    onClick={() => {
                      setTestChannel("SMS");
                      setTestRecipient("5511999998888");
                    }}
                    className={`p-3 rounded-lg border flex items-center space-x-2 text-xs font-semibold ${
                      testChannel === "SMS"
                        ? "bg-emerald-500/10 border-emerald-500 text-emerald-300"
                        : "bg-slate-900 border-slate-800 text-slate-400"
                    }`}
                  >
                    <MessageSquare className="w-4 h-4" />
                    <span>SMS</span>
                  </button>
                </div>

                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">
                    Destinatário ({testChannel === "EMAIL" ? "E-mail" : "Telefone"}) *
                  </label>
                  <input
                    type="text"
                    required
                    value={testRecipient}
                    onChange={(e) => setTestRecipient(e.target.value)}
                    className="w-full px-3.5 py-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-white focus:outline-none focus:border-emerald-500 font-mono"
                  />
                </div>

                {testChannel === "EMAIL" && (
                  <div>
                    <label className="block text-xs font-medium text-slate-300 mb-1">Assunto</label>
                    <input
                      type="text"
                      required
                      value={testSubject}
                      onChange={(e) => setTestSubject(e.target.value)}
                      className="w-full px-3.5 py-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-white focus:outline-none focus:border-emerald-500"
                    />
                  </div>
                )}

                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">
                    {testChannel === "EMAIL" ? "HTML / Texto" : "Mensagem de Texto"}
                  </label>
                  <textarea
                    rows={3}
                    required
                    value={testBody}
                    onChange={(e) => setTestBody(e.target.value)}
                    className="w-full px-3.5 py-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-white focus:outline-none focus:border-emerald-500 font-mono"
                  />
                </div>

                <div className="flex items-center justify-end space-x-3 pt-2">
                  <button
                    type="button"
                    onClick={() => setShowTestModal(false)}
                    className="px-4 py-2 rounded-lg border border-slate-800 text-xs text-slate-400 hover:text-white"
                  >
                    Cancelar
                  </button>
                  <button
                    type="submit"
                    disabled={sendingTest}
                    className="px-5 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold text-xs transition disabled:opacity-50"
                  >
                    {sendingTest ? "Enviando..." : "Disparar Mensagem"}
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
