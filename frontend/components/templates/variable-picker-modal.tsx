"use client";

import React, { useState, useEffect } from "react";
import { templateService, TemplateVariable } from "@/services/template-service";
import { Search, Tag, X, Check, HelpCircle } from "lucide-react";

interface VariablePickerModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSelectVariable: (tag: string) => void;
  channel: "EMAIL" | "SMS";
}

export function VariablePickerModal({
  isOpen,
  onClose,
  onSelectVariable,
  channel,
}: VariablePickerModalProps) {
  const [catalog, setCatalog] = useState<Record<string, TemplateVariable[]>>({});
  const [search, setSearch] = useState("");
  const [activeCategory, setActiveCategory] = useState<string>("ALL");
  const [loading, setLoading] = useState(false);
  const [customFallback, setCustomFallback] = useState<Record<string, string>>({});

  useEffect(() => {
    if (isOpen) {
      setLoading(true);
      templateService
        .getVariablesCatalog(channel)
        .then((data) => setCatalog(data))
        .catch((err) => console.error("Erro ao carregar catálogo:", err))
        .finally(() => setLoading(false));
    }
  }, [isOpen, channel]);

  if (!isOpen) return null;

  const categories = Object.keys(catalog);
  const allVariables: TemplateVariable[] = Object.values(catalog).flat();

  const filteredVariables = allVariables.filter((v) => {
    const matchesCategory =
      activeCategory === "ALL" || v.category === activeCategory;
    const matchesSearch =
      v.key.toLowerCase().includes(search.toLowerCase()) ||
      v.label.toLowerCase().includes(search.toLowerCase()) ||
      v.description.toLowerCase().includes(search.toLowerCase());
    return matchesCategory && matchesSearch;
  });

  const handleInsert = (v: TemplateVariable) => {
    const fallback = customFallback[v.key]?.trim();
    let tag = `{{${v.key}}}`;
    if (fallback) {
      tag = `{{${v.key}|default:"${fallback}"}}`;
    }
    onSelectVariable(tag);
    onClose();
  };

  const categoryLabels: Record<string, string> = {
    PLAYER: "Dados do Jogador",
    ACCOUNT: "Conta & Cadastro",
    FINANCIAL: "Métricas Financeiras",
    SEGMENTATION: "Segmentos & Tags",
    PLATFORM: "Dados da Casa / Plataforma",
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4 animate-in fade-in duration-150">
      <div className="bg-[#0f172a] border border-slate-800 rounded-xl w-full max-w-3xl flex flex-col max-h-[85vh] shadow-2xl overflow-hidden">
        {/* Header */}
        <div className="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-900/60">
          <div className="flex items-center space-x-2">
            <div className="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
              <Tag className="w-4 h-4" />
            </div>
            <div>
              <h2 className="text-sm font-semibold text-white">
                Inserir Variável Dinâmica
              </h2>
              <p className="text-xs text-slate-400">
                Canal atual:{" "}
                <span className="font-mono text-emerald-400 font-bold">
                  {channel}
                </span>{" "}
                • Variáveis validadas contra vazamento de dados
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Search & Category Pills */}
        <div className="p-4 border-b border-slate-800/80 bg-slate-900/40 space-y-3">
          <div className="relative">
            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              type="text"
              placeholder="Buscar por nome, tag ou descrição (ex: nome, saldo, deposito)..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full bg-slate-950 border border-slate-800 rounded-lg pl-9 pr-4 py-2 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-emerald-500"
            />
          </div>

          <div className="flex flex-wrap gap-1.5">
            <button
              onClick={() => setActiveCategory("ALL")}
              className={`px-2.5 py-1 rounded-full text-xs font-medium transition ${
                activeCategory === "ALL"
                  ? "bg-emerald-500 text-slate-950 font-bold"
                  : "bg-slate-800/80 text-slate-300 hover:bg-slate-800"
              }`}
            >
              Todas ({allVariables.length})
            </button>
            {categories.map((cat) => (
              <button
                key={cat}
                onClick={() => setActiveCategory(cat)}
                className={`px-2.5 py-1 rounded-full text-xs font-medium transition ${
                  activeCategory === cat
                    ? "bg-emerald-500 text-slate-950 font-bold"
                    : "bg-slate-800/80 text-slate-300 hover:bg-slate-800"
                }`}
              >
                {categoryLabels[cat] || cat} ({catalog[cat]?.length || 0})
              </button>
            ))}
          </div>
        </div>

        {/* Variables List */}
        <div className="flex-1 overflow-y-auto p-4 space-y-2">
          {loading ? (
            <div className="text-center py-12 text-slate-400 text-xs flex items-center justify-center space-x-2">
              <div className="animate-spin w-4 h-4 border-2 border-emerald-500 border-t-transparent rounded-full" />
              <span>Carregando variáveis compatíveis...</span>
            </div>
          ) : filteredVariables.length === 0 ? (
            <div className="text-center py-12 text-slate-500 text-xs">
              Nenhuma variável encontrada para os filtros selecionados.
            </div>
          ) : (
            filteredVariables.map((v) => (
              <div
                key={v.key}
                className="bg-slate-900/60 border border-slate-800 rounded-lg p-3 hover:border-slate-700 transition flex flex-col md:flex-row md:items-center justify-between gap-3"
              >
                <div className="space-y-1 max-w-lg">
                  <div className="flex items-center space-x-2">
                    <span className="font-mono text-xs font-bold text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">
                      {`{{${v.key}}}`}
                    </span>
                    <span className="text-xs font-medium text-slate-200">
                      {v.label}
                    </span>
                    <span className="text-[10px] uppercase font-mono px-1.5 py-0.2 rounded bg-slate-800 text-slate-400">
                      {v.type}
                    </span>
                  </div>
                  <p className="text-xs text-slate-400">{v.description}</p>
                  <p className="text-[11px] text-slate-500">
                    <span className="text-slate-400">Exemplo real:</span> {v.example}
                  </p>
                </div>

                <div className="flex items-center space-x-2 shrink-0">
                  <div className="flex items-center space-x-1">
                    <input
                      type="text"
                      placeholder="Valor fallback (opcional)"
                      value={customFallback[v.key] || ""}
                      onChange={(e) =>
                        setCustomFallback({
                          ...customFallback,
                          [v.key]: e.target.value,
                        })
                      }
                      className="bg-slate-950 border border-slate-800 rounded px-2 py-1 text-xs text-slate-200 placeholder-slate-600 focus:outline-none focus:border-emerald-500 w-36"
                    />
                  </div>
                  <button
                    onClick={() => handleInsert(v)}
                    className="px-3 py-1 bg-emerald-500 hover:bg-emerald-400 text-slate-950 rounded text-xs font-semibold flex items-center space-x-1 transition"
                  >
                    <Check className="w-3.5 h-3.5" />
                    <span>Inserir</span>
                  </button>
                </div>
              </div>
            ))
          )}
        </div>

        {/* Footer */}
        <div className="px-6 py-3 border-t border-slate-800 bg-slate-900/60 flex items-center justify-between text-xs text-slate-400">
          <div className="flex items-center space-x-1 text-[11px]">
            <HelpCircle className="w-3.5 h-3.5 text-slate-400" />
            <span>
              Fallback é usado automaticamente caso o jogador não tenha esse dado preenchido.
            </span>
          </div>
          <button
            onClick={onClose}
            className="px-3 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded text-xs transition"
          >
            Fechar
          </button>
        </div>
      </div>
    </div>
  );
}
