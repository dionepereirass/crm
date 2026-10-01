"use client";

import React, { useEffect, useState } from "react";
import { useParams, useRouter } from "next/navigation";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import { providerService, Provider } from "@/services/provider-service";
import {
  ArrowLeft,
  Shield,
  Key,
  Sliders,
  CheckCircle2,
  XCircle,
  RefreshCw,
} from "lucide-react";

export default function EditProviderPage() {
  const params = useParams();
  const router = useRouter();
  const id = params?.id as string;

  const [provider, setProvider] = useState<Provider | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const [name, setName] = useState("");
  const [priority, setPriority] = useState(10);
  const [rateLimit, setRateLimit] = useState(120);
  const [isDefault, setIsDefault] = useState(false);
  const [isFallback, setIsFallback] = useState(false);
  const [status, setStatus] = useState<"ACTIVE" | "INACTIVE">("ACTIVE");

  // Credential rotation
  const [apiKey, setApiKey] = useState("");
  const [apiToken, setApiToken] = useState("");
  const [senderName, setSenderName] = useState("");
  const [senderEmail, setSenderEmail] = useState("");
  const [senderId, setSenderId] = useState("");

  useEffect(() => {
    async function load() {
      try {
        setLoading(true);
        const res = await providerService.get(id);
        setProvider(res);
        setName(res.name);
        setPriority(res.priority);
        setRateLimit(res.rate_limit_per_minute);
        setIsDefault(res.is_default);
        setIsFallback(res.is_fallback);
        setStatus(res.status === "ACTIVE" ? "ACTIVE" : "INACTIVE");

        if (res.configuration?.sender_name) setSenderName(res.configuration.sender_name);
        if (res.configuration?.sender_email) setSenderEmail(res.configuration.sender_email);
        if (res.configuration?.sender_id) setSenderId(res.configuration.sender_id);
      } catch (err: any) {
        setError(err.message || "Erro ao carregar dados do provedor.");
      } finally {
        setLoading(false);
      }
    }
    if (id) load();
  }, [id]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setSuccess(null);

    try {
      setSaving(true);
      const configuration: Record<string, any> = { ...provider?.configuration };
      const credentials: Record<string, string> = {};

      if (provider?.driver === "brevo") {
        if (apiKey.trim()) credentials.api_key = apiKey.trim();
        if (senderName) configuration.sender_name = senderName;
        if (senderEmail) configuration.sender_email = senderEmail;
      } else if (provider?.driver === "zenvia") {
        if (apiToken.trim()) credentials.api_token = apiToken.trim();
        if (senderId) configuration.sender_id = senderId;
      }

      await providerService.update(id, {
        name,
        priority: Number(priority),
        rate_limit_per_minute: Number(rateLimit),
        is_default: Boolean(isDefault),
        is_fallback: Boolean(isFallback),
        status,
        configuration,
        credentials: Object.keys(credentials).length > 0 ? credentials : undefined,
        api_key: apiKey.trim() || undefined,
        api_token: apiToken.trim() || undefined,
      });

      setSuccess("Provedor atualizado com sucesso!");
      setTimeout(() => {
        router.push(`/providers/${id}`);
      }, 1000);
    } catch (err: any) {
      setError(err.message || "Falha ao atualizar provedor.");
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <AppLayout title="Editar Provedor" badge="Carregando">
        <div className="p-12 text-center text-slate-500 text-xs">
          <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2 text-emerald-500" />
          Carregando dados...
        </div>
      </AppLayout>
    );
  }

  return (
    <AppLayout
      title={`Editar Provedor: ${name}`}
      badge="Atualização de Gateway"
      actions={
        <Link
          href={`/providers/${id}`}
          className="flex items-center space-x-1.5 px-3 py-1.5 rounded-lg border border-slate-700 bg-slate-800 text-xs font-medium text-slate-200 hover:bg-slate-700 transition"
        >
          <ArrowLeft className="w-3.5 h-3.5" />
          <span>Voltar</span>
        </Link>
      }
    >
      <div className="p-8 max-w-4xl mx-auto space-y-6">
        {error && (
          <div className="p-4 rounded-xl bg-red-500/10 border border-red-500/30 flex items-center space-x-3 text-red-400 text-xs">
            <XCircle className="w-4 h-4 shrink-0" />
            <span>{error}</span>
          </div>
        )}

        {success && (
          <div className="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center space-x-3 text-emerald-400 text-xs">
            <CheckCircle2 className="w-4 h-4 shrink-0" />
            <span>{success}</span>
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="bg-[#0f172a] border border-slate-800 rounded-xl p-6 space-y-4 shadow-xl">
            <h3 className="text-sm font-bold text-white flex items-center space-x-2">
              <Sliders className="w-4 h-4 text-emerald-400" />
              <span>Configurações Gerais</span>
            </h3>

            <div>
              <label className="block text-xs font-medium text-slate-300 mb-1">Nome do Provedor *</label>
              <input
                type="text"
                required
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="w-full px-3.5 py-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-white focus:outline-none focus:border-emerald-500"
              />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">Prioridade</label>
                <input
                  type="number"
                  min={1}
                  max={999}
                  value={priority}
                  onChange={(e) => setPriority(Number(e.target.value))}
                  className="w-full px-3.5 py-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-white focus:outline-none focus:border-emerald-500"
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">Limite por Minuto</label>
                <input
                  type="number"
                  min={1}
                  max={10000}
                  value={rateLimit}
                  onChange={(e) => setRateLimit(Number(e.target.value))}
                  className="w-full px-3.5 py-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-white focus:outline-none focus:border-emerald-500"
                />
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
              <label className="flex items-center space-x-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={isDefault}
                  onChange={(e) => setIsDefault(e.target.checked)}
                  className="rounded bg-slate-900 border-slate-700 text-emerald-500"
                />
                <span className="text-xs text-slate-300">Provedor Padrão</span>
              </label>

              <label className="flex items-center space-x-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={isFallback}
                  onChange={(e) => setIsFallback(e.target.checked)}
                  className="rounded bg-slate-900 border-slate-700 text-emerald-500"
                />
                <span className="text-xs text-slate-300">Fallback de Circuito</span>
              </label>

              <label className="flex items-center space-x-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={status === "ACTIVE"}
                  onChange={(e) => setStatus(e.target.checked ? "ACTIVE" : "INACTIVE")}
                  className="rounded bg-slate-900 border-slate-700 text-emerald-500"
                />
                <span className="text-xs text-slate-300">Ativo</span>
              </label>
            </div>
          </div>

          {/* Credential Rotation Card */}
          <div className="bg-[#0f172a] border border-slate-800 rounded-xl p-6 space-y-4 shadow-xl">
            <h3 className="text-sm font-bold text-white flex items-center space-x-2">
              <Key className="w-4 h-4 text-emerald-400" />
              <span>Rotação de Credenciais & Segredos</span>
            </h3>

            <p className="text-[11px] text-slate-400">
              Por segurança, a chave criptografada atual não é exibida. Para manter a chave salva anteriormente, deixe o campo de senha em branco.
            </p>

            {provider?.driver === "brevo" && (
              <div className="space-y-4">
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Nova API Key Brevo</label>
                  <input
                    type="password"
                    value={apiKey}
                    onChange={(e) => setApiKey(e.target.value)}
                    placeholder="Deixe em branco para não alterar"
                    className="w-full px-3.5 py-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-white focus:outline-none focus:border-emerald-500 font-mono"
                  />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-medium text-slate-300 mb-1">Nome do Remetente</label>
                    <input
                      type="text"
                      value={senderName}
                      onChange={(e) => setSenderName(e.target.value)}
                      className="w-full px-3.5 py-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-white focus:outline-none focus:border-emerald-500"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-medium text-slate-300 mb-1">E-mail do Remetente</label>
                    <input
                      type="email"
                      value={senderEmail}
                      onChange={(e) => setSenderEmail(e.target.value)}
                      className="w-full px-3.5 py-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-white focus:outline-none focus:border-emerald-500"
                    />
                  </div>
                </div>
              </div>
            )}

            {provider?.driver === "zenvia" && (
              <div className="space-y-4">
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Novo Token Zenvia</label>
                  <input
                    type="password"
                    value={apiToken}
                    onChange={(e) => setApiToken(e.target.value)}
                    placeholder="Deixe em branco para não alterar"
                    className="w-full px-3.5 py-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-white focus:outline-none focus:border-emerald-500 font-mono"
                  />
                </div>

                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Sender ID</label>
                  <input
                    type="text"
                    value={senderId}
                    onChange={(e) => setSenderId(e.target.value)}
                    className="w-full px-3.5 py-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-white focus:outline-none focus:border-emerald-500"
                  />
                </div>
              </div>
            )}
          </div>

          <div className="flex items-center justify-end space-x-3">
            <Link
              href={`/providers/${id}`}
              className="px-4 py-2 rounded-lg border border-slate-800 text-xs font-medium text-slate-400 hover:text-white transition"
            >
              Cancelar
            </Link>
            <button
              type="submit"
              disabled={saving}
              className="px-5 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold text-xs transition shadow-lg shadow-emerald-500/20 disabled:opacity-50"
            >
              {saving ? "Salvando..." : "Salvar Alterações"}
            </button>
          </div>
        </form>
      </div>
    </AppLayout>
  );
}
