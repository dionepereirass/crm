"use client";

import React, { useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import { providerService } from "@/services/provider-service";
import {
  Globe,
  Mail,
  MessageSquare,
  Shield,
  ArrowLeft,
  CheckCircle2,
  XCircle,
  Key,
  Sliders,
  Info,
} from "lucide-react";

export default function NewProviderPage() {
  const router = useRouter();

  const [channel, setChannel] = useState<"EMAIL" | "SMS">("EMAIL");
  const [driver, setDriver] = useState<string>("brevo");
  const [name, setName] = useState<string>("");
  const [priority, setPriority] = useState<number>(10);
  const [rateLimit, setRateLimit] = useState<number>(120);
  const [isDefault, setIsDefault] = useState<boolean>(false);
  const [isFallback, setIsFallback] = useState<boolean>(false);
  const [status, setStatus] = useState<"ACTIVE" | "INACTIVE">("ACTIVE");

  // Brevo credentials
  const [brevoApiKey, setBrevoApiKey] = useState("");
  const [brevoSenderName, setBrevoSenderName] = useState("");
  const [brevoSenderEmail, setBrevoSenderEmail] = useState("");

  // Zenvia credentials
  const [zenviaToken, setZenviaToken] = useState("");
  const [zenviaSenderId, setZenviaSenderId] = useState("");

  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // When channel changes, adjust driver
  const handleChannelChange = (newChannel: "EMAIL" | "SMS") => {
    setChannel(newChannel);
    if (newChannel === "EMAIL") {
      setDriver("brevo");
      if (!name || name === "Zenvia SMS Principal" || name === "Fake SMS Dev") {
        setName("Brevo E-mail Principal");
      }
    } else {
      setDriver("zenvia");
      if (!name || name === "Brevo E-mail Principal" || name === "Fake E-mail Dev") {
        setName("Zenvia SMS Principal");
      }
    }
  };

  const handleDriverChange = (newDriver: string) => {
    setDriver(newDriver);
    if (newDriver === "fake_email") {
      setName("Fake E-mail Dev");
    } else if (newDriver === "fake_sms") {
      setName("Fake SMS Dev");
    } else if (newDriver === "brevo") {
      setName("Brevo E-mail Principal");
    } else if (newDriver === "zenvia") {
      setName("Zenvia SMS Principal");
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    if (!name.trim()) {
      setError("Por favor, preencha o nome do provedor.");
      return;
    }

    try {
      setSaving(true);

      const configuration: Record<string, any> = {};
      const credentials: Record<string, string> = {};

      if (driver === "brevo") {
        if (brevoApiKey) credentials.api_key = brevoApiKey;
        if (brevoSenderName) configuration.sender_name = brevoSenderName;
        if (brevoSenderEmail) configuration.sender_email = brevoSenderEmail;
      } else if (driver === "zenvia") {
        if (zenviaToken) credentials.api_token = zenviaToken;
        if (zenviaSenderId) configuration.sender_id = zenviaSenderId;
      }

      await providerService.create({
        name,
        channel,
        driver,
        status,
        priority: Number(priority),
        rate_limit_per_minute: Number(rateLimit),
        is_default: Boolean(isDefault),
        is_fallback: Boolean(isFallback),
        configuration,
        credentials: Object.keys(credentials).length > 0 ? credentials : undefined,
        api_key: driver === "brevo" ? brevoApiKey : undefined,
        api_token: driver === "zenvia" ? zenviaToken : undefined,
      });

      router.push("/providers");
    } catch (err: any) {
      setError(err.message || "Erro ao salvar provedor.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <AppLayout
      title="Novo Provedor"
      badge="Configuração de Gateway"
      actions={
        <Link
          href="/providers"
          className="flex items-center space-x-1.5 px-3 py-1.5 rounded-lg border border-slate-700 bg-slate-800 text-xs font-medium text-slate-200 hover:bg-slate-700 transition"
        >
          <ArrowLeft className="w-3.5 h-3.5" />
          <span>Voltar para Lista</span>
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

        {/* Security Notice */}
        <div className="p-4 rounded-xl bg-emerald-500/5 border border-emerald-500/20 flex items-start space-x-3 text-xs text-slate-300">
          <Shield className="w-5 h-5 text-emerald-400 shrink-0 mt-0.5" />
          <div>
            <h4 className="font-semibold text-white">Isolamento Multi-Tenant & Criptografia Segura</h4>
            <p className="text-[11px] text-slate-400 mt-0.5">
              As credenciais (API Keys e Tokens) são criptografadas com chave AES-256-GCM antes de serem gravadas no banco de dados da plataforma ativa. Nenhuma chave secreta é exposta em endpoints, logs ou respostas JSON.
            </p>
          </div>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Main Configuration Card */}
          <div className="bg-[#0f172a] border border-slate-800 rounded-xl p-6 space-y-6 shadow-xl">
            <h3 className="text-sm font-bold text-white flex items-center space-x-2">
              <Sliders className="w-4 h-4 text-emerald-400" />
              <span>Dados do Gateway</span>
            </h3>

            {/* Channel Selection */}
            <div>
              <label className="block text-xs font-medium text-slate-300 mb-2">Canal de Comunicação</label>
              <div className="grid grid-cols-2 gap-4">
                <button
                  type="button"
                  onClick={() => handleChannelChange("EMAIL")}
                  className={`p-4 rounded-xl border flex items-center space-x-3 transition text-left ${
                    channel === "EMAIL"
                      ? "bg-purple-500/10 border-purple-500/40 text-purple-200"
                      : "bg-slate-900 border-slate-800 text-slate-400 hover:border-slate-700"
                  }`}
                >
                  <Mail className="w-5 h-5 text-purple-400" />
                  <div>
                    <p className="font-semibold text-xs text-white">E-mail</p>
                    <p className="text-[11px] text-slate-400">Brevo (Sendinblue), Fake Provider</p>
                  </div>
                </button>

                <button
                  type="button"
                  onClick={() => handleChannelChange("SMS")}
                  className={`p-4 rounded-xl border flex items-center space-x-3 transition text-left ${
                    channel === "SMS"
                      ? "bg-emerald-500/10 border-emerald-500/40 text-emerald-200"
                      : "bg-slate-900 border-slate-800 text-slate-400 hover:border-slate-700"
                  }`}
                >
                  <MessageSquare className="w-5 h-5 text-emerald-400" />
                  <div>
                    <p className="font-semibold text-xs text-white">SMS</p>
                    <p className="text-[11px] text-slate-400">Zenvia v2 API, Fake Provider</p>
                  </div>
                </button>
              </div>
            </div>

            {/* Driver Selection */}
            <div>
              <label className="block text-xs font-medium text-slate-300 mb-2">Driver de Integração</label>
              <div className="grid grid-cols-2 gap-3">
                {channel === "EMAIL" ? (
                  <>
                    <button
                      type="button"
                      onClick={() => handleDriverChange("brevo")}
                      className={`p-3 rounded-lg border text-left transition ${
                        driver === "brevo"
                          ? "bg-emerald-500/10 border-emerald-500 text-white font-semibold"
                          : "bg-slate-900 border-slate-800 text-slate-400 hover:border-slate-700"
                      }`}
                    >
                      <span className="text-xs">Brevo (Sendinblue API v3)</span>
                      <span className="block text-[10px] text-slate-500">Transacional oficial via API HTTP</span>
                    </button>

                    <button
                      type="button"
                      onClick={() => handleDriverChange("fake_email")}
                      className={`p-3 rounded-lg border text-left transition ${
                        driver === "fake_email"
                          ? "bg-emerald-500/10 border-emerald-500 text-white font-semibold"
                          : "bg-slate-900 border-slate-800 text-slate-400 hover:border-slate-700"
                      }`}
                    >
                      <span className="text-xs">Fake Email Driver (Dev/Test)</span>
                      <span className="block text-[10px] text-slate-500">Simulação local sem custo</span>
                    </button>
                  </>
                ) : (
                  <>
                    <button
                      type="button"
                      onClick={() => handleDriverChange("zenvia")}
                      className={`p-3 rounded-lg border text-left transition ${
                        driver === "zenvia"
                          ? "bg-emerald-500/10 border-emerald-500 text-white font-semibold"
                          : "bg-slate-900 border-slate-800 text-slate-400 hover:border-slate-700"
                      }`}
                    >
                      <span className="text-xs">Zenvia SMS (API v2)</span>
                      <span className="block text-[10px] text-slate-500">Mensageria SMS corporativa Brasil</span>
                    </button>

                    <button
                      type="button"
                      onClick={() => handleDriverChange("fake_sms")}
                      className={`p-3 rounded-lg border text-left transition ${
                        driver === "fake_sms"
                          ? "bg-emerald-500/10 border-emerald-500 text-white font-semibold"
                          : "bg-slate-900 border-slate-800 text-slate-400 hover:border-slate-700"
                      }`}
                    >
                      <span className="text-xs">Fake SMS Driver (Dev/Test)</span>
                      <span className="block text-[10px] text-slate-500">Simulação local sem custo</span>
                    </button>
                  </>
                )}
              </div>
            </div>

            {/* Name */}
            <div>
              <label className="block text-xs font-medium text-slate-300 mb-1">Nome do Provedor *</label>
              <input
                type="text"
                required
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Ex: Brevo Principal Transacional"
                className="w-full px-3.5 py-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-white focus:outline-none focus:border-emerald-500"
              />
            </div>

            {/* Priority & Rate Limit */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">Prioridade de Roteamento</label>
                <input
                  type="number"
                  min={1}
                  max={999}
                  value={priority}
                  onChange={(e) => setPriority(Number(e.target.value))}
                  className="w-full px-3.5 py-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-white focus:outline-none focus:border-emerald-500"
                />
                <p className="text-[10px] text-slate-500 mt-1">Quanto menor o número, maior a preferência no envio.</p>
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">Limite de Disparos por Minuto</label>
                <input
                  type="number"
                  min={1}
                  max={10000}
                  value={rateLimit}
                  onChange={(e) => setRateLimit(Number(e.target.value))}
                  className="w-full px-3.5 py-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-white focus:outline-none focus:border-emerald-500"
                />
                <p className="text-[10px] text-slate-500 mt-1">Sliding-window Redis rate limiter contra bloqueios de gateway.</p>
              </div>
            </div>

            {/* Checkboxes */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
              <label className="flex items-center space-x-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={isDefault}
                  onChange={(e) => setIsDefault(e.target.checked)}
                  className="rounded bg-slate-900 border-slate-700 text-emerald-500 focus:ring-emerald-500"
                />
                <span className="text-xs text-slate-300">Provedor Padrão</span>
              </label>

              <label className="flex items-center space-x-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={isFallback}
                  onChange={(e) => setIsFallback(e.target.checked)}
                  className="rounded bg-slate-900 border-slate-700 text-emerald-500 focus:ring-emerald-500"
                />
                <span className="text-xs text-slate-300">Fallback de Circuito</span>
              </label>

              <label className="flex items-center space-x-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={status === "ACTIVE"}
                  onChange={(e) => setStatus(e.target.checked ? "ACTIVE" : "INACTIVE")}
                  className="rounded bg-slate-900 border-slate-700 text-emerald-500 focus:ring-emerald-500"
                />
                <span className="text-xs text-slate-300">Ativo para Disparos</span>
              </label>
            </div>
          </div>

          {/* Credentials Card */}
          <div className="bg-[#0f172a] border border-slate-800 rounded-xl p-6 space-y-4 shadow-xl">
            <h3 className="text-sm font-bold text-white flex items-center space-x-2">
              <Key className="w-4 h-4 text-emerald-400" />
              <span>Autenticação & Credenciais Criptografadas</span>
            </h3>

            {driver === "brevo" && (
              <div className="space-y-4">
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">
                    API Key da Brevo (xkeysib-...) *
                  </label>
                  <input
                    type="password"
                    value={brevoApiKey}
                    onChange={(e) => setBrevoApiKey(e.target.value)}
                    placeholder="Cole sua chave da Brevo"
                    className="w-full px-3.5 py-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-white focus:outline-none focus:border-emerald-500 font-mono"
                  />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-medium text-slate-300 mb-1">Nome do Remetente Padrão</label>
                    <input
                      type="text"
                      value={brevoSenderName}
                      onChange={(e) => setBrevoSenderName(e.target.value)}
                      placeholder="Ex: BET CRM Notificações"
                      className="w-full px-3.5 py-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-white focus:outline-none focus:border-emerald-500"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-medium text-slate-300 mb-1">E-mail do Remetente Padrão</label>
                    <input
                      type="email"
                      value={brevoSenderEmail}
                      onChange={(e) => setBrevoSenderEmail(e.target.value)}
                      placeholder="Ex: noreply@betcrm.com"
                      className="w-full px-3.5 py-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-white focus:outline-none focus:border-emerald-500"
                    />
                  </div>
                </div>
              </div>
            )}

            {driver === "zenvia" && (
              <div className="space-y-4">
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">
                    Token da API Zenvia (X-API-TOKEN) *
                  </label>
                  <input
                    type="password"
                    value={zenviaToken}
                    onChange={(e) => setZenviaToken(e.target.value)}
                    placeholder="Cole seu token Zenvia"
                    className="w-full px-3.5 py-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-white focus:outline-none focus:border-emerald-500 font-mono"
                  />
                </div>

                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">Identificador / Sender ID</label>
                  <input
                    type="text"
                    value={zenviaSenderId}
                    onChange={(e) => setZenviaSenderId(e.target.value)}
                    placeholder="Ex: BETCRM ou número curto cadastrado"
                    className="w-full px-3.5 py-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-white focus:outline-none focus:border-emerald-500"
                  />
                </div>
              </div>
            )}

            {(driver === "fake_email" || driver === "fake_sms") && (
              <div className="p-4 rounded-xl bg-slate-900 border border-slate-800 text-xs text-slate-400 space-y-1">
                <p className="font-semibold text-slate-300 flex items-center space-x-1.5">
                  <Info className="w-4 h-4 text-blue-400" />
                  <span>Modo Simulado / Não requer credenciais externas</span>
                </p>
                <p className="text-[11px] text-slate-500">
                  Os disparos serão simulados com sucesso em ambiente seguro sem envio para operadoras reais ou cobrança financeira.
                </p>
              </div>
            )}
          </div>

          {/* Action buttons */}
          <div className="flex items-center justify-end space-x-3">
            <Link
              href="/providers"
              className="px-4 py-2 rounded-lg border border-slate-800 text-xs font-medium text-slate-400 hover:text-white transition"
            >
              Cancelar
            </Link>
            <button
              type="submit"
              disabled={saving}
              className="px-5 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold text-xs transition shadow-lg shadow-emerald-500/20 disabled:opacity-50"
            >
              {saving ? "Salvando Provedor..." : "Salvar Provedor"}
            </button>
          </div>
        </form>
      </div>
    </AppLayout>
  );
}
