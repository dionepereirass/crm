"use client";

import React, { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import {
  templateService,
  CreateTemplatePayload,
} from "@/services/template-service";
import { VariablePickerModal } from "@/components/templates/variable-picker-modal";
import { SmsMetricsBadge } from "@/components/templates/sms-metrics-badge";
import { EmailPreviewPane } from "@/components/templates/email-preview-pane";
import {
  Mail,
  MessageSquare,
  ArrowLeft,
  Save,
  Tag,
  Eye,
  AlertCircle,
  HelpCircle,
} from "lucide-react";

export default function NewTemplatePage() {
  const router = useRouter();

  // Form State
  const [channel, setChannel] = useState<"EMAIL" | "SMS">("EMAIL");
  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [category, setCategory] = useState("WELCOME");
  const [status, setStatus] = useState<"DRAFT" | "ACTIVE">("ACTIVE");

  // Email Fields
  const [subject, setSubject] = useState("");
  const [preheader, setPreheader] = useState("");
  const [htmlContent, setHtmlContent] = useState("");
  const [textContent, setTextContent] = useState("");

  // SMS Fields
  const [smsContent, setSmsContent] = useState("");

  // UI State
  const [categories, setCategories] = useState<string[]>([]);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Variable Picker Modal State
  const [isVarPickerOpen, setIsVarPickerOpen] = useState(false);
  const [activeTargetField, setActiveTargetField] = useState<
    "subject" | "preheader" | "html" | "text" | "sms"
  >("html");

  useEffect(() => {
    templateService
      .getCategories()
      .then((cats) => setCategories(cats))
      .catch(() => {});
  }, []);

  // Starter templates for quick onboarding
  useEffect(() => {
    if (channel === "EMAIL" && !name && !htmlContent) {
      setSubject("Olá {{player.first_name}}, bem-vindo à {{platform.name}}!");
      setPreheader("Seu bônus exclusivo de primeiro depósito já está disponível.");
      setHtmlContent(
        `<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; background-color: #ffffff; color: #1e293b; border-radius: 8px;">\n  <h1 style="color: #059669; font-size: 24px; margin-bottom: 16px;">Bem-vindo à {{platform.name}}, {{player.first_name}}!</h1>\n  <p style="font-size: 14px; line-height: 1.6; margin-bottom: 20px;">Estamos muito felizes em ter você aqui conosco. Sua conta com o ID <strong>{{player.external_id}}</strong> está pronta para ser utilizada.</p>\n  <div style="background-color: #f1f5f9; padding: 16px; border-radius: 6px; margin-bottom: 24px;">\n    <p style="margin: 0; font-size: 13px; color: #475569;">Saldo de depósitos: <strong>{{player.deposit.total|default:"R$ 0,00"}}</strong></p>\n  </div>\n  <p style="text-align: center; margin-top: 30px;">\n    <a href="#" style="background-color: #059669; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: bold; display: inline-block;">Começar a Jogar</a>\n  </p>\n</div>`
      );
      setTextContent(
        "Olá {{player.first_name}}, bem-vindo à {{platform.name}}! Sua conta {{player.external_id}} está pronta."
      );
    } else if (channel === "SMS" && !smsContent) {
      setSmsContent(
        "Ola {{player.first_name|default:\"Apostador\"}}, seu deposito foi confirmado na {{platform.name}}! Acesse agora para aproveitar."
      );
    }
  }, [channel]);

  const openVariablePickerFor = (
    field: "subject" | "preheader" | "html" | "text" | "sms"
  ) => {
    setActiveTargetField(field);
    setIsVarPickerOpen(true);
  };

  const handleInsertVariable = (tag: string) => {
    if (activeTargetField === "subject") {
      setSubject((prev) => prev + " " + tag);
    } else if (activeTargetField === "preheader") {
      setPreheader((prev) => prev + " " + tag);
    } else if (activeTargetField === "html") {
      setHtmlContent((prev) => prev + tag);
    } else if (activeTargetField === "text") {
      setTextContent((prev) => prev + " " + tag);
    } else if (activeTargetField === "sms") {
      setSmsContent((prev) => prev + " " + tag);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) {
      setError("O nome do template é obrigatório.");
      return;
    }

    setSaving(true);
    setError(null);

    try {
      const payload: CreateTemplatePayload = {
        name,
        description,
        channel,
        category,
        status,
        ...(channel === "EMAIL"
          ? {
              subject,
              preheader,
              html_content: htmlContent,
              text_content: textContent,
            }
          : {
              sms_content: smsContent,
            }),
      };

      const created = await templateService.createTemplate(payload);
      router.push(`/templates/${created.id}`);
    } catch (err: any) {
      setError(err.message || "Erro ao salvar template.");
      setSaving(false);
    }
  };

  // Preview replacement on client for instant feedback
  const previewContext: Record<string, string> = {
    "player.name": "Carlos Eduardo Santos",
    "player.first_name": "Carlos",
    "player.email": "carlos.santos@email.com",
    "player.phone": "5531998877661",
    "player.city": "Belo Horizonte",
    "player.state": "MG",
    "player.external_id": "PLY-1001",
    "player.deposit.total": "R$ 1.500,00",
    "player.deposit.last_amount": "R$ 150,00",
    "player.bet.total": "R$ 3.200,00",
    "platform.name": "Bet Brasil",
    "platform.slug": "bet-brasil",
  };

  const renderSimulated = (text: string) => {
    if (!text) return "";
    return text.replace(
      /\{\{\s*([a-zA-Z0-9_\.]+)(?:\s*\|\s*default\s*:\s*["']([^"']*)["'])?\s*\}\}/g,
      (_, key, fallback) => {
        return previewContext[key] ?? fallback ?? `[${key}]`;
      }
    );
  };

  return (
    <AppLayout
      title="Novo Template de Comunicação"
      badge="FASE 6"
      actions={
        <div className="flex items-center space-x-2">
          <Link
            href="/templates"
            className="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-xs flex items-center space-x-1.5 transition"
          >
            <ArrowLeft className="w-3.5 h-3.5" />
            <span>Voltar</span>
          </Link>
          <button
            onClick={handleSubmit}
            disabled={saving}
            className="px-4 py-1.5 bg-emerald-500 hover:bg-emerald-400 disabled:opacity-50 text-slate-950 font-semibold rounded-lg text-xs flex items-center space-x-1.5 shadow-md shadow-emerald-500/20 transition"
          >
            <Save className="w-3.5 h-3.5" />
            <span>{saving ? "Salvando..." : "Salvar Template"}</span>
          </button>
        </div>
      }
    >
      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Error Alert */}
        {error && (
          <div className="bg-rose-500/10 border border-rose-500/30 text-rose-300 p-3 rounded-lg text-xs flex items-center justify-between">
            <div className="flex items-center space-x-2">
              <AlertCircle className="w-4 h-4 shrink-0" />
              <span>{error}</span>
            </div>
            <button
              type="button"
              onClick={() => setError(null)}
              className="text-rose-400 hover:text-rose-200 font-bold ml-2"
            >
              ×
            </button>
          </div>
        )}

        {/* Channel & Basic Info Card */}
        <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 space-y-4">
          <h3 className="text-xs font-semibold text-white uppercase tracking-wider">
            1. Definição do Canal & Metadados
          </h3>

          {/* Channel Selector */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div
              onClick={() => setChannel("EMAIL")}
              className={`p-4 rounded-xl border cursor-pointer transition flex items-start space-x-3 ${
                channel === "EMAIL"
                  ? "bg-blue-950/40 border-blue-500/60 ring-1 ring-blue-500/40"
                  : "bg-slate-950/60 border-slate-800 hover:border-slate-700"
              }`}
            >
              <div
                className={`p-2.5 rounded-lg ${
                  channel === "EMAIL"
                    ? "bg-blue-500/20 text-blue-400"
                    : "bg-slate-800 text-slate-400"
                }`}
              >
                <Mail className="w-5 h-5" />
              </div>
              <div className="space-y-1">
                <div className="flex items-center space-x-2">
                  <span className="text-sm font-semibold text-white">
                    Template de E-mail
                  </span>
                  {channel === "EMAIL" && (
                    <span className="text-[10px] font-bold px-1.5 py-0.5 rounded bg-blue-500/20 text-blue-400">
                      SELECIONADO
                    </span>
                  )}
                </div>
                <p className="text-xs text-slate-400">
                  Comunicação rica em HTML, suporte a tabelas, imagens,
                  pré-cabeçalho e versão texto alternativo.
                </p>
              </div>
            </div>

            <div
              onClick={() => setChannel("SMS")}
              className={`p-4 rounded-xl border cursor-pointer transition flex items-start space-x-3 ${
                channel === "SMS"
                  ? "bg-emerald-950/40 border-emerald-500/60 ring-1 ring-emerald-500/40"
                  : "bg-slate-950/60 border-slate-800 hover:border-slate-700"
              }`}
            >
              <div
                className={`p-2.5 rounded-lg ${
                  channel === "SMS"
                    ? "bg-emerald-500/20 text-emerald-400"
                    : "bg-slate-800 text-slate-400"
                }`}
              >
                <MessageSquare className="w-5 h-5" />
              </div>
              <div className="space-y-1">
                <div className="flex items-center space-x-2">
                  <span className="text-sm font-semibold text-white">
                    Template de SMS
                  </span>
                  {channel === "SMS" && (
                    <span className="text-[10px] font-bold px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-400">
                      SELECIONADO
                    </span>
                  )}
                </div>
                <p className="text-xs text-slate-400">
                  Mensagens instantâneas com contagem de caracteres em tempo
                  real, codificação GSM-7 / Unicode e segmentação automática.
                </p>
              </div>
            </div>
          </div>

          {/* Form Inputs Grid */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
            <div className="space-y-1">
              <label className="text-xs font-semibold text-slate-300">
                Nome do Template *
              </label>
              <input
                type="text"
                placeholder="Ex: Boas-vindas VIP Primeiro Depósito"
                value={name}
                onChange={(e) => setName(e.target.value)}
                required
                className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-emerald-500"
              />
            </div>

            <div className="space-y-1">
              <label className="text-xs font-semibold text-slate-300">
                Categoria
              </label>
              <select
                value={category}
                onChange={(e) => setCategory(e.target.value)}
                className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-emerald-500"
              >
                {categories.map((c) => (
                  <option key={c} value={c}>
                    {c}
                  </option>
                ))}
              </select>
            </div>

            <div className="space-y-1">
              <label className="text-xs font-semibold text-slate-300">
                Status Inicial
              </label>
              <select
                value={status}
                onChange={(e) => setStatus(e.target.value as any)}
                className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-emerald-500"
              >
                <option value="ACTIVE">Ativo (Publicado imediatamente)</option>
                <option value="DRAFT">Rascunho (Não disponível para disparos)</option>
              </select>
            </div>

            <div className="space-y-1 md:col-span-3">
              <label className="text-xs font-semibold text-slate-300">
                Descrição / Objetivo Interno
              </label>
              <input
                type="text"
                placeholder="Ex: Template utilizado na régua de boas-vindas para clientes com depósito acima de R$ 50"
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-emerald-500"
              />
            </div>
          </div>
        </div>

        {/* 2. Content Editor & Live Preview (Side by Side) */}
        {channel === "EMAIL" ? (
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
            {/* Email Form Left Column */}
            <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 space-y-4">
              <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 className="text-xs font-semibold text-white uppercase tracking-wider">
                  2. Conteúdo do E-mail
                </h3>
                <span className="text-[11px] text-slate-400">
                  Tags suportadas: <code className="text-emerald-400">{"{{chave}}"}</code>
                </span>
              </div>

              {/* Subject */}
              <div className="space-y-1.5">
                <div className="flex items-center justify-between">
                  <label className="text-xs font-semibold text-slate-300">
                    Assunto do E-mail *
                  </label>
                  <button
                    type="button"
                    onClick={() => openVariablePickerFor("subject")}
                    className="text-[11px] text-emerald-400 hover:text-emerald-300 flex items-center space-x-1"
                  >
                    <Tag className="w-3 h-3" />
                    <span>+ Inserir Variável</span>
                  </button>
                </div>
                <input
                  type="text"
                  placeholder="Olá {{player.first_name}}, bem-vindo à {{platform.name}}!"
                  value={subject}
                  onChange={(e) => setSubject(e.target.value)}
                  className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-emerald-500"
                />
              </div>

              {/* Preheader */}
              <div className="space-y-1.5">
                <div className="flex items-center justify-between">
                  <label className="text-xs font-semibold text-slate-300">
                    Pré-cabeçalho (Snippet visível na caixa de entrada)
                  </label>
                  <button
                    type="button"
                    onClick={() => openVariablePickerFor("preheader")}
                    className="text-[11px] text-emerald-400 hover:text-emerald-300 flex items-center space-x-1"
                  >
                    <Tag className="w-3 h-3" />
                    <span>+ Inserir Variável</span>
                  </button>
                </div>
                <input
                  type="text"
                  placeholder="Seu bônus exclusivo já está disponível na conta..."
                  value={preheader}
                  onChange={(e) => setPreheader(e.target.value)}
                  className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-emerald-500"
                />
              </div>

              {/* HTML Content */}
              <div className="space-y-1.5">
                <div className="flex items-center justify-between">
                  <label className="text-xs font-semibold text-slate-300">
                    Código HTML do E-mail *
                  </label>
                  <button
                    type="button"
                    onClick={() => openVariablePickerFor("html")}
                    className="text-[11px] text-emerald-400 hover:text-emerald-300 flex items-center space-x-1"
                  >
                    <Tag className="w-3 h-3" />
                    <span>+ Inserir Variável</span>
                  </button>
                </div>
                <textarea
                  rows={14}
                  value={htmlContent}
                  onChange={(e) => setHtmlContent(e.target.value)}
                  className="w-full bg-slate-950 border border-slate-800 rounded-lg p-3 text-xs font-mono text-slate-200 placeholder-slate-600 focus:outline-none focus:border-emerald-500"
                />
                <p className="text-[11px] text-slate-500">
                  Tags perigosas como &lt;script&gt; e manipuladores de eventos (onclick, etc.) são sanitizados automaticamente no backend.
                </p>
              </div>

              {/* Text Plain Content */}
              <div className="space-y-1.5">
                <div className="flex items-center justify-between">
                  <label className="text-xs font-semibold text-slate-300">
                    Versão Texto Alternativo (Plain Text)
                  </label>
                  <button
                    type="button"
                    onClick={() => openVariablePickerFor("text")}
                    className="text-[11px] text-emerald-400 hover:text-emerald-300 flex items-center space-x-1"
                  >
                    <Tag className="w-3 h-3" />
                    <span>+ Inserir Variável</span>
                  </button>
                </div>
                <textarea
                  rows={4}
                  value={textContent}
                  onChange={(e) => setTextContent(e.target.value)}
                  placeholder="Texto plano exibido em clientes de e-mail que não suportam HTML."
                  className="w-full bg-slate-950 border border-slate-800 rounded-lg p-3 text-xs font-mono text-slate-200 placeholder-slate-600 focus:outline-none focus:border-emerald-500"
                />
              </div>
            </div>

            {/* Email Live Preview Right Column */}
            <div className="sticky top-6">
              <EmailPreviewPane
                subject={renderSimulated(subject)}
                preheader={renderSimulated(preheader)}
                htmlContent={renderSimulated(htmlContent)}
                textContent={renderSimulated(textContent)}
              />
            </div>
          </div>
        ) : (
          /* SMS Editor & Phone Simulator (Side by Side) */
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
            {/* SMS Form Left Column */}
            <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 space-y-4">
              <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 className="text-xs font-semibold text-white uppercase tracking-wider">
                  2. Conteúdo do SMS
                </h3>
                <button
                  type="button"
                  onClick={() => openVariablePickerFor("sms")}
                  className="text-[11px] text-emerald-400 hover:text-emerald-300 flex items-center space-x-1"
                >
                  <Tag className="w-3 h-3" />
                  <span>+ Inserir Variável</span>
                </button>
              </div>

              {/* SMS Textarea */}
              <div className="space-y-2">
                <textarea
                  rows={6}
                  value={smsContent}
                  onChange={(e) => setSmsContent(e.target.value)}
                  placeholder="Digite a mensagem do SMS..."
                  className="w-full bg-slate-950 border border-slate-800 rounded-lg p-3 text-xs font-mono text-slate-200 placeholder-slate-600 focus:outline-none focus:border-emerald-500"
                />
              </div>

              {/* Live Character & GSM-7 Counter */}
              <SmsMetricsBadge content={smsContent} />

              <div className="bg-slate-950/40 border border-slate-800 rounded-lg p-3 text-[11px] text-slate-400 space-y-1">
                <p className="font-semibold text-slate-300">
                  Dicas para economia de SMS:
                </p>
                <p>
                  • Evite acentos agudos, circunflexos e cedilha (ex: utilize &quot;Ola&quot; ao inves de &quot;Olá&quot;, &quot;voce&quot; ao inves de &quot;você&quot;).
                </p>
                <p>
                  • Caracteres fora do alfabeto GSM-7 forçam codificação Unicode, reduzindo o limite por SMS de 160 para 70 caracteres.
                </p>
              </div>
            </div>

            {/* SMS Phone Screen Simulator Right Column */}
            <div className="sticky top-6 flex justify-center">
              <div className="w-[320px] bg-slate-950 border-4 border-slate-800 rounded-[36px] p-4 shadow-2xl space-y-4">
                {/* Phone Speaker Notch */}
                <div className="w-24 h-4 bg-slate-800 rounded-full mx-auto" />

                {/* SMS App Header */}
                <div className="text-center border-b border-slate-800 pb-2">
                  <div className="w-10 h-10 rounded-full bg-emerald-500/20 border border-emerald-500/40 text-emerald-400 font-bold mx-auto flex items-center justify-center text-xs">
                    BET
                  </div>
                  <h4 className="text-xs font-bold text-white mt-1">Bet Brasil</h4>
                  <p className="text-[10px] text-slate-500">SMS Oficial</p>
                </div>

                {/* Chat Bubble Canvas */}
                <div className="min-h-[220px] flex flex-col justify-end p-2 space-y-2">
                  <div className="text-center text-[10px] text-slate-500">
                    Hoje 14:32
                  </div>
                  <div className="bg-emerald-600 text-white rounded-2xl rounded-tr-none p-3 text-xs leading-relaxed shadow max-w-[90%] self-end">
                    {renderSimulated(smsContent) || (
                      <span className="text-emerald-200/60 italic">
                        Mensagem vazia...
                      </span>
                    )}
                  </div>
                </div>

                {/* Simulated Phone Bar */}
                <div className="w-28 h-1 bg-slate-700 rounded-full mx-auto" />
              </div>
            </div>
          </div>
        )}

        {/* Variable Picker Modal */}
        <VariablePickerModal
          isOpen={isVarPickerOpen}
          onClose={() => setIsVarPickerOpen(false)}
          onSelectVariable={handleInsertVariable}
          channel={channel}
        />
      </form>
    </AppLayout>
  );
}
