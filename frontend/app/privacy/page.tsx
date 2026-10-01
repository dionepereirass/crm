"use client";

import React, { useEffect, useState } from "react";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import {
  privacyService,
  PrivacyMetrics,
  DataSubjectRequest,
  AuditLogRecord,
} from "@/services/privacy-service";
import {
  ShieldCheck,
  Users,
  FileText,
  Clock,
  AlertTriangle,
  CheckCircle2,
  XCircle,
  Database,
  RefreshCw,
  ArrowRight,
  Shield,
  Layers,
  Lock,
  Eye,
  Trash2,
  Activity,
} from "lucide-react";

export default function PrivacyDashboardPage() {
  const [metrics, setMetrics] = useState<PrivacyMetrics | null>(null);
  const [recentRequests, setRecentRequests] = useState<DataSubjectRequest[]>([]);
  const [recentAudit, setRecentAudit] = useState<AuditLogRecord[]>([]);
  const [categories, setCategories] = useState<Record<string, any>>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchDashboard = async () => {
    try {
      setLoading(true);
      setError(null);
      const res = await privacyService.getDashboard();
      setMetrics(res.metrics);
      setRecentRequests(res.recent_requests);
      setRecentAudit(res.recent_audit);
      setCategories(res.categories);
    } catch (err: any) {
      setError(err.message || "Falha ao carregar dashboard de privacidade.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchDashboard();
  }, []);

  const getStatusBadge = (status: string) => {
    switch (status) {
      case "COMPLETED":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-950 text-emerald-300 border border-emerald-800">
            CONCLUÍDA
          </span>
        );
      case "IN_PROGRESS":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-950 text-blue-300 border border-blue-800">
            EM ANÁLISE
          </span>
        );
      case "OPEN":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-950 text-amber-300 border border-amber-800">
            ABERTA
          </span>
        );
      case "REJECTED":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-950 text-rose-300 border border-rose-800">
            REJEITADA
          </span>
        );
      default:
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-800 text-slate-400">
            {status}
          </span>
        );
    }
  };

  return (
    <AppLayout
      title="Governança de Dados & LGPD"
      badge="FASE 11 • CONFORMIDADE LEGAL"
      actions={
        <div className="flex items-center gap-2">
          <button
            onClick={fetchDashboard}
            disabled={loading}
            className="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-sm border border-slate-700 flex items-center gap-1.5 transition"
          >
            <RefreshCw className={`w-4 h-4 ${loading ? "animate-spin" : ""}`} />
            Atualizar
          </button>

          <Link
            href="/privacy/requests"
            className="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-semibold flex items-center gap-1.5 transition shadow-sm shadow-emerald-900/40"
          >
            <FileText className="w-4 h-4" />
            Solicitações de Titulares
          </Link>
        </div>
      }
    >
      <div className="space-y-6">
        {/* Navigation Tabs */}
        <div className="flex items-center gap-2 border-b border-slate-800 pb-3 text-sm">
          <Link
            href="/privacy"
            className="px-3 py-1.5 bg-slate-800 text-emerald-400 font-semibold rounded-lg border border-slate-700"
          >
            Visão Geral
          </Link>
          <Link
            href="/privacy/consents"
            className="px-3 py-1.5 text-slate-400 hover:text-white hover:bg-slate-800/60 rounded-lg transition"
          >
            Consentimentos
          </Link>
          <Link
            href="/privacy/requests"
            className="px-3 py-1.5 text-slate-400 hover:text-white hover:bg-slate-800/60 rounded-lg transition"
          >
            Direitos do Titular (DSR)
          </Link>
          <Link
            href="/privacy/retention"
            className="px-3 py-1.5 text-slate-400 hover:text-white hover:bg-slate-800/60 rounded-lg transition"
          >
            Políticas de Retenção
          </Link>
          <Link
            href="/privacy/audit"
            className="px-3 py-1.5 text-slate-400 hover:text-white hover:bg-slate-800/60 rounded-lg transition"
          >
            Auditoria & Acessos
          </Link>
        </div>

        {error && (
          <div className="p-4 bg-rose-950/60 border border-rose-800 rounded-lg text-rose-200 text-sm flex items-center gap-2">
            <AlertTriangle className="w-5 h-5 flex-shrink-0 text-rose-400" />
            <span>{error}</span>
          </div>
        )}

        {/* Metrics Grid */}
        {metrics && (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div className="p-4 bg-slate-900 border border-slate-800 rounded-xl space-y-1">
              <div className="flex items-center justify-between text-slate-400 text-xs uppercase tracking-wider font-semibold">
                Consentimentos Ativos
                <ShieldCheck className="w-4 h-4 text-emerald-400" />
              </div>
              <div className="text-2xl font-bold text-emerald-400">
                {metrics.active_consents.toLocaleString("pt-BR")}
              </div>
              <div className="text-xs text-slate-500">
                {metrics.revoked_consents} revogados (opt-out)
              </div>
            </div>

            <div className="p-4 bg-slate-900 border border-slate-800 rounded-xl space-y-1">
              <div className="flex items-center justify-between text-slate-400 text-xs uppercase tracking-wider font-semibold">
                Solicitações Abertas
                <FileText className="w-4 h-4 text-amber-400" />
              </div>
              <div className="text-2xl font-bold text-amber-400">
                {metrics.open_requests + metrics.in_progress_requests}
              </div>
              <div className="text-xs text-slate-500">
                {metrics.near_sla_requests > 0 ? (
                  <span className="text-rose-400 font-semibold">{metrics.near_sla_requests} próximas do SLA</span>
                ) : (
                  <span>Todas dentro do prazo legal</span>
                )}
              </div>
            </div>

            <div className="p-4 bg-slate-900 border border-slate-800 rounded-xl space-y-1">
              <div className="flex items-center justify-between text-slate-400 text-xs uppercase tracking-wider font-semibold">
                Jogadores Anonimizados
                <Lock className="w-4 h-4 text-purple-400" />
              </div>
              <div className="text-2xl font-bold text-purple-400">
                {metrics.anonymized_players.toLocaleString("pt-BR")}
              </div>
              <div className="text-xs text-slate-500">Exclusão irreversível de PII</div>
            </div>

            <div className="p-4 bg-slate-900 border border-slate-800 rounded-xl space-y-1">
              <div className="flex items-center justify-between text-slate-400 text-xs uppercase tracking-wider font-semibold">
                Políticas de Retenção
                <Database className="w-4 h-4 text-blue-400" />
              </div>
              <div className="text-2xl font-bold text-blue-400">
                {metrics.active_retention_policies}
              </div>
              <div className="text-xs text-slate-500">Ciclo de vida automático ativo</div>
            </div>
          </div>
        )}

        {/* Middle Section: Recent DSR Requests + Personal Data Registry Classification */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
          {/* Recent Requests (Cols 1-7) */}
          <div className="lg:col-span-7 bg-slate-900 border border-slate-800 rounded-xl p-5 space-y-3">
            <div className="flex items-center justify-between pb-3 border-b border-slate-800">
              <div className="flex items-center gap-2">
                <FileText className="w-4 h-4 text-emerald-400" />
                <h3 className="font-semibold text-white text-sm">Últimas Solicitações de Titulares</h3>
              </div>
              <Link
                href="/privacy/requests"
                className="text-xs text-emerald-400 hover:text-emerald-300 font-semibold flex items-center gap-1"
              >
                Ver Todas
                <ArrowRight className="w-3.5 h-3.5" />
              </Link>
            </div>

            {recentRequests.length === 0 ? (
              <div className="p-8 text-center text-slate-500 text-xs">
                Nenhuma solicitação de titular em aberto.
              </div>
            ) : (
              <div className="divide-y divide-slate-800/60 text-xs">
                {recentRequests.map((req) => (
                  <div key={req.id} className="py-3 flex items-center justify-between">
                    <div>
                      <div className="flex items-center gap-2">
                        <span className="font-mono font-semibold text-slate-200">#{req.id}</span>
                        <span className="font-semibold text-white">{req.player?.name}</span>
                        <span className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-950 text-slate-400 border border-slate-800">
                          {req.type}
                        </span>
                      </div>
                      <span className="text-[11px] text-slate-500 mt-0.5 block truncate max-w-sm">
                        {req.reason || "Sem descrição"}
                      </span>
                    </div>

                    <div className="flex items-center gap-2">
                      {getStatusBadge(req.status)}
                      <Link
                        href={`/privacy/requests/${req.id}`}
                        className="p-1 hover:bg-slate-800 text-slate-400 hover:text-white rounded"
                        title="Ver Detalhes"
                      >
                        <Eye className="w-4 h-4" />
                      </Link>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Data Inventory & Classification (Cols 8-12) */}
          <div className="lg:col-span-5 bg-slate-900 border border-slate-800 rounded-xl p-5 space-y-3">
            <div className="flex items-center justify-between pb-3 border-b border-slate-800">
              <div className="flex items-center gap-2">
                <Shield className="w-4 h-4 text-purple-400" />
                <h3 className="font-semibold text-white text-sm">Inventário de Dados Pessoais</h3>
              </div>
              <span className="text-[10px] font-mono px-2 py-0.5 rounded bg-slate-950 text-slate-400 border border-slate-800">
                PersonalDataRegistry
              </span>
            </div>

            <div className="space-y-2.5 text-xs max-h-72 overflow-y-auto">
              {Object.entries(categories).map(([catKey, cat]) => (
                <div key={catKey} className="p-3 bg-slate-950 border border-slate-800 rounded-lg space-y-1">
                  <div className="flex items-center justify-between">
                    <span className="font-semibold text-slate-200">{cat.name}</span>
                    {cat.is_sensitive ? (
                      <span className="text-[10px] px-1.5 py-0.5 rounded bg-rose-950/80 text-rose-300 border border-rose-900">
                        SENSÍVEL
                      </span>
                    ) : (
                      <span className="text-[10px] px-1.5 py-0.5 rounded bg-slate-800 text-slate-400">
                        COMUM
                      </span>
                    )}
                  </div>
                  <div className="text-[11px] text-slate-400 font-mono">
                    {cat.fields?.join(", ")}
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Recent Audit Logs Strip */}
        <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 space-y-3">
          <div className="flex items-center justify-between pb-3 border-b border-slate-800">
            <div className="flex items-center gap-2">
              <Activity className="w-4 h-4 text-blue-400" />
              <h3 className="font-semibold text-white text-sm">Eventos Recentes de Auditoria LGPD</h3>
            </div>
            <Link
              href="/privacy/audit"
              className="text-xs text-blue-400 hover:text-blue-300 font-semibold flex items-center gap-1"
            >
              Trilha Completa de Auditoria
              <ArrowRight className="w-3.5 h-3.5" />
            </Link>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            {recentAudit.map((log) => (
              <div key={log.id} className="p-3 bg-slate-950 border border-slate-800 rounded-lg space-y-1 text-xs">
                <div className="flex items-center justify-between">
                  <span className="font-mono font-bold text-emerald-400">{log.action}</span>
                  <span className="text-[10px] text-slate-500 font-mono">
                    {new Date(log.created_at).toLocaleTimeString("pt-BR")}
                  </span>
                </div>
                <div className="text-slate-400 text-[11px]">
                  Recurso: <strong className="text-slate-300">{log.resource_type || "Sistema"}</strong> #{log.resource_id || "-"}
                </div>
                {log.ip_address && (
                  <div className="text-[10px] text-slate-500 font-mono">IP: {log.ip_address}</div>
                )}
              </div>
            ))}
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
