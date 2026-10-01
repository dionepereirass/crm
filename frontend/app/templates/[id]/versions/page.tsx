"use client";

import React, { useState, useEffect } from "react";
import { useParams, useRouter } from "next/navigation";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import {
  templateService,
  Template,
  TemplateVersion,
} from "@/services/template-service";
import {
  History,
  ArrowLeft,
  RotateCcw,
  CheckCircle,
  Eye,
  ShieldCheck,
  Calendar,
  User,
  AlertCircle,
  Columns,
} from "lucide-react";

export default function TemplateVersionsPage() {
  const params = useParams();
  const router = useRouter();
  const id = params?.id as string;

  const [template, setTemplate] = useState<Template | null>(null);
  const [versions, setVersions] = useState<TemplateVersion[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);

  // Selected version for inspection
  const [selectedVersion, setSelectedVersion] =
    useState<TemplateVersion | null>(null);

  // Compare mode
  const [compareVersionA, setCompareVersionA] =
    useState<TemplateVersion | null>(null);
  const [compareVersionB, setCompareVersionB] =
    useState<TemplateVersion | null>(null);
  const [showDiffModal, setShowDiffModal] = useState(false);

  const fetchVersions = async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const [tplData, versData] = await Promise.all([
        templateService.getTemplate(id),
        templateService.getVersions(id),
      ]);
      setTemplate(tplData);
      setVersions(versData);
      if (versData.length > 0) {
        setSelectedVersion(
          versData.find((v) => v.id === tplData.current_version_id) ||
            versData[0]
        );
      }
    } catch (err: any) {
      setError(err.message || "Erro ao carregar histórico de versões.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchVersions();
  }, [id]);

  const handlePublish = async (versionNumber: number) => {
    try {
      await templateService.publishVersion(id, versionNumber);
      setSuccessMsg(
        `Versão v${versionNumber} publicada com sucesso como versão ativa!`
      );
      fetchVersions();
      setTimeout(() => setSuccessMsg(null), 4000);
    } catch (err: any) {
      setError(err.message || "Erro ao publicar versão.");
    }
  };

  const handleRestore = async (versionNumber: number) => {
    if (
      !confirm(
        `Deseja restaurar o conteúdo da versão v${versionNumber}? Isso criará uma nova versão (v${
          versions.length + 1
        }) contendo o snapshot desta versão histórica.`
      )
    ) {
      return;
    }

    try {
      const newVersion = await templateService.restoreVersion(id, versionNumber);
      setSuccessMsg(
        `Snapshot da v${versionNumber} restaurado com sucesso como v${newVersion.version}!`
      );
      fetchVersions();
      setTimeout(() => setSuccessMsg(null), 4000);
    } catch (err: any) {
      setError(err.message || "Erro ao restaurar versão.");
    }
  };

  const openComparison = (vA: TemplateVersion, vB: TemplateVersion) => {
    setCompareVersionA(vA);
    setCompareVersionB(vB);
    setShowDiffModal(true);
  };

  if (loading) {
    return (
      <AppLayout title="Histórico de Versões" badge="FASE 6">
        <div className="text-center py-24 text-slate-500 text-xs flex items-center justify-center space-x-2">
          <div className="animate-spin w-4 h-4 border-2 border-emerald-500 border-t-transparent rounded-full" />
          <span>Carregando histórico de versões...</span>
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

  return (
    <AppLayout
      title={`Histórico de Versões: ${template.name}`}
      badge="FASE 6"
      actions={
        <div className="flex items-center space-x-2">
          <Link
            href={`/templates/${id}`}
            className="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-xs flex items-center space-x-1.5 transition"
          >
            <ArrowLeft className="w-3.5 h-3.5" />
            <span>Voltar ao Template</span>
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

        {/* Master Explanation Banner */}
        <div className="bg-slate-900 border border-slate-800 rounded-xl p-4 flex items-start space-x-3 text-xs">
          <ShieldCheck className="w-5 h-5 text-emerald-400 shrink-0 mt-0.5" />
          <div className="space-y-1">
            <h4 className="font-semibold text-white">
              Imutabilidade e Rastreabilidade Completa
            </h4>
            <p className="text-slate-400">
              Versões publicadas nunca são alteradas ou sobrescritas no BET CRM.
              Qualquer atualização cria uma nova versão. É possível inspecionar,
              comparar e restaurar qualquer snapshot histórico a qualquer momento.
            </p>
          </div>
        </div>

        {/* Versions Grid / Inspector */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
          {/* Versions Timeline (1 col) */}
          <div className="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-3">
            <h3 className="text-xs font-semibold text-white uppercase tracking-wider border-b border-slate-800 pb-2">
              Linha do Tempo ({versions.length} vers
              {versions.length === 1 ? "ão" : "ões"})
            </h3>

            <div className="space-y-2">
              {versions.map((ver, idx) => {
                const isCurrent = ver.id === template.current_version_id;
                const isSelected = selectedVersion?.id === ver.id;

                return (
                  <div
                    key={ver.id}
                    onClick={() => setSelectedVersion(ver)}
                    className={`p-3 rounded-xl border cursor-pointer transition space-y-2 ${
                      isSelected
                        ? "bg-slate-800/80 border-emerald-500/60 ring-1 ring-emerald-500/30"
                        : "bg-slate-950/40 border-slate-800 hover:border-slate-700"
                    }`}
                  >
                    <div className="flex items-center justify-between">
                      <div className="flex items-center space-x-2">
                        <span className="font-mono text-xs font-bold px-2 py-0.5 rounded bg-slate-800 text-slate-100 border border-slate-700">
                          v{ver.version}
                        </span>
                        {isCurrent && (
                          <span className="text-[10px] font-bold px-1.5 py-0.2 rounded bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                            ATIVA
                          </span>
                        )}
                      </div>

                      <span
                        className={`text-[10px] font-bold px-1.5 py-0.5 rounded ${
                          ver.status === "PUBLISHED"
                            ? "bg-emerald-500/10 text-emerald-400"
                            : ver.status === "DRAFT"
                            ? "bg-amber-500/10 text-amber-400"
                            : "bg-slate-800 text-slate-400"
                        }`}
                      >
                        {ver.status}
                      </span>
                    </div>

                    <div className="text-[11px] text-slate-400 space-y-0.5">
                      <div className="flex items-center space-x-1">
                        <User className="w-3 h-3 text-slate-500" />
                        <span>{ver.creator?.name || "Admin"}</span>
                      </div>
                      <div className="flex items-center space-x-1 text-slate-500">
                        <Calendar className="w-3 h-3" />
                        <span>{new Date(ver.created_at).toLocaleString("pt-BR")}</span>
                      </div>
                    </div>

                    {ver.changelog && (
                      <p className="text-[11px] text-slate-300 italic border-l-2 border-slate-700 pl-2">
                        &quot;{ver.changelog}&quot;
                      </p>
                    )}

                    {/* Quick Version Actions */}
                    <div className="flex items-center justify-between pt-2 border-t border-slate-800/80 text-xs">
                      {/* Compare with previous button */}
                      {idx < versions.length - 1 && (
                        <button
                          type="button"
                          onClick={(e) => {
                            e.stopPropagation();
                            openComparison(ver, versions[idx + 1]);
                          }}
                          className="text-[11px] text-blue-400 hover:text-blue-300 flex items-center space-x-1"
                        >
                          <Columns className="w-3 h-3" />
                          <span>Comparar com v{versions[idx + 1].version}</span>
                        </button>
                      )}

                      {!isCurrent && (
                        <button
                          type="button"
                          onClick={(e) => {
                            e.stopPropagation();
                            handlePublish(ver.version);
                          }}
                          className="text-[11px] text-emerald-400 hover:text-emerald-300 ml-auto"
                        >
                          Tornar Ativa
                        </button>
                      )}
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

          {/* Selected Version Inspector (2 cols) */}
          <div className="lg:col-span-2 space-y-4">
            {selectedVersion ? (
              <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 space-y-4">
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-2 border-b border-slate-800 pb-3">
                  <div>
                    <div className="flex items-center space-x-2">
                      <h3 className="text-sm font-bold text-white">
                        Inspeção de Conteúdo da Versão v
                        {selectedVersion.version}
                      </h3>
                      <span className="font-mono text-xs px-2 py-0.5 rounded bg-slate-800 text-slate-300">
                        {selectedVersion.status}
                      </span>
                    </div>
                    <p className="text-xs text-slate-400">
                      Criada em {new Date(selectedVersion.created_at).toLocaleString("pt-BR")} por {selectedVersion.creator?.name || "Admin"}
                    </p>
                  </div>

                  <div className="flex items-center space-x-2">
                    <button
                      type="button"
                      onClick={() => handleRestore(selectedVersion.version)}
                      className="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-xs flex items-center space-x-1.5 transition"
                    >
                      <RotateCcw className="w-3.5 h-3.5 text-amber-400" />
                      <span>Restaurar como Nova Versão</span>
                    </button>
                    {selectedVersion.id !== template.current_version_id && (
                      <button
                        type="button"
                        onClick={() => handlePublish(selectedVersion.version)}
                        className="px-3 py-1.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-semibold rounded-lg text-xs flex items-center space-x-1.5 transition shadow"
                      >
                        <CheckCircle className="w-3.5 h-3.5" />
                        <span>Publicar esta Versão</span>
                      </button>
                    )}
                  </div>
                </div>

                {/* Content Display */}
                {template.channel === "EMAIL" ? (
                  <div className="space-y-3">
                    <div className="bg-slate-950/60 border border-slate-800 rounded-lg p-3 space-y-1 text-xs">
                      <span className="text-slate-500 font-semibold block text-[10px] uppercase">
                        Assunto:
                      </span>
                      <span className="text-white font-medium">
                        {selectedVersion.subject || "-"}
                      </span>
                    </div>

                    {selectedVersion.preheader && (
                      <div className="bg-slate-950/60 border border-slate-800 rounded-lg p-3 space-y-1 text-xs">
                        <span className="text-slate-500 font-semibold block text-[10px] uppercase">
                          Pré-cabeçalho:
                        </span>
                        <span className="text-slate-300">
                          {selectedVersion.preheader}
                        </span>
                      </div>
                    )}

                    <div className="bg-slate-950/60 border border-slate-800 rounded-lg p-3 space-y-2 text-xs">
                      <span className="text-slate-500 font-semibold block text-[10px] uppercase">
                        Conteúdo HTML:
                      </span>
                      <pre className="bg-slate-950 p-3 rounded font-mono text-[11px] text-slate-300 overflow-x-auto whitespace-pre-wrap max-h-96">
                        {selectedVersion.html_content || "Sem conteúdo HTML."}
                      </pre>
                    </div>

                    {selectedVersion.text_content && (
                      <div className="bg-slate-950/60 border border-slate-800 rounded-lg p-3 space-y-1 text-xs">
                        <span className="text-slate-500 font-semibold block text-[10px] uppercase">
                          Texto Puro (Plain Text):
                        </span>
                        <pre className="bg-slate-950 p-3 rounded font-mono text-[11px] text-slate-300 whitespace-pre-wrap">
                          {selectedVersion.text_content}
                        </pre>
                      </div>
                    )}
                  </div>
                ) : (
                  <div className="space-y-3">
                    <div className="bg-slate-950/60 border border-slate-800 rounded-lg p-4 space-y-2 text-xs">
                      <span className="text-slate-500 font-semibold block text-[10px] uppercase">
                        Texto do SMS:
                      </span>
                      <div className="bg-slate-950 p-4 rounded-lg font-mono text-sm text-slate-100 whitespace-pre-wrap border border-slate-850">
                        {selectedVersion.sms_content || "Sem texto de SMS."}
                      </div>
                    </div>
                  </div>
                )}

                {/* Variables Schema Table */}
                <div className="pt-2">
                  <h4 className="text-xs font-semibold text-white uppercase tracking-wider mb-2">
                    Variáveis Registradas no Schema (
                    {selectedVersion.variables_schema?.length || 0})
                  </h4>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                    {selectedVersion.variables_schema?.map((v, i) => (
                      <div
                        key={i}
                        className="bg-slate-950/80 border border-slate-800 rounded p-2 text-xs flex items-center justify-between"
                      >
                        <span className="font-mono text-emerald-400 font-bold">
                          {`{{${v.key}}}`}
                        </span>
                        {v.fallback && (
                          <span className="text-[10px] text-slate-400">
                            default: &quot;{v.fallback}&quot;
                          </span>
                        )}
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            ) : (
              <div className="bg-slate-900 border border-slate-800 rounded-xl p-12 text-center text-slate-500 text-xs">
                Selecione uma versão na lista ao lado para inspecionar.
              </div>
            )}
          </div>
        </div>

        {/* Side-by-Side Comparison Modal */}
        {showDiffModal && compareVersionA && compareVersionB && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4 animate-in fade-in duration-150">
            <div className="bg-[#0f172a] border border-slate-800 rounded-xl w-full max-w-5xl flex flex-col max-h-[90vh] shadow-2xl overflow-hidden">
              <div className="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-900/60">
                <div className="flex items-center space-x-2">
                  <Columns className="w-5 h-5 text-emerald-400" />
                  <h3 className="text-sm font-semibold text-white">
                    Comparação de Versões: v{compareVersionA.version} vs v
                    {compareVersionB.version}
                  </h3>
                </div>
                <button
                  onClick={() => setShowDiffModal(false)}
                  className="text-slate-400 hover:text-white text-lg font-bold"
                >
                  ×
                </button>
              </div>

              <div className="p-6 overflow-y-auto grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                {/* Version A */}
                <div className="space-y-3 bg-slate-950/60 border border-slate-800 rounded-xl p-4">
                  <div className="flex items-center justify-between border-b border-slate-800 pb-2">
                    <span className="font-mono font-bold text-white text-sm">
                      Versão v{compareVersionA.version}
                    </span>
                    <span className="text-emerald-400 font-mono">
                      {compareVersionA.status}
                    </span>
                  </div>
                  {template.channel === "EMAIL" ? (
                    <>
                      <div>
                        <strong className="text-slate-400 block text-[10px] uppercase">
                          Assunto:
                        </strong>
                        <p className="text-slate-200">
                          {compareVersionA.subject || "-"}
                        </p>
                      </div>
                      <div>
                        <strong className="text-slate-400 block text-[10px] uppercase">
                          HTML:
                        </strong>
                        <pre className="bg-slate-950 p-2 rounded text-[11px] font-mono text-slate-300 whitespace-pre-wrap max-h-72 overflow-y-auto">
                          {compareVersionA.html_content}
                        </pre>
                      </div>
                    </>
                  ) : (
                    <div>
                      <strong className="text-slate-400 block text-[10px] uppercase">
                        SMS:
                      </strong>
                      <p className="text-slate-200 whitespace-pre-wrap">
                        {compareVersionA.sms_content}
                      </p>
                    </div>
                  )}
                </div>

                {/* Version B */}
                <div className="space-y-3 bg-slate-950/60 border border-slate-800 rounded-xl p-4">
                  <div className="flex items-center justify-between border-b border-slate-800 pb-2">
                    <span className="font-mono font-bold text-white text-sm">
                      Versão v{compareVersionB.version}
                    </span>
                    <span className="text-slate-400 font-mono">
                      {compareVersionB.status}
                    </span>
                  </div>
                  {template.channel === "EMAIL" ? (
                    <>
                      <div>
                        <strong className="text-slate-400 block text-[10px] uppercase">
                          Assunto:
                        </strong>
                        <p className="text-slate-200">
                          {compareVersionB.subject || "-"}
                        </p>
                      </div>
                      <div>
                        <strong className="text-slate-400 block text-[10px] uppercase">
                          HTML:
                        </strong>
                        <pre className="bg-slate-950 p-2 rounded text-[11px] font-mono text-slate-300 whitespace-pre-wrap max-h-72 overflow-y-auto">
                          {compareVersionB.html_content}
                        </pre>
                      </div>
                    </>
                  ) : (
                    <div>
                      <strong className="text-slate-400 block text-[10px] uppercase">
                        SMS:
                      </strong>
                      <p className="text-slate-200 whitespace-pre-wrap">
                        {compareVersionB.sms_content}
                      </p>
                    </div>
                  )}
                </div>
              </div>

              <div className="px-6 py-3 border-t border-slate-800 bg-slate-900/60 flex justify-end">
                <button
                  onClick={() => setShowDiffModal(false)}
                  className="px-4 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-xs transition"
                >
                  Fechar Comparação
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
