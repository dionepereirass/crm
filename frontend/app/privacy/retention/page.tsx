"use client";

import React, { useState, useEffect } from "react";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import {
  privacyService,
  RetentionPolicyRecord,
} from "@/services/privacy-service";
import {
  Clock,
  ShieldAlert,
  Play,
  CheckCircle2,
  Trash2,
  UserX,
  FileText,
  Activity,
  Layers,
  Save,
  RefreshCw,
  AlertTriangle,
  Info,
} from "lucide-react";

const CATEGORY_METADATA: Record<
  string,
  { label: string; description: string; defaultDays: number; allowedActions: string[]; icon: any }
> = {
  WEBHOOK_LOGS: {
    label: "Logs de Webhooks",
    description: "Payloads brutos recebidos dos provedores externos e integrações de apostas.",
    defaultDays: 30,
    allowedActions: ["DELETE", "RETAIN"],
    icon: Activity,
  },
  MESSAGE_EVENTS: {
    label: "Eventos de Mensagens (Tracking)",
    description: "Cliques, aberturas, bounces e entregas granulares de e-mail e SMS.",
    defaultDays: 90,
    allowedActions: ["DELETE", "ANONYMIZE", "RETAIN"],
    icon: FileText,
  },
  EXPORT_FILES: {
    label: "Arquivos de Exportação",
    description: "Snapshots gerados para exportação ou portabilidade de dados do titular.",
    defaultDays: 7,
    allowedActions: ["DELETE", "RETAIN"],
    icon: Layers,
  },
  TEMPORARY_TOKENS: {
    label: "Tokens Temporários & Sessões",
    description: "Chaves de autenticação de sessão e tokens de link temporário expirados.",
    defaultDays: 1,
    allowedActions: ["DELETE", "RETAIN"],
    icon: Clock,
  },
  INACTIVE_PLAYERS: {
    label: "Jogadores Inativos (Sem Transações)",
    description: "Contas de apostadores sem atividade ou depósitos nos últimos 5 anos.",
    defaultDays: 1825,
    allowedActions: ["ANONYMIZE", "DELETE", "RETAIN"],
    icon: UserX,
  },
  AUDIT_LOGS: {
    label: "Trilha Geral de Auditoria",
    description: "Registros de ações administrativas e governança na plataforma.",
    defaultDays: 730,
    allowedActions: ["RETAIN", "DELETE"],
    icon: ShieldAlert,
  },
};

