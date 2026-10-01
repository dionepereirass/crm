"use client";

import React, { useEffect, useState } from "react";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import {
  campaignService,
  Campaign,
} from "@/services/campaign-service";
import {
  Mail,
  MessageSquare,
  Plus,
  RefreshCw,
  Search,
  CheckCircle2,
  XCircle,
  AlertTriangle,
  Play,
  Pause,
  RotateCcw,
  Ban,
  Trash2,
  Eye,
  Edit,
  Clock,
  Send,
  Users,
  BarChart3,
  Flame,
} from "lucide-react";

export default function CampaignsPage() {
  const [campaigns, setCampaigns] = useState<Campaign[]>([]);
  const [loading, setLoading] = useState(true);
  const [channelFilter, setChannelFilter] = useState("ALL");
  const [statusFilter, setStatusFilter] = useState("ALL");
  const [search, setSearch] = useState("");
  const [actionError, setActionError] = useState<string | null>(null);
  const [actionSuccess, setActionSuccess] = useState<string | null>(null);
  const [actionLoadingId, setActionLoadingId] = useState<number | null>(null);

  // Modal de Confirmação de Disparo
  const [launchModalCampaign, setLaunchModalCampaign] = useState<Campaign | null>(null);
  const [launchConfirmed, setLaunchConfirmed] = useState(false);

  const fetchCampaigns = async () => {
    try {
      setLoading(true);
      setActionError(null);
      const res = await campaignService.list({
        channel: channelFilter,
        status: statusFilter,
        search: search || undefined,
      });
      setCampaigns(res.data);
    } catch (err: any) {
      setActionError(err.message || "Falha ao carregar campanhas.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchCampaigns();
  }, [channelFilter, statusFilter]);

  const handleLaunch = async () => {
    if (!launchModalCampaign) return;
    try {
      setActionLoadingId(launchModalCampaign.id);
      setActionError(null);
      setActionSuccess(null);
      await campaignService.launch(launchModalCampaign.id, true);
      setActionSuccess(`Campanha #${launchModalCampaign.id} disparada com sucesso!`);
      setLaunchModalCampaign(null);
      setLaunchConfirmed(false);
      fetchCampaigns();
    } catch (err: any) {
      setActionError(err.message || "Erro ao disparar campanha.");
    } finally {
      setActionLoadingId(null);
    }
  };

  const handlePause = async (campaign: Campaign) => {
    try {
      setActionLoadingId(campaign.id);
      setActionError(null);
      setActionSuccess(null);
      await campaignService.pause(campaign.id);
      setActionSuccess(`Campanha #${campaign.id} pausada com sucesso.`);
      fetchCampaigns();
    } catch (err: any) {
      setActionError(err.message || "Erro ao pausar campanha.");
    } finally {
      setActionLoadingId(null);
    }
  };

  const handleResume = async (campaign: Campaign) => {
    try {
      setActionLoadingId(campaign.id);
      setActionError(null);
      setActionSuccess(null);
      await campaignService.resume(campaign.id);
      setActionSuccess(`Campanha #${campaign.id} retomada com sucesso.`);
      fetchCampaigns();
    } catch (err: any) {
      setActionError(err.message || "Erro ao retomar campanha.");
    } finally {
      setActionLoadingId(null);
    }
  };

  const handleCancel = async (campaign: Campaign) => {
    if (!confirm(`Tem certeza que deseja cancelar a campanha '${campaign.name}'?`)) return;
    try {
      setActionLoadingId(campaign.id);
      setActionError(null);
      setActionSuccess(null);
      await campaignService.cancel(campaign.id);
      setActionSuccess(`Campanha #${campaign.id} cancelada.`);
      fetchCampaigns();
    } catch (err: any) {
      setActionError(err.message || "Erro ao cancelar campanha.");
    } finally {
      setActionLoadingId(null);
    }
  };

  const handleDelete = async (campaign: Campaign) => {
    if (!confirm(`Deseja realmente excluir a campanha '${campaign.name}'?`)) return;
    try {
      setActionLoadingId(campaign.id);
      setActionError(null);
      setActionSuccess(null);
      await campaignService.delete(campaign.id);
      setActionSuccess(`Campanha #${campaign.id} excluída com sucesso.`);
      fetchCampaigns();
    } catch (err: any) {
      setActionError(err.message || "Erro ao excluir campanha.");
    } finally {
      setActionLoadingId(null);
    }
  };

  // KPIs
  const totalCampaigns = campaigns.length;
  const processingCount = campaigns.filter((c) => c.status === "PROCESSING").length;
  const totalSent = campaigns.reduce((acc, c) => acc + (c.messages_sent || 0), 0);
  const totalDelivered = campaigns.reduce((acc, c) => acc + (c.messages_delivered || 0), 0);
  const overallDeliveryRate = totalSent > 0 ? Math.round((totalDelivered / totalSent) * 100) : 0;

  const getStatusBadge = (status: Campaign["status"]) => {
    switch (status) {
      case "DRAFT":
        return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-800 text-gray-300 border border-gray-700">RASCUNHO</span>;
      case "VALIDATING":
        return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-yellow-950 text-yellow-400 border border-yellow-800">VALIDANDO</span>;
      case "READY":
        return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-blue-950 text-blue-400 border border-blue-800">PRONTA</span>;
      case "SCHEDULED":
        return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-purple-950 text-purple-400 border border-purple-800">AGENDADA</span>;
      case "PROCESSING":
        return (
          <span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-semibold bg-amber-950 text-amber-400 border border-amber-800 animate-pulse">
            <span className="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
            DISPARANDO
          </span>
        );
      case "PAUSED":
        return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-orange-950 text-orange-400 border border-orange-800">PAUSADA</span>;
      case "COMPLETED":
        return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-950 text-emerald-400 border border-emerald-800">CONCLUÍDA</span>;
      case "CANCELLED":
        return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-zinc-800 text-zinc-400 border border-zinc-700">CANCELADA</span>;
      case "FAILED":
        return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-rose-950 text-rose-400 border border-rose-800">FALHA</span>;
      default:
        return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-800 text-gray-300">{status}</span>;
    }
  };

  return (
    <AppLayout>
      <div className="space-y-6">
        {/* Top Header */}
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
              <Flame className="w-6 h-6 text-emerald-500" />
              Gestão de Campanhas
            </h1>
            <p className="text-sm text-gray-400 mt-1">
              Crie, agende e monitore disparos segmentados em massa via E-mail e SMS com controle estrito de consentimento e idempotência.
            </p>
          </div>

          <div className="flex items-center gap-3">
            <button
              onClick={fetchCampaigns}
              disabled={loading}
              className="px-3 py-2 bg-gray-800 hover:bg-gray-700 text-gray-200 rounded-lg text-sm font-medium border border-gray-700 flex items-center gap-2 transition-colors disabled:opacity-50"
            >
              <RefreshCw className={`w-4 h-4 ${loading ? "animate-spin" : ""}`} />
              Atualizar
            </button>

            <Link
              href="/campaigns/new"
              className="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-semibold flex items-center gap-2 transition-colors shadow-sm shadow-emerald-900/50"
            >
              <Plus className="w-4 h-4" />
              Nova Campanha
            </Link>
          </div>
        </div>

        {/* Alerts */}
        {actionError && (
          <div className="p-4 bg-rose-950/60 border border-rose-800 rounded-lg text-rose-200 text-sm flex items-center gap-2">
            <AlertTriangle className="w-5 h-5 flex-shrink-0 text-rose-400" />
            <span>{actionError}</span>
          </div>
        )}

        {actionSuccess && (
          <div className="p-4 bg-emerald-950/60 border border-emerald-800 rounded-lg text-emerald-200 text-sm flex items-center gap-2">
            <CheckCircle2 className="w-5 h-5 flex-shrink-0 text-emerald-400" />
            <span>{actionSuccess}</span>
          </div>
        )}

        {/* Metric Cards */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div className="p-4 bg-gray-900 border border-gray-800 rounded-xl space-y-1">
            <div className="flex items-center justify-between text-gray-400 text-xs uppercase tracking-wider font-semibold">
              Total de Campanhas
              <Flame className="w-4 h-4 text-gray-500" />
            </div>
            <div className="text-2xl font-bold text-white">{totalCampaigns}</div>
            <div className="text-xs text-gray-500">Cadastradas na plataforma</div>
          </div>

          <div className="p-4 bg-gray-900 border border-gray-800 rounded-xl space-y-1">
            <div className="flex items-center justify-between text-gray-400 text-xs uppercase tracking-wider font-semibold">
              Em Processamento
              <Play className="w-4 h-4 text-amber-500" />
            </div>
            <div className="text-2xl font-bold text-amber-400">{processingCount}</div>
            <div className="text-xs text-gray-500">Filas ativas de mensageria</div>
          </div>

          <div className="p-4 bg-gray-900 border border-gray-800 rounded-xl space-y-1">
            <div className="flex items-center justify-between text-gray-400 text-xs uppercase tracking-wider font-semibold">
              Mensagens Enviadas
              <Send className="w-4 h-4 text-blue-500" />
            </div>
            <div className="text-2xl font-bold text-blue-400">{totalSent.toLocaleString("pt-BR")}</div>
            <div className="text-xs text-gray-500">Disparadas aos provedores</div>
          </div>

          <div className="p-4 bg-gray-900 border border-gray-800 rounded-xl space-y-1">
            <div className="flex items-center justify-between text-gray-400 text-xs uppercase tracking-wider font-semibold">
              Taxa de Entrega
              <CheckCircle2 className="w-4 h-4 text-emerald-500" />
            </div>
            <div className="text-2xl font-bold text-emerald-400">{overallDeliveryRate}%</div>
            <div className="text-xs text-gray-500">{totalDelivered.toLocaleString("pt-BR")} entregues confirmadas</div>
          </div>
        </div>

        {/* Filter Bar */}
        <div className="p-4 bg-gray-900 border border-gray-800 rounded-xl flex flex-col md:flex-row items-center gap-4 justify-between">
          <div className="flex-1 w-full md:max-w-md relative">
            <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" />
            <input
              type="text"
              placeholder="Buscar por nome ou descrição..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              onKeyDown={(e) => e.key === "Enter" && fetchCampaigns()}
              className="w-full pl-9 pr-4 py-2 bg-gray-950 border border-gray-800 rounded-lg text-sm text-gray-200 focus:outline-none focus:border-emerald-500"
            />
          </div>

          <div className="flex flex-wrap items-center gap-3 w-full md:w-auto">
            <div className="flex items-center gap-2">
              <span className="text-xs text-gray-400">Canal:</span>
              <select
                value={channelFilter}
                onChange={(e) => setChannelFilter(e.target.value)}
                className="bg-gray-950 border border-gray-800 text-gray-200 text-sm rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-500"
              >
                <option value="ALL">Todos os Canais</option>
                <option value="EMAIL">E-mail</option>
                <option value="SMS">SMS</option>
              </select>
            </div>

            <div className="flex items-center gap-2">
              <span className="text-xs text-gray-400">Status:</span>
              <select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
                className="bg-gray-950 border border-gray-800 text-gray-200 text-sm rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-500"
              >
                <option value="ALL">Todos os Status</option>
                <option value="DRAFT">Rascunho</option>
                <option value="READY">Pronta</option>
                <option value="SCHEDULED">Agendada</option>
                <option value="PROCESSING">Disparando</option>
                <option value="PAUSED">Pausada</option>
                <option value="COMPLETED">Concluída</option>
                <option value="CANCELLED">Cancelada</option>
                <option value="FAILED">Falha</option>
              </select>
            </div>
          </div>
        </div>

        {/* Campaign List */}
        <div className="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden shadow-sm">
          {loading ? (
            <div className="py-16 text-center text-gray-400">
              <RefreshCw className="w-8 h-8 animate-spin mx-auto text-emerald-500 mb-3" />
              <p>Carregando campanhas...</p>
            </div>
          ) : campaigns.length === 0 ? (
            <div className="py-16 text-center text-gray-400 space-y-3">
              <Flame className="w-12 h-12 text-gray-600 mx-auto" />
              <div className="text-lg font-medium text-gray-300">Nenhuma campanha encontrada</div>
              <p className="text-sm text-gray-500 max-w-sm mx-auto">
                Crie sua primeira campanha para disparar mensagens personalizadas aos jogadores da sua base.
              </p>
              <Link
                href="/campaigns/new"
                className="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-semibold transition-colors mt-2"
              >
                <Plus className="w-4 h-4" />
                Criar Campanha
              </Link>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm text-gray-300">
                <thead className="bg-gray-950/80 text-xs uppercase text-gray-400 border-b border-gray-800">
                  <tr>
                    <th className="px-5 py-3">Campanha</th>
                    <th className="px-5 py-3">Canal & Segmento</th>
                    <th className="px-5 py-3">Template & Versão</th>
                    <th className="px-5 py-3">Status</th>
                    <th className="px-5 py-3">Progresso / Envios</th>
                    <th className="px-5 py-3">Agendamento</th>
                    <th className="px-5 py-3 text-right">Ações</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-800/60 font-normal">
                  {campaigns.map((camp) => (
                    <tr key={camp.id} className="hover:bg-gray-800/30 transition-colors">
                      <td className="px-5 py-4">
                        <div className="font-semibold text-white">
                          <Link href={`/campaigns/${camp.id}`} className="hover:text-emerald-400 transition-colors">
                            {camp.name}
                          </Link>
                        </div>
                        {camp.description && (
                          <div className="text-xs text-gray-400 line-clamp-1 mt-0.5">{camp.description}</div>
                        )}
                        <div className="text-[11px] text-gray-500 mt-1 font-mono">UUID: {camp.uuid.slice(0, 8)}...</div>
                      </td>

                      <td className="px-5 py-4">
                        <div className="flex items-center gap-1.5 mb-1">
                          {camp.channel === "EMAIL" ? (
                            <span className="inline-flex items-center gap-1 text-xs text-blue-400 bg-blue-950/80 px-2 py-0.5 rounded border border-blue-900">
                              <Mail className="w-3 h-3" /> E-mail
                            </span>
                          ) : (
                            <span className="inline-flex items-center gap-1 text-xs text-emerald-400 bg-emerald-950/80 px-2 py-0.5 rounded border border-emerald-900">
                              <MessageSquare className="w-3 h-3" /> SMS
                            </span>
                          )}
                        </div>
                        <div className="text-xs text-gray-300 font-medium flex items-center gap-1">
                          <Users className="w-3 h-3 text-gray-400" />
                          {camp.segment?.name || `Segmento #${camp.segment_id}`}
                        </div>
                      </td>

                      <td className="px-5 py-4">
                        <div className="text-xs text-gray-200 font-medium">{camp.template?.name || `Template #${camp.template_id}`}</div>
                        <div className="text-[11px] text-gray-400">
                          Versão: v{camp.template_version?.version || 1} • {camp.provider?.name || "Provider Padrão"}
                        </div>
                      </td>

                      <td className="px-5 py-4 whitespace-nowrap">{getStatusBadge(camp.status)}</td>

                      <td className="px-5 py-4">
                        <div className="w-40 space-y-1.5">
                          <div className="flex justify-between text-xs text-gray-400">
                            <span>{camp.progress_percentage}%</span>
                            <span>{camp.messages_sent} / {camp.total_recipients}</span>
                          </div>
                          <div className="w-full bg-gray-800 rounded-full h-1.5 overflow-hidden">
                            <div
                              className={`h-full rounded-full transition-all duration-300 ${
                                camp.status === "COMPLETED"
                                  ? "bg-emerald-500"
                                  : camp.status === "FAILED"
                                  ? "bg-rose-500"
                                  : "bg-emerald-500"
                              }`}
                              style={{ width: `${Math.min(camp.progress_percentage, 100)}%` }}
                            ></div>
                          </div>
                        </div>
                      </td>

                      <td className="px-5 py-4 text-xs text-gray-400 whitespace-nowrap">
                        {camp.scheduled_at ? (
                          <div className="flex items-center gap-1 text-purple-400">
                            <Clock className="w-3 h-3" />
                            {new Date(camp.scheduled_at).toLocaleString("pt-BR")}
                          </div>
                        ) : camp.started_at ? (
                          <div className="text-gray-400">
                            Iniciado: {new Date(camp.started_at).toLocaleTimeString("pt-BR")}
                          </div>
                        ) : (
                          <span className="text-gray-500">Imediato</span>
                        )}
                      </td>

                      <td className="px-5 py-4 text-right whitespace-nowrap">
                        <div className="flex items-center justify-end gap-1.5">
                          <Link
                            href={`/campaigns/${camp.id}`}
                            className="p-1.5 hover:bg-gray-800 text-gray-300 hover:text-white rounded-lg transition-colors"
                            title="Ver Detalhes"
                          >
                            <Eye className="w-4 h-4" />
                          </Link>

                          {["DRAFT", "READY"].includes(camp.status) && (
                            <Link
                              href={`/campaigns/${camp.id}/edit`}
                              className="p-1.5 hover:bg-gray-800 text-gray-300 hover:text-white rounded-lg transition-colors"
                              title="Editar"
                            >
                              <Edit className="w-4 h-4" />
                            </Link>
                          )}

                          {camp.status === "READY" && (
                            <button
                              onClick={() => {
                                setLaunchModalCampaign(camp);
                                setLaunchConfirmed(false);
                              }}
                              disabled={actionLoadingId === camp.id}
                              className="p-1.5 bg-emerald-950/80 hover:bg-emerald-900 border border-emerald-800 text-emerald-300 rounded-lg transition-colors"
                              title="Disparar Campanha"
                            >
                              <Play className="w-4 h-4" />
                            </button>
                          )}

                          {camp.status === "PROCESSING" && (
                            <button
                              onClick={() => handlePause(camp)}
                              disabled={actionLoadingId === camp.id}
                              className="p-1.5 bg-amber-950/80 hover:bg-amber-900 border border-amber-800 text-amber-300 rounded-lg transition-colors"
                              title="Pausar Disparo"
                            >
                              <Pause className="w-4 h-4" />
                            </button>
                          )}

                          {camp.status === "PAUSED" && (
                            <button
                              onClick={() => handleResume(camp)}
                              disabled={actionLoadingId === camp.id}
                              className="p-1.5 bg-blue-950/80 hover:bg-blue-900 border border-blue-800 text-blue-300 rounded-lg transition-colors"
                              title="Retomar Disparo"
                            >
                              <RotateCcw className="w-4 h-4" />
                            </button>
                          )}

                          {["READY", "SCHEDULED", "PROCESSING", "PAUSED"].includes(camp.status) && (
                            <button
                              onClick={() => handleCancel(camp)}
                              disabled={actionLoadingId === camp.id}
                              className="p-1.5 hover:bg-gray-800 text-gray-400 hover:text-rose-400 rounded-lg transition-colors"
                              title="Cancelar Campanha"
                            >
                              <Ban className="w-4 h-4" />
                            </button>
                          )}

                          {camp.status === "DRAFT" && (
                            <button
                              onClick={() => handleDelete(camp)}
                              disabled={actionLoadingId === camp.id}
                              className="p-1.5 hover:bg-gray-800 text-gray-400 hover:text-rose-400 rounded-lg transition-colors"
                              title="Excluir Rascunho"
                            >
                              <Trash2 className="w-4 h-4" />
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>

      {/* Modal de Confirmação de Disparo */}
      {launchModalCampaign && (
        <div className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-gray-900 border border-gray-800 rounded-2xl max-w-lg w-full p-6 space-y-5 shadow-2xl">
            <div className="flex items-center gap-3 text-emerald-400">
              <AlertTriangle className="w-6 h-6 text-amber-400" />
              <h3 className="text-lg font-bold text-white">Confirmar Disparo de Campanha</h3>
            </div>

            <p className="text-sm text-gray-300 leading-relaxed">
              Você está prestes a iniciar o processamento da campanha{" "}
              <strong className="text-white">"{launchModalCampaign.name}"</strong> via canal{" "}
              <strong className="text-white">{launchModalCampaign.channel}</strong>.
            </p>

            <div className="p-4 bg-gray-950 border border-gray-800 rounded-xl space-y-2 text-xs text-gray-400">
              <div className="flex justify-between">
                <span>Segmento Alvo:</span>
                <span className="text-gray-200 font-medium">{launchModalCampaign.segment?.name || "Definido"}</span>
              </div>
              <div className="flex justify-between">
                <span>Total Estimado:</span>
                <span className="text-gray-200 font-medium">{launchModalCampaign.total_recipients} destinatários</span>
              </div>
              <div className="flex justify-between">
                <span>Filtro de Consentimento:</span>
                <span className="text-emerald-400 font-medium">LGPD Ativo (somente com opt-in)</span>
              </div>
            </div>

            <div className="flex items-start gap-2.5 p-3 bg-amber-950/30 border border-amber-900/50 rounded-lg">
              <input
                type="checkbox"
                id="confirm-launch"
                checked={launchConfirmed}
                onChange={(e) => setLaunchConfirmed(e.target.checked)}
                className="mt-0.5 rounded border-gray-700 bg-gray-900 text-emerald-600 focus:ring-emerald-500"
              />
              <label htmlFor="confirm-launch" className="text-xs text-amber-200/90 leading-tight cursor-pointer">
                Declaro que revisei o conteúdo do template e autorizo o envio em lote para todos os jogadores elegíveis da base.
              </label>
            </div>

            <div className="flex items-center justify-end gap-3 pt-2">
              <button
                type="button"
                onClick={() => {
                  setLaunchModalCampaign(null);
                  setLaunchConfirmed(false);
                }}
                disabled={actionLoadingId === launchModalCampaign.id}
                className="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 text-sm font-medium rounded-lg transition-colors"
              >
                Cancelar
              </button>

              <button
                type="button"
                onClick={handleLaunch}
                disabled={!launchConfirmed || actionLoadingId === launchModalCampaign.id}
                className="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-lg transition-colors flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed shadow-md shadow-emerald-900/50"
              >
                {actionLoadingId === launchModalCampaign.id ? (
                  <RefreshCw className="w-4 h-4 animate-spin" />
                ) : (
                  <Play className="w-4 h-4" />
                )}
                Confirmar Disparo
              </button>
            </div>
          </div>
        </div>
      )}
    </AppLayout>
  );
}
