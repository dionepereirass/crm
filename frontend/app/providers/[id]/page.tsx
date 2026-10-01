"use client";

import React, { useEffect, useState } from "react";
import { useParams, useRouter } from "next/navigation";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import {
  providerService,
  Provider,
  HealthCheckResult,
} from "@/services/provider-service";
import {
  ArrowLeft,
  Mail,
  MessageSquare,
  Shield,
  Activity,
  Zap,
  Play,
  CheckCircle2,
  XCircle,
  AlertTriangle,
  RefreshCw,
  Edit,
  Clock,
  Server,
} from "lucide-react";

export default function ProviderDetailsPage() {
  const params = useParams();
  const router = useRouter();
  const id = params?.id as string;

  const [provider, setProvider] = useState<Provider | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Health check state
  const [checkingHealth, setCheckingHealth] = useState(false);
  const [healthResult, setHealthResult] = useState<HealthCheckResult | null>(null);

  // Test send state
  const [testRecipient, setTestRecipient] = useState("");
  const [testSubject, setTestSubject] = useState("Teste de Conectividade BET CRM");
  const [testContent, setTestContent] = useState("<p>Olá! Este é um teste do gateway de mensageria BET CRM.</p>");
  const [sendingTest, setSendingTest] = useState(false);
  const [testResult, setTestResult] = useState<any | null>(null);
  const [testError, setTestError] = useState<string | null>(null);

  const fetchProvider = async () => {
    try {
      setLoading(true);
      setError(null);
      const res = await providerService.get(id);
      setProvider(res);
      if (res.channel === "SMS" && (!testRecipient || testRecipient.includes("@"))) {
        setTestRecipient("5511999998888");
        setTestContent("BET CRM: Teste de conectividade do gateway SMS concluido com sucesso.");
      } else if (res.channel === "EMAIL" && (!testRecipient || !testRecipient.includes("@"))) {
        setTestRecipient("teste@crm.example.com");
      }
    } catch (err: any) {
      setError(err.message || "Provedor não encontrado.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (id) fetchProvider();
  }, [id]);

  const handleHealthCheck = async () => {
    try {
      setCheckingHealth(true);
      const res = await providerService.healthCheck(id);
      setHealthResult(res);
      fetchProvider();
    } catch (err: any) {
      setError(err.message || "Erro no health check.");
    } finally {
      setCheckingHealth(false);
    }
  };

  const handleSendTest = async (e: React.FormEvent) => {
    e.preventDefault();
    setTestError(null);
    setTestResult(null);

    if (!testRecipient) {
      setTestError("Informe o destinatário.");
      return;
    }

    try {
      setSendingTest(true);
      const res = await providerService.testSend(id, {
        recipient: testRecipient,
        subject: provider?.channel === "EMAIL" ? testSubject : undefined,
        html_content: provider?.channel === "EMAIL" ? testContent : undefined,
        sms_content: provider?.channel === "SMS" ? testContent : undefined,
      });
      setTestResult(res);
      fetchProvider();
    } catch (err: any) {
      setTestError(err.message || "Falha no envio de teste.");
    } finally {
      setSendingTest(false);
    }
  };

  if (loading) {
    return (
      <AppLayout title="Provedor" badge="Carregando">
        <div className="p-12 text-center text-slate-500 text-xs">
          <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2 text-emerald-500" />
          Carregando informações do provedor...
        </div>
      </AppLayout>
    );
  }

  if (!provider) {
    return (
      <AppLayout title="Provedor" badge="Não Encontrado">
        <div className="p-12 text-center text-slate-400 text-xs space-y-4">
          <p>O provedor solicitado não foi encontrado para esta plataforma.</p>
          <Link
            href="/providers"
            className="inline-flex items-center space-x-1.5 px-4 py-2 rounded-lg bg-slate-800 text-white text-xs hover:bg-slate-700 transition"
          >
            <ArrowLeft className="w-4 h-4" />
            <span>Voltar para Provedores</span>
          </Link>
        </div>
      </AppLayout>
    );
  }

  return (
    <AppLayout
      title={provider.name}
      badge={`Canal: ${provider.channel} • Driver: ${provider.driver.toUpperCase()}`}
      actions={
        <div className="flex items-center space-x-3">
          <Link
            href="/providers"
            className="flex items-center space-x-1.5 px-3 py-1.5 rounded-lg border border-slate-700 bg-slate-800 text-xs font-medium text-slate-200 hover:bg-slate-700 transition"
          >
            <ArrowLeft className="w-3.5 h-3.5" />
            <span>Voltar</span>
          </Link>
          <button
            onClick={handleHealthCheck}
            disabled={checkingHealth}
            className="flex items-center space-x-1.5 px-3 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-xs font-bold text-slate-950 transition disabled:opacity-50"
          >
            <Activity className={`w-3.5 h-3.5 ${checkingHealth ? "animate-spin" : ""}`} />
            <span>{checkingHealth ? "Verificando..." : "Testar Conexão"}</span>
          </button>
          <Link
            href={`/providers/${provider.id}/edit`}
            className="flex items-center space-x-1.5 px-3 py-1.5 rounded-lg border border-slate-700 bg-slate-800 text-xs font-medium text-white hover:bg-slate-700 transition"
          >
            <Edit className="w-3.5 h-3.5" />
            <span>Editar</span>
          </Link>
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

        {/* Health Check Result Banner */}
        {healthResult && (
          <div
            className={`p-4 rounded-xl border flex items-center justify-between text-xs ${
              healthResult.healthy
                ? "bg-emerald-500/10 border-emerald-500/30 text-emerald-300"
                : "bg-red-500/10 border-red-500/30 text-red-300"
            }`}
          >
            <div className="flex items-center space-x-3">
              {healthResult.healthy ? (
                <CheckCircle2 className="w-5 h-5 text-emerald-400 shrink-0" />
              ) : (
                <XCircle className="w-5 h-5 text-red-400 shrink-0" />
              )}
              <div>
                <p className="font-semibold text-white">
                  Health Check: {healthResult.healthy ? "ONLINE (HEALTHY)" : "FALHA DE CONECTIVIDADE"}
                </p>
                <p className="text-[11px] opacity-80">{healthResult.message}</p>
              </div>
            </div>
            <div className="text-right font-mono text-[11px]">
              <span>Latência: {healthResult.latency_ms}ms</span>
            </div>
          </div>
        )}

        {/* Overview Cards */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div className="bg-[#0f172a] border border-slate-800 rounded-xl p-4">
            <span className="text-[11px] font-medium text-slate-400">Canal & Driver</span>
            <div className="flex items-center space-x-2 mt-1">
              <span className="px-2 py-0.5 rounded text-xs font-bold bg-slate-800 text-white">
                {provider.channel}
              </span>
              <span className="text-xs font-mono uppercase text-emerald-400">{provider.driver}</span>
            </div>
          </div>

          <div className="bg-[#0f172a] border border-slate-800 rounded-xl p-4">
            <span className="text-[11px] font-medium text-slate-400">Prioridade & Limite</span>
            <p className="text-xs font-semibold text-white mt-1">
              #{provider.priority} • {provider.rate_limit_per_minute}/min
            </p>
          </div>

          <div className="bg-[#0f172a] border border-slate-800 rounded-xl p-4">
            <span className="text-[11px] font-medium text-slate-400">Segurança de Credenciais</span>
            <div className="flex items-center space-x-1.5 mt-1 text-xs">
              <Shield className="w-3.5 h-3.5 text-emerald-400" />
              <span className="text-emerald-400 font-medium">AES-256 Criptografada</span>
            </div>
          </div>

          <div className="bg-[#0f172a] border border-slate-800 rounded-xl p-4">
            <span className="text-[11px] font-medium text-slate-400">Status Operacional</span>
            <div className="flex items-center space-x-2 mt-1">
              <span
                className={`w-2 h-2 rounded-full ${
                  provider.status === "ACTIVE" ? "bg-emerald-400 animate-pulse" : "bg-slate-600"
                }`}
              />
              <span className="text-xs font-bold text-white">{provider.status}</span>
              {provider.is_default && (
                <span className="px-1.5 py-0.5 text-[9px] font-bold rounded bg-amber-500/20 text-amber-300">
                  PADRÃO
                </span>
              )}
            </div>
          </div>
        </div>

        {/* Test Dispatch Card & Config */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Quick Test Box */}
          <div className="bg-[#0f172a] border border-slate-800 rounded-xl p-6 space-y-4 shadow-xl">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-bold text-white flex items-center space-x-2">
                <Play className="w-4 h-4 text-emerald-400" />
                <span>Simulador / Disparo de Teste</span>
              </h3>
              <span className="text-[10px] text-slate-500 font-mono">Execução Síncrona</span>
            </div>

            {testError && (
              <div className="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-xs flex items-center space-x-2">
                <XCircle className="w-4 h-4 shrink-0" />
                <span>{testError}</span>
              </div>
            )}

            {testResult && (
              <div className="p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs space-y-1">
                <div className="flex items-center space-x-2 font-semibold text-emerald-400">
                  <CheckCircle2 className="w-4 h-4 shrink-0" />
                  <span>{testResult.message || "Disparo enviado com sucesso!"}</span>
                </div>
                {testResult.data && (
                  <p className="font-mono text-[10px] text-slate-400">
                    ID Externo: {testResult.data.provider_message_id || testResult.data.id} • Status: {testResult.data.status}
                  </p>
                )}
              </div>
            )}

            <form onSubmit={handleSendTest} className="space-y-4">
              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">
                  Destinatário ({provider.channel === "EMAIL" ? "E-mail" : "Telefone celular"}) *
                </label>
                <input
                  type={provider.channel === "EMAIL" ? "email" : "text"}
                  required
                  value={testRecipient}
                  onChange={(e) => setTestRecipient(e.target.value)}
                  placeholder={provider.channel === "EMAIL" ? "jogador@exemplo.com" : "5511999998888"}
                  className="w-full px-3.5 py-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-white focus:outline-none focus:border-emerald-500 font-mono"
                />
              </div>

              {provider.channel === "EMAIL" && (
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Assunto do E-mail</label>
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
                  {provider.channel === "EMAIL" ? "Conteúdo HTML" : "Texto da Mensagem SMS"}
                </label>
                <textarea
                  rows={provider.channel === "EMAIL" ? 3 : 2}
                  required
                  value={testContent}
                  onChange={(e) => setTestContent(e.target.value)}
                  className="w-full px-3.5 py-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-white focus:outline-none focus:border-emerald-500 font-mono"
                />
              </div>

              <button
                type="submit"
                disabled={sendingTest}
                className="w-full py-2 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold text-xs transition shadow-lg shadow-emerald-500/20 disabled:opacity-50 flex items-center justify-center space-x-2"
              >
                <Zap className={`w-3.5 h-3.5 ${sendingTest ? "animate-spin" : ""}`} />
                <span>{sendingTest ? "Enviando Disparo..." : "Enviar Disparo de Teste Agora"}</span>
              </button>
            </form>
          </div>

          {/* Configuration & Meta */}
          <div className="bg-[#0f172a] border border-slate-800 rounded-xl p-6 space-y-4 shadow-xl">
            <h3 className="text-sm font-bold text-white flex items-center space-x-2">
              <Server className="w-4 h-4 text-purple-400" />
              <span>Metadados & Roteamento</span>
            </h3>

            <div className="space-y-3 text-xs">
              <div className="flex justify-between py-2 border-b border-slate-800">
                <span className="text-slate-400">UUID Interno</span>
                <span className="font-mono text-slate-200">{provider.uuid}</span>
              </div>
              <div className="flex justify-between py-2 border-b border-slate-800">
                <span className="text-slate-400">Fallback de Circuito</span>
                <span className="text-slate-200">{provider.is_fallback ? "Sim" : "Não"}</span>
              </div>
              <div className="flex justify-between py-2 border-b border-slate-800">
                <span className="text-slate-400">Última Checagem de Saúde</span>
                <span className="text-slate-200 font-mono">
                  {provider.last_health_check_at
                    ? new Date(provider.last_health_check_at).toLocaleString()
                    : "Nunca"}
                </span>
              </div>
              <div className="flex justify-between py-2 border-b border-slate-800">
                <span className="text-slate-400">Data de Criação</span>
                <span className="text-slate-200">{new Date(provider.created_at).toLocaleDateString()}</span>
              </div>

              {provider.configuration && Object.keys(provider.configuration).length > 0 && (
                <div className="pt-2">
                  <span className="text-[11px] font-medium text-slate-400 block mb-1">Configurações Adicionais</span>
                  <pre className="p-3 rounded-lg bg-slate-900 border border-slate-800 font-mono text-[11px] text-slate-300 overflow-x-auto">
                    {JSON.stringify(provider.configuration, null, 2)}
                  </pre>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Logs Table */}
        <div className="bg-[#0f172a] border border-slate-800 rounded-xl overflow-hidden shadow-xl">
          <div className="p-4 border-b border-slate-800 flex items-center justify-between">
            <h3 className="text-xs font-bold text-white uppercase tracking-wider flex items-center space-x-2">
              <Clock className="w-4 h-4 text-slate-400" />
              <span>Histórico de Logs & Auditoria de Comunicação</span>
            </h3>
            <span className="text-[11px] text-slate-500">Zero segredos persistidos</span>
          </div>

          {!provider.logs || provider.logs.length === 0 ? (
            <div className="p-8 text-center text-slate-500 text-xs">
              Nenhum log registrado para este provedor até o momento.
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs text-slate-300">
                <thead className="bg-slate-900 text-[10px] text-slate-400 uppercase tracking-wider border-b border-slate-800">
                  <tr>
                    <th className="py-2.5 px-4">Ação</th>
                    <th className="py-2.5 px-4">Status</th>
                    <th className="py-2.5 px-4">Código HTTP</th>
                    <th className="py-2.5 px-4">Latência</th>
                    <th className="py-2.5 px-4">Data / Hora</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800/60 font-mono text-[11px]">
                  {provider.logs.map((log) => (
                    <tr key={log.id} className="hover:bg-slate-800/30">
                      <td className="py-2.5 px-4 font-sans font-medium text-slate-200">{log.action}</td>
                      <td className="py-2.5 px-4">
                        <span
                          className={`px-2 py-0.5 rounded text-[10px] font-bold ${
                            log.status === "SUCCESS"
                              ? "bg-emerald-500/10 text-emerald-400 border border-emerald-500/20"
                              : "bg-red-500/10 text-red-400 border border-red-500/20"
                          }`}
                        >
                          {log.status}
                        </span>
                      </td>
                      <td className="py-2.5 px-4 text-slate-400">{log.response_code || "-"}</td>
                      <td className="py-2.5 px-4 text-slate-400">{log.latency_ms ? `${log.latency_ms}ms` : "-"}</td>
                      <td className="py-2.5 px-4 text-slate-500">
                        {new Date(log.created_at).toLocaleString()}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </AppLayout>
  );
}
