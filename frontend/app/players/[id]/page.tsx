"use client";

import React, { useState, useEffect, useCallback } from "react";
import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { AppLayout } from "@/components/layout/app-layout";
import { playerService, Player360, Tag } from "@/services/player-service";
import { useAuth } from "@/hooks/use-auth";
import {
  ArrowLeft,
  Edit2,
  Trash2,
  RefreshCw,
  Tag as TagIcon,
  Plus,
  X,
  ShieldCheck,
  Calendar,
  Mail,
  Phone,
  MapPin,
  Clock,
  Layers,
  CheckCircle2,
  AlertCircle,
  Activity,
  UserCheck,
  FileText,
  Lock,
} from "lucide-react";

export default function Player360Page() {
  const params = useParams();
  const router = useRouter();
  const id = params?.id as string;
  const { activePlatform } = useAuth(true);

  const [player, setPlayer] = useState<Player360 | null>(null);
  const [allTags, setAllTags] = useState<Tag[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Tag association state
  const [selectedNewTagId, setSelectedNewTagId] = useState<string>("");
  const [tagLoading, setTagLoading] = useState(false);

  // Active tab inside 360
  const [activeTab, setActiveTab] = useState<"overview" | "consents" | "timeline" | "custom_fields">("overview");

  const loadData = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const [playerData, tagsData] = await Promise.all([
        playerService.getPlayer360(id),
        playerService.getTags().catch(() => []),
      ]);
      setPlayer(playerData);
      setAllTags(tagsData);
    } catch (err: any) {
      setError(err.message || "Erro ao carregar Ficha 360° do jogador.");
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    if (activePlatform && id) {
      loadData();
    }
  }, [activePlatform, id, loadData]);

  const handleAttachTag = async () => {
    if (!selectedNewTagId || !player) return;
    setTagLoading(true);
    try {
      await playerService.attachTag(player.id, Number(selectedNewTagId));
      setSelectedNewTagId("");
      await loadData();
    } catch (err: any) {
      alert(err.message || "Erro ao adicionar tag.");
    } finally {
      setTagLoading(false);
    }
  };

  const handleDetachTag = async (tagId: number) => {
    if (!player) return;
    if (!confirm("Deseja realmente remover esta tag do jogador?")) return;
    setTagLoading(true);
    try {
      await playerService.detachTag(player.id, tagId);
      await loadData();
    } catch (err: any) {
      alert(err.message || "Erro ao remover tag.");
    } finally {
      setTagLoading(false);
    }
  };

  const handleDeletePlayer = async () => {
    if (!player) return;
    if (
      !confirm(
        `Confirma a exclusão do jogador ${player.personal.name} (ID Externo: ${player.external_id})?`
      )
    )
      return;
    try {
      await playerService.deletePlayer(player.id);
      router.push("/players");
    } catch (err: any) {
      alert(err.message || "Erro ao excluir jogador.");
    }
  };

  const getStatusBadge = (st: string) => {
    switch (st) {
      case "active":
        return (
          <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
            <span className="w-2 h-2 rounded-full bg-emerald-400 mr-2 animate-pulse" />
            Ativo
          </span>
        );
      case "inactive":
        return (
          <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/30">
            Inativo
          </span>
        );
      case "churned":
        return (
          <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/30">
            Churned
          </span>
        );
      case "blocked":
        return (
          <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-500/10 text-red-400 border border-red-500/30">
            Bloqueado
          </span>
        );
      default:
        return (
          <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-slate-500/10 text-slate-400 border border-slate-500/30">
            {st}
          </span>
        );
    }
  };

  if (loading) {
    return (
      <AppLayout title="Ficha 360° do Jogador">
        <div className="py-24 flex flex-col items-center justify-center text-slate-400 space-y-3">
          <div className="animate-spin w-8 h-8 border-2 border-emerald-500 border-t-transparent rounded-full" />
          <p className="text-xs">Carregando dados 360° do jogador...</p>
        </div>
      </AppLayout>
    );
  }

  if (error || !player) {
    return (
      <AppLayout title="Ficha 360° do Jogador">
        <div className="p-6 rounded-xl border border-rose-500/30 bg-rose-500/10 text-rose-300 space-y-3">
          <div className="flex items-center space-x-2">
            <AlertCircle className="w-5 h-5 shrink-0" />
            <span className="font-semibold text-sm">Não foi possível carregar o jogador</span>
          </div>
          <p className="text-xs text-rose-200">{error || "Registro inexistente ou sem permissão de acesso."}</p>
          <Link
            href="/players"
            className="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-800 text-slate-200 hover:bg-slate-700 transition"
          >
            <ArrowLeft className="w-3.5 h-3.5" />
            <span>Voltar para lista de jogadores</span>
          </Link>
        </div>
      </AppLayout>
    );
  }

  // Tags available to add (not yet attached)
  const attachedTagIds = new Set(player.tags.map((t) => t.id));
  const unattachedTags = allTags.filter((t) => !attachedTagIds.has(t.id));

  return (
    <AppLayout
      title={`Ficha 360° • ${player.personal.name}`}
      badge={`ID: ${player.external_id}`}
      actions={
        <div className="flex items-center space-x-2">
          <Link
            href="/players"
            className="flex items-center space-x-1.5 px-3 py-1.5 rounded-md text-xs font-medium bg-slate-800 hover:bg-slate-700 text-slate-200 transition border border-slate-700"
          >
            <ArrowLeft className="w-3.5 h-3.5" />
            <span>Lista</span>
          </Link>
          <Link
            href={`/players/${player.id}/edit`}
            className="flex items-center space-x-1.5 px-3 py-1.5 rounded-md text-xs font-medium bg-slate-800 hover:bg-slate-700 text-sky-400 transition border border-slate-700"
          >
            <Edit2 className="w-3.5 h-3.5" />
            <span>Editar</span>
          </Link>
          <button
            onClick={handleDeletePlayer}
            className="flex items-center space-x-1.5 px-3 py-1.5 rounded-md text-xs font-medium bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 transition border border-rose-500/20"
          >
            <Trash2 className="w-3.5 h-3.5" />
            <span>Excluir</span>
          </button>
        </div>
      }
    >
      <div className="space-y-6">
        {/* Header Hero Banner */}
        <div className="p-6 rounded-2xl border border-slate-800 bg-[#0d131f] flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div className="flex items-center space-x-5">
            <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-500/20 to-teal-500/10 border border-emerald-500/30 flex items-center justify-center font-black text-2xl text-emerald-400 shrink-0 shadow-lg shadow-emerald-950/40">
              {player.personal.name.slice(0, 2).toUpperCase()}
            </div>
            <div className="space-y-1">
              <div className="flex items-center space-x-3">
                <h1 className="text-xl font-bold text-white tracking-tight">{player.personal.name}</h1>
                {getStatusBadge(player.status)}
              </div>
              <div className="flex flex-wrap items-center gap-3 text-xs text-slate-400">
                <span className="font-mono bg-slate-900 px-2 py-0.5 rounded border border-slate-800 text-emerald-300">
                  External ID: {player.external_id}
                </span>
                <span>•</span>
                <span>Plataforma: {player.platform.name}</span>
                <span>•</span>
                <span className="font-mono text-slate-500">UUID: {player.uuid}</span>
              </div>
            </div>
          </div>

          <div className="flex items-center space-x-3">
            <button
              onClick={() => loadData()}
              disabled={loading}
              className="p-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 transition"
              title="Recarregar"
            >
              <RefreshCw className={`w-4 h-4 ${loading ? "animate-spin text-emerald-400" : ""}`} />
            </button>
          </div>
        </div>

        {/* Tags Live Bar */}
        <div className="p-4 rounded-xl border border-slate-800 bg-[#0f172a]/70 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div className="flex items-center space-x-2">
            <TagIcon className="w-4 h-4 text-emerald-400 shrink-0" />
            <span className="text-xs font-semibold text-slate-300">Tags do Jogador:</span>
            <div className="flex flex-wrap gap-1.5 ml-2">
              {player.tags.length === 0 ? (
                <span className="text-xs text-slate-500 italic">Nenhuma tag atribuída.</span>
              ) : (
                player.tags.map((tag) => (
                  <span
                    key={tag.id}
                    className="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium border space-x-1.5 group"
                    style={{
                      backgroundColor: tag.color ? `${tag.color}15` : "#10b98115",
                      borderColor: tag.color ? `${tag.color}40` : "#10b98140",
                      color: tag.color || "#10b981",
                    }}
                  >
                    <span>{tag.name}</span>
                    <button
                      type="button"
                      disabled={tagLoading}
                      onClick={() => handleDetachTag(tag.id)}
                      className="text-slate-400 hover:text-rose-400 transition"
                      title="Remover tag"
                    >
                      <X className="w-3 h-3" />
                    </button>
                  </span>
                ))
              )}
            </div>
          </div>

          {/* Add Tag Dropdown */}
          {unattachedTags.length > 0 && (
            <div className="flex items-center space-x-2 shrink-0">
              <select
                value={selectedNewTagId}
                onChange={(e) => setSelectedNewTagId(e.target.value)}
                disabled={tagLoading}
                className="px-2.5 py-1 bg-slate-900 border border-slate-700 rounded-lg text-xs text-slate-200 focus:outline-none focus:border-emerald-500"
              >
                <option value="">+ Associar Nova Tag...</option>
                {unattachedTags.map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.name}
                  </option>
                ))}
              </select>
              <button
                type="button"
                onClick={handleAttachTag}
                disabled={!selectedNewTagId || tagLoading}
                className="px-3 py-1 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-40 text-white rounded-lg text-xs font-medium transition"
              >
                Adicionar
              </button>
            </div>
          )}
        </div>

        {/* Tab Navigation */}
        <div className="flex items-center space-x-2 border-b border-slate-800 text-xs font-medium">
          <button
            onClick={() => setActiveTab("overview")}
            className={`pb-3 px-3 transition border-b-2 flex items-center space-x-2 ${
              activeTab === "overview"
                ? "border-emerald-400 text-emerald-400 font-semibold"
                : "border-transparent text-slate-400 hover:text-slate-200"
            }`}
          >
            <UserCheck className="w-4 h-4" />
            <span>Visão Geral</span>
          </button>

          <button
            onClick={() => setActiveTab("consents")}
            className={`pb-3 px-3 transition border-b-2 flex items-center space-x-2 ${
              activeTab === "consents"
                ? "border-emerald-400 text-emerald-400 font-semibold"
                : "border-transparent text-slate-400 hover:text-slate-200"
            }`}
          >
            <ShieldCheck className="w-4 h-4" />
            <span>Consentimentos LGPD ({player.consents?.length || 0})</span>
          </button>

          <button
            onClick={() => setActiveTab("custom_fields")}
            className={`pb-3 px-3 transition border-b-2 flex items-center space-x-2 ${
              activeTab === "custom_fields"
                ? "border-emerald-400 text-emerald-400 font-semibold"
                : "border-transparent text-slate-400 hover:text-slate-200"
            }`}
          >
            <Layers className="w-4 h-4" />
            <span>Campos Customizados ({Object.keys(player.custom_fields || {}).length})</span>
          </button>

          <button
            onClick={() => setActiveTab("timeline")}
            className={`pb-3 px-3 transition border-b-2 flex items-center space-x-2 ${
              activeTab === "timeline"
                ? "border-emerald-400 text-emerald-400 font-semibold"
                : "border-transparent text-slate-400 hover:text-slate-200"
            }`}
          >
            <Clock className="w-4 h-4" />
            <span>Timeline de Atividades ({player.timeline?.length || 0})</span>
          </button>
        </div>

        {/* Tab Content */}
        {activeTab === "overview" && (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {/* Card 1: Personal Data */}
            <div className="p-5 rounded-xl border border-slate-800 bg-[#0d131f] space-y-4">
              <div className="flex items-center space-x-2 text-white font-semibold text-sm border-b border-slate-800/80 pb-3">
                <UserCheck className="w-4 h-4 text-emerald-400" />
                <span>Dados Pessoais (LGPD)</span>
              </div>
              <div className="space-y-3 text-xs">
                <div>
                  <span className="text-slate-400 block text-[11px]">Nome Completo</span>
                  <span className="font-semibold text-slate-200">{player.personal.name}</span>
                </div>
                <div>
                  <span className="text-slate-400 block text-[11px]">CPF (Mascarado)</span>
                  <span className="font-mono text-slate-300">
                    {player.personal.cpf_masked || "Não informado"}
                  </span>
                </div>
                <div>
                  <span className="text-slate-400 block text-[11px]">Data de Nascimento</span>
                  <span className="text-slate-300">
                    {player.personal.birth_date
                      ? new Date(player.personal.birth_date).toLocaleDateString("pt-BR")
                      : "Não informada"}
                  </span>
                </div>
                <div>
                  <span className="text-slate-400 block text-[11px]">Gênero</span>
                  <span className="text-slate-300">{player.personal.gender || "Não informado"}</span>
                </div>
              </div>
            </div>

            {/* Card 2: Contact Data */}
            <div className="p-5 rounded-xl border border-slate-800 bg-[#0d131f] space-y-4">
              <div className="flex items-center space-x-2 text-white font-semibold text-sm border-b border-slate-800/80 pb-3">
                <Mail className="w-4 h-4 text-sky-400" />
                <span>Canais de Contato</span>
              </div>
              <div className="space-y-3 text-xs">
                <div>
                  <span className="text-slate-400 block text-[11px]">E-mail Mascarado</span>
                  <span className="font-mono text-emerald-400">{player.contact.email_masked}</span>
                </div>
                <div>
                  <span className="text-slate-400 block text-[11px]">Telefone Mascarado</span>
                  <span className="font-mono text-slate-300">
                    {player.contact.phone_masked || "Não cadastrado"}
                  </span>
                </div>
                <div>
                  <span className="text-slate-400 block text-[11px]">Localização</span>
                  <span className="text-slate-300">
                    {player.contact.city && player.contact.state
                      ? `${player.contact.city}, ${player.contact.state}`
                      : player.contact.state || "Não informada"}
                  </span>
                </div>
              </div>
            </div>

            {/* Card 3: Platform & Acquisition */}
            <div className="p-5 rounded-xl border border-slate-800 bg-[#0d131f] space-y-4">
              <div className="flex items-center space-x-2 text-white font-semibold text-sm border-b border-slate-800/80 pb-3">
                <Activity className="w-4 h-4 text-violet-400" />
                <span>Aquisição & Plataforma</span>
              </div>
              <div className="space-y-3 text-xs">
                <div>
                  <span className="text-slate-400 block text-[11px]">Operadora / Plataforma</span>
                  <span className="font-semibold text-slate-200">
                    {player.platform.name} ({player.platform.slug})
                  </span>
                </div>
                <div>
                  <span className="text-slate-400 block text-[11px]">Afiliado / Origem</span>
                  <span className="font-mono text-slate-300">
                    {player.acquisition.affiliate || "Cadastro Direto (Orgânico)"}
                  </span>
                </div>
                <div>
                  <span className="text-slate-400 block text-[11px]">Data de Cadastro</span>
                  <span className="font-mono text-slate-300">
                    {new Date(player.acquisition.created_at).toLocaleString("pt-BR")}
                  </span>
                </div>
                <div>
                  <span className="text-slate-400 block text-[11px]">Último Login na Bet</span>
                  <span className="font-mono text-slate-300">
                    {player.activity.last_login_at
                      ? new Date(player.activity.last_login_at).toLocaleString("pt-BR")
                      : "Nenhum login registrado"}
                  </span>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Tab Consents */}
        {activeTab === "consents" && (
          <div className="rounded-xl border border-slate-800 bg-[#0d131f] overflow-hidden">
            <div className="p-4 border-b border-slate-800 bg-[#0f172a] flex items-center justify-between">
              <div>
                <h3 className="text-xs font-semibold text-white">Registro de Consentimentos LGPD</h3>
                <p className="text-[11px] text-slate-400">
                  Rastreabilidade legal com IP, User-Agent e carimbo de data/hora para auditoria.
                </p>
              </div>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs text-slate-300">
                <thead className="bg-[#0f172a] text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-800">
                  <tr>
                    <th className="py-3 px-4">Canal</th>
                    <th className="py-3 px-4">Finalidade</th>
                    <th className="py-3 px-4">Status</th>
                    <th className="py-3 px-4">Data Concessão</th>
                    <th className="py-3 px-4">Endereço IP</th>
                    <th className="py-3 px-4">Navegador / Dispositivo</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800/60 font-sans">
                  {player.consents && player.consents.length > 0 ? (
                    player.consents.map((c) => (
                      <tr key={c.id} className="hover:bg-slate-900/50 transition">
                        <td className="py-3 px-4 font-semibold uppercase text-emerald-400 font-mono">
                          {c.channel}
                        </td>
                        <td className="py-3 px-4 text-slate-300">{c.type}</td>
                        <td className="py-3 px-4">
                          {c.status === "granted" ? (
                            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                              Autorizado
                            </span>
                          ) : (
                            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                              Revogado
                            </span>
                          )}
                        </td>
                        <td className="py-3 px-4 font-mono text-[11px] text-slate-400">
                          {c.granted_at ? new Date(c.granted_at).toLocaleString("pt-BR") : "—"}
                        </td>
                        <td className="py-3 px-4 font-mono text-[11px] text-slate-400">
                          {c.ip_address || "—"}
                        </td>
                        <td className="py-3 px-4 text-[11px] text-slate-400 truncate max-w-xs">
                          {c.user_agent || "—"}
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={6} className="py-8 text-center text-slate-500">
                        Nenhum consentimento registrado para este jogador.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {/* Tab Custom Fields */}
        {activeTab === "custom_fields" && (
          <div className="p-6 rounded-xl border border-slate-800 bg-[#0d131f] space-y-4">
            <div>
              <h3 className="text-sm font-semibold text-white">Metadados Flexíveis (JSONB)</h3>
              <p className="text-xs text-slate-400">
                Propriedades dinâmicas indexadas no PostgreSQL para filtros e réguas de relacionamento.
              </p>
            </div>

            {Object.keys(player.custom_fields || {}).length === 0 ? (
              <p className="text-xs text-slate-500 py-4">Nenhum campo customizado preenchido.</p>
            ) : (
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                {Object.entries(player.custom_fields).map(([key, val]) => (
                  <div
                    key={key}
                    className="p-3 rounded-lg border border-slate-800 bg-slate-900/60 space-y-1"
                  >
                    <span className="text-[10px] font-mono uppercase text-slate-400 block">{key}</span>
                    <span className="text-xs font-semibold text-emerald-300 font-mono">
                      {typeof val === "object" ? JSON.stringify(val) : String(val)}
                    </span>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        {/* Tab Timeline */}
        {activeTab === "timeline" && (
          <div className="p-6 rounded-xl border border-slate-800 bg-[#0d131f] space-y-6">
            <div>
              <h3 className="text-sm font-semibold text-white">Linha do Tempo de Atividades</h3>
              <p className="text-xs text-slate-400">
                Histórico consolidado de eventos, logins e alterações de cadastro.
              </p>
            </div>

            <div className="relative pl-6 space-y-6 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-800">
              {player.timeline && player.timeline.length > 0 ? (
                player.timeline.map((event) => (
                  <div key={event.id} className="relative group">
                    <div className="absolute -left-6 top-1 w-3.5 h-3.5 rounded-full bg-emerald-500/20 border-2 border-emerald-400 ring-4 ring-[#0d131f]" />
                    <div className="space-y-1">
                      <div className="flex items-center space-x-2">
                        <span className="text-xs font-semibold text-slate-200">{event.title}</span>
                        <span className="text-[10px] font-mono text-slate-500">
                          {new Date(event.timestamp).toLocaleString("pt-BR")}
                        </span>
                      </div>
                      <p className="text-xs text-slate-400">{event.description}</p>
                    </div>
                  </div>
                ))
              ) : (
                <p className="text-xs text-slate-500">Nenhum evento registrado na linha do tempo.</p>
              )}
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
