"use client";

import React, { useState, useEffect, useCallback } from "react";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import { playerService, Player, Tag } from "@/services/player-service";
import { useAuth } from "@/hooks/use-auth";
import {
  Users,
  Search,
  Filter,
  Plus,
  Eye,
  Edit2,
  Trash2,
  RefreshCw,
  Tag as TagIcon,
  CheckCircle2,
  AlertCircle,
  Clock,
  ShieldAlert,
  ChevronLeft,
  ChevronRight,
  ExternalLink,
} from "lucide-react";

export default function PlayersListPage() {
  const { activePlatform } = useAuth(true);
  const [players, setPlayers] = useState<Player[]>([]);
  const [tags, setTags] = useState<Tag[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Filters state
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [selectedTag, setSelectedTag] = useState("");
  const [stateFilter, setStateFilter] = useState("");
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalCount, setTotalCount] = useState(0);

  // Deletion modal
  const [playerToDelete, setPlayerToDelete] = useState<Player | null>(null);
  const [deleting, setDeleting] = useState(false);

  const fetchTags = useCallback(async () => {
    try {
      const data = await playerService.getTags();
      setTags(data);
    } catch (err) {
      console.error("Falha ao carregar tags", err);
    }
  }, []);

  const fetchPlayers = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await playerService.getPlayers({
        search: search || undefined,
        status: status || undefined,
        tag_id: selectedTag || undefined,
        state: stateFilter || undefined,
        page,
        per_page: 15,
      });

      setPlayers(res.data);
      if (res.meta) {
        setTotalPages(res.meta.last_page || 1);
        setTotalCount(res.meta.total || 0);
      }
    } catch (err: any) {
      setError(err.message || "Erro ao carregar lista de jogadores.");
    } finally {
      setLoading(false);
    }
  }, [search, status, selectedTag, stateFilter, page]);

  useEffect(() => {
    if (activePlatform) {
      fetchTags();
      fetchPlayers();
    }
  }, [activePlatform, fetchPlayers, fetchTags]);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setPage(1);
    fetchPlayers();
  };

  const handleDeletePlayer = async () => {
    if (!playerToDelete) return;
    setDeleting(true);
    try {
      await playerService.deletePlayer(playerToDelete.id);
      setPlayerToDelete(null);
      await fetchPlayers();
    } catch (err: any) {
      alert(err.message || "Erro ao excluir jogador.");
    } finally {
      setDeleting(false);
    }
  };

  const getStatusBadge = (st: string) => {
    switch (st) {
      case "active":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
            <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 mr-1.5 animate-pulse" />
            Ativo
          </span>
        );
      case "inactive":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
            Inativo
          </span>
        );
      case "churned":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
            Churned
          </span>
        );
      case "blocked":
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-500/10 text-red-400 border border-red-500/20">
            Bloqueado
          </span>
        );
      default:
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-500/10 text-slate-400 border border-slate-500/20">
            {st}
          </span>
        );
    }
  };

  return (
    <AppLayout
      title="Gestão de Jogadores"
      badge={`${totalCount} Cadastrados`}
      actions={
        <div className="flex items-center space-x-2">
          <button
            onClick={() => fetchPlayers()}
            disabled={loading}
            className="flex items-center space-x-1.5 px-3 py-1.5 rounded-md text-xs font-medium bg-slate-800 hover:bg-slate-700 text-slate-200 transition border border-slate-700"
          >
            <RefreshCw className={`w-3.5 h-3.5 ${loading ? "animate-spin text-emerald-400" : ""}`} />
            <span>Atualizar</span>
          </button>
          <Link
            href="/players/new"
            className="flex items-center space-x-1.5 px-3.5 py-1.5 rounded-md text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white transition shadow-lg shadow-emerald-950/40"
          >
            <Plus className="w-3.5 h-3.5" />
            <span>Novo Jogador</span>
          </Link>
        </div>
      }
    >
      <div className="space-y-6">
        {/* Filter Bar */}
        <div className="p-4 rounded-xl border border-slate-800 bg-[#0f172a]/70 space-y-4">
          <form onSubmit={handleSearchSubmit} className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
            {/* Search Input */}
            <div className="lg:col-span-4 relative">
              <Search className="w-4 h-4 text-slate-500 absolute left-3 top-2.5" />
              <input
                type="text"
                placeholder="Buscar por nome, e-mail, telefone ou ID externo..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full pl-9 pr-3 py-2 bg-slate-900 border border-slate-700/80 rounded-lg text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 transition"
              />
            </div>

            {/* Status Filter */}
            <div className="lg:col-span-2">
              <select
                value={status}
                onChange={(e) => {
                  setStatus(e.target.value);
                  setPage(1);
                }}
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700/80 rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500 transition"
              >
                <option value="">Status: Todos</option>
                <option value="active">Ativo</option>
                <option value="inactive">Inativo</option>
                <option value="churned">Churned</option>
                <option value="blocked">Bloqueado</option>
                <option value="pending">Pendente</option>
              </select>
            </div>

            {/* Tag Filter */}
            <div className="lg:col-span-3">
              <select
                value={selectedTag}
                onChange={(e) => {
                  setSelectedTag(e.target.value);
                  setPage(1);
                }}
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700/80 rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500 transition"
              >
                <option value="">Filtrar por Tag: Todas</option>
                {tags.map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.name}
                  </option>
                ))}
              </select>
            </div>

            {/* State Filter */}
            <div className="lg:col-span-2">
              <input
                type="text"
                placeholder="Estado (UF)"
                maxLength={2}
                value={stateFilter}
                onChange={(e) => {
                  setStateFilter(e.target.value.toUpperCase());
                  setPage(1);
                }}
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700/80 rounded-lg text-xs text-white placeholder-slate-500 uppercase focus:outline-none focus:border-emerald-500 transition"
              />
            </div>

            {/* Submit Filter Button */}
            <div className="lg:col-span-1 flex items-center">
              <button
                type="submit"
                className="w-full py-2 bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 rounded-lg text-xs font-medium transition flex items-center justify-center space-x-1"
              >
                <Filter className="w-3.5 h-3.5" />
                <span>Filtrar</span>
              </button>
            </div>
          </form>
        </div>

        {/* Error Alert */}
        {error && (
          <div className="p-4 rounded-lg border border-rose-500/30 bg-rose-500/10 text-rose-300 text-xs flex items-center space-x-2">
            <AlertCircle className="w-4 h-4 shrink-0" />
            <span>{error}</span>
          </div>
        )}

        {/* Table Container */}
        <div className="rounded-xl border border-slate-800 bg-[#0d131f] overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs text-slate-300">
              <thead className="bg-[#0f172a] text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-800">
                <tr>
                  <th className="py-3 px-4">Jogador</th>
                  <th className="py-3 px-4">ID Externo</th>
                  <th className="py-3 px-4">Contato (LGPD Mascarado)</th>
                  <th className="py-3 px-4">Local</th>
                  <th className="py-3 px-4">Status</th>
                  <th className="py-3 px-4">Tags</th>
                  <th className="py-3 px-4">Data Cadastro</th>
                  <th className="py-3 px-4 text-right">Ações</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/60 font-sans">
                {loading ? (
                  <tr>
                    <td colSpan={8} className="py-12 text-center text-slate-500">
                      <div className="flex items-center justify-center space-x-2">
                        <div className="animate-spin w-4 h-4 border-2 border-emerald-500 border-t-transparent rounded-full" />
                        <span>Carregando jogadores da plataforma...</span>
                      </div>
                    </td>
                  </tr>
                ) : players.length === 0 ? (
                  <tr>
                    <td colSpan={8} className="py-12 text-center text-slate-500">
                      <div className="max-w-sm mx-auto space-y-3">
                        <Users className="w-10 h-10 mx-auto text-slate-600" />
                        <p className="text-sm font-medium text-slate-400">Nenhum jogador encontrado</p>
                        <p className="text-xs text-slate-500">
                          {search || status || selectedTag || stateFilter
                            ? "Tente ajustar os filtros de busca para encontrar registros."
                            : "Comece cadastrando o primeiro jogador da sua base de apostas."}
                        </p>
                        <Link
                          href="/players/new"
                          className="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-md text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white transition"
                        >
                          <Plus className="w-3.5 h-3.5" />
                          <span>Cadastrar Jogador</span>
                        </Link>
                      </div>
                    </td>
                  </tr>
                ) : (
                  players.map((player) => (
                    <tr key={player.id} className="hover:bg-slate-900/50 transition-colors group">
                      <td className="py-3 px-4">
                        <div className="flex items-center space-x-3">
                          <div className="w-7 h-7 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold text-[10px] shrink-0">
                            {player.name.slice(0, 2).toUpperCase()}
                          </div>
                          <div>
                            <Link
                              href={`/players/${player.id}`}
                              className="font-semibold text-slate-100 hover:text-emerald-400 transition"
                            >
                              {player.name}
                            </Link>
                            <span className="block text-[10px] text-slate-500 font-mono">
                              UUID: {player.uuid.slice(0, 8)}...
                            </span>
                          </div>
                        </div>
                      </td>

                      <td className="py-3 px-4 font-mono text-[11px] text-slate-400">
                        <span className="px-2 py-0.5 rounded bg-slate-900 border border-slate-800 text-emerald-300">
                          {player.external_id}
                        </span>
                      </td>

                      <td className="py-3 px-4 text-slate-400 text-[11px]">
                        <div className="font-mono text-slate-300">{player.email}</div>
                        {player.phone && (
                          <div className="font-mono text-[10px] text-slate-500">{player.phone}</div>
                        )}
                      </td>

                      <td className="py-3 px-4 text-slate-400 text-[11px]">
                        {player.city && player.state ? (
                          <span>
                            {player.city}, {player.state}
                          </span>
                        ) : player.state ? (
                          <span>{player.state}</span>
                        ) : (
                          <span className="text-slate-600">—</span>
                        )}
                      </td>

                      <td className="py-3 px-4">{getStatusBadge(player.status)}</td>

                      <td className="py-3 px-4">
                        <div className="flex flex-wrap gap-1 max-w-[200px]">
                          {player.tags && player.tags.length > 0 ? (
                            player.tags.map((t) => (
                              <span
                                key={t.id}
                                className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium border"
                                style={{
                                  backgroundColor: t.color ? `${t.color}15` : "#10b98115",
                                  borderColor: t.color ? `${t.color}40` : "#10b98140",
                                  color: t.color || "#10b981",
                                }}
                              >
                                {t.name}
                              </span>
                            ))
                          ) : (
                            <span className="text-slate-600 text-[10px]">Sem tags</span>
                          )}
                        </div>
                      </td>

                      <td className="py-3 px-4 text-[11px] text-slate-400 font-mono">
                        {new Date(player.created_at).toLocaleDateString("pt-BR")}
                      </td>

                      <td className="py-3 px-4 text-right">
                        <div className="flex items-center justify-end space-x-1.5">
                          <Link
                            href={`/players/${player.id}`}
                            title="Ficha 360°"
                            className="p-1.5 rounded-md hover:bg-slate-800 text-slate-400 hover:text-emerald-400 transition"
                          >
                            <Eye className="w-3.5 h-3.5" />
                          </Link>
                          <Link
                            href={`/players/${player.id}/edit`}
                            title="Editar Jogador"
                            className="p-1.5 rounded-md hover:bg-slate-800 text-slate-400 hover:text-sky-400 transition"
                          >
                            <Edit2 className="w-3.5 h-3.5" />
                          </Link>
                          <button
                            onClick={() => setPlayerToDelete(player)}
                            title="Excluir Jogador"
                            className="p-1.5 rounded-md hover:bg-rose-500/10 text-slate-400 hover:text-rose-400 transition"
                          >
                            <Trash2 className="w-3.5 h-3.5" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>

          {/* Pagination Controls */}
          {totalPages > 1 && (
            <div className="p-4 border-t border-slate-800 bg-[#0f172a] flex items-center justify-between text-xs text-slate-400">
              <div>
                Página <span className="font-semibold text-white">{page}</span> de{" "}
                <span className="font-semibold text-white">{totalPages}</span> (Total de {totalCount} registros)
              </div>
              <div className="flex items-center space-x-2">
                <button
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  disabled={page <= 1 || loading}
                  className="px-2.5 py-1 rounded border border-slate-700 bg-slate-800 hover:bg-slate-700 text-slate-200 disabled:opacity-40 disabled:cursor-not-allowed transition flex items-center space-x-1"
                >
                  <ChevronLeft className="w-3 h-3" />
                  <span>Anterior</span>
                </button>
                <button
                  onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                  disabled={page >= totalPages || loading}
                  className="px-2.5 py-1 rounded border border-slate-700 bg-slate-800 hover:bg-slate-700 text-slate-200 disabled:opacity-40 disabled:cursor-not-allowed transition flex items-center space-x-1"
                >
                  <span>Próximo</span>
                  <ChevronRight className="w-3 h-3" />
                </button>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Delete Confirmation Modal */}
      {playerToDelete && (
        <div className="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="w-full max-w-md rounded-xl border border-slate-800 bg-[#0f172a] p-6 space-y-4 shadow-2xl">
            <div className="flex items-center space-x-3 text-rose-400">
              <div className="p-2 rounded-lg bg-rose-500/10 border border-rose-500/20">
                <Trash2 className="w-5 h-5" />
              </div>
              <h3 className="text-base font-bold text-white">Confirmar Exclusão</h3>
            </div>

            <p className="text-xs text-slate-300 leading-relaxed">
              Tem certeza que deseja excluir o jogador{" "}
              <strong className="text-white font-semibold">{playerToDelete.name}</strong> (ID Externo:{" "}
              <code className="text-emerald-400 font-mono">{playerToDelete.external_id}</code>)?
            </p>
            <p className="text-[11px] text-slate-500">
              A exclusão é suave (Soft Delete) e preserva o histórico de eventos e consentimentos.
            </p>

            <div className="flex items-center justify-end space-x-2 pt-2">
              <button
                type="button"
                onClick={() => setPlayerToDelete(null)}
                disabled={deleting}
                className="px-4 py-2 rounded-lg text-xs font-medium text-slate-300 hover:bg-slate-800 border border-slate-700 transition"
              >
                Cancelar
              </button>
              <button
                type="button"
                onClick={handleDeletePlayer}
                disabled={deleting}
                className="px-4 py-2 rounded-lg text-xs font-semibold bg-rose-600 hover:bg-rose-500 text-white transition flex items-center space-x-1.5"
              >
                {deleting && <div className="animate-spin w-3 h-3 border border-white border-t-transparent rounded-full" />}
                <span>{deleting ? "Excluindo..." : "Confirmar Exclusão"}</span>
              </button>
            </div>
          </div>
        </div>
      )}
    </AppLayout>
  );
}
