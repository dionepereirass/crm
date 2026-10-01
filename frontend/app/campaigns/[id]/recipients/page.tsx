"use client";

import React, { useEffect, useState, use } from "react";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import {
  campaignService,
  Campaign,
  CampaignRecipient,
} from "@/services/campaign-service";
import {
  ArrowLeft,
  Users,
  Search,
  RefreshCw,
  Mail,
  MessageSquare,
  ShieldCheck,
  CheckCircle2,
  AlertTriangle,
  XCircle,
  Clock,
  Filter,
} from "lucide-react";

export default function CampaignRecipientsPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = use(params);

  const [campaign, setCampaign] = useState<Campaign | null>(null);
  const [recipients, setRecipients] = useState<CampaignRecipient[]>([]);
  const [pagination, setPagination] = useState({
    total: 0,
    per_page: 15,
    current_page: 1,
    last_page: 1,
  });
  const [statusFilter, setStatusFilter] = useState("ALL");
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchRecipients = async (page: number = 1) => {
    try {
      setLoading(true);
      setError(null);

      const [campData, recData] = await Promise.all([
        campaignService.getById(id),
        campaignService.getRecipients(id, {
          status: statusFilter,
          search: search || undefined,
          page,
        }),
      ]);

      setCampaign(campData);
      setRecipients(recData.data);
      setPagination(recData.pagination);
    } catch (err: any) {
      setError(err.message || "Erro ao carregar lista de destinatários.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRecipients(1);
  }, [id, statusFilter]);

  const getRecipientStatusBadge = (status: CampaignRecipient["status"]) => {
    switch (status) {
      case "PENDING":
        return <span className="px-2 py-0.5 rounded text-[11px] font-semibold bg-gray-800 text-gray-300 border border-gray-700">PENDENTE</span>;
      case "QUEUED":
        return <span className="px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-950 text-blue-400 border border-blue-800">ENFILEIRADO</span>;
      case "SENT":
        return <span className="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-950 text-emerald-400 border border-emerald-800">ENVIADO</span>;
      case "DELIVERED":
        return <span className="px-2 py-0.5 rounded text-[11px] font-semibold bg-green-950 text-green-300 border border-green-800">ENTREGUE</span>;
      case "FAILED":
        return <span className="px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-950 text-rose-400 border border-rose-800">FALHOU</span>;
      case "SKIPPED":
        return <span className="px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-950 text-amber-400 border border-amber-800">IGNORADO</span>;
      default:
        return <span className="px-2 py-0.5 rounded text-[11px] font-semibold bg-gray-800 text-gray-300">{status}</span>;
    }
  };

  return (
    <AppLayout>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <Link
              href={`/campaigns/${id}`}
              className="p-2 hover:bg-gray-800 rounded-lg text-gray-400 hover:text-white transition-colors"
            >
              <ArrowLeft className="w-5 h-5" />
            </Link>
            <div>
              <div className="flex items-center gap-2 mb-0.5">
                <span className="text-xs font-mono text-gray-500">Campanha #{id}</span>
                <span className="text-xs px-2 py-0.5 rounded bg-emerald-950 text-emerald-400 font-mono">
                  SNAPSHOT IMUTÁVEL
                </span>
              </div>
              <h1 className="text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                <Users className="w-6 h-6 text-emerald-500" />
                Destinatários da Campanha
              </h1>
            </div>
          </div>

          <button
            onClick={() => fetchRecipients(pagination.current_page)}
            disabled={loading}
            className="px-3 py-2 bg-gray-800 hover:bg-gray-700 text-gray-200 rounded-lg text-xs font-semibold border border-gray-700 flex items-center gap-1.5 transition-colors"
          >
            <RefreshCw className={`w-3.5 h-3.5 ${loading ? "animate-spin" : ""}`} />
            Atualizar
          </button>
        </div>

        {/* LGPD Banner */}
        <div className="p-4 bg-gray-900 border border-gray-800 rounded-xl flex items-center gap-3 text-xs text-gray-300">
          <ShieldCheck className="w-5 h-5 text-emerald-400 flex-shrink-0" />
          <span>
            <strong>Conformidade LGPD:</strong> Os dados de contato nesta visualização são estritamente mascarados para proteção à privacidade dos jogadores.
          </span>
        </div>

        {/* Filters */}
        <div className="p-4 bg-gray-900 border border-gray-800 rounded-xl flex flex-col md:flex-row items-center gap-4 justify-between">
          <div className="flex-1 w-full md:max-w-md relative">
            <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" />
            <input
              type="text"
              placeholder="Buscar por contato ou jogador..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              onKeyDown={(e) => e.key === "Enter" && fetchRecipients(1)}
              className="w-full pl-9 pr-4 py-2 bg-gray-950 border border-gray-800 rounded-lg text-sm text-gray-200 focus:outline-none focus:border-emerald-500"
            />
          </div>

          <div className="flex items-center gap-2 w-full md:w-auto">
            <span className="text-xs text-gray-400">Status:</span>
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="bg-gray-950 border border-gray-800 text-gray-200 text-xs rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-500"
            >
              <option value="ALL">Todos os Status</option>
              <option value="PENDING">Pendente</option>
              <option value="QUEUED">Enfileirado</option>
              <option value="SENT">Enviado</option>
              <option value="DELIVERED">Entregue</option>
              <option value="FAILED">Falha</option>
              <option value="SKIPPED">Ignorado (Opt-out/LGPD)</option>
            </select>
          </div>
        </div>

        {/* Table */}
        <div className="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden shadow-sm">
          {loading ? (
            <div className="py-16 text-center text-gray-400">
              <RefreshCw className="w-8 h-8 animate-spin mx-auto text-emerald-500 mb-3" />
              <p>Carregando destinatários...</p>
            </div>
          ) : recipients.length === 0 ? (
            <div className="py-16 text-center text-gray-500">Nenhum destinatário encontrado com os filtros aplicados.</div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm text-gray-300">
                <thead className="bg-gray-950/80 text-xs uppercase text-gray-400 border-b border-gray-800">
                  <tr>
                    <th className="px-5 py-3">Jogador</th>
                    <th className="px-5 py-3">Canal</th>
                    <th className="px-5 py-3">Contato Mascarado (LGPD)</th>
                    <th className="px-5 py-3">Status</th>
                    <th className="px-5 py-3">Motivo / Observação</th>
                    <th className="px-5 py-3">Registrado em</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-800/60 font-normal">
                  {recipients.map((rec) => (
                    <tr key={rec.id} className="hover:bg-gray-800/30 transition-colors">
                      <td className="px-5 py-3.5">
                        <div className="font-semibold text-white">{rec.player?.name || `Jogador #${rec.player_id}`}</div>
                        <div className="text-[11px] text-gray-500 font-mono">ID: {rec.player_id}</div>
                      </td>

                      <td className="px-5 py-3.5">
                        <span className="inline-flex items-center gap-1 text-xs text-gray-300">
                          {rec.channel === "EMAIL" ? <Mail className="w-3.5 h-3.5 text-blue-400" /> : <MessageSquare className="w-3.5 h-3.5 text-emerald-400" />}
                          {rec.channel}
                        </span>
                      </td>

                      <td className="px-5 py-3.5 font-mono text-xs text-gray-200">
                        {rec.recipient}
                      </td>

                      <td className="px-5 py-3.5 whitespace-nowrap">
                        {getRecipientStatusBadge(rec.status)}
                      </td>

                      <td className="px-5 py-3.5 text-xs text-gray-400">
                        {rec.reason ? (
                          <span className="px-2 py-0.5 rounded bg-gray-950 border border-gray-800 text-amber-300 font-mono text-[11px]">
                            {rec.reason}
                          </span>
                        ) : (
                          <span className="text-gray-600">—</span>
                        )}
                      </td>

                      <td className="px-5 py-3.5 text-xs text-gray-400 whitespace-nowrap">
                        {new Date(rec.created_at).toLocaleString("pt-BR")}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {/* Pagination */}
          {pagination.last_page > 1 && (
            <div className="p-4 bg-gray-950 border-t border-gray-800 flex items-center justify-between text-xs text-gray-400">
              <div>
                Total de <strong>{pagination.total}</strong> destinatários • Página <strong>{pagination.current_page}</strong> de <strong>{pagination.last_page}</strong>
              </div>
              <div className="flex items-center gap-2">
                <button
                  onClick={() => fetchRecipients(pagination.current_page - 1)}
                  disabled={pagination.current_page === 1 || loading}
                  className="px-3 py-1.5 bg-gray-900 hover:bg-gray-800 rounded border border-gray-800 disabled:opacity-30"
                >
                  Anterior
                </button>
                <button
                  onClick={() => fetchRecipients(pagination.current_page + 1)}
                  disabled={pagination.current_page === pagination.last_page || loading}
                  className="px-3 py-1.5 bg-gray-900 hover:bg-gray-800 rounded border border-gray-800 disabled:opacity-30"
                >
                  Próxima
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </AppLayout>
  );
}
