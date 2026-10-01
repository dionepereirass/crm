"use client";

import React, { useState, useEffect, useCallback } from "react";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import { eventService, WebhookLogItem, computeHmacSha256 } from "@/services/event-service";
import { useAuth } from "@/hooks/use-auth";
import {
  Webhook,
  Activity,
  Send,
  RefreshCw,
  Search,
  CheckCircle2,
  XCircle,
  AlertTriangle,
  Eye,
  Key,
  Code2,
  Terminal,
  ShieldCheck,
  ChevronLeft,
  ChevronRight,
} from "lucide-react";

export default function WebhooksPage() {
  const { user, activePlatform } = useAuth(true);
  const [activeTab, setActiveTab] = useState<"logs" | "tester">("logs");

  // Webhook Logs State
  const [logs, setLogs] = useState<WebhookLogItem[]>([]);
  const [loadingLogs, setLoadingLogs] = useState(true);
  const [logError, setLogError] = useState<string | null>(null);
  const [logSearch, setLogSearch] = useState("");
  const [logStatus, setLogStatus] = useState("");
  const [logSignature, setLogSignature] = useState("");
  const [logPage, setLogPage] = useState(1);
  const [totalLogPages, setTotalLogPages] = useState(1);
  const [totalLogCount, setTotalLogCount] = useState(0);

  // Selected Log for inspection modal
  const [selectedLog, setSelectedLog] = useState<WebhookLogItem | null>(null);

  // Webhook Tester State
  const [testerEvent, setTesterEvent] = useState("deposit.success");
  const [testerSecret, setTesterSecret] = useState("whsec_demo_secret_key_12345");
  const [testerPayload, setTesterPayload] = useState("");
  const [sendingTest, setSendingTest] = useState(false);
  const [testResult, setTestResult] = useState<any | null>(null);

  const fetchLogs = useCallback(async () => {
    setLoadingLogs(true);
    setLogError(null);
    try {
      const res = await eventService.getWebhookLogs({
        search: logSearch || undefined,
        status: logStatus || undefined,
        signature_valid: logSignature || undefined,
        page: logPage,
        per_page: 20,
      });

      setLogs(res.data);
      if (res.meta) {
        setTotalLogPages(res.meta.last_page || 1);
        setTotalLogCount(res.meta.total || 0);
      }
    } catch (err: any) {
      setLogError(err.message || "Erro ao carregar logs de webhook.");
    } finally {
      setLoadingLogs(false);
    }
  }, [logSearch, logStatus, logSignature, logPage]);

  useEffect(() => {
    if (activePlatform) {
      fetchLogs();
    }
  }, [activePlatform, fetchLogs]);

  // Preset templates for Webhook Tester
  useEffect(() => {
    const randomId = "evt_" + Math.random().toString(36).substring(2, 9);
    const nowIso = new Date().toISOString();

    let sample: any = {};
    switch (testerEvent) {
      case "deposit.success":
        sample = {
          event_id: randomId,
          event: "deposit.success",
          timestamp: nowIso,
          player: { external_id: "player_123" },
          data: { amount: 150.0, currency: "BRL", payment_method: "PIX" },
        };
        break;
      case "bet.placed":
        sample = {
          event_id: randomId,
          event: "bet.placed",
          timestamp: nowIso,
          player: { external_id: "player_123" },
          data: { bet_amount: 50.0, odds: 2.15, sport: "Futebol", market: "Vencedor do Jogo" },
        };
        break;
      case "bet.settled":
        sample = {
          event_id: randomId,
          event: "bet.settled",
          timestamp: nowIso,
          player: { external_id: "player_123" },
          data: { win_amount: 107.5, status: "WON" },
        };
        break;
      case "player.created":
        sample = {
          event_id: randomId,
          event: "player.created",
          timestamp: nowIso,
          player: {
            external_id: "player_" + Math.floor(Math.random() * 90000 + 10000),
            name: "Novo Apostador Teste",
            email: "teste_" + Math.floor(Math.random() * 900) + "@betcrm.local",
            phone: "(11) 99999-8888",
          },
        };
        break;
      case "player.updated":
        sample = {
          event_id: randomId,
          event: "player.updated",
          timestamp: nowIso,
          player: { external_id: "player_123" },
          data: { city: "São Paulo", state: "SP" },
        };
        break;
      case "login":
        sample = {
          event_id: randomId,
          event: "login",
          timestamp: nowIso,
          player: { external_id: "player_123" },
          data: { ip: "189.12.34.56", device: "Mobile Safari" },
        };
        break;
      case "withdrawal.success":
        sample = {
          event_id: randomId,
          event: "withdrawal.success",
          timestamp: nowIso,
          player: { external_id: "player_123" },
          data: { amount: 300.0, currency: "BRL", status: "COMPLETED" },
        };
        break;
      default:
        sample = { event_id: randomId, event: testerEvent, timestamp: nowIso };
    }

    setTesterPayload(JSON.stringify(sample, null, 2));
  }, [testerEvent]);

  const handleSendTestWebhook = async () => {
    if (!activePlatform) return;
    setSendingTest(true);
    setTestResult(null);

    try {
      const parsed = JSON.parse(testerPayload);
      const res = await eventService.sendTestWebhook(activePlatform.slug, testerSecret, parsed);
      setTestResult(res);
      await fetchLogs();
    } catch (err: any) {
      setTestResult({
        error: err.message || "Erro de requisição local.",
      });
    } finally {
      setSendingTest(false);
    }
  };

  return (
    <AppLayout
      title="Webhooks & Integrações Externas"
      badge="FASE 4 • INGESTÃO HMAC-SHA256"
      actions={
        <button
          onClick={fetchLogs}
          disabled={loadingLogs}
          className="flex items-center space-x-1.5 px-3 py-1.5 rounded-md text-xs font-medium bg-slate-800 hover:bg-slate-700 text-slate-200 transition border border-slate-700"
        >
          <RefreshCw className={`w-3.5 h-3.5 ${loadingLogs ? "animate-spin text-emerald-400" : ""}`} />
          <span>Atualizar Logs</span>
        </button>
      }
    >
      <div className="space-y-6">
        {/* Navigation Tabs */}
        <div className="flex items-center space-x-3 border-b border-slate-800 text-xs font-medium">
          <button
            onClick={() => setActiveTab("logs")}
            className={`pb-3 px-3 transition border-b-2 flex items-center space-x-2 ${
              activeTab === "logs"
                ? "border-emerald-400 text-emerald-400 font-semibold"
                : "border-transparent text-slate-400 hover:text-slate-200"
            }`}
          >
            <Activity className="w-4 h-4" />
            <span>Logs de Ingestão HTTP ({totalLogCount})</span>
          </button>

          <button
            onClick={() => setActiveTab("tester")}
            className={`pb-3 px-3 transition border-b-2 flex items-center space-x-2 ${
              activeTab === "tester"
                ? "border-emerald-400 text-emerald-400 font-semibold"
                : "border-transparent text-slate-400 hover:text-slate-200"
            }`}
          >
            <Terminal className="w-4 h-4 text-emerald-400" />
            <span>Webhook Tester (Ambiente de Testes)</span>
          </button>
        </div>

        {/* Tab 1: Webhook Logs */}
        {activeTab === "logs" && (
          <div className="space-y-4">
            {/* Filters */}
            <div className="p-4 rounded-xl border border-slate-800 bg-[#0f172a]/70">
              <form
                onSubmit={(e) => {
                  e.preventDefault();
                  setLogPage(1);
                  fetchLogs();
                }}
                className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3"
              >
                <div className="lg:col-span-5 relative">
                  <Search className="w-4 h-4 text-slate-500 absolute left-3 top-2.5" />
                  <input
                    type="text"
                    placeholder="Buscar por ID externo ou endpoint..."
                    value={logSearch}
                    onChange={(e) => setLogSearch(e.target.value)}
                    className="w-full pl-9 pr-3 py-2 bg-slate-900 border border-slate-700/80 rounded-lg text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 transition"
                  />
                </div>

                <div className="lg:col-span-3">
                  <select
                    value={logStatus}
                    onChange={(e) => {
                      setLogStatus(e.target.value);
                      setLogPage(1);
                    }}
                    className="w-full px-3 py-2 bg-slate-900 border border-slate-700/80 rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500 transition"
                  >
                    <option value="">Status: Todos</option>
                    <option value="RECEIVED">RECEIVED (Enfileirado)</option>
                    <option value="DUPLICATE">DUPLICATE (Idempotente)</option>
                    <option value="INVALID_SIGNATURE">INVALID_SIGNATURE (HMAC Inválido)</option>
                    <option value="INVALID_PAYLOAD">INVALID_PAYLOAD (JSON Ruim)</option>
                    <option value="FAILED">FAILED (Erro)</option>
                  </select>
                </div>

                <div className="lg:col-span-3">
                  <select
                    value={logSignature}
                    onChange={(e) => {
                      setLogSignature(e.target.value);
                      setLogPage(1);
                    }}
                    className="w-full px-3 py-2 bg-slate-900 border border-slate-700/80 rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500 transition"
                  >
                    <option value="">Assinatura HMAC: Todas</option>
                    <option value="true">Válida (HMAC OK)</option>
                    <option value="false">Inválida (Rejeitado)</option>
                  </select>
                </div>

                <div className="lg:col-span-1">
                  <button
                    type="submit"
                    className="w-full py-2 bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 rounded-lg text-xs font-medium transition"
                  >
                    Filtrar
                  </button>
                </div>
              </form>
            </div>

            {/* Logs Table */}
            <div className="rounded-xl border border-slate-800 bg-[#0d131f] overflow-hidden">
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs text-slate-300">
                  <thead className="bg-[#0f172a] text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-800">
                    <tr>
                      <th className="py-3 px-4">Endpoint / ID Externo</th>
                      <th className="py-3 px-4">Assinatura HMAC</th>
                      <th className="py-3 px-4">HTTP Status</th>
                      <th className="py-3 px-4">Status Interno</th>
                      <th className="py-3 px-4">IP / Origem</th>
                      <th className="py-3 px-4">Recebido Em</th>
                      <th className="py-3 px-4 text-right">Ações</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-800/60 font-sans">
                    {loadingLogs ? (
                      <tr>
                        <td colSpan={7} className="py-12 text-center text-slate-500">
                          <div className="flex items-center justify-center space-x-2">
                            <div className="animate-spin w-4 h-4 border-2 border-emerald-500 border-t-transparent rounded-full" />
                            <span>Carregando logs de webhooks...</span>
                          </div>
                        </td>
                      </tr>
                    ) : logs.length === 0 ? (
                      <tr>
                        <td colSpan={7} className="py-12 text-center text-slate-500">
                          Nenhum webhook recebido ainda.
                        </td>
                      </tr>
                    ) : (
                      logs.map((log) => (
                        <tr key={log.id} className="hover:bg-slate-900/50 transition">
                          <td className="py-3 px-4 font-mono text-[11px]">
                            <span className="font-semibold text-slate-200 block">{log.endpoint}</span>
                            <span className="text-[10px] text-slate-500">
                              {log.external_event_id ? `ID: ${log.external_event_id}` : "Sem ID externo"}
                            </span>
                          </td>

                          <td className="py-3 px-4">
                            {log.signature_valid ? (
                              <span className="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                <CheckCircle2 className="w-3 h-3 mr-1" />
                                Válida
                              </span>
                            ) : (
                              <span className="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                <XCircle className="w-3 h-3 mr-1" />
                                Inválida
                              </span>
                            )}
                          </td>

                          <td className="py-3 px-4 font-mono font-semibold">
                            <span
                              className={
                                log.http_status === 202
                                  ? "text-emerald-400"
                                  : log.http_status === 401
                                  ? "text-rose-400"
                                  : "text-amber-400"
                              }
                            >
                              HTTP {log.http_status}
                            </span>
                          </td>

                          <td className="py-3 px-4">
                            <span className="px-2 py-0.5 rounded bg-slate-900 border border-slate-800 text-[10px] font-mono text-slate-300">
                              {log.processing_status}
                            </span>
                          </td>

                          <td className="py-3 px-4 font-mono text-[11px] text-slate-400">
                            {log.ip_address || "—"}
                          </td>

                          <td className="py-3 px-4 font-mono text-[11px] text-slate-400">
                            {new Date(log.received_at).toLocaleString("pt-BR")}
                          </td>

                          <td className="py-3 px-4 text-right">
                            <button
                              type="button"
                              onClick={() => setSelectedLog(log)}
                              className="p-1.5 rounded-md hover:bg-slate-800 text-slate-400 hover:text-sky-400 transition"
                              title="Inspecionar Payload & Headers"
                            >
                              <Eye className="w-3.5 h-3.5" />
                            </button>
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>

              {/* Pagination */}
              {totalLogPages > 1 && (
                <div className="p-4 border-t border-slate-800 bg-[#0f172a] flex items-center justify-between text-xs text-slate-400">
                  <div>
                    Página <span className="font-semibold text-white">{logPage}</span> de{" "}
                    <span className="font-semibold text-white">{totalLogPages}</span> (Total de{" "}
                    {totalLogCount} requisições)
                  </div>
                  <div className="flex items-center space-x-2">
                    <button
                      onClick={() => setLogPage((p) => Math.max(1, p - 1))}
                      disabled={logPage <= 1 || loadingLogs}
                      className="px-2.5 py-1 rounded border border-slate-700 bg-slate-800 hover:bg-slate-700 text-slate-200 disabled:opacity-40"
                    >
                      <ChevronLeft className="w-3 h-3" />
                    </button>
                    <button
                      onClick={() => setLogPage((p) => Math.min(totalLogPages, p + 1))}
                      disabled={logPage >= totalLogPages || loadingLogs}
                      className="px-2.5 py-1 rounded border border-slate-700 bg-slate-800 hover:bg-slate-700 text-slate-200 disabled:opacity-40"
                    >
                      <ChevronRight className="w-3 h-3" />
                    </button>
                  </div>
                </div>
              )}
            </div>
          </div>
        )}

        {/* Tab 2: Webhook Tester */}
        {activeTab === "tester" && (
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div className="lg:col-span-7 space-y-4">
              <div className="p-5 rounded-xl border border-slate-800 bg-[#0d131f] space-y-4">
                <div className="flex items-center space-x-2 text-white font-semibold text-sm border-b border-slate-800 pb-3">
                  <Terminal className="w-4 h-4 text-emerald-400" />
                  <span>Configuração do Disparo de Teste (Local)</span>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-medium text-slate-300 mb-1">
                      Plataforma Alvo:
                    </label>
                    <input
                      type="text"
                      disabled
                      value={activePlatform?.slug || "bet-brasil"}
                      className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-xs font-mono text-emerald-400"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-medium text-slate-300 mb-1">
                      Template de Evento:
                    </label>
                    <select
                      value={testerEvent}
                      onChange={(e) => setTesterEvent(e.target.value)}
                      className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500"
                    >
                      <option value="deposit.success">deposit.success (Depósito)</option>
                      <option value="bet.placed">bet.placed (Aposta Realizada)</option>
                      <option value="bet.settled">bet.settled (Aposta Liquidada)</option>
                      <option value="player.created">player.created (Novo Cadastro)</option>
                      <option value="player.updated">player.updated (Atualização)</option>
                      <option value="withdrawal.success">withdrawal.success (Saque)</option>
                      <option value="login">login (Sessão de Usuário)</option>
                    </select>
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">
                    Webhook Secret da Operadora:
                  </label>
                  <input
                    type="text"
                    value={testerSecret}
                    onChange={(e) => setTesterSecret(e.target.value)}
                    placeholder="Chave secreta configurada na casa de aposta"
                    className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs font-mono text-slate-200 focus:outline-none focus:border-emerald-500"
                  />
                  <span className="text-[10px] text-slate-500 mt-1 block">
                    A assinatura HMAC-SHA256 é gerada sobre o corpo exato antes do envio.
                  </span>
                </div>

                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">
                    Corpo do Webhook (JSON):
                  </label>
                  <textarea
                    rows={12}
                    value={testerPayload}
                    onChange={(e) => setTesterPayload(e.target.value)}
                    className="w-full p-3 bg-[#070b13] border border-slate-800 rounded-xl text-xs font-mono text-emerald-300 focus:outline-none focus:border-emerald-500 transition"
                  />
                </div>

                <div className="flex items-center justify-end">
                  <button
                    type="button"
                    onClick={handleSendTestWebhook}
                    disabled={sendingTest || !activePlatform}
                    className="flex items-center space-x-2 px-5 py-2 rounded-lg text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white transition shadow-lg shadow-emerald-950/40"
                  >
                    {sendingTest ? (
                      <div className="animate-spin w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full" />
                    ) : (
                      <Send className="w-3.5 h-3.5" />
                    )}
                    <span>Disparar Webhook</span>
                  </button>
                </div>
              </div>
            </div>

            {/* Test Result Inspector */}
            <div className="lg:col-span-5 space-y-4">
              <div className="p-5 rounded-xl border border-slate-800 bg-[#0d131f] space-y-4 h-full">
                <div className="flex items-center space-x-2 text-white font-semibold text-sm border-b border-slate-800 pb-3">
                  <Code2 className="w-4 h-4 text-sky-400" />
                  <span>Resultado da Resposta HTTP</span>
                </div>

                {testResult ? (
                  <div className="space-y-4 text-xs">
                    <div className="flex items-center justify-between p-3 rounded-lg border border-slate-800 bg-slate-900/60">
                      <span className="text-slate-400 text-[11px]">Código de Resposta</span>
                      <span
                        className={`font-mono font-bold ${
                          testResult.httpStatus === 202
                            ? "text-emerald-400"
                            : "text-rose-400"
                        }`}
                      >
                        HTTP {testResult.httpStatus || 500}
                      </span>
                    </div>

                    {testResult.signatureUsed && (
                      <div className="p-3 rounded-lg border border-slate-800 bg-slate-900/60 space-y-1">
                        <span className="text-slate-400 text-[10px] block">Assinatura HMAC-SHA256 Utilizada:</span>
                        <code className="text-[10px] text-sky-300 font-mono break-all block">
                          {testResult.signatureUsed}
                        </code>
                      </div>
                    )}

                    <div>
                      <span className="text-slate-400 text-[11px] block mb-1">Resposta da API:</span>
                      <pre className="p-3 rounded-lg border border-slate-800 bg-[#070b13] text-[11px] font-mono text-slate-300 overflow-x-auto">
                        {JSON.stringify(testResult.response || testResult, null, 2)}
                      </pre>
                    </div>

                    <Link
                      href="/events"
                      className="block text-center py-2 px-3 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium border border-slate-700 transition"
                    >
                      Ver Eventos Processados na Fila →
                    </Link>
                  </div>
                ) : (
                  <div className="py-24 text-center text-slate-500 text-xs space-y-2">
                    <Terminal className="w-8 h-8 mx-auto text-slate-600" />
                    <p>Envie um webhook para inspecionar a resposta em tempo real.</p>
                  </div>
                )}
              </div>
            </div>
          </div>
        )}

        {/* Modal Inspection for Single Log */}
        {selectedLog && (
          <div className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
            <div className="w-full max-w-2xl rounded-2xl border border-slate-800 bg-[#0d131f] p-6 space-y-4 shadow-2xl max-h-[85vh] overflow-y-auto">
              <div className="flex items-center justify-between pb-3 border-b border-slate-800">
                <h3 className="text-sm font-bold text-white flex items-center space-x-2">
                  <span>Log de Webhook • ID: {selectedLog.uuid.slice(0, 8)}</span>
                  <span className="font-mono text-emerald-400 text-xs">
                    HTTP {selectedLog.http_status}
                  </span>
                </h3>
                <button
                  type="button"
                  onClick={() => setSelectedLog(null)}
                  className="text-slate-400 hover:text-white"
                >
                  ✕
                </button>
              </div>

              {selectedLog.error_message && (
                <div className="p-3 rounded-lg border border-rose-500/30 bg-rose-500/10 text-rose-300 text-xs">
                  {selectedLog.error_message}
                </div>
              )}

              <div>
                <span className="text-xs font-semibold text-slate-300 block mb-1">
                  Corpo (Payload Recebido):
                </span>
                <pre className="p-3 rounded-xl border border-slate-800 bg-[#070b13] text-[11px] font-mono text-slate-300 overflow-x-auto max-h-56">
                  {JSON.stringify(selectedLog.payload, null, 2)}
                </pre>
              </div>

              <div>
                <span className="text-xs font-semibold text-slate-300 block mb-1">
                  Cabeçalhos HTTP (Headers Seguros / Mascarados):
                </span>
                <pre className="p-3 rounded-xl border border-slate-800 bg-[#070b13] text-[11px] font-mono text-slate-400 overflow-x-auto max-h-48">
                  {JSON.stringify(selectedLog.headers, null, 2)}
                </pre>
              </div>

              <div className="flex items-center justify-end pt-2 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setSelectedLog(null)}
                  className="px-4 py-1.5 rounded-lg text-xs font-medium bg-slate-800 text-slate-300 border border-slate-700"
                >
                  Fechar
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
