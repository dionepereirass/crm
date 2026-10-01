"use client";

import React, { useState, useEffect } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { AppLayout } from "@/components/layout/app-layout";
import { playerService, Tag } from "@/services/player-service";
import { useAuth } from "@/hooks/use-auth";
import {
  ArrowLeft,
  Save,
  Users,
  AlertCircle,
  Tag as TagIcon,
  ShieldCheck,
  CheckCircle2,
} from "lucide-react";

export default function NewPlayerPage() {
  const router = useRouter();
  const { activePlatform } = useAuth(true);

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [availableTags, setAvailableTags] = useState<Tag[]>([]);

  // Form State
  const [formData, setFormData] = useState({
    external_id: "",
    name: "",
    email: "",
    phone: "",
    cpf: "",
    birth_date: "",
    gender: "M",
    state: "",
    city: "",
    affiliate: "",
    status: "active",
  });

  const [selectedTagIds, setSelectedTagIds] = useState<number[]>([]);

  // Custom Fields (dynamic key-value pairs)
  const [customFields, setCustomFields] = useState<Array<{ key: string; value: string }>>([
    { key: "vip_tier", value: "bronze" },
    { key: "preferred_game", value: "slots" },
  ]);

  useEffect(() => {
    async function loadTags() {
      try {
        const tags = await playerService.getTags();
        setAvailableTags(tags);
      } catch (err) {
        console.error("Erro ao carregar tags", err);
      }
    }
    if (activePlatform) {
      loadTags();
    }
  }, [activePlatform]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  const handleCustomFieldChange = (index: number, field: "key" | "value", val: string) => {
    setCustomFields((prev) => {
      const updated = [...prev];
      updated[index][field] = val;
      return updated;
    });
  };

  const addCustomField = () => {
    setCustomFields((prev) => [...prev, { key: "", value: "" }]);
  };

  const removeCustomField = (index: number) => {
    setCustomFields((prev) => prev.filter((_, i) => i !== index));
  };

  const toggleTag = (tagId: number) => {
    setSelectedTagIds((prev) =>
      prev.includes(tagId) ? prev.filter((id) => id !== tagId) : [...prev, tagId]
    );
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);
    setFieldErrors({});

    try {
      // Build custom fields object
      const cfObject: Record<string, any> = {};
      customFields.forEach(({ key, value }) => {
        if (key.trim()) {
          cfObject[key.trim()] = value;
        }
      });

      const payload = {
        ...formData,
        custom_fields: cfObject,
        tag_ids: selectedTagIds,
      };

      const player = await playerService.createPlayer(payload as any);
      router.push(`/players/${player.id}`);
    } catch (err: any) {
      setError(err.message || "Erro ao salvar jogador.");
      if (err.errors) {
        setFieldErrors(err.errors);
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <AppLayout
      title="Cadastrar Novo Jogador"
      badge="Formulário 360°"
      actions={
        <Link
          href="/players"
          className="flex items-center space-x-1.5 px-3 py-1.5 rounded-md text-xs font-medium bg-slate-800 hover:bg-slate-700 text-slate-200 transition border border-slate-700"
        >
          <ArrowLeft className="w-3.5 h-3.5" />
          <span>Voltar para Lista</span>
        </Link>
      }
    >
      <form onSubmit={handleSubmit} className="space-y-6 max-w-4xl">
        {error && (
          <div className="p-4 rounded-xl border border-rose-500/30 bg-rose-500/10 text-rose-300 text-xs flex items-center space-x-2">
            <AlertCircle className="w-4 h-4 shrink-0" />
            <span>{error}</span>
          </div>
        )}

        {/* 1. Identification Section */}
        <div className="p-6 rounded-xl border border-slate-800 bg-[#0d131f] space-y-4">
          <div className="flex items-center space-x-3 pb-3 border-b border-slate-800">
            <div className="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-bold">
              1
            </div>
            <div>
              <h3 className="text-sm font-semibold text-white">Identificação Principal & Autenticação</h3>
              <p className="text-xs text-slate-400">
                O ID Externo é a chave de integração única por plataforma (Bet Platform ID).
              </p>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
              <label className="block text-xs font-medium text-slate-300 mb-1">
                ID Externo <span className="text-rose-400">*</span>
              </label>
              <input
                type="text"
                name="external_id"
                required
                placeholder="Ex: 94821 ou usr_48a91"
                value={formData.external_id}
                onChange={handleChange}
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 transition font-mono"
              />
              {fieldErrors.external_id && (
                <p className="text-[11px] text-rose-400 mt-1">{fieldErrors.external_id[0]}</p>
              )}
            </div>

            <div>
              <label className="block text-xs font-medium text-slate-300 mb-1">
                Nome Completo <span className="text-rose-400">*</span>
              </label>
              <input
                type="text"
                name="name"
                required
                placeholder="Ex: Carlos Roberto Silva"
                value={formData.name}
                onChange={handleChange}
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 transition"
              />
              {fieldErrors.name && (
                <p className="text-[11px] text-rose-400 mt-1">{fieldErrors.name[0]}</p>
              )}
            </div>

            <div>
              <label className="block text-xs font-medium text-slate-300 mb-1">
                Status Inicial <span className="text-rose-400">*</span>
              </label>
              <select
                name="status"
                value={formData.status}
                onChange={handleChange}
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500 transition"
              >
                <option value="active">Ativo (Permite campanhas)</option>
                <option value="inactive">Inativo</option>
                <option value="churned">Churned</option>
                <option value="blocked">Bloqueado</option>
                <option value="pending">Pendente</option>
              </select>
            </div>
          </div>
        </div>

        {/* 2. Contact & LGPD Info */}
        <div className="p-6 rounded-xl border border-slate-800 bg-[#0d131f] space-y-4">
          <div className="flex items-center space-x-3 pb-3 border-b border-slate-800">
            <div className="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-bold">
              2
            </div>
            <div>
              <h3 className="text-sm font-semibold text-white">Canais de Contato & Dados LGPD</h3>
              <p className="text-xs text-slate-400">
                Dados sensíveis serão mascarados nas listagens gerais para conformidade com a LGPD.
              </p>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
              <label className="block text-xs font-medium text-slate-300 mb-1">
                E-mail <span className="text-rose-400">*</span>
              </label>
              <input
                type="email"
                name="email"
                required
                placeholder="carlos@exemplo.com.br"
                value={formData.email}
                onChange={handleChange}
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 transition font-mono"
              />
              {fieldErrors.email && (
                <p className="text-[11px] text-rose-400 mt-1">{fieldErrors.email[0]}</p>
              )}
            </div>

            <div>
              <label className="block text-xs font-medium text-slate-300 mb-1">Telefone / Celular</label>
              <input
                type="text"
                name="phone"
                placeholder="(11) 98765-4321"
                value={formData.phone}
                onChange={handleChange}
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 transition font-mono"
              />
            </div>

            <div>
              <label className="block text-xs font-medium text-slate-300 mb-1">CPF (Opcional)</label>
              <input
                type="text"
                name="cpf"
                placeholder="123.456.789-00"
                value={formData.cpf}
                onChange={handleChange}
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 transition font-mono"
              />
            </div>
          </div>
        </div>

        {/* 3. Demographic & Acquisition */}
        <div className="p-6 rounded-xl border border-slate-800 bg-[#0d131f] space-y-4">
          <div className="flex items-center space-x-3 pb-3 border-b border-slate-800">
            <div className="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-bold">
              3
            </div>
            <div>
              <h3 className="text-sm font-semibold text-white">Demografia & Aquisição</h3>
              <p className="text-xs text-slate-400">
                Segmentação por localização e rastreamento de afiliados da operadora.
              </p>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <label className="block text-xs font-medium text-slate-300 mb-1">Data de Nascimento</label>
              <input
                type="date"
                name="birth_date"
                value={formData.birth_date}
                onChange={handleChange}
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500 transition"
              />
            </div>

            <div>
              <label className="block text-xs font-medium text-slate-300 mb-1">Gênero</label>
              <select
                name="gender"
                value={formData.gender}
                onChange={handleChange}
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500 transition"
              >
                <option value="M">Masculino</option>
                <option value="F">Feminino</option>
                <option value="OTHER">Outro</option>
              </select>
            </div>

            <div>
              <label className="block text-xs font-medium text-slate-300 mb-1">Estado (UF)</label>
              <input
                type="text"
                name="state"
                maxLength={2}
                placeholder="Ex: SP"
                value={formData.state}
                onChange={(e) => setFormData((prev) => ({ ...prev, state: e.target.value.toUpperCase() }))}
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white uppercase placeholder-slate-500 focus:outline-none focus:border-emerald-500 transition"
              />
            </div>

            <div>
              <label className="block text-xs font-medium text-slate-300 mb-1">Cidade</label>
              <input
                type="text"
                name="city"
                placeholder="Ex: São Paulo"
                value={formData.city}
                onChange={handleChange}
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 transition"
              />
            </div>

            <div className="sm:col-span-2 lg:col-span-4">
              <label className="block text-xs font-medium text-slate-300 mb-1">Afiliado / Origem de Aquisição</label>
              <input
                type="text"
                name="affiliate"
                placeholder="Ex: afiliado_influencer_top ou campanha_google_ads"
                value={formData.affiliate}
                onChange={handleChange}
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 transition"
              />
            </div>
          </div>
        </div>

        {/* 4. Tags */}
        <div className="p-6 rounded-xl border border-slate-800 bg-[#0d131f] space-y-4">
          <div className="flex items-center space-x-3 pb-3 border-b border-slate-800">
            <div className="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-bold">
              4
            </div>
            <div>
              <h3 className="text-sm font-semibold text-white">Tags da Plataforma</h3>
              <p className="text-xs text-slate-400">
                Selecione as etiquetas comportamentais para segmentação instantânea.
              </p>
            </div>
          </div>

          <div className="flex flex-wrap gap-2">
            {availableTags.length === 0 ? (
              <p className="text-xs text-slate-500">Nenhuma tag cadastrada nesta plataforma.</p>
            ) : (
              availableTags.map((tag) => {
                const isSelected = selectedTagIds.includes(tag.id);
                return (
                  <button
                    key={tag.id}
                    type="button"
                    onClick={() => toggleTag(tag.id)}
                    className={`px-3 py-1.5 rounded-lg text-xs font-medium border transition flex items-center space-x-1.5 ${
                      isSelected
                        ? "bg-emerald-500/20 border-emerald-500 text-emerald-300 shadow-md shadow-emerald-950/20"
                        : "bg-slate-900 border-slate-800 text-slate-400 hover:border-slate-700"
                    }`}
                  >
                    <span
                      className="w-2 h-2 rounded-full"
                      style={{ backgroundColor: tag.color || "#10b981" }}
                    />
                    <span>{tag.name}</span>
                    {isSelected && <CheckCircle2 className="w-3 h-3 text-emerald-400 ml-1" />}
                  </button>
                );
              })
            )}
          </div>
        </div>

        {/* 5. Custom Fields */}
        <div className="p-6 rounded-xl border border-slate-800 bg-[#0d131f] space-y-4">
          <div className="flex items-center justify-between pb-3 border-b border-slate-800">
            <div className="flex items-center space-x-3">
              <div className="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-bold">
                5
              </div>
              <div>
                <h3 className="text-sm font-semibold text-white">Campos Customizados (JSONB)</h3>
                <p className="text-xs text-slate-400">
                  Metadados flexíveis adicionais da casa de aposta.
                </p>
              </div>
            </div>

            <button
              type="button"
              onClick={addCustomField}
              className="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 text-xs font-medium transition"
            >
              + Adicionar Campo
            </button>
          </div>

          <div className="space-y-2">
            {customFields.map((cf, idx) => (
              <div key={idx} className="flex items-center space-x-2">
                <input
                  type="text"
                  placeholder="Chave (ex: vip_level)"
                  value={cf.key}
                  onChange={(e) => handleCustomFieldChange(idx, "key", e.target.value)}
                  className="w-1/3 px-3 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white placeholder-slate-500 font-mono"
                />
                <input
                  type="text"
                  placeholder="Valor (ex: platina)"
                  value={cf.value}
                  onChange={(e) => handleCustomFieldChange(idx, "value", e.target.value)}
                  className="flex-1 px-3 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white placeholder-slate-500"
                />
                <button
                  type="button"
                  onClick={() => removeCustomField(idx)}
                  className="px-2 py-1 text-slate-500 hover:text-rose-400 text-xs transition"
                >
                  ✕
                </button>
              </div>
            ))}
          </div>
        </div>

        {/* Submit Actions */}
        <div className="flex items-center justify-end space-x-3 pt-4">
          <Link
            href="/players"
            className="px-4 py-2 rounded-lg text-xs font-medium text-slate-400 hover:bg-slate-800 border border-slate-700 transition"
          >
            Cancelar
          </Link>

          <button
            type="submit"
            disabled={loading}
            className="flex items-center space-x-2 px-5 py-2 rounded-lg text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white transition shadow-lg shadow-emerald-950/40"
          >
            {loading ? (
              <div className="animate-spin w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full" />
            ) : (
              <Save className="w-3.5 h-3.5" />
            )}
            <span>{loading ? "Cadastrando..." : "Salvar Jogador"}</span>
          </button>
        </div>
      </form>
    </AppLayout>
  );
}
