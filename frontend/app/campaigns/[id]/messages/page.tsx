"use client";

import React, { useEffect, useState, use } from "react";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import { campaignService, Campaign } from "@/services/campaign-service";
import {
  ArrowLeft,
  Send,
  Search,
  RefreshCw,
  Mail,
  MessageSquare,
  CheckCircle2,
  AlertTriangle,
  Clock,
  Radio,
} from "lucide-react";

export default function CampaignMessagesPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = use(params);

  const [campaign, setCampaign] = useState<Campaign | null>(null);
  const [messages, setMessages] = useState<any[]>([]);
  const [pagination, setPagination] = useState({
    total: 0,
    per_page: 15,
    current_page: 1,
    last_page: 1,
  });
  const [statusFilter, setStatusFilter] = useState("ALL");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchMessages = async (page: number = 1) => {
    try {
      setLoading(true);
      setError(null);

      const [campData, msgData] = await Promise.all([
        campaignService.getById(id),
        campaignService.getMessages(id, {
          status: statusFilter,
          page,
        }),
      ]);

      setCampaign(campData);
      setMessages(msgData.data);
      setPagination(msgData.pagination);
    } catch (err: any) {
      setError(err.message || "Erro ao carregar mensagens geradas pela campanha.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchMessages(1);
  }, [id, statusFilter]);

  const getMessageStatusBadge = (status: string) => {
    switch (status) {
      case "QUEUED":
        return <span className="px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-950 text-blue-400 border border-blue-800">ENFILEIRADO</span>;
      case "SENT":
        return <span className="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-950 text-emerald-400 border border-emerald-800">ENVIADO</span>;
      case "DELIVERED":
        return <span className="px-2 py-0.5 rounded text-[11px] font-semibold bg-green-950 text-green-300 border border-green-800">ENTREGUE</span>;
      case "FAILED":
        return <span className="px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-950 text-rose-400 border border-rose-800">FALHOU</span>;
      case "CANCELLED":
        return <span className="px-2 py-0.5 rounded text-[11px] font-semibold bg-zinc-800 text-zinc-400 border border-zinc-700">CANCELADA</span>;
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
                <span className="text-xs px-2 py-0.5 rounded bg-blue-950 text-blue-400 font-mono">
                  HISTÓRICO DE MENSAGENS
                </span>
              </div>
              <h1 className="text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                <Send className="w-6 h-6 text-emerald-500" />
                Mensagens da Campanha
              </h1>
            </div>
          </div>

          <button
            onClick={() => fetchMessages(pagination.current_page)}
            disabled={loading}
            className="px-3 py-2 bg-gray-800 hover:bg-gray-700 text-gray-200 rounded-lg text-xs font-semibold border border-gray-700 flex items-center gap-1.5 transition-colors"
          >
            <RefreshCw className={`w-3.5 h-3.5 ${loading ? "animate-spin" : ""}`} />
            Atualizar
          </button>
        </div>

        {/* Filters */}
        <div className="p-4 bg-gray-900 border border-gray-800 rounded-xl flex items-center justify-between">
          <div className="flex items-center gap-2">
            <span className="text-xs text-gray-400">Filtrar por Status:</span>
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="bg-gray-950 border border-gray-800 text-gray-200 text-xs rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-500"
            >
              <option value="ALL">Todos os Status</option>
              <option value="QUEUED">Enfileirado</option>
              <option value="SENT">Enviado</option>
              <option value="DELIVERED">Entregue</option>
              <option value="FAILED">Falha</option>
              <option value="CANCELLED">Cancelada</option>
            </select>
          </div>
        </div>

        {/* Messages Table */}
        <div className="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden shadow-sm">
          {loading ? (
            <div className="py-16 text-center text-gray-400">
              <RefreshCw className="w-8 h-8 animate-spin mx-auto text-emerald-500 mb-3" />
              <p>Carregando mensagens da campanha...</p>
            </div>
          ) : messages.length === 0 ? (
            <div className="py-16 text-center text-gray-500">Nenhuma mensagem gerada para esta campanha ainda.</div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm text-gray-300">
                <thead className="bg-gray-950/80 text-xs uppercase text-gray-400 border-b border-gray-800">
                  <tr>
                    <th className="px-5 py-3">ID / Destino</th>
                    <th className="px-5 py-3">Canal</th>
                    <th className="px-5 py-3">Provedor</th>
                    <th className="px-5 py-3">Status</th>
                    <th className="px-5 py-3">Tentativas / Latência</th>
                    <th className="px-5 py-3">Disparado em</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-800/60 font-normal">
                  {messages.map((msg) => (
                    <tr key={msg.id} className="hover:bg-gray-800/30 transition-colors">
                      <td className="px-5 py-3.5">
                        <div className="font-semibold text-white font-mono text-xs">{msg.recipient}</div>
                        <div className="text-[11px] text-gray-500 font-mono">Msg #{msg.id}</div>
                      </td>

                      <td className="px-5 py-3.5">
                        <span className="inline-flex items-center gap-1 text-xs text-gray-300">
                          {msg.channel === "EMAIL" ? <Mail className="w-3.5 h-3.5 text-blue-400" /> : <MessageSquare className="w-3.5 h-3.5 text-emerald-400" />}
                          {msg.channel}
                        </span>
                      </td>

                      <td className="px-5 py-3.5 text-xs text-gray-300">
                        <div className="font-medium">{msg.provider?.name || "Provider"}</div>
                        <div className="text-[11px] text-gray-500 font-mono">{msg.provider?.driver}</div>
                      </td>

                      <td className="px-5 py-3.5 whitespace-nowrap">
                        {getMessageStatusBadge(msg.status)}
                      </td>

                      <td className="px-5 py-3.5 text-xs text-gray-400">
                        <div>Tentativas: {msg.attempts || 1}</div>
                        <div className="text-[11px] text-gray-500">{msg.latency_ms ? `${msg.latency_ms}ms` : "—"}</div>
                      </td>

                      <td className="px-5 py-3.5 text-xs text-gray-400 whitespace-nowrap">
                        {msg.sent_at
                          ? new Date(msg.sent_at).toLocaleString("pt-BR")
                          : msg.created_at
                          ? new Date(msg.created_at).toLocaleString("pt-BR")
                          : "—"}
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
                Total de <strong>{pagination.total}</strong> mensagens • Página <strong>{pagination.current_page}</strong> de <strong>{pagination.last_page}</strong>
              </div>
              <div className="flex items-center gap-2">
                <button
                  onClick={() => fetchMessages(pagination.current_page - 1)}
                  disabled={pagination.current_page === 1 || loading}
                  className="px-3 py-1.5 bg-gray-900 hover:bg-gray-800 rounded border border-gray-800 disabled:opacity-30"
                >
                  Anterior
                </button>
                <button
                  onClick={() => fetchMessages(pagination.current_page + 1)}
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
