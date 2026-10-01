"use client";

import React, { useState, useEffect } from "react";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import {
  privacyService,
  AuditLogRecord,
} from "@/services/privacy-service";
import {
  ShieldAlert,
  Search,
  Filter,
  Eye,
  User,
  Clock,
  Layers,
  ArrowRight,
  RefreshCw,
  Lock,
  FileCode,
} from "lucide-react";

export default function PrivacyAuditPage() {
  const [logs, setLogs] = useState<AuditLogRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [actionFilter, setActionFilter] = useState("ALL");
  const [resourceFilter, setResourceFilter] = useState("ALL");
  const [actorId, setActorId] = useState("");
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalCount, setTotalCount] = useState(0);

  // Inspector Modal
  const [selectedLog, setSelectedLog] = useState<AuditLogRecord | null>(null);
  const [inspectorOpen, setInspectorOpen] = useState(false);

  const loadLogs = async () => {
    setLoading(true);
    try {
      const res = await privacyService.listAuditLogs({
        action: actionFilter,
        resource_type: resourceFilter,
        actor_id: actorId,
        page,
      });
      setLogs(res.data);
      setTotalPages(res.last_page);
      setTotalCount(res.total);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadLogs();
  }, [actionFilter, resourceFilter, page]);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setPage(1);
    loadLogs();
  };

  const openInspector = (log: AuditLogRecord) => {
    setSelectedLog(log);
    setInspectorOpen(true);
  };

  const getActionColor = (action: string) => {
    switch (action.toUpperCase()) {
      case "CREATE":
      case "GRANT_CONSENT":
        return "bg-emerald-500/10 text-emerald-400 border-emerald-500/20";
      case "UPDATE":
        return "bg-blue-500/10 text-blue-400 border-blue-500/20";
      case "DELETE":
      case "REVOKE_CONSENT":
        return "bg-rose-500/10 text-rose-400 border-rose-500/20";
      case "ANONYMIZE":
        return "bg-purple-500/10 text-purple-400 border-purple-500/20";
      case "EXPORT":
        return "bg-amber-500/10 text-amber-400 border-amber-500/20";
      default:
        return "bg-slate-800 text-slate-300 border-slate-700";
    }
  };

  return (
    <AppLayout
      title="Trilha de Auditoria & Governança (LGPD)"
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
            onClick={() => loadLogs()}
            className="flex items-center space-x-1 px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 transition"
          >
            <RefreshCw className="w-3.5 h-3.5" />
            <span>Atualizar</span>
          </button>
        </div>
      }
    >
      <div className="space-y-6">
        {/* Banner */}
        <div className="bg-slate-900/60 border border-slate-800 rounded-xl p-4 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
          <div className="flex items-start space-x-3">
            <div className="w-10 h-10 rounded-lg bg-blue-500/10 border border-blue-500/20 flex items-center justify-center shrink-0">
              <ShieldAlert className="w-5 h-5 text-blue-400" />
            </div>
            <div>
              <h2 className="text-sm font-semibold text-white">
                Log de Eventos Administrativos & Acesso a Dados Pessoais
              </h2>
              <p className="text-xs text-slate-400 mt-0.5">
                Rastreabilidade completa de todas as operações sensíveis, anonimizações e exportações. Dados sigilosos (senhas e tokens) são ofuscados automaticamente antes da persistência.
              </p>
            </div>
          </div>
          <div className="flex items-center space-x-3 shrink-0 text-xs">
            <span className="text-slate-400 font-mono">
              Total de Eventos: <strong className="text-white">{totalCount}</strong>
            </span>
          </div>
        </div>

        {/* Filters */}
        <div className="bg-slate-900/40 border border-slate-800/80 rounded-xl p-4">
          <form onSubmit={handleSearchSubmit} className="flex flex-col md:flex-row gap-3">
            <div className="relative flex-1">
              <input
                type="text"
                value={actorId}
                onChange={(e) => setActorId(e.target.value)}
                placeholder="Filtrar por ID do Usuário ou E-mail..."
                className="w-full px-3 py-2 bg-slate-950/70 border border-slate-800 rounded-lg text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-blue-500 transition"
              />
            </div>

            <div className="flex items-center gap-2">
              <select
                value={actionFilter}
                onChange={(e) => {
                  setActionFilter(e.target.value);
                  setPage(1);
                }}
                className="px-3 py-2 bg-slate-950/70 border border-slate-800 rounded-lg text-xs text-slate-200 focus:outline-none focus:border-blue-500"
              >
                <option value="ALL">Todas as Ações</option>
                <option value="CREATE">CREATE</option>
                <option value="UPDATE">UPDATE</option>
                <option value="DELETE">DELETE</option>
                <option value="ANONYMIZE">ANONYMIZE</option>
                <option value="EXPORT">EXPORT</option>
                <option value="GRANT_CONSENT">GRANT_CONSENT</option>
                <option value="REVOKE_CONSENT">REVOKE_CONSENT</option>
              </select>

              <select
                value={resourceFilter}
                onChange={(e) => {
                  setResourceFilter(e.target.value);
                  setPage(1);
                }}
                className="px-3 py-2 bg-slate-950/70 border border-slate-800 rounded-lg text-xs text-slate-200 focus:outline-none focus:border-blue-500"
              >
                <option value="ALL">Todos os Recursos</option>
                <option value="Player">Player</option>
                <option value="Consent">Consent</option>
                <option value="DataSubjectRequest">DataSubjectRequest</option>
                <option value="RetentionPolicy">RetentionPolicy</option>
                <option value="Campaign">Campaign</option>
                <option value="Template">Template</option>
              </select>

              <button
                type="submit"
                className="px-3 py-2 bg-blue-500/10 hover:bg-blue-500/20 border border-blue-500/30 text-blue-400 rounded-lg text-xs font-medium transition"
              >
                Filtrar
              </button>
            </div>
          </form>
        </div>

        {/* Logs Table */}
        <div className="bg-slate-900/40 border border-slate-800 rounded-xl overflow-hidden">
          {loading ? (
            <div className="p-8 text-center text-xs text-slate-400 flex items-center justify-center space-x-2">
              <RefreshCw className="w-4 h-4 animate-spin text-blue-400" />
              <span>Carregando logs de auditoria...</span>
            </div>
          ) : logs.length === 0 ? (
            <div className="p-12 text-center text-xs text-slate-500">
              Nenhum evento de auditoria localizado para os filtros informados.
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs border-collapse">
                <thead>
                  <tr className="bg-slate-950/50 border-b border-slate-800 text-slate-400 uppercase tracking-wider text-[10px]">
                    <th className="py-3 px-4 font-semibold">Data / Hora</th>
                    <th className="py-3 px-4 font-semibold">Ação</th>
                    <th className="py-3 px-4 font-semibold">Recurso</th>
                    <th className="py-3 px-4 font-semibold">Usuário / Ator</th>
                    <th className="py-3 px-4 font-semibold">IP & Conexão</th>
                    <th className="py-3 px-4 font-semibold text-right">Detalhes</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800/60 text-slate-300">
                  {logs.map((log) => (
                    <tr key={log.id} className="hover:bg-slate-800/30 transition">
                      <td className="py-3 px-4 font-mono text-[11px] text-slate-400">
                        {new Date(log.created_at).toLocaleString("pt-BR")}
                      </td>

                      <td className="py-3 px-4">
                        <span
                          className={`inline-block px-2 py-0.5 rounded text-[10px] font-bold border ${getActionColor(
                            log.action
                          )}`}
                        >
                          {log.action}
                        </span>
                      </td>

                      <td className="py-3 px-4">
                        <span className="font-mono text-slate-200">
                          {log.resource_type}
                        </span>
                        {log.resource_id && (
                          <span className="text-slate-500 font-mono ml-1">
                            #{log.resource_id}
                          </span>
                        )}
                      </td>

                      <td className="py-3 px-4">
                        <div className="text-slate-200 font-medium">
                          {log.user?.name || log.actor_id || (log.user_id ? `Usuário #${log.user_id}` : "Sistema")}
                        </div>
                        <div className="text-[10px] text-slate-500">
                          {log.user?.email || log.actor_type || "Automático"}
                        </div>
                      </td>

                      <td className="py-3 px-4 font-mono text-[11px] text-slate-400">
                        <div>{log.ip_address || "127.0.0.1"}</div>
                        <div className="text-[10px] text-slate-500 truncate max-w-[120px]">
                          {log.user_agent || "API / CLI"}
                        </div>
                      </td>

                      <td className="py-3 px-4 text-right">
                        <button
                          onClick={() => openInspector(log)}
                          className="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded text-[11px] transition inline-flex items-center space-x-1"
                        >
                          <Eye className="w-3 h-3 text-slate-400" />
                          <span>Inspecionar</span>
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {/* Pagination */}
          {totalPages > 1 && (
            <div className="p-3 border-t border-slate-800 bg-slate-950/40 flex items-center justify-between text-xs text-slate-400">
              <span>
                Página {page} de {totalPages} ({totalCount} registros)
              </span>
              <div className="flex items-center space-x-2">
                <button
                  disabled={page <= 1}
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  className="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 disabled:opacity-40 rounded transition"
                >
                  Anterior
                </button>
                <button
                  disabled={page >= totalPages}
                  onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                  className="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 disabled:opacity-40 rounded transition"
                >
                  Próxima
                </button>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Log Inspector Modal */}
      {inspectorOpen && selectedLog && (
        <div className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-xl max-w-2xl w-full max-h-[85vh] flex flex-col shadow-2xl">
            <div className="p-4 border-b border-slate-800 flex items-center justify-between">
              <div className="flex items-center space-x-2">
                <FileCode className="w-4 h-4 text-blue-400" />
                <h3 className="font-semibold text-sm text-white">
                  Auditoria de Ação #{selectedLog.id} — {selectedLog.action}
                </h3>
              </div>
              <button
                onClick={() => setInspectorOpen(false)}
                className="text-slate-400 hover:text-white text-xs px-2 py-1"
              >
                ✕ Fechar
              </button>
            </div>

            <div className="p-4 overflow-y-auto space-y-4 text-xs">
              <div className="grid grid-cols-2 gap-3 bg-slate-950/60 p-3 rounded-lg border border-slate-800 font-mono text-[11px]">
                <div>
                  <span className="text-slate-500">Recurso:</span>{" "}
                  <strong className="text-white">{selectedLog.resource_type}</strong> (ID: {selectedLog.resource_id || "—"})
                </div>
                <div>
                  <span className="text-slate-500">Horário:</span>{" "}
                  <span className="text-slate-300">{new Date(selectedLog.created_at).toLocaleString("pt-BR")}</span>
                </div>
                <div>
                  <span className="text-slate-500">Ator / Usuário:</span>{" "}
                  <span className="text-slate-300">{selectedLog.user?.name || selectedLog.actor_id || (selectedLog.user_id ? `ID #${selectedLog.user_id}` : "Sistema")}</span>
                </div>
                <div>
                  <span className="text-slate-500">IP:</span>{" "}
                  <span className="text-slate-300">{selectedLog.ip_address || "—"}</span>
                </div>
                <div className="col-span-2 truncate">
                  <span className="text-slate-500">User Agent:</span>{" "}
                  <span className="text-slate-300">{selectedLog.user_agent || "—"}</span>
                </div>
              </div>

              {/* Old vs New values */}
              <div className="space-y-3">
                {selectedLog.old_values && Object.keys(selectedLog.old_values).length > 0 && (
                  <div>
                    <h4 className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">
                      Valores Anteriores (Old Values)
                    </h4>
                    <pre className="p-3 bg-slate-950 rounded-lg border border-slate-800 text-[11px] font-mono text-rose-300 overflow-x-auto">
                      {JSON.stringify(selectedLog.old_values, null, 2)}
                    </pre>
                  </div>
                )}

                {selectedLog.new_values && Object.keys(selectedLog.new_values).length > 0 && (
                  <div>
                    <h4 className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">
                      Novos Valores (New Values)
                    </h4>
                    <pre className="p-3 bg-slate-950 rounded-lg border border-slate-800 text-[11px] font-mono text-emerald-300 overflow-x-auto">
                      {JSON.stringify(selectedLog.new_values, null, 2)}
                    </pre>
                  </div>
                )}

                {!selectedLog.old_values && !selectedLog.new_values && (
                  <div className="p-4 bg-slate-950/40 rounded-lg text-slate-500 text-center">
                    Nenhum payload de alteração de atributos associado a este evento.
                  </div>
                )}
              </div>
            </div>

            <div className="p-3 border-t border-slate-800 bg-slate-950/40 text-right">
              <button
                onClick={() => setInspectorOpen(false)}
                className="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs rounded transition"
              >
                Fechar Inspeção
              </button>
            </div>
          </div>
        </div>
      )}
    </AppLayout>
  );
}