export default function RetentionPoliciesPage() {
  const [policies, setPolicies] = useState<RetentionPolicyRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [savingCategory, setSavingCategory] = useState<string | null>(null);
  const [executing, setExecuting] = useState(false);
  const [executionResult, setExecutionResult] = useState<any | null>(null);
  const [confirmModalOpen, setConfirmModalOpen] = useState(false);

  // Form states per category
  const [formState, setFormState] = useState<
    Record<string, { days: number; action: "DELETE" | "ANONYMIZE" | "RETAIN"; active: boolean }>
  >({});

  const loadPolicies = async () => {
    setLoading(true);
    try {
      const res = await privacyService.listRetentionPolicies();
      setPolicies(res.data);

      const initialMap: Record<string, { days: number; action: "DELETE" | "ANONYMIZE" | "RETAIN"; active: boolean }> = {};
      Object.keys(CATEGORY_METADATA).forEach((cat) => {
        const existing = res.data.find((p) => p.data_category === cat);
        if (existing) {
          initialMap[cat] = {
            days: existing.retention_days,
            action: existing.action as any,
            active: existing.active,
          };
        } else {
          initialMap[cat] = {
            days: CATEGORY_METADATA[cat].defaultDays,
            action: (CATEGORY_METADATA[cat].allowedActions[0] as any) || "DELETE",
            active: true,
          };
        }
      });
      setFormState(initialMap);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadPolicies();
  }, []);

  const handleFieldChange = (cat: string, field: "days" | "action" | "active", value: any) => {
    setFormState((prev) => ({
      ...prev,
      [cat]: {
        ...prev[cat],
        [field]: value,
      },
    }));
  };

  const handleSaveCategory = async (cat: string) => {
    const config = formState[cat];
    if (!config) return;

    setSavingCategory(cat);
    try {
      await privacyService.saveRetentionPolicy({
        data_category: cat,
        retention_days: Number(config.days),
        action: config.action,
        active: config.active,
      });
      await loadPolicies();
    } catch (err) {
      console.error(err);
    } finally {
      setSavingCategory(null);
    }
  };

  const handleExecuteRetention = async () => {
    setConfirmModalOpen(false);
    setExecuting(true);
    setExecutionResult(null);
    try {
      const res = await privacyService.processRetention();
      setExecutionResult(res.summary);
    } catch (err: any) {
      alert(err.message || "Falha ao executar rotina de retenção");
    } finally {
      setExecuting(false);
    }
  };

  return (
    <AppLayout
      title="Políticas de Retenção & Descarte (LGPD Art. 16)"
      badge="GOVERNANÇA & PRIVACIDADE"
      actions={
        <div className="flex items-center space-x-2">
          <Link
            href="/privacy"
            className="px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 transition"
          >
            ← Painel Geral
          </Link>
          <button
            onClick={() => setConfirmModalOpen(true)}
            disabled={executing}
            className="flex items-center space-x-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-amber-500 hover:bg-amber-400 disabled:opacity-50 text-slate-950 transition"
          >
            {executing ? (
              <RefreshCw className="w-3.5 h-3.5 animate-spin" />
            ) : (
              <Play className="w-3.5 h-3.5" />
            )}
            <span>Executar Ciclo de Retenção</span>
          </button>
        </div>
      }
    >
      <div className="space-y-6">
        {/* Intro Banner */}
        <div className="bg-slate-900/60 border border-slate-800 rounded-xl p-4 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
          <div className="flex items-start space-x-3">
            <div className="w-10 h-10 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center shrink-0">
              <Clock className="w-5 h-5 text-amber-400" />
            </div>
            <div>
              <h2 className="text-sm font-semibold text-white">
                Ciclo de Vida de Dados & Expurgos Programados
              </h2>
              <p className="text-xs text-slate-400 mt-0.5">
                Defina prazos de guarda para cada categoria de dados pessoais e técnicos. O processamento opera em lotes assíncronos (chunks) garantindo alta performance e descarte auditado.
              </p>
            </div>
          </div>
          <div className="flex items-center space-x-2 text-xs text-slate-400 bg-slate-950 px-3 py-1.5 rounded-lg border border-slate-800 shrink-0 font-mono">
            <Info className="w-3.5 h-3.5 text-slate-500" />
            <span>Job: <strong className="text-emerald-400">ProcessRetentionPoliciesJob</strong></span>
          </div>
        </div>

        {/* Execution Result Banner */}
        {executionResult && (
          <div className="p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-xl text-xs space-y-2">
            <div className="flex items-center space-x-2 text-emerald-400 font-semibold">
              <CheckCircle2 className="w-4 h-4" />
              <span>Ciclo de Retenção Concluído com Sucesso!</span>
            </div>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-3 pt-2 text-slate-300">
              {Object.entries(executionResult).map(([key, val]: any) => (
                <div key={key} className="bg-slate-900/80 p-2.5 rounded-lg border border-slate-800 font-mono">
                  <div className="text-[10px] text-slate-500 uppercase">{key}</div>
                  <div className="text-sm font-bold text-white mt-0.5">
                    {typeof val === "object" ? JSON.stringify(val) : val}
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Policies Grid */}
        {loading ? (
          <div className="p-12 text-center text-xs text-slate-400 flex items-center justify-center space-x-2">
            <RefreshCw className="w-4 h-4 animate-spin text-amber-400" />
            <span>Carregando políticas de retenção...</span>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {Object.keys(CATEGORY_METADATA).map((cat) => {
              const meta = CATEGORY_METADATA[cat];
              const config = formState[cat] || {
                days: meta.defaultDays,
                action: meta.allowedActions[0] as any,
                active: true,
              };
              const isSaving = savingCategory === cat;
              const Icon = meta.icon;

              return (
                <div
                  key={cat}
                  className="bg-slate-900/40 border border-slate-800 rounded-xl p-5 flex flex-col justify-between space-y-4 hover:border-slate-700/80 transition"
                >
                  <div className="space-y-2">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center space-x-2.5">
                        <div className="w-8 h-8 rounded-lg bg-slate-800/80 border border-slate-700/60 flex items-center justify-center">
                          <Icon className="w-4 h-4 text-amber-400" />
                        </div>
                        <div>
                          <h3 className="text-xs font-semibold text-white">{meta.label}</h3>
                          <span className="text-[10px] font-mono text-slate-500">{cat}</span>
                        </div>
                      </div>

                      <label className="relative inline-flex items-center cursor-pointer">
                        <input
                          type="checkbox"
                          checked={config.active}
                          onChange={(e) => handleFieldChange(cat, "active", e.target.checked)}
                          className="sr-only peer"
                        />
                        <div className="w-8 h-4 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-emerald-500"></div>
                      </label>
                    </div>

                    <p className="text-[11px] text-slate-400 leading-relaxed">
                      {meta.description}
                    </p>
                  </div>

                  <div className="pt-3 border-t border-slate-800/60 space-y-3">
                    <div className="grid grid-cols-2 gap-3 text-xs">
                      <div>
                        <label className="block text-[11px] text-slate-400 mb-1 font-medium">
                          Prazo de Retenção
                        </label>
                        <div className="flex items-center space-x-1.5">
                          <input
                            type="number"
                            min="1"
                            max="3650"
                            value={config.days}
                            onChange={(e) =>
                              handleFieldChange(cat, "days", Math.max(1, parseInt(e.target.value) || 1))
                            }
                            className="w-full px-2.5 py-1.5 bg-slate-950 border border-slate-800 rounded-lg text-slate-200 text-xs font-mono focus:outline-none focus:border-amber-500"
                          />
                          <span className="text-slate-500 text-[11px] font-medium">dias</span>
                        </div>
                      </div>

                      <div>
                        <label className="block text-[11px] text-slate-400 mb-1 font-medium">
                          Ação ao Expirar
                        </label>
                        <select
                          value={config.action}
                          onChange={(e) => handleFieldChange(cat, "action", e.target.value)}
                          className="w-full px-2.5 py-1.5 bg-slate-950 border border-slate-800 rounded-lg text-slate-200 text-xs font-mono focus:outline-none focus:border-amber-500"
                        >
                          {meta.allowedActions.map((act) => (
                            <option key={act} value={act}>
                              {act === "DELETE"
                                ? "Expurgar (DELETE)"
                                : act === "ANONYMIZE"
                                ? "Anonimizar (ANONYMIZE)"
                                : "Manter (RETAIN)"}
                            </option>
                          ))}
                        </select>
                      </div>
                    </div>

                    <div className="flex items-center justify-between pt-1">
                      <span className="text-[10px] text-slate-500 font-mono">
                        Status:{" "}
                        <strong className={config.active ? "text-emerald-400" : "text-rose-400"}>
                          {config.active ? "ATIVO" : "INATIVO"}
                        </strong>
                      </span>

                      <button
                        onClick={() => handleSaveCategory(cat)}
                        disabled={isSaving}
                        className="px-3 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded text-xs font-medium transition inline-flex items-center space-x-1 disabled:opacity-50"
                      >
                        {isSaving ? (
                          <RefreshCw className="w-3 h-3 animate-spin text-amber-400" />
                        ) : (
                          <Save className="w-3 h-3 text-slate-400" />
                        )}
                        <span>{isSaving ? "Salvando..." : "Salvar Política"}</span>
                      </button>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* Confirmation Modal */}
      {confirmModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-xl max-w-md w-full shadow-2xl p-5 space-y-4">
            <div className="flex items-center space-x-2.5 text-amber-400 border-b border-slate-800 pb-3">
              <AlertTriangle className="w-5 h-5 shrink-0" />
              <h3 className="font-semibold text-sm text-white">
                Confirmar Execução de Retenção Manual
              </h3>
            </div>

            <p className="text-xs text-slate-300 leading-relaxed">
              Você está prestes a disparar o expurgo e anonimização de registros antigos de acordo com os prazos ativos das políticas configuradas.
            </p>

            <div className="p-3 bg-amber-500/10 border border-amber-500/20 rounded-lg text-[11px] text-amber-300">
              Esta ação é irreversível para dados configurados como <strong>DELETE</strong> ou <strong>ANONYMIZE</strong>.
            </div>

            <div className="flex justify-end space-x-2 pt-2 border-t border-slate-800">
              <button
                onClick={() => setConfirmModalOpen(false)}
                className="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs rounded transition"
              >
                Cancelar
              </button>
              <button
                onClick={handleExecuteRetention}
                className="px-4 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold text-xs rounded transition"
              >
                Confirmar e Executar
              </button>
            </div>
          </div>
        </div>
      )}
    </AppLayout>
  );
}
