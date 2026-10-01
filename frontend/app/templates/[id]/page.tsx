"use client";

import React, { useState, useEffect } from "react";
import { useParams, useRouter } from "next/navigation";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import {
  templateService,
  Template,
  TemplatePreviewResponse,
} from "@/services/template-service";
import { EmailPreviewPane } from "@/components/templates/email-preview-pane";
import { SmsMetricsBadge } from "@/components/templates/sms-metrics-badge";
import {
  Mail,
  MessageSquare,
  ArrowLeft,
  Edit,
  History,
  Copy,
  Archive,
  Trash2,
  CheckCircle,
  Tag,
  ShieldCheck,
  Calendar,
  User,
  AlertCircle,
} from "lucide-react";

export default function TemplateDetailsPage() {
  const params = useParams();
  const router = useRouter();
  const id = params?.id as string;

  const [template, setTemplate] = useState<Template | null>(null);
  const [preview, setPreview] = useState<TemplatePreviewResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);

  const fetchTemplateData = async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const data = await templateService.getTemplate(id);
      setTemplate(data);

      // Fetch official rendered preview from backend
      const previewData = await templateService.previewTemplate(id);
      setPreview(previewData);
    } catch (err: any) {
      setError(err.message || "Erro ao carregar detalhes do template.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTemplateData();
  }, [id]);

  const handlePublish = async () => {
    try {
      await templateService.publishTemplate(id);
      setSuccessMsg("Template e versão ativa publicados com sucesso!");
      fetchTemplateData();
      setTimeout(() => setSuccessMsg(null), 4000);
    } catch (err: any) {
      setError(err.message || "Erro ao publicar template.");
    }
  };

  const handleDuplicate = async () => {
    try {
      const duplicated = await templateService.duplicateTemplate(id);
      setSuccessMsg("Template duplicado com sucesso!");
      router.push(`/templates/${duplicated.id}`);
    } catch (err: any) {
      setError(err.message || "Erro ao duplicar template.");
    }
  };

  const handleArchive = async () => {
    if (!confirm("Deseja realmente arquivar este template?")) return;
    try {
      await templateService.archiveTemplate(id);
      setSuccessMsg("Template arquivado com sucesso!");
      fetchTemplateData();
      setTimeout(() => setSuccessMsg(null), 4000);
    } catch (err: any) {
      setError(err.message || "Erro ao arquivar template.");
    }
  };

  const handleDelete = async () => {
    if (
      !confirm(
        "Atenção: Tem certeza que deseja excluir este template? Essa ação não pode ser desfeita."
      )
    )
      return;
    try {
      await templateService.deleteTemplate(id);
      router.push("/templates");
    } catch (err: any) {
      setError(err.message || "Erro ao excluir template.");
    }
  };

  if (loading) {
    return (
      <AppLayout title="Detalhes do Template" badge="FASE 6">
        <div className="text-center py-24 text-slate-500 text-xs flex items-center justify-center space-x-2">
          <div className="animate-spin w-4 h-4 border-2 border-emerald-500 border-t-transparent rounded-full" />
          <span>Carregando template #{id}...</span>
        </div>
      </AppLayout>
    );
  }

  if (!template) {
    return (
      <AppLayout title="Template não encontrado" badge="FASE 6">
        <div className="bg-slate-900 border border-slate-800 rounded-xl p-8 text-center space-y-4">
          <AlertCircle className="w-8 h-8 text-rose-400 mx-auto" />
          <h2 className="text-sm font-semibold text-white">
            Template #{id} não encontrado na plataforma ativa
          </h2>
          <Link
            href="/templates"
            className="inline-flex items-center space-x-1 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-xs"
          >
            <ArrowLeft className="w-4 h-4" />
            <span>Voltar para Listagem</span>
          </Link>
        </div>
      </AppLayout>
    );
  }

  const currentVer = template.current_version;

  return (
    <AppLayout
      title={template.name}
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
          <Link
            href={`/templates/${template.id}/versions`}
            className="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-xs flex items-center space-x-1.5 transition"
          >
            <History className="w-3.5 h-3.5 text-blue-400" />
            <span>Versões ({template.versions_count || 1})</span>
          </Link>
          <button
            onClick={handleDuplicate}
            className="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-xs flex items-center space-x-1.5 transition"
          >
            <Copy className="w-3.5 h-3.5 text-amber-400" />
            <span>Duplicar</span>
          </button>
          {template.status !== "ACTIVE" && (
            <button
              onClick={handlePublish}
              className="px-3 py-1.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-semibold rounded-lg text-xs flex items-center space-x-1.5 shadow-md shadow-emerald-500/20 transition"
            >
              <CheckCircle className="w-3.5 h-3.5" />
              <span>Publicar</span>
            </button>
          )}
          {template.status !== "ARCHIVED" && (
            <button
              onClick={handleArchive}
              className="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-xs flex items-center space-x-1.5 transition"
            >
              <Archive className="w-3.5 h-3.5" />
              <span>Arquivar</span>
            </button>
          )}
          <Link
            href={`/templates/${template.id}/edit`}
            className="px-3 py-1.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-semibold rounded-lg text-xs flex items-center space-x-1.5 shadow-md shadow-emerald-500/20 transition"
          >
            <Edit className="w-3.5 h-3.5" />
            <span>Editar</span>
          </Link>
        </div>
      }
    >
      <div className="space-y-6">
        {/* Feedback Alerts */}
        {error && (
          <div className="bg-rose-500/10 border border-rose-500/30 text-rose-300 p-3 rounded-lg text-xs flex items-center justify-between">
            <div className="flex items-center space-x-2">
              <AlertCircle className="w-4 h-4 shrink-0" />
              <span>{error}</span>
            </div>
            <button
              onClick={() => setError(null)}
              className="text-rose-400 hover:text-rose-200 font-bold ml-2"
            >
              ×
            </button>
          </div>
        )}

        {successMsg && (
          <div className="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 p-3 rounded-lg text-xs flex items-center justify-between">
            <div className="flex items-center space-x-2">
              <CheckCircle className="w-4 h-4 shrink-0" />
              <span>{successMsg}</span>
            </div>
            <button
              onClick={() => setSuccessMsg(null)}
              className="text-emerald-400 hover:text-emerald-200 font-bold ml-2"
            >
              ×
            </button>
          </div>
        )}

        {/* Master Details Header */}
        <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 space-y-4">
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-800 pb-4">
            <div className="flex items-center space-x-3">
              {template.channel === "EMAIL" ? (
                <div className="w-10 h-10 rounded-xl bg-blue-500/20 border border-blue-500/40 flex items-center justify-center text-blue-400">
                  <Mail className="w-5 h-5" />
                </div>
              ) : (
                <div className="w-10 h-10 rounded-xl bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center text-emerald-400">
                  <MessageSquare className="w-5 h-5" />
                </div>
              )}
              <div>
                <div className="flex items-center space-x-2">
                  <h1 className="text-base font-bold text-white">
                    {template.name}
                  </h1>
                  <span className="font-mono text-xs font-bold px-2 py-0.5 rounded bg-slate-800 text-slate-300 border border-slate-700">
                    v{currentVer?.version || 1}
                  </span>
                </div>
                <p className="text-xs text-slate-400">
                  {template.description || "Sem descrição informada."}
                </p>
              </div>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              <span className="font-mono text-xs uppercase px-2.5 py-1 rounded bg-slate-800 text-slate-300">
                Categoria: {template.category}
              </span>
              {template.status === "ACTIVE" ? (
                <span className="inline-flex items-center space-x-1.5 text-xs font-bold px-2.5 py-1 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                  <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" />
                  <span>ATIVO / PUBLICADO</span>
                </span>
              ) : template.status === "DRAFT" ? (
                <span className="inline-flex items-center space-x-1.5 text-xs font-bold px-2.5 py-1 rounded bg-amber-500/10 text-amber-400 border border-amber-500/30">
                  <span>RASCUNHO</span>
                </span>
              ) : (
                <span className="inline-flex items-center space-x-1.5 text-xs font-bold px-2.5 py-1 rounded bg-slate-800 text-slate-400 border border-slate-700">
                  <span>ARQUIVADO</span>
                </span>
              )}
            </div>
          </div>

          {/* Metadata Grid */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
            <div>
              <span className="text-slate-500 block text-[10px] uppercase font-medium">
                Slug do Template
              </span>
              <span className="font-mono text-slate-300">{template.slug}</span>
            </div>
            <div>
              <span className="text-slate-500 block text-[10px] uppercase font-medium">
                UUID
              </span>
              <span className="font-mono text-slate-400 text-[11px] truncate block">
                {template.uuid}
              </span>
            </div>
            <div>
              <span className="text-slate-500 block text-[10px] uppercase font-medium">
                Criado por
              </span>
              <span className="text-slate-300">
                {template.creator?.name || "Sistema"}
              </span>
            </div>
            <div>
              <span className="text-slate-500 block text-[10px] uppercase font-medium">
                Última Atualização
              </span>
              <span className="text-slate-300">
                {new Date(template.updated_at).toLocaleString("pt-BR")}
              </span>
            </div>
          </div>
        </div>

        {/* Content & Rendered Preview */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
          {/* Main Preview (2 cols) */}
          <div className="lg:col-span-2 space-y-4">
            {template.channel === "EMAIL" ? (
              <EmailPreviewPane
                subject={preview?.rendered?.subject || currentVer?.subject || ""}
                preheader={
                  preview?.rendered?.preheader || currentVer?.preheader || ""
                }
                htmlContent={
                  preview?.rendered?.html_content ||
                  currentVer?.html_content ||
                  ""
                }
                textContent={
                  preview?.rendered?.text_content ||
                  currentVer?.text_content ||
                  ""
                }
              />
            ) : (
              <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 space-y-4">
                <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                  <h3 className="text-xs font-semibold text-white uppercase tracking-wider">
                    Pré-visualização do SMS
                  </h3>
                  <span className="text-xs text-slate-400">
                    Renderizado com dados simulados
                  </span>
                </div>

                {/* SMS Live Metrics */}
                <SmsMetricsBadge
                  content={currentVer?.sms_content || ""}
                />

                {/* Simulated Phone Screen */}
                <div className="w-[320px] bg-slate-950 border-4 border-slate-800 rounded-[36px] p-4 shadow-2xl mx-auto space-y-4">
                  <div className="w-24 h-4 bg-slate-800 rounded-full mx-auto" />
                  <div className="text-center border-b border-slate-800 pb-2">
                    <div className="w-10 h-10 rounded-full bg-emerald-500/20 border border-emerald-500/40 text-emerald-400 font-bold mx-auto flex items-center justify-center text-xs">
                      BET
                    </div>
                    <h4 className="text-xs font-bold text-white mt-1">
                      Bet Brasil
                    </h4>
                    <p className="text-[10px] text-slate-500">SMS Oficial</p>
                  </div>
                  <div className="min-h-[180px] flex flex-col justify-end p-2 space-y-2">
                    <div className="bg-emerald-600 text-white rounded-2xl rounded-tr-none p-3 text-xs leading-relaxed shadow max-w-[90%] self-end">
                      {preview?.rendered?.sms_content ||
                        currentVer?.sms_content || (
                          <span className="text-emerald-200/60 italic">
                            Sem mensagem...
                          </span>
                        )}
                    </div>
                  </div>
                  <div className="w-28 h-1 bg-slate-700 rounded-full mx-auto" />
                </div>
              </div>
            )}
          </div>

          {/* Variables Schema & Version Audit Right Column */}
          <div className="space-y-4">
            {/* Variables Detected Card */}
            <div className="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-3">
              <div className="flex items-center space-x-2 border-b border-slate-800 pb-2">
                <Tag className="w-4 h-4 text-emerald-400" />
                <h3 className="text-xs font-semibold text-white uppercase tracking-wider">
                  Variáveis Detectadas (
                  {currentVer?.variables_schema?.length || 0})
                </h3>
              </div>

              {currentVer?.variables_schema &&
              currentVer.variables_schema.length > 0 ? (
                <div className="space-y-2 max-h-[300px] overflow-y-auto pr-1">
                  {currentVer.variables_schema.map((v, i) => (
                    <div
                      key={i}
                      className="bg-slate-950/60 border border-slate-800 rounded-lg p-2.5 text-xs space-y-1"
                    >
                      <div className="flex items-center justify-between">
                        <span className="font-mono text-emerald-400 font-bold text-[11px]">
                          {`{{${v.key}}}`}
                        </span>
                        {v.fallback && (
                          <span className="text-[10px] bg-slate-800 text-slate-400 px-1.5 py-0.5 rounded">
                            fallback: &quot;{v.fallback}&quot;
                          </span>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-xs text-slate-500 italic">
                  Nenhuma variável dinâmica detectada nesta versão.
                </p>
              )}
            </div>

            {/* Version Information Card */}
            <div className="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-3 text-xs">
              <div className="flex items-center space-x-2 border-b border-slate-800 pb-2">
                <ShieldCheck className="w-4 h-4 text-blue-400" />
                <h3 className="text-xs font-semibold text-white uppercase tracking-wider">
                  Auditoria da Versão
                </h3>
              </div>

              <div className="space-y-2 text-slate-300">
                <div className="flex items-center justify-between">
                  <span className="text-slate-500">Versão:</span>
                  <span className="font-mono font-bold text-white">
                    v{currentVer?.version || 1}
                  </span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-slate-500">Status da Versão:</span>
                  <span className="font-bold text-emerald-400">
                    {currentVer?.status}
                  </span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-slate-500">Autor:</span>
                  <span>{currentVer?.creator?.name || "Admin"}</span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-slate-500">Criada em:</span>
                  <span>
                    {currentVer?.created_at
                      ? new Date(currentVer.created_at).toLocaleString("pt-BR")
                      : "-"}
                  </span>
                </div>
                {currentVer?.changelog && (
                  <div className="pt-2 border-t border-slate-800">
                    <span className="text-slate-500 block text-[10px] uppercase">
                      Notas da Versão:
                    </span>
                    <p className="text-slate-400 mt-1 italic">
                      &quot;{currentVer.changelog}&quot;
                    </p>
                  </div>
                )}
              </div>

              <div className="pt-3 border-t border-slate-800">
                <Link
                  href={`/templates/${template.id}/versions`}
                  className="w-full block text-center px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-xs font-medium transition"
                >
                  Ver Histórico de Versões
                </Link>
              </div>
            </div>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
