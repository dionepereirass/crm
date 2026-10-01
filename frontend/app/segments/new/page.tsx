"use client";

import React, { useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import { SegmentBuilder } from "@/components/segments/segment-builder";
import { segmentService, RulesTree } from "@/services/segment-service";
import {
  ArrowLeft,
  Save,
  CheckCircle,
  AlertCircle,
  Layers,
  Sparkles,
} from "lucide-react";

export default function NewSegmentPage() {
  const router = useRouter();

  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [status, setStatus] = useState("ACTIVE");
  const [rules, setRules] = useState<RulesTree>({
    operator: "AND",
    children: [
      {
        type: "condition",
        field: "player.status",
        operator: "equals",
        value: "ACTIVE",
      },
    ],
  });

  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) {
      setError("Por favor, forneça um nome descritivo para o segmento.");
      return;
    }

    setSaving(true);
    setError(null);

    try {
      const created = await segmentService.createSegment({
        name: name.trim(),
        description: description.trim() || undefined,
        status,
        rules_tree: rules,
      });

      router.push(`/segments/${created.id}`);
    } catch (err: any) {
      setError(err.message || "Erro ao salvar o segmento.");
      setSaving(false);
    }
  };

  return (
    <AppLayout
      title="Criar Novo Segmento"
      badge="Visual Segment Builder"
      actions={
        <Link
          href="/segments"
          className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 transition-colors"
        >
          <ArrowLeft className="w-3.5 h-3.5" />
          Voltar para Lista
        </Link>
      }
    >
      <form onSubmit={handleSubmit} className="space-y-6 max-w-6xl mx-auto">
        {error && (
          <div className="p-3.5 rounded-xl border border-rose-500/30 bg-rose-500/10 text-rose-300 text-xs flex items-center gap-2">
            <AlertCircle className="w-4 h-4 shrink-0 text-rose-400" />
            {error}
          </div>
        )}

        {/* Basic Information Card */}
        <div className="rounded-xl border border-slate-800 bg-[#0d1322] p-5 space-y-4">
          <div className="flex items-center gap-2 pb-3 border-b border-slate-800 text-slate-300 font-semibold text-xs uppercase tracking-wider">
            <Layers className="w-4 h-4 text-emerald-400" />
            Identificação do Segmento
          </div>

          <div className="grid grid-cols-1 md:grid-cols-12 gap-4">
            <div className="md:col-span-8 space-y-1">
              <label className="text-xs font-medium text-slate-400">
                Nome do Segmento <span className="text-emerald-400">*</span>
              </label>
              <input
                type="text"
                required
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Ex: VIPs Ativos com Recorrência de Depósito"
                className="w-full text-xs bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-200 focus:outline-none focus:border-emerald-500"
              />
            </div>

            <div className="md:col-span-4 space-y-1">
              <label className="text-xs font-medium text-slate-400">
                Status Inicial
              </label>
              <select
                value={status}
                onChange={(e) => setStatus(e.target.value)}
                className="w-full text-xs bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-200 focus:outline-none focus:border-emerald-500"
              >
                <option value="ACTIVE">ATIVO (Elegível para campanhas)</option>
                <option value="DRAFT">RASCUNHO (Em planejamento)</option>
                <option value="INACTIVE">INATIVO (Desabilitado)</option>
              </select>
            </div>

            <div className="md:col-span-12 space-y-1">
              <label className="text-xs font-medium text-slate-400">
                Descrição ou Objetivo de Marketing
              </label>
              <textarea
                rows={2}
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="Breve resumo da finalidade e das premissas de negócio deste segmento..."
                className="w-full text-xs bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-200 focus:outline-none focus:border-emerald-500 resize-none"
              />
            </div>
          </div>
        </div>

        {/* Visual Rule Builder Section */}
        <div className="space-y-2">
          <div className="flex items-center justify-between">
            <div className="text-xs font-semibold text-slate-300 uppercase tracking-wider">
              Árvore de Condições & Regras Avançadas
            </div>
            <span className="text-[11px] text-slate-500">
              Combine grupos com AND / OR e filtros agregados em tempo real
            </span>
          </div>

          <SegmentBuilder
            initialRules={rules}
            onChange={(updated) => setRules(updated)}
          />
        </div>

        {/* Form Submission */}
        <div className="flex items-center justify-end gap-3 pt-2">
          <Link
            href="/segments"
            className="px-4 py-2 text-xs font-medium rounded-lg text-slate-400 hover:text-slate-200 hover:bg-slate-800 transition-colors"
          >
            Cancelar
          </Link>

          <button
            type="submit"
            disabled={saving}
            className="inline-flex items-center gap-2 px-5 py-2 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white shadow-lg disabled:opacity-50 transition-all"
          >
            {saving ? (
              <>
                <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                Salvando Segmento...
              </>
            ) : (
              <>
                <Save className="w-4 h-4" />
                Criar e Salvar Segmento
              </>
            )}
          </button>
        </div>
      </form>
    </AppLayout>
  );
}
