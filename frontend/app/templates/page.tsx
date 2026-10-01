"use client";

import React, { useState, useEffect } from "react";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import {
  templateService,
  Template,
  TemplateListParams,
} from "@/services/template-service";
import {
  Mail,
  MessageSquare,
  Plus,
  Search,
  Filter,
  Copy,
  Archive,
  Trash2,
  Edit,
  History,
  CheckCircle,
  Eye,
  AlertCircle,
} from "lucide-react";

export default function TemplatesPage() {
  const [templates, setTemplates] = useState<Template[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);

  // Filters
  const [channel, setChannel] = useState<string>("ALL");
  const [status, setStatus] = useState<string>("ALL");
  const [category, setCategory] = useState<string>("ALL");
  const [search, setSearch] = useState<string>("");
  const [categories, setCategories] = useState<string[]>([]);
  const [page, setPage] = useState<number>(1);
  const [totalPages, setTotalPages] = useState<number>(1);
  const [totalCount, setTotalCount] = useState<number>(0);

  const fetchTemplates = async () => {
    setLoading(true);
    setError(null);
    try {
      const params: TemplateListParams = {
        channel,
        status,
        category,
        search,
        page,
        per_page: 15,
      };
      const res = await templateService.getTemplates(params);
      setTemplates(res.data);
      setTotalPages(res.pagination.last_page);
      setTotalCount(res.pagination.total);
    } catch (err: any) {
      setError(err.message || "Erro ao carregar templates.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    templateService
      .getCategories()
      .then((cats) => setCategories(cats))
      .catch(() => {});
  }, []);

  useEffect(() => {
    fetchTemplates();
  }, [channel, status, category, page]);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setPage(1);
    fetchTemplates();
  };

  const handleDuplicate = async (id: number) => {
    try {
      await templateService.duplicateTemplate(id);
      setSuccessMsg("Template duplicado com sucesso como rascunho!");
      fetchTemplates();
      setTimeout(() => setSuccessMsg(null), 4000);
    } catch (err: any) {
      setError(err.message || "Erro ao duplicar template.");
    }
  };

  const handleArchive = async (id: number) => {
    if (!confirm("Deseja realmente arquivar este template?")) return;
    try {
      await templateService.archiveTemplate(id);
      setSuccessMsg("Template arquivado com sucesso!");
      fetchTemplates();
      setTimeout(() => setSuccessMsg(null), 4000);
    } catch (err: any) {
      setError(err.message || "Erro ao arquivar template.");
    }
  };

  const handleDelete = async (id: number) => {
    if (
      !confirm(
        "Atenção: Tem certeza que deseja excluir permanentemente este template? Essa ação não pode ser desfeita."
      )
    )
      return;
    try {
      await templateService.deleteTemplate(id);
      setSuccessMsg("Template excluído com sucesso!");
      fetchTemplates();
      setTimeout(() => setSuccessMsg(null), 4000);
    } catch (err: any) {
      setError(err.message || "Erro ao excluir template.");
    }
  };

  const handlePublish = async (id: number) => {
    try {
      await templateService.publishTemplate(id);
      setSuccessMsg("Template e versão ativa publicados com sucesso!");
      fetchTemplates();
      setTimeout(() => setSuccessMsg(null), 4000);
    } catch (err: any) {
      setError(err.message || "Erro ao publicar template.");
    }
  };

  return (
    <AppLayout
      title="Templates de Comunicação"
      badge="FASE 6"
      actions={
        <Link
          href="/templates/new"
          className="px-3.5 py-1.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-semibold rounded-lg text-xs flex items-center space-x-1.5 shadow-md shadow-emerald-500/20 transition"
        >
          <Plus className="w-4 h-4" />
          <span>Novo Template</span>
        </Link>
      }
    >
      <div className="space-y-6">
        {/* Feedback Alerts */}
        {error && (
          <div className="bg-rose-500/10 border border-rose-500/30 text-rose-300 p-3 rounded-lg text-xs flex items-center justify-between">
            <div className="flex items-center space-x-2">
              <AlertCircle className="w-4 h-4 shrink-0" />
              <span>{error}</span>
            </div>
            <button
              onClick={() => setError(null)}
              className="text-rose-400 hover:text-rose-200 font-bold ml-2"
            >
              ×
            </button>
          </div>
        )}

        {successMsg && (
          <div className="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 p-3 rounded-lg text-xs flex items-center justify-between">
            <div className="flex items-center space-x-2">
              <CheckCircle className="w-4 h-4 shrink-0" />
              <span>{successMsg}</span>
            </div>
            <button
              onClick={() => setSuccessMsg(null)}
              className="text-emerald-400 hover:text-emerald-200 font-bold ml-2"
            >
              ×
            </button>
          </div>
        )}

        {/* Filters Bar */}
        <div className="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-4">
          <form
            onSubmit={handleSearchSubmit}
            className="flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between"
          >
            <div className="relative flex-1">
              <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                type="text"
                placeholder="Buscar por nome, slug ou descrição..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full bg-slate-950 border border-slate-800 rounded-lg pl-9 pr-4 py-2 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-emerald-500"
              />
            </div>

            <div className="flex flex-wrap items-center gap-2">
              {/* Channel filter */}
              <div className="flex items-center space-x-1 bg-slate-950 border border-slate-800 rounded-lg p-1 text-xs">
                <button
                  type="button"
                  onClick={() => {
                    setChannel("ALL");
                    setPage(1);
                  }}
                  className={`px-2.5 py-1 rounded transition text-[11px] font-medium ${
                    channel === "ALL"
                      ? "bg-slate-800 text-white shadow"
                      : "text-slate-400 hover:text-slate-200"
                  }`}
                >
                  Todos
                </button>
                <button
                  type="button"
                  onClick={() => {
                    setChannel("EMAIL");
                    setPage(1);
                  }}
                  className={`px-2.5 py-1 rounded transition text-[11px] font-medium flex items-center space-x-1 ${
                    channel === "EMAIL"
                      ? "bg-blue-500/20 text-blue-400 border border-blue-500/30"
                      : "text-slate-400 hover:text-slate-200"
                  }`}
                >
                  <Mail className="w-3 h-3" />
                  <span>E-mail</span>
                </button>
                <button
                  type="button"
                  onClick={() => {
                    setChannel("SMS");
                    setPage(1);
                  }}
                  className={`px-2.5 py-1 rounded transition text-[11px] font-medium flex items-center space-x-1 ${
                    channel === "SMS"
                      ? "bg-emerald-500/20 text-emerald-400 border border-emerald-500/30"
                      : "text-slate-400 hover:text-slate-200"
                  }`}
                >
                  <MessageSquare className="w-3 h-3" />
                  <span>SMS</span>
                </button>
              </div>

              {/* Status filter */}
              <select
                value={status}
                onChange={(e) => {
                  setStatus(e.target.value);
                  setPage(1);
                }}
                className="bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-slate-300 focus:outline-none focus:border-emerald-500"
              >
                <option value="ALL">Status: Todos</option>
                <option value="ACTIVE">Ativo / Publicado</option>
                <option value="DRAFT">Rascunho</option>
                <option value="ARCHIVED">Arquivado</option>
              </select>

              {/* Category filter */}
              <select
                value={category}
                onChange={(e) => {
                  setCategory(e.target.value);
                  setPage(1);
                }}
                className="bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-slate-300 focus:outline-none focus:border-emerald-500"
              >
                <option value="ALL">Categoria: Todas</option>
                {categories.map((c) => (
                  <option key={c} value={c}>
                    {c}
                  </option>
                ))}
              </select>

              <button
                type="submit"
                className="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-xs transition"
              >
                Filtrar
              </button>
            </div>
          </form>
        </div>

        {/* Templates Table */}
        <div className="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-lg">
          <div className="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
            <h3 className="text-xs font-semibold text-white uppercase tracking-wider">
              Templates Cadastrados ({totalCount})
            </h3>
            <span className="text-xs text-slate-400">
              Página {page} de {totalPages || 1}
            </span>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs text-slate-300">
              <thead className="bg-slate-950/60 border-b border-slate-800 text-[11px] uppercase tracking-wider text-slate-400">
                <tr>
                  <th className="px-6 py-3">Canal</th>
                  <th className="px-6 py-3">Nome / Descrição</th>
                  <th className="px-6 py-3">Categoria</th>
                  <th className="px-6 py-3">Versão Ativa</th>
                  <th className="px-6 py-3">Status</th>
                  <th className="px-6 py-3">Atualização</th>
                  <th className="px-6 py-3 text-right">Ações</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/80">
                {loading ? (
                  <tr>
                    <td colSpan={7} className="px-6 py-12 text-center text-slate-500">
                      <div className="flex items-center justify-center space-x-2">
                        <div className="animate-spin w-4 h-4 border-2 border-emerald-500 border-t-transparent rounded-full" />
                        <span>Carregando templates...</span>
                      </div>
                    </td>
                  </tr>
                ) : templates.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="px-6 py-12 text-center text-slate-500">
                      Nenhum template encontrado para os filtros selecionados.
                    </td>
                  </tr>
                ) : (
                  templates.map((tpl) => (
                    <tr
                      key={tpl.id}
                      className="hover:bg-slate-800/30 transition group"
                    >
                      {/* Channel Badge */}
                      <td className="px-6 py-4">
                        {tpl.channel === "EMAIL" ? (
                          <span className="inline-flex items-center space-x-1 font-mono text-[10px] font-bold px-2 py-0.5 rounded bg-blue-500/10 text-blue-400 border border-blue-500/30">
                            <Mail className="w-3 h-3" />
                            <span>E-MAIL</span>
                          </span>
                        ) : (
                          <span className="inline-flex items-center space-x-1 font-mono text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                            <MessageSquare className="w-3 h-3" />
                            <span>SMS</span>
                          </span>
                        )}
                      </td>

                      {/* Name & Slug */}
                      <td className="px-6 py-4">
                        <div className="space-y-0.5">
                          <Link
                            href={`/templates/${tpl.id}`}
                            className="text-white font-medium hover:text-emerald-400 transition"
                          >
                            {tpl.name}
                          </Link>
                          {tpl.description && (
                            <p className="text-slate-400 text-[11px] line-clamp-1">
                              {tpl.description}
                            </p>
                          )}
                          <p className="text-[10px] font-mono text-slate-500">
                            slug: {tpl.slug}
                          </p>
                        </div>
                      </td>

                      {/* Category */}
                      <td className="px-6 py-4">
                        <span className="font-mono text-[10px] uppercase px-2 py-0.5 rounded bg-slate-800 text-slate-300">
                          {tpl.category}
                        </span>
                      </td>

                      {/* Active Version */}
                      <td className="px-6 py-4">
                        <Link
                          href={`/templates/${tpl.id}/versions`}
                          className="inline-flex items-center space-x-1 text-slate-300 hover:text-white transition"
                        >
                          <span className="font-mono text-xs font-bold bg-slate-800 px-1.5 py-0.5 rounded border border-slate-700">
                            v{tpl.current_version?.version || 1}
                          </span>
                          <span className="text-[10px] text-slate-500">
                            ({tpl.versions_count || 1} vers
                            {tpl.versions_count === 1 ? "ão" : "ões"})
                          </span>
                        </Link>
                      </td>

                      {/* Status */}
                      <td className="px-6 py-4">
                        {tpl.status === "ACTIVE" ? (
                          <span className="inline-flex items-center space-x-1 text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                            <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
                            <span>ATIVO</span>
                          </span>
                        ) : tpl.status === "DRAFT" ? (
                          <span className="inline-flex items-center space-x-1 text-[10px] font-bold px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/30">
                            <span>RASCUNHO</span>
                          </span>
                        ) : (
                          <span className="inline-flex items-center space-x-1 text-[10px] font-bold px-2 py-0.5 rounded bg-slate-800 text-slate-400 border border-slate-700">
                            <span>ARQUIVADO</span>
                          </span>
                        )}
                      </td>

                      {/* Updated At */}
                      <td className="px-6 py-4 text-slate-400 text-[11px]">
                        {new Date(tpl.updated_at).toLocaleString("pt-BR", {
                          day: "2-digit",
                          month: "2-digit",
                          year: "numeric",
                          hour: "2-digit",
                          minute: "2-digit",
                        })}
                      </td>

                      {/* Actions */}
                      <td className="px-6 py-4 text-right">
                        <div className="flex items-center justify-end space-x-1">
                          <Link
                            href={`/templates/${tpl.id}`}
                            title="Visualizar Detalhes & Preview"
                            className="p-1.5 text-slate-400 hover:text-white rounded hover:bg-slate-800 transition"
                          >
                            <Eye className="w-3.5 h-3.5" />
                          </Link>
                          <Link
                            href={`/templates/${tpl.id}/edit`}
                            title="Editar Template"
                            className="p-1.5 text-slate-400 hover:text-emerald-400 rounded hover:bg-slate-800 transition"
                          >
                            <Edit className="w-3.5 h-3.5" />
                          </Link>
                          <Link
                            href={`/templates/${tpl.id}/versions`}
                            title="Histórico de Versões"
                            className="p-1.5 text-slate-400 hover:text-blue-400 rounded hover:bg-slate-800 transition"
                          >
                            <History className="w-3.5 h-3.5" />
                          </Link>
                          <button
                            type="button"
                            onClick={() => handleDuplicate(tpl.id)}
                            title="Duplicar como Rascunho"
                            className="p-1.5 text-slate-400 hover:text-amber-400 rounded hover:bg-slate-800 transition"
                          >
                            <Copy className="w-3.5 h-3.5" />
                          </button>
                          {tpl.status !== "ACTIVE" && (
                            <button
                              type="button"
                              onClick={() => handlePublish(tpl.id)}
                              title="Publicar Template"
                              className="p-1.5 text-slate-400 hover:text-emerald-400 rounded hover:bg-slate-800 transition"
                            >
                              <CheckCircle className="w-3.5 h-3.5" />
                            </button>
                          )}
                          {tpl.status !== "ARCHIVED" && (
                            <button
                              type="button"
                              onClick={() => handleArchive(tpl.id)}
                              title="Arquivar Template"
                              className="p-1.5 text-slate-400 hover:text-amber-400 rounded hover:bg-slate-800 transition"
                            >
                              <Archive className="w-3.5 h-3.5" />
                            </button>
                          )}
                          <button
                            type="button"
                            onClick={() => handleDelete(tpl.id)}
                            title="Excluir Template"
                            className="p-1.5 text-slate-400 hover:text-rose-400 rounded hover:bg-slate-800 transition"
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

          {/* Pagination Footer */}
          {totalPages > 1 && (
            <div className="px-6 py-3 border-t border-slate-800 bg-slate-950/40 flex items-center justify-between text-xs text-slate-400">
              <button
                disabled={page <= 1}
                onClick={() => setPage((p) => Math.max(p - 1, 1))}
                className="px-3 py-1 bg-slate-800 hover:bg-slate-700 disabled:opacity-40 text-slate-200 rounded transition"
              >
                Anterior
              </button>
              <span>
                Página {page} de {totalPages}
              </span>
              <button
                disabled={page >= totalPages}
                onClick={() => setPage((p) => Math.min(p + 1, totalPages))}
                className="px-3 py-1 bg-slate-800 hover:bg-slate-700 disabled:opacity-40 text-slate-200 rounded transition"
              >
                Próxima
              </button>
            </div>
          )}
        </div>
      </div>
    </AppLayout>
  );
}
