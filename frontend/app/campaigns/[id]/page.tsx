"use client";

import React, { useEffect, useState, use } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { AppLayout } from "@/components/layout/app-layout";
import {
  campaignService,
  Campaign,
  CampaignStats,
  CampaignPreviewResult,
} from "@/services/campaign-service";
import {
  analyticsService,
  CampaignAnalyticsResponse,
  CampaignEventItem,
} from "@/services/analytics-service";
import {
  ArrowLeft,
  Mail,
  MessageSquare,
  Play,
  Pause,
  RotateCcw,
  Ban,
  Trash2,
  RefreshCw,
  Edit,
  Clock,
  Send,
  Users,
  CheckCircle2,
  AlertTriangle,
  FileText,
  Radio,
  Eye,
  BarChart3,
  Flame,
  Check,
  Calendar,
  Layers,
  TrendingUp,
  Percent,
} from "lucide-react";

export default function CampaignDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = use(params);
  const router = useRouter();

  const [campaign, setCampaign] = useState<Campaign | null>(null);
  const [stats, setStats] = useState<CampaignStats | null>(null);
  const [preview, setPreview] = useState<CampaignPreviewResult | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState(false);

  // Tabs: overview, analytics, events
  const [activeTab, setActiveTab] = useState<"overview" | "analytics" | "events">("overview");

  // Analytics State
  const [analyticsData, setAnalyticsData] = useState<CampaignAnalyticsResponse | null>(null);
  const [analyticsLoading, setAnalyticsLoading] = useState(false);
  const [rebuilding, setRebuilding] = useState(false);

  // Events Timeline State
  const [eventsList, setEventsList] = useState<CampaignEventItem[]>([]);
  const [eventsPagination, setEventsPagination] = useState<any>(null);
  const [eventsPage, setEventsPage] = useState(1);
  const [eventsLoading, setEventsLoading] = useState(false);

  // Modals
  const [showTestModal, setShowTestModal] = useState(false);
  const [testRecipient, setTestRecipient] = useState("");
  const [testSending, setTestSending] = useState(false);
  const [showLaunchModal, setShowLaunchModal] = useState(false);
  const [launchConfirmed, setLaunchConfirmed] = useState(false);

  const fetchCampaignData = async () => {
    try {
      setLoading(true);
      setError(null);
      const [campData, statsData] = await Promise.all([
        campaignService.getById(id),
        campaignService.getStats(id).catch(() => null),
      ]);
      setCampaign(campData);
      setStats(statsData);

      // Load preview
      campaignService
        .preview(id)
        .then((p) => setPreview(p))
        .catch(() => null);
    } catch (err: any) {
      setError(err.message || "Erro ao carregar dados da campanha.");
    } finally {
      setLoading(false);
    }
  };

  const loadAnalytics = async () => {
    try {
      setAnalyticsLoading(true);
      const data = await analyticsService.getCampaignAnalytics(id);
      setAnalyticsData(data);
    } catch (err: any) {
      console.error("Erro ao carregar analytics:", err);
    } finally {
      setAnalyticsLoading(false);
    }
  };

  const loadEvents = async (page: number = 1) => {
    try {
      setEventsLoading(true);
      const res = await analyticsService.getCampaignEvents(id, page, 25);
      setEventsList(res.data);
      setEventsPagination(res.pagination);
      setEventsPage(page);
    } catch (err: any) {
      console.error("Erro ao carregar eventos:", err);
    } finally {
      setEventsLoading(false);
    }
  };

  const handleRebuildAnalytics = async () => {
    try {
      setRebuilding(true);
      await analyticsService.rebuildCampaignAnalytics(id);
      await loadAnalytics();
      setSuccessMsg("Métricas da campanha recalculadas com sucesso a partir do histórico de eventos!");
    } catch (err: any) {
      setError(err.message || "Falha ao recalcular métricas.");
    } finally {
      setRebuilding(false);
    }
  };

  useEffect(() => {
    fetchCampaignData();
  }, [id]);

  useEffect(() => {
    if (activeTab === "analytics") {
      loadAnalytics();
    } else if (activeTab === "events") {
      loadEvents(1);
    }
  }, [activeTab, id]);

  // Polling automático caso a campanha esteja em processamento
  useEffect(() => {
    if (!campaign || campaign.status !== "PROCESSING") return;

    const interval = setInterval(() => {
      campaignService
        .getById(id)
        .then((updated) => {
          setCampaign(updated);
          if (updated.status !== "PROCESSING") {
            clearInterval(interval);
          }
        })
        .catch(() => null);
    }, 5000);

    return () => clearInterval(interval);
  }, [campaign, id]);

  const handleLaunch = async () => {
    if (!launchConfirmed) return;
    try {
      setActionLoading(true);
      setError(null);
      await campaignService.launch(id);
      setSuccessMsg("Campanha lançada com sucesso! As mensagens estão sendo processadas nas filas.");
      setShowLaunchModal(false);
      fetchCampaignData();
    } catch (err: any) {
      setError(err.message || "Erro ao disparar campanha.");
    } finally {
      setActionLoading(false);
    }
  };

  const handlePause = async () => {
    try {
      setActionLoading(true);
      setError(null);
      await campaignService.pause(id);
      setSuccessMsg("Disparo pausado. Novas mensagens não serão criadas até a retomada.");
      fetchCampaignData();
    } catch (err: any) {
      setError(err.message || "Erro ao pausar campanha.");
    } finally {
      setActionLoading(false);
    }
  };

  const handleResume = async () => {
    try {
      setActionLoading(true);
      setError(null);
      await campaignService.resume(id);
      setSuccessMsg("Disparo retomado com sucesso!");
      fetchCampaignData();
    } catch (err: any) {
      setError(err.message || "Erro ao retomar campanha.");
    } finally {
      setActionLoading(false);
    }
  };

  const handleCancel = async () => {
    if (!confirm("Tem certeza que deseja cancelar esta campanha definitivamente?")) return;
    try {
      setActionLoading(true);
      setError(null);
      await campaignService.cancel(id);
      setSuccessMsg("Campanha cancelada com sucesso.");
      fetchCampaignData();
    } catch (err: any) {
      setError(err.message || "Erro ao cancelar campanha.");
    } finally {
      setActionLoading(false);
    }
  };

  const handleDelete = async () => {
    if (!confirm("Tem certeza que deseja excluir esta campanha? Esta ação não pode ser desfeita.")) return;
    try {
      setActionLoading(true);
      await campaignService.delete(id);
      router.push("/campaigns");
    } catch (err: any) {
      setError(err.message || "Erro ao excluir campanha.");
      setActionLoading(false);
    }
  };

  const handleTestSend = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!testRecipient) return;

    try {
      setTestSending(true);
      setError(null);
      await campaignService.testSend(id, testRecipient);
      setSuccessMsg(`Mensagem de teste enviada com sucesso para ${testRecipient}!`);
      setShowTestModal(false);
      setTestRecipient("");
    } catch (err: any) {
      setError(err.message || "Erro ao enviar mensagem de teste.");
    } finally {
      setTestSending(false);
    }
  };

  const getStatusBadge = (status: string) => {
    switch (status) {
      case "DRAFT":
        return <span className="px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-800 text-gray-300 border border-gray-700">RASCUNHO</span>;
      case "READY":
        return <span className="px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-950 text-blue-400 border border-blue-800">PRONTA PARA DISPARO</span>;
      case "SCHEDULED":
        return <span className="px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-950 text-purple-400 border border-purple-800">AGENDADA</span>;
      case "PROCESSING":
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-950 text-amber-400 border border-amber-800 animate-pulse">
            <span className="w-2 h-2 rounded-full bg-amber-400"></span>
            DISPARANDO AGORA
          </span>
        );
      case "PAUSED":
        return <span className="px-2.5 py-1 rounded-full text-xs font-semibold bg-orange-950 text-orange-400 border border-orange-800">PAUSADA</span>;
      case "COMPLETED":
        return <span className="px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-950 text-emerald-400 border border-emerald-800">CONCLUÍDA</span>;
      case "CANCELLED":
        return <span className="px-2.5 py-1 rounded-full text-xs font-semibold bg-zinc-800 text-zinc-400 border border-zinc-700">CANCELADA</span>;
      case "FAILED":
        return <span className="px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-950 text-rose-400 border border-rose-800">FALHA</span>;
      default:
        return <span className="px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-800 text-gray-300">{status}</span>;
    }
  };

  const getEventBadge = (eventType: string) => {
    switch (eventType) {
      case "SENT":
        return <span className="px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-950 text-blue-400 border border-blue-800">SENT</span>;
      case "DELIVERED":
        return <span className="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-950 text-emerald-400 border border-emerald-800">DELIVERED</span>;
      case "OPENED":
        return <span className="px-2 py-0.5 rounded text-[11px] font-semibold bg-purple-950 text-purple-400 border border-purple-800">OPENED</span>;
      case "CLICKED":
        return <span className="px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-950 text-amber-400 border border-amber-800">CLICKED</span>;
      case "BOUNCED":
        return <span className="px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-950 text-rose-400 border border-rose-800">BOUNCED</span>;
      case "FAILED":
      case "REJECTED":
        return <span className="px-2 py-0.5 rounded text-[11px] font-semibold bg-red-950 text-red-400 border border-red-800">{eventType}</span>;
      case "UNSUBSCRIBED":
        return <span className="px-2 py-0.5 rounded text-[11px] font-semibold bg-zinc-800 text-zinc-400 border border-zinc-700">UNSUBSCRIBED</span>;
      default:
        return <span className="px-2 py-0.5 rounded text-[11px] font-semibold bg-gray-800 text-gray-300">{eventType}</span>;
    }
  };

  if (loading && !campaign) {
    return (
      <AppLayout>
        <div className="py-24 text-center text-gray-400">
          <RefreshCw className="w-8 h-8 animate-spin mx-auto text-emerald-500 mb-3" />
          <p>Carregando Ficha 360° da Campanha #{id}...</p>
        </div>
      </AppLayout>
    );
  }

  if (!campaign) {
    return (
      <AppLayout>
        <div className="p-8 text-center bg-gray-900 border border-gray-800 rounded-2xl max-w-lg mx-auto space-y-4">
          <AlertTriangle className="w-12 h-12 text-rose-500 mx-auto" />
          <h2 className="text-xl font-bold text-white">Campanha não encontrada</h2>
          <p className="text-sm text-gray-400">A campanha solicitada não existe ou pertence a outra plataforma.</p>
          <Link href="/campaigns" className="inline-block px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white rounded-lg text-sm">
            Voltar para Campanhas
          </Link>
        </div>
      </AppLayout>
    );
  }

  return (
    <AppLayout>
      <div className="space-y-6">
        {/* Navigation Breadcrumb */}
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <Link
              href="/campaigns"
              className="p-2 hover:bg-gray-800 rounded-lg text-gray-400 hover:text-white transition-colors"
            >
              <ArrowLeft className="w-5 h-5" />
            </Link>
            <div>
              <div className="flex items-center gap-2 mb-1">
                <span className="text-xs font-mono text-gray-500">#{campaign.id}</span>
                {getStatusBadge(campaign.status)}
                <span className="text-xs text-gray-500 flex items-center gap-1">
                  <Calendar className="w-3.5 h-3.5" />
                  {new Date(campaign.created_at).toLocaleDateString("pt-BR")}
                </span>
              </div>
              <h1 className="text-2xl font-bold text-white flex items-center gap-2">
                {campaign.channel === "EMAIL" ? (
                  <Mail className="w-6 h-6 text-blue-400" />
                ) : (
                  <MessageSquare className="w-6 h-6 text-emerald-400" />
                )}
                {campaign.name}
              </h1>
            </div>
          </div>

          {/* Action Toolbar */}
          <div className="flex flex-wrap items-center gap-2">
            <button
              onClick={fetchCampaignData}
              disabled={loading || actionLoading}
              title="Atualizar dados"
              className="p-2 bg-gray-900 hover:bg-gray-800 text-gray-300 rounded-lg border border-gray-800 transition-colors"
            >
              <RefreshCw className={`w-4 h-4 ${loading ? "animate-spin" : ""}`} />
            </button>

            {["DRAFT", "READY"].includes(campaign.status) && (
              <button
                onClick={() => router.push(`/campaigns/${campaign.id}/edit`)}
                className="px-3 py-2 bg-gray-800 hover:bg-gray-700 text-gray-200 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-colors border border-gray-700"
              >
                <Edit className="w-3.5 h-3.5" />
                Editar
              </button>
            )}

            {/* Test Send Button */}
            <button
              onClick={() => setShowTestModal(true)}
              className="px-3 py-2 bg-gray-800 hover:bg-gray-700 text-blue-400 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-colors border border-gray-700"
            >
              <Send className="w-3.5 h-3.5" />
              Enviar Teste
            </button>

            {/* Lifecycle Triggers */}
            {campaign.status === "READY" && (
              <button
                onClick={() => {
                  setLaunchConfirmed(false);
                  setShowLaunchModal(true);
                }}
                disabled={actionLoading}
                className="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold flex items-center gap-1.5 transition-colors shadow-md shadow-emerald-900/50"
              >
                <Play className="w-3.5 h-3.5 fill-current" />
                Disparar Campanha
              </button>
            )}

            {campaign.status === "PROCESSING" && (
              <button
                onClick={handlePause}
                disabled={actionLoading}
                className="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white rounded-lg text-xs font-bold flex items-center gap-1.5 transition-colors shadow-md shadow-amber-900/50"
              >
                <Pause className="w-3.5 h-3.5" />
                Pausar Disparo
              </button>
            )}

            {campaign.status === "PAUSED" && (
              <button
                onClick={handleResume}
                disabled={actionLoading}
                className="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-bold flex items-center gap-1.5 transition-colors shadow-md shadow-blue-900/50"
              >
                <RotateCcw className="w-3.5 h-3.5" />
                Retomar Disparo
              </button>
            )}

            {["READY", "SCHEDULED", "PROCESSING", "PAUSED"].includes(campaign.status) && (
              <button
                onClick={handleCancel}
                disabled={actionLoading}
                className="px-3 py-2 bg-gray-800 hover:bg-rose-950 text-gray-400 hover:text-rose-400 rounded-lg text-xs font-semibold border border-gray-700 transition-colors"
              >
                <Ban className="w-3.5 h-3.5" />
                Cancelar
              </button>
            )}
          </div>
        </div>

        {/* Notifications */}
        {error && (
          <div className="p-4 bg-rose-950/60 border border-rose-800 rounded-xl text-rose-200 text-sm flex items-center gap-2">
            <AlertTriangle className="w-5 h-5 flex-shrink-0 text-rose-400" />
            <span>{error}</span>
          </div>
        )}

        {successMsg && (
          <div className="p-4 bg-emerald-950/60 border border-emerald-800 rounded-xl text-emerald-200 text-sm flex items-center gap-2">
            <CheckCircle2 className="w-5 h-5 flex-shrink-0 text-emerald-400" />
            <span>{successMsg}</span>
          </div>
        )}

        {/* Progress Bar Header */}
        <div className="p-6 bg-gray-900 border border-gray-800 rounded-2xl shadow-sm space-y-3">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
              <span className="text-xs font-semibold uppercase tracking-wider text-gray-400">Progresso do Disparo</span>
              <div className="text-xl font-bold text-white mt-0.5">
                {campaign.messages_sent.toLocaleString("pt-BR")} de {campaign.total_recipients.toLocaleString("pt-BR")} mensagens disparadas
              </div>
            </div>
            <div className="text-right">
              <span className="text-2xl font-black text-emerald-400">{campaign.progress_percentage}%</span>
              <div className="text-xs text-gray-500">
                {campaign.status === "COMPLETED" ? "Envio Finalizado" : "Processando nas Filas"}
              </div>
            </div>
          </div>

          <div className="w-full bg-gray-950 rounded-full h-2.5 overflow-hidden border border-gray-800">
            <div
              className={`h-full rounded-full transition-all duration-500 ${
                campaign.status === "COMPLETED"
                  ? "bg-emerald-500"
                  : campaign.status === "FAILED"
                  ? "bg-rose-500"
                  : "bg-emerald-500"
              }`}
              style={{ width: `${Math.min(campaign.progress_percentage, 100)}%` }}
            ></div>
          </div>
        </div>

        {/* Metric Cards Grid */}
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
          <div className="p-4 bg-gray-900 border border-gray-800 rounded-xl space-y-1">
            <span className="text-[11px] text-gray-500 font-semibold uppercase">Total Público</span>
            <div className="text-xl font-bold text-white">{campaign.total_recipients.toLocaleString("pt-BR")}</div>
          </div>

          <div className="p-4 bg-gray-900 border border-gray-800 rounded-xl space-y-1">
            <span className="text-[11px] text-gray-500 font-semibold uppercase">Msgs Criadas</span>
            <div className="text-xl font-bold text-blue-400">{campaign.messages_created.toLocaleString("pt-BR")}</div>
          </div>

          <div className="p-4 bg-gray-900 border border-gray-800 rounded-xl space-y-1">
            <span className="text-[11px] text-gray-500 font-semibold uppercase">Enviadas</span>
            <div className="text-xl font-bold text-emerald-400">{campaign.messages_sent.toLocaleString("pt-BR")}</div>
          </div>

          <div className="p-4 bg-gray-900 border border-gray-800 rounded-xl space-y-1">
            <span className="text-[11px] text-gray-500 font-semibold uppercase">Entregues</span>
            <div className="text-xl font-bold text-emerald-400">{campaign.messages_delivered.toLocaleString("pt-BR")}</div>
          </div>

          <div className="p-4 bg-gray-900 border border-gray-800 rounded-xl space-y-1">
            <span className="text-[11px] text-gray-500 font-semibold uppercase">Falhas</span>
            <div className="text-xl font-bold text-rose-400">{campaign.messages_failed.toLocaleString("pt-BR")}</div>
          </div>

          <div className="p-4 bg-gray-900 border border-gray-800 rounded-xl space-y-1">
            <span className="text-[11px] text-gray-500 font-semibold uppercase">Taxa Entrega</span>
            <div className="text-xl font-bold text-emerald-400">{stats?.delivery_rate_percentage || 0}%</div>
          </div>
        </div>

        {/* Tab Switcher */}
        <div className="flex border-b border-gray-800 gap-6 text-sm font-semibold">
          <button
            onClick={() => setActiveTab("overview")}
            className={`pb-3 flex items-center gap-1.5 transition-colors ${
              activeTab === "overview"
                ? "text-emerald-400 border-b-2 border-emerald-500"
                : "text-gray-400 hover:text-white"
            }`}
          >
            <FileText className="w-4 h-4" />
            Visão Geral
          </button>
          <button
            onClick={() => setActiveTab("analytics")}
            className={`pb-3 flex items-center gap-1.5 transition-colors ${
              activeTab === "analytics"
                ? "text-emerald-400 border-b-2 border-emerald-500"
                : "text-gray-400 hover:text-white"
            }`}
          >
            <BarChart3 className="w-4 h-4" />
            Analytics & Funil
          </button>
          <button
            onClick={() => setActiveTab("events")}
            className={`pb-3 flex items-center gap-1.5 transition-colors ${
              activeTab === "events"
                ? "text-emerald-400 border-b-2 border-emerald-500"
                : "text-gray-400 hover:text-white"
            }`}
          >
            <Radio className="w-4 h-4" />
            Eventos da Mensageria
          </button>
          <Link
            href={`/campaigns/${campaign.id}/recipients`}
            className="pb-3 text-gray-400 hover:text-white transition-colors flex items-center gap-1.5"
          >
            <Users className="w-4 h-4" />
            Destinatários ({campaign.total_recipients})
          </Link>
          <Link
            href={`/campaigns/${campaign.id}/messages`}
            className="pb-3 text-gray-400 hover:text-white transition-colors flex items-center gap-1.5"
          >
            <Send className="w-4 h-4" />
            Mensagens ({campaign.messages_created})
          </Link>
        </div>

        {/* TAB 1: VISÃO GERAL */}
        {activeTab === "overview" && (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div className="lg:col-span-2 space-y-6">
              {/* Template Render Preview */}
              <div className="p-6 bg-gray-900 border border-gray-800 rounded-2xl space-y-4">
                <div className="flex items-center justify-between border-b border-gray-800 pb-3">
                  <div className="flex items-center gap-2">
                    <Eye className="w-4 h-4 text-emerald-400" />
                    <h3 className="text-base font-bold text-white">Pré-visualização do Template</h3>
                  </div>
                  <span className="text-xs text-gray-400">Variáveis reais de exemplo</span>
                </div>

                {preview ? (
                  <div className="space-y-3">
                    {preview.subject && (
                      <div className="p-3 bg-gray-950 border border-gray-800 rounded-lg text-sm text-gray-300">
                        <span className="text-xs font-bold text-gray-500 mr-2 uppercase">Assunto:</span>
                        {preview.subject}
                      </div>
                    )}
                    {preview.html ? (
                      <div className="border border-gray-800 rounded-xl overflow-hidden bg-white">
                        <iframe
                          srcDoc={preview.html}
                          title="Preview Template"
                          className="w-full h-80 border-0"
                          sandbox="allow-same-origin"
                        />
                      </div>
                    ) : (
                      <div className="p-4 bg-gray-950 border border-gray-800 rounded-lg text-sm font-mono text-gray-300 whitespace-pre-wrap">
                        {preview.text}
                      </div>
                    )}
                  </div>
                ) : (
                  <div className="py-12 text-center text-gray-500 text-sm">Carregando pré-visualização...</div>
                )}
              </div>
            </div>

            {/* Sidebar Meta Info */}
            <div className="space-y-6">
              <div className="p-6 bg-gray-900 border border-gray-800 rounded-2xl space-y-4">
                <h3 className="text-sm font-bold uppercase tracking-wider text-gray-400 border-b border-gray-800 pb-2">
                  Configurações da Campanha
                </h3>
                <div className="space-y-3 text-sm">
                  <div>
                    <span className="text-xs text-gray-500 block">Canal</span>
                    <span className="text-white font-medium">{campaign.channel}</span>
                  </div>
                  <div>
                    <span className="text-xs text-gray-500 block">Segmento</span>
                    <span className="text-white font-medium">{campaign.segment?.name || `#${campaign.segment_id}`}</span>
                  </div>
                  <div>
                    <span className="text-xs text-gray-500 block">Template & Versão</span>
                    <span className="text-white font-medium">
                      {campaign.template?.name || `#${campaign.template_id}`} (v{campaign.template_version?.version || 1})
                    </span>
                  </div>
                  <div>
                    <span className="text-xs text-gray-500 block">Provedor de Envio</span>
                    <span className="text-white font-medium">{campaign.provider?.name || "Padrão da Plataforma"}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* TAB 2: ANALYTICS & FUNIL */}
        {activeTab === "analytics" && (
          <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 bg-gray-900 border border-gray-800 rounded-2xl">
              <div>
                <h3 className="text-base font-bold text-white flex items-center gap-2">
                  <BarChart3 className="w-5 h-5 text-emerald-400" />
                  Métricas Operacionais & Performance
                </h3>
                <p className="text-xs text-gray-400 mt-0.5">
                  Dados agregados derivados diretamente dos eventos das mensagens (Event Sourcing).
                </p>
              </div>
              <button
                onClick={handleRebuildAnalytics}
                disabled={rebuilding}
                className="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-emerald-400 hover:text-emerald-300 rounded-lg text-xs font-semibold flex items-center gap-2 border border-gray-700 transition-colors"
              >
                <RefreshCw className={`w-3.5 h-3.5 ${rebuilding ? "animate-spin" : ""}`} />
                Recalcular Métricas
              </button>
            </div>

            {analyticsLoading && !analyticsData ? (
              <div className="py-20 text-center text-gray-500">
                <RefreshCw className="w-6 h-6 animate-spin mx-auto text-emerald-500 mb-2" />
                Carregando analytics da campanha...
              </div>
            ) : analyticsData ? (
              <div className="space-y-6">
                {/* Taxas Percentuais Grid */}
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                  <div className="p-4 bg-gray-900 border border-gray-800 rounded-xl space-y-1">
                    <span className="text-[11px] text-gray-500 font-semibold uppercase">Taxa de Entrega</span>
                    <div className="text-2xl font-black text-emerald-400">{analyticsData.metrics.delivery_rate}%</div>
                    <span className="text-[10px] text-gray-500">Entregues / Enviadas</span>
                  </div>

                  <div className="p-4 bg-gray-900 border border-gray-800 rounded-xl space-y-1">
                    <span className="text-[11px] text-gray-500 font-semibold uppercase">Taxa de Abertura</span>
                    <div className="text-2xl font-black text-purple-400">{analyticsData.metrics.open_rate}%</div>
                    <span className="text-[10px] text-gray-500">Únicos / Entregues</span>
                  </div>

                  <div className="p-4 bg-gray-900 border border-gray-800 rounded-xl space-y-1">
                    <span className="text-[11px] text-gray-500 font-semibold uppercase">Taxa de Cliques</span>
                    <div className="text-2xl font-black text-amber-400">{analyticsData.metrics.click_rate}%</div>
                    <span className="text-[10px] text-gray-500">Únicos / Entregues</span>
                  </div>

                  <div className="p-4 bg-gray-900 border border-gray-800 rounded-xl space-y-1">
                    <span className="text-[11px] text-gray-500 font-semibold uppercase">Taxa de Bounce</span>
                    <div className="text-2xl font-black text-rose-400">{analyticsData.metrics.bounce_rate}%</div>
                    <span className="text-[10px] text-gray-500">Bounces / Enviadas</span>
                  </div>

                  <div className="p-4 bg-gray-900 border border-gray-800 rounded-xl space-y-1">
                    <span className="text-[11px] text-gray-500 font-semibold uppercase">Taxa de Falhas</span>
                    <div className="text-2xl font-black text-red-400">{analyticsData.metrics.failure_rate}%</div>
                    <span className="text-[10px] text-gray-500">Falhas / Enviadas</span>
                  </div>

                  <div className="p-4 bg-gray-900 border border-gray-800 rounded-xl space-y-1">
                    <span className="text-[11px] text-gray-500 font-semibold uppercase">Descadastros</span>
                    <div className="text-2xl font-black text-zinc-400">{analyticsData.metrics.unsubscribed}</div>
                    <span className="text-[10px] text-gray-500">{analyticsData.metrics.unsubscribe_rate}% LGPD</span>
                  </div>
                </div>

                {/* Funil de Conversão */}
                <div className="p-6 bg-gray-900 border border-gray-800 rounded-2xl space-y-6">
                  <div className="flex items-center justify-between border-b border-gray-800 pb-3">
                    <div className="flex items-center gap-2">
                      <TrendingUp className="w-5 h-5 text-emerald-400" />
                      <h4 className="text-base font-bold text-white">Funil de Conversão Operacional</h4>
                    </div>
                    <span className="text-xs text-gray-500">Público → Entrega → Engajamento</span>
                  </div>

                  <div className="space-y-4">
                    {analyticsData.funnel.map((step, idx) => {
                      const maxCount = analyticsData.funnel[0]?.count || 1;
                      const percentOfMax = maxCount > 0 ? Math.round((step.count / maxCount) * 100) : 0;

                      return (
                        <div key={step.key} className="space-y-1.5">
                          <div className="flex justify-between text-xs font-semibold">
                            <span className="text-gray-300">{step.label}</span>
                            <span className="text-white">
                              {step.count.toLocaleString("pt-BR")}{" "}
                              <span className="text-gray-500 font-normal">({percentOfMax}%)</span>
                            </span>
                          </div>
                          <div className="w-full bg-gray-950 rounded-full h-3 overflow-hidden border border-gray-800">
                            <div
                              className="h-full rounded-full bg-gradient-to-r from-emerald-600 to-blue-500 transition-all duration-500"
                              style={{ width: `${Math.min(percentOfMax, 100)}%` }}
                            ></div>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>

                {/* Provedores e Distribuição */}
                {analyticsData.providers && analyticsData.providers.length > 0 && (
                  <div className="p-6 bg-gray-900 border border-gray-800 rounded-2xl space-y-4">
                    <h4 className="text-base font-bold text-white flex items-center gap-2">
                      <Radio className="w-5 h-5 text-blue-400" />
                      Performance por Provedor de Mensageria
                    </h4>
                    <div className="overflow-x-auto">
                      <table className="w-full text-left text-xs">
                        <thead className="bg-gray-950 text-gray-400 uppercase border-b border-gray-800">
                          <tr>
                            <th className="p-3">Provedor</th>
                            <th className="p-3">Driver</th>
                            <th className="p-3">Total Msgs</th>
                            <th className="p-3">Enviadas</th>
                            <th className="p-3">Entregues</th>
                            <th className="p-3">Falhas</th>
                            <th className="p-3">Bounces</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-800 text-gray-300">
                          {analyticsData.providers.map((p) => (
                            <tr key={p.provider_id}>
                              <td className="p-3 font-semibold text-white">{p.provider?.name || `#${p.provider_id}`}</td>
                              <td className="p-3 font-mono">{p.provider?.driver || "custom"}</td>
                              <td className="p-3">{p.total_messages.toLocaleString("pt-BR")}</td>
                              <td className="p-3 text-emerald-400">{p.sent_count.toLocaleString("pt-BR")}</td>
                              <td className="p-3 text-emerald-400">{p.delivered_count.toLocaleString("pt-BR")}</td>
                              <td className="p-3 text-rose-400">{p.failed_count.toLocaleString("pt-BR")}</td>
                              <td className="p-3 text-amber-400">{p.bounced_count.toLocaleString("pt-BR")}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </div>
                )}
              </div>
            ) : null}
          </div>
        )}

        {/* TAB 3: EVENTOS EM TEMPO REAL */}
        {activeTab === "events" && (
          <div className="p-6 bg-gray-900 border border-gray-800 rounded-2xl space-y-4">
            <div className="flex items-center justify-between border-b border-gray-800 pb-3">
              <div>
                <h3 className="text-base font-bold text-white flex items-center gap-2">
                  <Radio className="w-5 h-5 text-emerald-400" />
                  Linha do Tempo de Eventos de Mensagens
                </h3>
                <p className="text-xs text-gray-400">
                  Eventos cronológicos de entrega, abertura, cliques e bounces recebidos dos webhooks e tracking.
                </p>
              </div>
              <button
                onClick={() => loadEvents(eventsPage)}
                disabled={eventsLoading}
                className="p-2 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-lg border border-gray-700 transition-colors"
                title="Atualizar eventos"
              >
                <RefreshCw className={`w-4 h-4 ${eventsLoading ? "animate-spin" : ""}`} />
              </button>
            </div>

            {eventsLoading && eventsList.length === 0 ? (
              <div className="py-16 text-center text-gray-500">
                <RefreshCw className="w-6 h-6 animate-spin mx-auto text-emerald-500 mb-2" />
                Carregando eventos da mensageria...
              </div>
            ) : eventsList.length === 0 ? (
              <div className="py-16 text-center text-gray-500">
                Nenhum evento registrado ainda para as mensagens desta campanha.
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs">
                  <thead className="bg-gray-950 text-gray-400 uppercase border-b border-gray-800">
                    <tr>
                      <th className="p-3">Evento</th>
                      <th className="p-3">Ocorrido Em</th>
                      <th className="p-3">Destinatário (LGPD)</th>
                      <th className="p-3">Canal</th>
                      <th className="p-3">ID Mensagem</th>
                      <th className="p-3">ID Evento Provedor</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-800 text-gray-300">
                    {eventsList.map((evt) => (
                      <tr key={evt.id} className="hover:bg-gray-800/40 transition-colors">
                        <td className="p-3">{getEventBadge(evt.event_type)}</td>
                        <td className="p-3 text-gray-400">
                          {new Date(evt.occurred_at || evt.created_at).toLocaleString("pt-BR")}
                        </td>
                        <td className="p-3 font-mono text-gray-300">{evt.recipient}</td>
                        <td className="p-3">{evt.channel}</td>
                        <td className="p-3 font-mono text-gray-400">#{evt.message_id}</td>
                        <td className="p-3 font-mono text-gray-500 text-[11px]">
                          {evt.provider_event_id ? evt.provider_event_id.slice(0, 18) + "..." : "—"}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>

                {/* Event Pagination */}
                {eventsPagination && eventsPagination.last_page > 1 && (
                  <div className="flex items-center justify-between pt-4 border-t border-gray-800 text-xs text-gray-400">
                    <div>
                      Página {eventsPagination.current_page} de {eventsPagination.last_page} ({eventsPagination.total} eventos)
                    </div>
                    <div className="flex gap-2">
                      <button
                        onClick={() => loadEvents(eventsPage - 1)}
                        disabled={eventsPage <= 1}
                        className="px-3 py-1.5 bg-gray-800 rounded disabled:opacity-40"
                      >
                        Anterior
                      </button>
                      <button
                        onClick={() => loadEvents(eventsPage + 1)}
                        disabled={eventsPage >= eventsPagination.last_page}
                        className="px-3 py-1.5 bg-gray-800 rounded disabled:opacity-40"
                      >
                        Próxima
                      </button>
                    </div>
                  </div>
                )}
              </div>
            )}
          </div>
        )}
      </div>

      {/* Modal de Confirmação de Lançamento */}
      {showLaunchModal && (
        <div className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-gray-900 border border-gray-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl">
            <div className="flex items-center gap-3 text-emerald-400">
              <Play className="w-5 h-5 fill-current" />
              <h3 className="text-lg font-bold text-white">Confirmar Disparo da Campanha</h3>
            </div>

            <p className="text-xs text-gray-400 leading-relaxed">
              Você está prestes a iniciar o processamento de envio para{" "}
              <strong className="text-white">{campaign.total_recipients.toLocaleString("pt-BR")} destinatários</strong>.
              O snapshot da audiência será congelado e as mensagens serão despachadas pelas filas do Horizon.
            </p>

            <div className="p-3 bg-gray-950 border border-gray-800 rounded-xl space-y-2 text-xs">
              <div className="flex justify-between text-gray-400">
                <span>Canal:</span>
                <span className="text-white font-semibold">{campaign.channel}</span>
              </div>
              <div className="flex justify-between text-gray-400">
                <span>Segmento:</span>
                <span className="text-white font-semibold">{campaign.segment?.name}</span>
              </div>
              <div className="flex justify-between text-gray-400">
                <span>Provedor:</span>
                <span className="text-white font-semibold">{campaign.provider?.name || "Padrão"}</span>
              </div>
            </div>

            <label className="flex items-start gap-2 text-xs text-gray-300 cursor-pointer pt-1">
              <input
                type="checkbox"
                checked={launchConfirmed}
                onChange={(e) => setLaunchConfirmed(e.target.checked)}
                className="mt-0.5 rounded border-gray-700 bg-gray-950 text-emerald-500 focus:ring-emerald-500"
              />
              <span>Confirmo a revisão dos templates, variáveis dinâmicas e remetentes antes do envio.</span>
            </label>

            <div className="flex justify-end gap-3 pt-2">
              <button
                type="button"
                onClick={() => setShowLaunchModal(false)}
                className="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-lg text-xs font-medium"
              >
                Cancelar
              </button>
              <button
                type="button"
                onClick={handleLaunch}
                disabled={!launchConfirmed || actionLoading}
                className="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold disabled:opacity-50 flex items-center gap-2"
              >
                {actionLoading && <RefreshCw className="w-3.5 h-3.5 animate-spin" />}
                Confirmar Disparo
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Modal de Test Send */}
      {showTestModal && (
        <div className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
          <form onSubmit={handleTestSend} className="bg-gray-900 border border-gray-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl">
            <div className="flex items-center gap-3 text-blue-400">
              <Send className="w-5 h-5 text-blue-400" />
              <h3 className="text-lg font-bold text-white">Disparar Mensagem de Teste</h3>
            </div>

            <p className="text-xs text-gray-400 leading-relaxed">
              Envia uma única mensagem renderizada com as variáveis de exemplo da campanha diretamente para o contato informado. Não altera contadores de audiência.
            </p>

            <div>
              <label className="block text-xs font-semibold text-gray-300 mb-1.5 uppercase">
                {campaign.channel === "EMAIL" ? "E-mail de Destino" : "Telefone / Celular (E.164)"} *
              </label>
              <input
                type={campaign.channel === "EMAIL" ? "email" : "text"}
                required
                placeholder={campaign.channel === "EMAIL" ? "seu.email@empresa.com" : "+5511999998888"}
                value={testRecipient}
                onChange={(e) => setTestRecipient(e.target.value)}
                className="w-full px-4 py-2 bg-gray-950 border border-gray-800 rounded-lg text-sm text-gray-200 focus:outline-none focus:border-blue-500"
              />
            </div>

            <div className="flex justify-end gap-3 pt-2">
              <button
                type="button"
                onClick={() => setShowTestModal(false)}
                className="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-lg text-xs font-medium"
              >
                Fechar
              </button>
              <button
                type="submit"
                disabled={testSending || !testRecipient}
                className="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-bold disabled:opacity-50 flex items-center gap-2"
              >
                {testSending && <RefreshCw className="w-3.5 h-3.5 animate-spin" />}
                Enviar Teste
              </button>
            </div>
          </form>
        </div>
      )}
    </AppLayout>
  );
}
