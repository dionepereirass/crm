"use client";

import React, { useState, useEffect } from "react";
import { useParams, useRouter } from "next/navigation";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import {
  templateService,
  Template,
  UpdateTemplatePayload,
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
  AlertCircle,
  Info,
} from "lucide-react";

export default function EditTemplatePage() {
  const params = useParams();
  const router = useRouter();
  const id = params?.id as string;

  const [template, setTemplate] = useState<Template | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Form State
  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [category, setCategory] = useState("WELCOME");
  const [status, setStatus] = useState<"DRAFT" | "ACTIVE" | "ARCHIVED">("DRAFT");
  const [changelog, setChangelog] = useState("");

  // Email Fields
  const [subject, setSubject] = useState("");
  const [preheader, setPreheader] = useState("");
  const [htmlContent, setHtmlContent] = useState("");
  const [textContent, setTextContent] = useState("");

  // SMS Fields
  const [smsContent, setSmsContent] = useState("");

  // UI State
  const [categories, setCategories] = useState<string[]>([]);
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

  useEffect(() => {
    if (!id) return;
    setLoading(true);
    templateService
      .getTemplate(id)
      .then((data) => {
        setTemplate(data);
        setName(data.name);
        setDescription(data.description || "");
        setCategory(data.category);
        setStatus(data.status);

        const currentVer = data.current_version;
        if (currentVer) {
          setSubject(currentVer.subject || "");
          setPreheader(currentVer.preheader || "");
          setHtmlContent(currentVer.html_content || "");
          setTextContent(currentVer.text_content || "");
          setSmsContent(currentVer.sms_content || "");
        }
      })
      .catch((err) => {
        setError(err.message || "Erro ao carregar template.");
      })
      .finally(() => setLoading(false));
  }, [id]);

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
      const payload: UpdateTemplatePayload = {
        name,
        description,
        category,
        status,
        changelog: changelog || undefined,
        ...(template?.channel === "EMAIL"
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

      await templateService.updateTemplate(id, payload);
      router.push(`/templates/${id}`);
    } catch (err: any) {
      setError(err.message || "Erro ao salvar alterações no template.");
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

  if (loading) {
    return (
      <AppLayout title="Editar Template" badge="FASE 6">
        <div className="text-center py-24 text-slate-500 text-xs flex items-center justify-center space-x-2">
          <div className="animate-spin w-4 h-4 border-2 border-emerald-500 border-t-transparent rounded-full" />
          <span>Carregando dados para edição...</span>
        </div>
      </AppLayout>
    );
  }

  if (!template) {
    return (
      <AppLayout title="Template não encontrado" badge="FASE 6">
        <div className="p-8 text-center text-slate-400">
          Template #{id} não encontrado.
        </div>
      </AppLayout>
    );
  }

  const isPublished = template.current_version?.status === "PUBLISHED";

  return (
    <AppLayout
      title={`Editar: ${template.name}`}
      badge="FASE 6"
      actions={
        <div className="flex items-center space-x-2">
          <Link
            href={`/templates/${id}`}
            className="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-xs flex items-center space-x-1.5 transition"
          >
            <ArrowLeft className="w-3.5 h-3.5" />
            <span>Cancelar</span>
          </Link>
          <button
            onClick={handleSubmit}
            disabled={saving}
            className="px-4 py-1.5 bg-emerald-500 hover:bg-emerald-400 disabled:opacity-50 text-slate-950 font-semibold rounded-lg text-xs flex items-center space-x-1.5 shadow-md shadow-emerald-500/20 transition"
          >
            <Save className="w-3.5 h-3.5" />
            <span>{saving ? "Salvando..." : "Salvar Alterações"}</span>
          </button>
        </div>
      }
    >
      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Immutability Alert */}
        {isPublished && (
          <div className="bg-blue-950/40 border border-blue-800/60 text-blue-300 p-4 rounded-xl text-xs flex items-start space-x-3">
            <Info className="w-5 h-5 text-blue-400 shrink-0 mt-0.5" />
            <div className="space-y-1">
              <strong className="text-white block font-semibold">
                Versão Imutável (v{template.current_version?.version}):
              </strong>
              <span>
                A versão atual está publicada e protegida contra sobrescrita. Ao salvar modificações de conteúdo, o BET CRM criará automaticamente a{" "}
                <strong className="text-emerald-400">
                  versão v{(template.current_version?.version || 1) + 1}
                </strong>{" "}
                em estado Rascunho, preservando integralmente o histórico de auditoria.
              </span>
            </div>
          </div>
        )}

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

        {/* Basic Information Card */}
        <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 space-y-4">
          <div className="flex items-center justify-between border-b border-slate-800 pb-3">
            <h3 className="text-xs font-semibold text-white uppercase tracking-wider">
              1. Metadados do Template
            </h3>
            <span className="font-mono text-xs px-2 py-0.5 rounded bg-slate-800 text-slate-300 border border-slate-700">
              Canal: {template.channel}
            </span>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="space-y-1">
              <label className="text-xs font-semibold text-slate-300">
                Nome do Template *
              </label>
              <input
                type="text"
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
                Status do Template
              </label>
              <select
                value={status}
                onChange={(e) => setStatus(e.target.value as any)}
                className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-300 focus:outline-none focus:border-emerald-500"
              >
                <option value="ACTIVE">Ativo (Pronto para uso)</option>
                <option value="DRAFT">Rascunho</option>
                <option value="ARCHIVED">Arquivado</option>
              </select>
            </div>

            <div className="space-y-1 md:col-span-2">
              <label className="text-xs font-semibold text-slate-300">
                Descrição Interna
              </label>
              <input
                type="text"
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-emerald-500"
              />
            </div>

            <div className="space-y-1">
              <label className="text-xs font-semibold text-slate-300">
                Nota de Versão / Changelog (Opcional)
              </label>
              <input
                type="text"
                placeholder="Ex: Atualizado botão de CTA e texto promocional"
                value={changelog}
                onChange={(e) => setChangelog(e.target.value)}
                className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-emerald-500"
              />
            </div>
          </div>
        </div>

        {/* Content Editor & Live Preview */}
        {template.channel === "EMAIL" ? (
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
            <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 space-y-4">
              <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 className="text-xs font-semibold text-white uppercase tracking-wider">
                  2. Conteúdo do E-mail
                </h3>
                <span className="text-[11px] text-slate-400">
                  Canal: E-mail
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
                  value={subject}
                  onChange={(e) => setSubject(e.target.value)}
                  className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-emerald-500"
                />
              </div>

              {/* Preheader */}
              <div className="space-y-1.5">
                <div className="flex items-center justify-between">
                  <label className="text-xs font-semibold text-slate-300">
                    Pré-cabeçalho
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
              </div>

              {/* Plain Text Content */}
              <div className="space-y-1.5">
                <div className="flex items-center justify-between">
                  <label className="text-xs font-semibold text-slate-300">
                    Versão Texto Alternativo
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
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
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

              <textarea
                rows={6}
                value={smsContent}
                onChange={(e) => setSmsContent(e.target.value)}
                className="w-full bg-slate-950 border border-slate-800 rounded-lg p-3 text-xs font-mono text-slate-200 placeholder-slate-600 focus:outline-none focus:border-emerald-500"
              />

              <SmsMetricsBadge content={smsContent} />
            </div>

            {/* SMS Simulator Right Column */}
            <div className="sticky top-6 flex justify-center">
              <div className="w-[320px] bg-slate-950 border-4 border-slate-800 rounded-[36px] p-4 shadow-2xl space-y-4">
                <div className="w-24 h-4 bg-slate-800 rounded-full mx-auto" />
                <div className="text-center border-b border-slate-800 pb-2">
                  <div className="w-10 h-10 rounded-full bg-emerald-500/20 border border-emerald-500/40 text-emerald-400 font-bold mx-auto flex items-center justify-center text-xs">
                    BET
                  </div>
                  <h4 className="text-xs font-bold text-white mt-1">Bet Brasil</h4>
                  <p className="text-[10px] text-slate-500">SMS Oficial</p>
                </div>
                <div className="min-h-[200px] flex flex-col justify-end p-2 space-y-2">
                  <div className="bg-emerald-600 text-white rounded-2xl rounded-tr-none p-3 text-xs leading-relaxed shadow max-w-[90%] self-end">
                    {renderSimulated(smsContent) || (
                      <span className="text-emerald-200/60 italic">
                        Mensagem vazia...
                      </span>
                    )}
                  </div>
                </div>
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
          channel={template.channel}
        />
      </form>
    </AppLayout>
  );
}
