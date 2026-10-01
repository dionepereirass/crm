"use client";

import React, { useState, useEffect, useCallback } from "react";
import { AppLayout } from "@/components/layout/app-layout";
import { useAuth } from "@/hooks/use-auth";
import {
  analyticsService,
  ScheduledReportItem,
} from "@/services/analytics-service";
import {
  FileSpreadsheet,
  Download,
  Calendar,
  Clock,
  Plus,
  Trash2,
  RefreshCw,
  CheckCircle2,
  AlertTriangle,
  FileText,
  Send,
  Users,
  DollarSign,
  Zap,
  ShieldCheck,
} from "lucide-react";

export default function ReportsPage() {
  const { activePlatform } = useAuth(true);
  const [activeTab, setActiveTab] = useState<"generator" | "scheduled">("generator");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);

  // Generator State
  const [reportType, setReportType] = useState<string>("PLAYERS");
  const [period, setPeriod] = useState<string>("30d");
  const [previewData, setPreviewData] = useState<any[]>([]);
  const [totalRows, setTotalRows] = useState<number>(0);
  const [exporting, setExporting] = useState<boolean>(false);

  // Scheduled Reports State
  const [scheduledList, setScheduledList] = useState<ScheduledReportItem[]>([]);
  const [showScheduleModal, setShowScheduleModal] = useState<boolean>(false);
  const [newScheduleName, setNewScheduleName] = useState<string>("");
  const [newScheduleType, setNewScheduleType] = useState<string>("PLAYERS");
  const [newScheduleFrequency, setNewScheduleFrequency] = useState<string>("WEEKLY");
  const [newScheduleRecipients, setNewScheduleRecipients] = useState<string>("");
  const [newScheduleFormat, setNewScheduleFormat] = useState<string>("CSV");
  const [savingSchedule, setSavingSchedule] = useState<boolean>(false);

  const fetchPreview = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await analyticsService.getReportPreview(reportType, { period });
      setPreviewData(res.data || []);
      setTotalRows(res.total_rows || 0);
    } catch (err: any) {
      setError(err.message || "Erro ao gerar prévia do relatório.");
    } finally {
      setLoading(false);
    }
  }, [reportType, period]);

  const fetchScheduled = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const list = await analyticsService.listScheduledReports();
      setScheduledList(list || []);
    } catch (err: any) {
      setError(err.message || "Erro ao listar relatórios agendados.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (activePlatform) {
      if (activeTab === "generator") {
        fetchPreview();
      } else {
        fetchScheduled();
      }
    }
  }, [activePlatform, activeTab, fetchPreview, fetchScheduled]);

  const handleExport = async (format: "CSV" | "JSON", isAsync: boolean = false) => {
    setExporting(true);
    setError(null);
    setSuccessMsg(null);
    try {
      const res = await analyticsService.exportReport(reportType, format, { period }, isAsync);
      if (res.isAsync) {
        setSuccessMsg(`Exportação iniciada em segundo plano. ID da Tarefa: ${res.exportId}`);
      } else if (res.blob) {
        const url = window.URL.createObjectURL(res.blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `${reportType.toLowerCase()}_report_${Date.now()}.${format.toLowerCase()}`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        a.remove();
        setSuccessMsg(`Relatório ${format} exportado com sucesso!`);
      }
    } catch (err: any) {
      setError(err.message || "Falha na exportação do relatório.");
    } finally {
      setExporting(false);
    }
  };

  const handleCreateSchedule = async (e: React.FormEvent) => {
    e.preventDefault();
    setSavingSchedule(true);
    setError(null);
    try {
      const emails = newScheduleRecipients
        .split(",")
        .map((s) => s.trim())
        .filter((s) => s.length > 0);

      if (emails.length === 0) {
        throw new Error("Informe pelo menos um e-mail de destinatário.");
      }

      await analyticsService.createScheduledReport({
        name: newScheduleName,
        report_type: newScheduleType,
        frequency: newScheduleFrequency,
        recipients: emails,
        format: newScheduleFormat,
        filters: { period: "30d" },
      });

      setShowScheduleModal(false);
      setNewScheduleName("");
      setNewScheduleRecipients("");
      setSuccessMsg("Relatório recorrente agendado com sucesso!");
      fetchScheduled();
    } catch (err: any) {
      setError(err.message || "Falha ao criar agendamento.");
    } finally {
      setSavingSchedule(false);
    }
  };

  const handleDeleteSchedule = async (id: number) => {
    if (!confirm("Deseja realmente remover este agendamento?")) return;
    try {
      await analyticsService.deleteScheduledReport(id);
      setSuccessMsg("Agendamento removido.");
      fetchScheduled();
    } catch (err: any) {
      setError(err.message || "Erro ao remover agendamento.");
    }
  };

  return (
    <AppLayout
      title="Relatórios & Exportação"
      badge="INTELIGÊNCIA & AUDITORIA"
      actions={
        <div className="flex items-center space-x-2">
          {activeTab === "scheduled" && (
            <button
              onClick={() => setShowScheduleModal(true)}
              className="flex items-center space-x-1 px-3 py-1.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-xs font-bold rounded-lg transition"
            >
              <Plus className="w-4 h-4" />
              <span>Novo Agendamento</span>
            </button>
          )}

          <button
            onClick={() => (activeTab === "generator" ? fetchPreview() : fetchScheduled())}
            disabled={loading}
            className="p-1.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-lg text-slate-300 transition"
          >
            <RefreshCw className={`w-4 h-4 ${loading ? "animate-spin text-emerald-400" : ""}`} />
          </button>
        </div>
      }
    >
      <div className="p-6 space-y-6 max-w-7xl mx-auto">
        {/* Navigation Tabs */}
        <div className="flex border-b border-slate-800 space-x-2">
          <button
            onClick={() => setActiveTab("generator")}
            className={`flex items-center space-x-2 px-4 py-3 text-xs font-bold border-b-2 transition ${
              activeTab === "generator"
                ? "border-emerald-500 text-emerald-400 bg-emerald-500/10"
                : "border-transparent text-slate-400 hover:text-slate-200 hover:bg-slate-800/40"
            }`}
          >
            <FileSpreadsheet className="w-4 h-4" />
            <span>Gerador & Exportação</span>
          </button>

          <button
            onClick={() => setActiveTab("scheduled")}
            className={`flex items-center space-x-2 px-4 py-3 text-xs font-bold border-b-2 transition ${
              activeTab === "scheduled"
                ? "border-emerald-500 text-emerald-400 bg-emerald-500/10"
                : "border-transparent text-slate-400 hover:text-slate-200 hover:bg-slate-800/40"
            }`}
          >
            <Clock className="w-4 h-4" />
            <span>Relatórios Agendados</span>
          </button>
        </div>

        {/* Alerts / Feedback */}
        {error && (
          <div className="bg-rose-950/40 border border-rose-500/40 rounded-xl p-4 text-xs text-rose-300">
            {error}
          </div>
        )}
        {successMsg && (
          <div className="bg-emerald-950/40 border border-emerald-500/40 rounded-xl p-4 text-xs text-emerald-300 flex items-center space-x-2">
            <CheckCircle2 className="w-4 h-4 text-emerald-400 shrink-0" />
            <span>{successMsg}</span>
          </div>
        )}

        {/* TAB 1: GENERATOR & PREVIEW */}
        {activeTab === "generator" && (
          <div className="space-y-6">
            {/* Filter Bar */}
            <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-4 flex flex-wrap items-center justify-between gap-4">
              <div className="flex flex-wrap items-center gap-3">
                <div>
                  <label className="block text-[10px] uppercase font-bold text-slate-400 mb-1">
                    Tipo de Relatório
                  </label>
                  <select
                    value={reportType}
                    onChange={(e) => setReportType(e.target.value)}
                    className="bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-emerald-500"
                  >
                    <option value="PLAYERS">Jogadores & Retenção</option>
                    <option value="FINANCIAL">Financeiro & Transações</option>
                    <option value="MARKETING">Marketing & Disparos</option>
                    <option value="AUTOMATIONS">Automações & Jornadas</option>
                    <option value="PRIVACY">Conformidade & LGPD</option>
                  </select>
                </div>

                <div>
                  <label className="block text-[10px] uppercase font-bold text-slate-400 mb-1">
                    Período
                  </label>
                  <select
                    value={period}
                    onChange={(e) => setPeriod(e.target.value)}
                    className="bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-emerald-500"
                  >
                    <option value="today">Hoje</option>
                    <option value="yesterday">Ontem</option>
                    <option value="7d">Últimos 7 dias</option>
                    <option value="30d">Últimos 30 dias</option>
                    <option value="90d">Últimos 90 dias</option>
                    <option value="this_month">Este Mês</option>
                    <option value="last_month">Mês Anterior</option>
                  </select>
                </div>

                <div className="self-end">
                  <button
                    onClick={fetchPreview}
                    disabled={loading}
                    className="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-lg text-xs font-semibold text-white transition"
                  >
                    Filtrar Prévia
                  </button>
                </div>
              </div>

              {/* Export Buttons */}
              <div className="flex items-center space-x-2 self-end">
                <button
                  onClick={() => handleExport("CSV", false)}
                  disabled={exporting}
                  className="flex items-center space-x-1.5 px-3 py-1.5 bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-500/40 text-emerald-300 text-xs font-bold rounded-lg transition"
                >
                  <Download className="w-3.5 h-3.5" />
                  <span>Baixar CSV</span>
                </button>

                <button
                  onClick={() => handleExport("JSON", false)}
                  disabled={exporting}
                  className="flex items-center space-x-1.5 px-3 py-1.5 bg-cyan-500/20 hover:bg-cyan-500/30 border border-cyan-500/40 text-cyan-300 text-xs font-bold rounded-lg transition"
                >
                  <Download className="w-3.5 h-3.5" />
                  <span>Baixar JSON</span>
                </button>

                <button
                  onClick={() => handleExport("CSV", true)}
                  disabled={exporting}
                  title="Exportar grandes volumes em fila assíncrona"
                  className="flex items-center space-x-1.5 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 text-xs font-medium rounded-lg transition"
                >
                  <span>Export Assíncrono (Fila)</span>
                </button>
              </div>
            </div>

            {/* Preview Table */}
            <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-5 space-y-4">
              <div className="flex items-center justify-between">
                <div>
                  <h3 className="text-xs font-bold uppercase tracking-wider text-slate-300">
                    Prévia dos Dados ({totalRows} registros encontrados)
                  </h3>
                  <p className="text-[11px] text-slate-500">
                    Dados reais extraídos diretamente do PostgreSQL multi-tenant
                  </p>
                </div>
              </div>

              {loading ? (
                <div className="py-12 flex justify-center text-slate-400">
                  <div className="w-6 h-6 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin" />
                </div>
              ) : previewData.length === 0 ? (
                <div className="p-8 text-center text-xs text-slate-500">
                  Sem dados suficientes para o período selecionado.
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs">
                    <thead>
                      <tr className="border-b border-slate-800 text-[10px] text-slate-400 uppercase font-mono">
                        {Object.keys(previewData[0] || {}).map((col) => (
                          <th key={col} className="pb-2 px-3">
                            {col}
                          </th>
                        ))}
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-800/60 font-mono text-[11px]">
                      {previewData.slice(0, 15).map((row, idx) => (
                        <tr key={idx} className="hover:bg-slate-800/30 transition">
                          {Object.keys(row).map((col) => (
                            <td key={col} className="py-2.5 px-3 text-slate-300 max-w-xs truncate">
                              {typeof row[col] === "boolean"
                                ? row[col]
                                  ? "SIM"
                                  : "NÃO"
                                : String(row[col] ?? "-")}
                            </td>
                          ))}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                  {previewData.length > 15 && (
                    <div className="p-3 text-center text-[11px] text-slate-500 border-t border-slate-800">
                      Exibindo as primeiras 15 linhas da prévia. Baixe o CSV completo para acessar todos os {totalRows} registros.
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>
        )}

        {/* TAB 2: SCHEDULED REPORTS */}
        {activeTab === "scheduled" && (
          <div className="space-y-6">
            <div className="bg-[#0d131f] border border-slate-800 rounded-xl p-5 space-y-4">
              <div className="flex items-center justify-between">
                <div>
                  <h3 className="text-xs font-bold uppercase tracking-wider text-slate-300">
                    Rotinas Recorrentes de Relatórios
                  </h3>
                  <p className="text-[11px] text-slate-500">
                    Relatórios enviados periodicamente para a equipe por e-mail
                  </p>
                </div>
              </div>

              {loading ? (
                <div className="py-12 flex justify-center text-slate-400">
                  <div className="w-6 h-6 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin" />
                </div>
              ) : scheduledList.length === 0 ? (
                <div className="p-8 text-center text-xs text-slate-500 border border-dashed border-slate-800 rounded-xl">
                  Nenhum relatório agendado cadastrado para esta plataforma.
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs">
                    <thead>
                      <tr className="border-b border-slate-800 text-[10px] text-slate-400 uppercase font-mono">
                        <th className="pb-2">Nome</th>
                        <th className="pb-2">Tipo</th>
                        <th className="pb-2">Frequência</th>
                        <th className="pb-2">Formato</th>
                        <th className="pb-2">Destinatários</th>
                        <th className="pb-2">Próxima Execução</th>
                        <th className="pb-2 text-right">Ações</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-800/60 font-mono text-[11px]">
                      {scheduledList.map((item) => (
                        <tr key={item.id} className="hover:bg-slate-800/20">
                          <td className="py-2.5 font-sans font-medium text-white">{item.name}</td>
                          <td className="py-2.5">
                            <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-slate-300">
                              {item.report_type}
                            </span>
                          </td>
                          <td className="py-2.5 text-cyan-400 font-bold">{item.frequency}</td>
                          <td className="py-2.5 text-slate-400">{item.format}</td>
                          <td className="py-2.5 text-slate-300 font-sans">
                            {item.recipients?.join(", ")}
                          </td>
                          <td className="py-2.5 text-slate-400">{item.next_run_at || "-"}</td>
                          <td className="py-2.5 text-right">
                            <button
                              onClick={() => handleDeleteSchedule(item.id)}
                              className="p-1 hover:bg-rose-500/20 rounded text-slate-400 hover:text-rose-400 transition"
                              title="Remover agendamento"
                            >
                              <Trash2 className="w-4 h-4" />
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        )}

        {/* Create Schedule Modal */}
        {showScheduleModal && (
          <div className="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4">
            <div className="bg-[#0f172a] border border-slate-700 rounded-2xl p-6 max-w-md w-full space-y-4">
              <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 className="text-sm font-bold text-white">Agendar Envio de Relatório</h3>
                <button
                  onClick={() => setShowScheduleModal(false)}
                  className="text-slate-400 hover:text-white text-xs"
                >
                  ✕
                </button>
              </div>

              <form onSubmit={handleCreateSchedule} className="space-y-4 text-xs">
                <div>
                  <label className="block text-[11px] text-slate-300 font-medium mb-1">
                    Nome do Agendamento *
                  </label>
                  <input
                    type="text"
                    required
                    value={newScheduleName}
                    onChange={(e) => setNewScheduleName(e.target.value)}
                    placeholder="Ex: Resumo Semanal de Jogadores"
                    className="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-emerald-500"
                  />
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-[11px] text-slate-300 font-medium mb-1">
                      Tipo de Relatório *
                    </label>
                    <select
                      value={newScheduleType}
                      onChange={(e) => setNewScheduleType(e.target.value)}
                      className="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-emerald-500"
                    >
                      <option value="PLAYERS">Jogadores</option>
                      <option value="FINANCIAL">Financeiro</option>
                      <option value="MARKETING">Marketing</option>
                      <option value="AUTOMATIONS">Automações</option>
                      <option value="PRIVACY">Privacidade</option>
                    </select>
                  </div>

                  <div>
                    <label className="block text-[11px] text-slate-300 font-medium mb-1">
                      Frequência *
                    </label>
                    <select
                      value={newScheduleFrequency}
                      onChange={(e) => setNewScheduleFrequency(e.target.value)}
                      className="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-emerald-500"
                    >
                      <option value="DAILY">Diário</option>
                      <option value="WEEKLY">Semanal</option>
                      <option value="MONTHLY">Mensal</option>
                    </select>
                  </div>
                </div>

                <div>
                  <label className="block text-[11px] text-slate-300 font-medium mb-1">
                    Formato *
                  </label>
                  <select
                    value={newScheduleFormat}
                    onChange={(e) => setNewScheduleFormat(e.target.value)}
                    className="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-emerald-500"
                  >
                    <option value="CSV">CSV (Planilha)</option>
                    <option value="JSON">JSON</option>
                  </select>
                </div>

                <div>
                  <label className="block text-[11px] text-slate-300 font-medium mb-1">
                    E-mails dos Destinatários (separados por vírgula) *
                  </label>
                  <textarea
                    required
                    value={newScheduleRecipients}
                    onChange={(e) => setNewScheduleRecipients(e.target.value)}
                    placeholder="operacao@betcrm.com, diretoria@betcrm.com"
                    rows={3}
                    className="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-emerald-500"
                  />
                </div>

                <div className="flex justify-end space-x-2 pt-2 border-t border-slate-800">
                  <button
                    type="button"
                    onClick={() => setShowScheduleModal(false)}
                    className="px-3 py-2 bg-slate-800 hover:bg-slate-700 rounded-lg text-slate-300 transition"
                  >
                    Cancelar
                  </button>
                  <button
                    type="submit"
                    disabled={savingSchedule}
                    className="px-4 py-2 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold rounded-lg transition"
                  >
                    {savingSchedule ? "Salvando..." : "Confirmar Agendamento"}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
